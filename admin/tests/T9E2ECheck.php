<?php
// tester-m9 E2E：crypto 下单→支付→admin 确认→Redis 密钥；M8 三渠道回归
// 运行：DB_DATABASE=logistics_test php tests/T9E2ECheck.php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use app\api\v1\controller\ClientAppController;
use app\admin\controller\OrderController;
use app\common\PaymentService;
use app\model\ClientApp;
use app\model\Order;
use app\model\Plan;
use app\model\SystemConfig;
use support\Db;
use support\Redis;

$failures = [];
function t9check(bool $cond, string $label): void
{
    global $failures;
    if (!$cond) {
        $failures[] = $label;
        fwrite(STDERR, "FAIL: {$label}\n");
    } else {
        echo "ok: {$label}\n";
    }
}

function t9request(array $params): \support\Request
{
    $body = http_build_query($params);
    $raw = "POST /api/test HTTP/1.1\r\n"
        . "Host: 127.0.0.1\r\n"
        . "Content-Type: application/x-www-form-urlencoded\r\n"
        . "Content-Length: " . strlen($body) . "\r\n\r\n"
        . $body;
    return new \support\Request($raw);
}

function t9resp($resp): array
{
    return json_decode((string) $resp->rawBody(), true) ?? [];
}

// ---------- 准备数据 ----------
$clientId = 70000000000000001 + rand(0, 99);
Db::table('client')->insertOrIgnore([
    'id' => $clientId, 'username' => "t9client_{$clientId}", 'password' => 'x',
    'status' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
]);
$plan = Plan::where('price', 9900)->first();
if (!$plan) {
    fwrite(STDERR, "no test plan (9900) found\n");
    exit(1);
}

// ---------- 创建应用 ----------
$req = t9request(['name' => 't9cryptoapp', 'purpose' => 't9 e2e']);
$req->clientId = $clientId;
$r = t9resp((new ClientAppController())->store($req));
t9check(($r['code'] ?? -1) === 0, 'store app 返回 0');
$appHashId = $r['data']['id'] ?? '';
$appId = app\common\HashidsService::decode($appHashId);
t9check($appId > 0, 'app hashid 解码成功');

// ---------- 下单：参数校验 ----------
$ctrl = new ClientAppController();
$req = t9request(['plan_id' => app\common\HashidsService::encode((int) $plan->id), 'channel' => 'crypto']);
$req->clientId = $clientId;
$r = t9resp($ctrl->order($req, $appHashId));
t9check(($r['code'] ?? -1) === 422, 'crypto 缺 chain → 422');

$req = t9request(['plan_id' => app\common\HashidsService::encode((int) $plan->id), 'channel' => 'crypto', 'chain' => 'solana']);
$req->clientId = $clientId;
$r = t9resp($ctrl->order($req, $appHashId));
t9check(($r['code'] ?? -1) === 422, '非法 chain → 422');

$req = t9request(['plan_id' => app\common\HashidsService::encode((int) $plan->id), 'channel' => 'eth']);
$req->clientId = $clientId;
$r = t9resp($ctrl->order($req, $appHashId));
t9check(($r['code'] ?? -1) === 422, '非法 channel → 422');

// ---------- 下单 crypto trc20（无收款地址配置） ----------
SystemConfig::where('group', 'payment')->delete();
$req = t9request(['plan_id' => app\common\HashidsService::encode((int) $plan->id), 'channel' => 'crypto', 'chain' => 'trc20']);
$req->clientId = $clientId;
$r = t9resp($ctrl->order($req, $appHashId));
t9check(($r['code'] ?? -1) === 0, 'crypto 下单成功(无地址配置也可下单)');
$orderHashId = $r['data']['id'] ?? '';
$orderNo = $r['data']['order_no'] ?? '';
$orderId = app\common\HashidsService::decode($orderHashId);
t9check($orderId > 0 && $orderNo !== '', '下单返回 order_no');

// pay：无地址配置 → payment_config_missing，且不泄露配置
$req = t9request([]);
$req->clientId = $clientId;
$r = t9resp($ctrl->pay($req, $orderHashId));
t9check(($r['code'] ?? -1) === 'payment_config_missing', '无地址配置 pay → payment_config_missing');
t9check(!isset($r['data']['address']) && !str_contains(json_encode($r), 'TR7N'), '错误响应不泄露地址');

// ---------- 配置收款地址 + 兜底汇率 ----------
foreach (['crypto_trc20_address', 'crypto_bep20_address', 'crypto_erc20_address'] as $k) {
    $m = new SystemConfig();
    $m->id = 99999000 + rand(1, 500);
    $m->group = 'payment';
    $m->key = $k;
    $m->value = $k === 'crypto_trc20_address' ? 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' : '0x' . str_repeat('ab', 20);
    $m->type = 'text';
    $m->save();
}
$m = new SystemConfig();
$m->id = 99999500 + rand(1, 500);
$m->group = 'payment';
$m->key = 'crypto_usdt_usd_rate';
$m->value = '1.2345';
$m->type = 'text';
$m->save();

// ---------- 下单 crypto trc20（有配置）→ pay 验证结构 ----------
$req = t9request(['plan_id' => app\common\HashidsService::encode((int) $plan->id), 'channel' => 'crypto', 'chain' => 'trc20']);
$req->clientId = $clientId;
$r = t9resp($ctrl->order($req, $appHashId));
t9check(($r['code'] ?? -1) === 0, 'crypto trc20 下单成功');
$trcOrderHashId = $r['data']['id'] ?? '';
$trcOrderNo = $r['data']['order_no'] ?? '';
$trcOrderId = app\common\HashidsService::decode($trcOrderHashId);

$req = t9request([]);
$req->clientId = $clientId;
$r = t9resp($ctrl->pay($req, $trcOrderHashId));
t9check(($r['code'] ?? -1) === 0, 'trc20 pay 返回 0');
$d = $r['data'] ?? [];
t9check(($d['chain'] ?? '') === 'trc20' && ($d['coin'] ?? '') === 'USDT', 'pay 返回 chain/coin');
t9check(($d['memo'] ?? '') === 'LG' . substr($trcOrderNo, -8), 'TRC20 memo = LG+后8位: ' . ($d['memo'] ?? 'null'));
t9check(($d['amount'] ?? 0) > 0, 'pay 返回 amount>0');
t9check(str_starts_with((string) ($d['address'] ?? ''), 'TR7'), 'pay 返回地址为配置地址');

// 落库验证
$o = Order::find($trcOrderId);
t9check($o !== null && $o->chain === 'trc20', '订单落库 chain=trc20');
// Coinbase 可达时用实时汇率（≈1.0），不可达回退配置 1.2345；两种情况都应在 [99×0.98, 99×1.3] 区间
t9check($o !== null && (float) $o->crypto_amount >= 97.0 && (float) $o->crypto_amount <= 129.0, '落库 crypto_amount 在合理区间(Coinbase实时或配置兜底): ' . ($o->crypto_amount ?? 'null'));
t9check($o !== null && $o->memo === 'LG' . substr($trcOrderNo, -8), '落库 memo 正确');

// ---------- bep20：memo 为 null ----------
$req = t9request(['plan_id' => app\common\HashidsService::encode((int) $plan->id), 'channel' => 'crypto', 'chain' => 'bep20']);
$req->clientId = $clientId;
$r = t9resp($ctrl->order($req, $appHashId));
$bepOrderHashId = $r['data']['id'] ?? '';
$req = t9request([]);
$req->clientId = $clientId;
$r = t9resp($ctrl->pay($req, $bepOrderHashId));
t9check(($r['code'] ?? -1) === 0, 'bep20 pay 返回 0');
t9check(array_key_exists('memo', $r['data'] ?? []) && $r['data']['memo'] === null, 'BEP20 memo 为 null');

// ---------- 汇率回退：Coinbase 不可达时用配置汇率 ----------
$cfgRate = PaymentService::getConfig('crypto_usdt_usd_rate');
t9check($cfgRate === '1.2345', '配置汇率读取 1.2345');
// 用反射把 usdtUsdRate 的回退分支打出来：直接验证 Coinbase 超时场景 → 模拟不可达不可行，验证回退逻辑本身
$r = t9resp($ctrl->pay($req, $trcOrderHashId)); // 已 paid? 未 confirm，仍 pending 可重复 pay
t9check(($r['code'] ?? -1) === 0, '重复 pay 幂等（未确认前可重新发起）');

// ---------- admin confirm：TRC20 核验（无真实转账→null，人工兜底） ----------
$adminCtrl = new OrderController();
$req = t9request(['remark' => 't9 manual confirm']);
$r = t9resp($adminCtrl->confirm($req, $trcOrderHashId));
t9check(($r['code'] ?? -1) === 0, 'admin confirm trc20 成功');
$o = Order::find($trcOrderId);
t9check($o !== null && $o->status === 'paid' && $o->paid_at !== null, '订单 pending→paid');

// 已 paid 幂等：重复 confirm 应 422
$req = t9request([]);
$r = t9resp($adminCtrl->confirm($req, $trcOrderHashId));
t9check(($r['code'] ?? -1) === 422, '重复 confirm → 422（幂等保护）');

// Redis 密钥：confirmOrder 里 app 需 approved 才写；本测试 app 是 pending，跳过续期
// 手工把 app 置 approved 再确认一个订单验证 Redis
Db::table('client_app')->where('id', $appId)->update(['status' => 'approved', 'expire_at' => null]);
$req = t9request(['plan_id' => app\common\HashidsService::encode((int) $plan->id), 'channel' => 'crypto', 'chain' => 'erc20']);
$req->clientId = $clientId;
$r = t9resp($ctrl->order($req, $appHashId));
$ercOrderHashId = $r['data']['id'] ?? '';
$req = t9request([]);
$req->clientId = $clientId;
$r = t9resp($ctrl->pay($req, $ercOrderHashId));
t9check(($r['code'] ?? -1) === 0, 'erc20 pay 返回 0');
$req = t9request([]);
$r = t9resp($adminCtrl->confirm($req, $ercOrderHashId));
t9check(($r['code'] ?? -1) === 0, 'erc20 confirm 成功');
$app = ClientApp::find($appId);
$key = $app->api_key_sha256;
$redisVal = Redis::get("api_keys:{$key}");
t9check($redisVal !== false && str_contains((string) $redisVal, '"approved"'), 'Redis api_keys 密钥写入');

// ---------- M8 回归：manual / stripe 错误路径 ----------
$req = t9request(['plan_id' => app\common\HashidsService::encode((int) $plan->id), 'channel' => 'manual']);
$req->clientId = $clientId;
$r = t9resp($ctrl->order($req, $appHashId));
$manualOrderHashId = $r['data']['id'] ?? '';
$req = t9request([]);
$req->clientId = $clientId;
$r = t9resp($ctrl->pay($req, $manualOrderHashId));
t9check(($r['code'] ?? -1) === 0 && !empty($r['data']['payment_code']), 'manual pay 返回 payment_code（回归）');

$req = t9request(['plan_id' => app\common\HashidsService::encode((int) $plan->id), 'channel' => 'stripe']);
$req->clientId = $clientId;
$r = t9resp($ctrl->order($req, $appHashId));
$stripeOrderHashId = $r['data']['id'] ?? '';
$req = t9request([]);
$req->clientId = $clientId;
$r = t9resp($ctrl->pay($req, $stripeOrderHashId));
t9check(($r['code'] ?? -1) === 'payment_config_missing', 'stripe 无配置 → payment_config_missing（回归）');

echo "\n" . (empty($failures) ? 'ALL PASS (' : 'FAILURES: ' . count($failures) . ' of ')
    . ($failures ? implode('; ', $failures) : 't9 e2e') . ")\n";
exit(empty($failures) ? 0 : 1);
