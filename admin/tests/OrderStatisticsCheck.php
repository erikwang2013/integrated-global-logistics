<?php
// tester-m13 order statistics check：StatisticsController::order 契约验证（增量断言，容忍库中已有数据）
// 运行：DB_DATABASE=logistics_test php tests/OrderStatisticsCheck.php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use app\admin\controller\StatisticsController;
use app\common\PaymentService;
use support\Db;

$failures = [];
function check(bool $cond, string $label): void
{
    global $failures;
    if (!$cond) {
        $failures[] = $label;
        fwrite(STDERR, "FAIL: {$label}\n");
    } else {
        echo "ok: {$label}\n";
    }
}

// ---------- DB 容错：连接失败或表不存在 → SKIP（不算失败） ----------
try {
    Db::select("SELECT 1");
} catch (\Throwable $e) {
    fwrite(STDERR, "SKIP: 数据库不可用（{$e->getMessage()}）\n");
    exit(0);
}
$tables = Db::select("SHOW TABLES LIKE 'logistics_order'");
if (!$tables) {
    fwrite(STDERR, "SKIP: logistics_order 表不存在\n");
    exit(0);
}

function oresp(int $days): array
{
    $req = new \support\Request("GET /admin/order/statistics?days={$days} HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n");
    $body = json_decode((string) (new StatisticsController())->order($req)->rawBody(), true);
    return $body['data'] ?? [];
}

function rows(array $byChannel, string $channel): array
{
    foreach ($byChannel as $c) {
        if ($c['channel'] === $channel) {
            return $c;
        }
    }
    return [];
}

// ---------- 基线 ----------
$before = oresp(30);
check(($before['overview']['total_orders'] ?? -1) >= 0, '基线 overview 可读');

// ---------- 插入测试数据：3 paid / 1 pending / 1 cancelled，渠道覆盖 ----------
$rows = [
    ['channel' => 'stripe',  'status' => 'paid',      'amount' => 9900],
    ['channel' => 'manual',  'status' => 'paid',      'amount' => 5000],
    ['channel' => 'crypto',  'status' => 'paid',      'amount' => 10000],
    ['channel' => 'paypal',  'status' => 'pending',   'amount' => 8800],
    ['channel' => 'stripe',  'status' => 'cancelled', 'amount' => 1200],
];
$ids = [];
foreach ($rows as $i => $r) {
    $id = 80000000000000000 + rand(0, 999999999) * 1000 + $i;
    $ids[] = $id;
    Db::table('order')->insert([
        'id' => $id, 'order_no' => "os{$id}", 'client_id' => 1, 'app_id' => 1,
        'plan_id' => 999999999999, // 不存在 → by_plan 应显示 '未知套餐'
        'amount' => $r['amount'], 'status' => $r['status'], 'channel' => $r['channel'],
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
}

try {
    $after = oresp(30);

    // overview 增量
    $bo = $before['overview'];
    $ao = $after['overview'];
    check(($ao['total_orders'] - $bo['total_orders']) === 5, 'overview.total_orders +5');
    check(($ao['paid_count'] - $bo['paid_count']) === 3, 'overview.paid_count +3');
    check(($ao['pending_count'] - $bo['pending_count']) === 1, 'overview.pending_count +1');
    check(($ao['cancelled_count'] - $bo['cancelled_count']) === 1, 'overview.cancelled_count +1');
    check(abs(($ao['paid_amount'] - $bo['paid_amount']) - 249.0) < 0.001, 'overview.paid_amount +249.0 元（24900分/100）');

    // by_channel 增量
    $bb = $before['by_channel'];
    $ab = $after['by_channel'];
    check(is_array($ab) && count($ab) === 4 && array_column($ab, 'channel') === PaymentService::CHANNELS, 'by_channel 4 渠道齐全且按 CHANNELS 顺序');
    check((rows($ab, 'stripe')['orders'] - rows($bb, 'stripe')['orders']) === 2, 'by_channel stripe orders +2');
    check((rows($ab, 'paypal')['orders'] - rows($bb, 'paypal')['orders']) === 1
        && (rows($ab, 'paypal')['paid_count'] - rows($bb, 'paypal')['paid_count']) === 0, 'by_channel paypal orders +1 paid +0');
    check(abs((rows($ab, 'manual')['paid_amount'] - rows($bb, 'manual')['paid_amount']) - 50.0) < 0.001
        && abs((rows($ab, 'crypto')['paid_amount'] - rows($bb, 'crypto')['paid_amount']) - 100.0) < 0.001, 'by_channel paid_amount 分转元正确');

    // by_plan：plan_id 不存在 → '未知套餐' 增量 5
    $plansBefore = [];
    foreach ($before['by_plan'] as $p) {
        $plansBefore[$p['plan_name']] = $p['orders'];
    }
    $unknownAfter = 0;
    foreach ($after['by_plan'] as $p) {
        if ($p['plan_name'] === '未知套餐') {
            $unknownAfter = $p['orders'];
        }
    }
    check(($unknownAfter - ($plansBefore['未知套餐'] ?? 0)) === 5, 'by_plan 未知套餐 +5');

    // by_day：长度与序列
    $bd = $after['by_day'];
    check(count($bd) === 30, 'by_day 补齐 30 天');
    $first = date('Y-m-d', strtotime('-29 days'));
    check($bd[0]['date'] === $first && $bd[29]['date'] === date('Y-m-d'), 'by_day 日期序列 [D-29, D] 含今天（实际首日 ' . $bd[0]['date'] . ' 末日 ' . $bd[29]['date'] . '）');
    // 今天的订单必须出现在 by_day 中（口径与 overview 一致）
    $todayRow = $bd[29];
    check(($todayRow['orders'] - $before['by_day'][29]['orders']) === 5, 'by_day 今天 orders +5（今天数据计入按日）');

    // days clamp
    check(count(oresp(999)['by_day']) === 90, 'days=999 clamp 到 90 天');
    check(count(oresp(0)['by_day']) === 1, 'days=0 clamp 到 1 天');
} finally {
    Db::table('order')->whereIn('id', $ids)->delete();
}

echo "\n" . (empty($failures) ? 'ALL PASS (order statistics)' : 'FAILURES: ' . count($failures) . ' of ')
    . ($failures ? implode('; ', $failures) : '') . "\n";
exit(empty($failures) ? 0 : 1);
