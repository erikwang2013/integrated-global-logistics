<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\common\CryptoService;
use app\common\HashidsService;
use app\common\PaymentService;
use app\common\SnowflakeService;
use app\model\ClientApp;
use app\model\Order;
use app\model\Plan;
use support\Redis;
use support\Request;
use support\Response;
use Throwable;

/**
 * @Apidoc\Title("客户端门户")
 */
class ClientAppController
{
    private const KEY_LEN = 32;

    /**
     * 生成 base62 随机密钥/标识
     */
    private function randomToken(int $len = self::KEY_LEN): string
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $out = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $len; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }

    /**
     * 校验自设密钥：长度>=16 且同时含字母和数字
     */
    private function validApiKey(string $key): bool
    {
        return strlen($key) >= 16 && preg_match('/[A-Za-z]/', $key) && preg_match('/[0-9]/', $key);
    }

    private function decodeId(string $hashid): ?int
    {
        try {
            return HashidsService::decode($hashid);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * 应用剩余有效天数（expire_at 存 UTC，展示时按 UTC 换算）
     */
    private function daysLeft(?string $expireAt): int
    {
        if (!$expireAt) {
            return 0;
        }
        $ts = strtotime($expireAt . ' UTC');
        return $ts ? max(0, (int) ceil(($ts - time()) / 86400)) : 0;
    }

    /**
     * @Apidoc\Title("套餐列表")
     * @Apidoc\Group("客户端门户")
     * @Apidoc\Method("GET")
     * @Apidoc\Url("/api/plan")
     */
    public function plans(Request $request): Response
    {
        $list = Plan::where('status', 1)->orderBy('price', 'asc')->get()->map(fn($p) => [
            'id' => HashidsService::encode($p->id),
            'name' => $p->name,
            'price' => $p->price,
            'price_yuan' => $p->price / 100,
            'valid_days' => $p->valid_days,
        ]);
        return json(['code' => 0, 'message' => 'success', 'data' => $list]);
    }

    /**
     * @Apidoc\Title("我的应用列表")
     * @Apidoc\Group("客户端门户")
     * @Apidoc\Method("GET")
     * @Apidoc\Url("/api/app")
     */
    public function index(Request $request): Response
    {
        $clientId = $request->clientId;
        $list = ClientApp::where('client_id', $clientId)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($app) {
                $plan = $app->plan;
                return [
                    'id' => HashidsService::encode($app->id),
                    'appid' => $app->appid,
                    'name' => $app->name,
                    'purpose' => $app->purpose,
                    'status' => $app->status,
                    'plan_name' => $plan ? $plan->name : '',
                    'expire_at' => $app->expire_at,
                    'days_left' => $this->daysLeft($app->expire_at),
                    'review_remark' => $app->review_remark,
                    'created_at' => $app->created_at,
                ];
            });
        return json(['code' => 0, 'message' => 'success', 'data' => $list]);
    }

    /**
     * @Apidoc\Title("创建应用")
     * @Apidoc\Group("客户端门户")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/api/app")
     * @Apidoc\Desc("创建应用申请，提交后进入 pending 待管理员审核。api_key 可自设（>=16位且含字母数字），否则服务端生成；明文仅本次返回")
     * @Apidoc\Param("name", type="string", require=true, desc="应用名称(1-50)")
     * @Apidoc\Param("purpose", type="string", require=false, desc="申请用途(<=200)")
     * @Apidoc\Param("api_key", type="string", require=false, desc="自设API密钥，留空则自动生成")
     * @Apidoc\Returned("id", type="string", desc="应用ID(hashid)")
     * @Apidoc\Returned("appid", type="string", desc="应用公开标识")
     * @Apidoc\Returned("api_key", type="string", desc="API密钥明文（仅本次返回，请妥善保存）")
     */
    public function store(Request $request): Response
    {
        $clientId = $request->clientId;
        $name = trim((string) $request->input('name', ''));
        $purpose = trim((string) $request->input('purpose', ''));
        $apiKey = trim((string) $request->input('api_key', ''));

        if ($name === '' || mb_strlen($name) > 50) {
            return json(['code' => 422, 'message' => '应用名称需为1-50位', 'data' => []]);
        }
        if (mb_strlen($purpose) > 200) {
            return json(['code' => 422, 'message' => '申请用途过长', 'data' => []]);
        }
        if ($apiKey !== '' && !$this->validApiKey($apiKey)) {
            return json(['code' => 422, 'message' => 'API密钥需>=16位且同时包含字母和数字', 'data' => []]);
        }
        if ($apiKey === '') {
            $apiKey = $this->randomToken();
        }

        $app = new ClientApp();
        $app->id = SnowflakeService::generate();
        $app->client_id = $clientId;
        $app->appid = 'app_' . $this->randomToken(16);
        $app->name = $name;
        $app->purpose = $purpose;
        $app->api_key_sha256 = hash('sha256', $apiKey);
        $app->status = 'pending';
        $app->save();

        return json(['code' => 0, 'message' => '创建成功，等待审核', 'data' => [
            'id' => HashidsService::encode($app->id),
            'appid' => $app->appid,
            'api_key' => $apiKey,
        ]]);
    }

    /**
     * @Apidoc\Title("重置密钥")
     * @Apidoc\Group("客户端门户")
     * @Apidoc\Method("PUT")
     * @Apidoc\Url("/api/app/{id}/key")
     * @Apidoc\Desc("重置API密钥，旧密钥立即失效（删除网关Redis记录）；已通过审核的应用新密钥即时生效")
     * @Apidoc\Param("api_key", type="string", require=false, desc="自设新密钥，留空则自动生成")
     * @Apidoc\Returned("api_key", type="string", desc="新密钥明文（仅本次返回）")
     */
    public function resetKey(Request $request, string $id): Response
    {
        $appId = $this->decodeId($id);
        $app = $appId ? ClientApp::where('id', $appId)->where('client_id', $request->clientId)->first() : null;
        if (!$app) {
            return json(['code' => 404, 'message' => '应用不存在', 'data' => []]);
        }

        $apiKey = trim((string) $request->input('api_key', ''));
        if ($apiKey !== '' && !$this->validApiKey($apiKey)) {
            return json(['code' => 422, 'message' => 'API密钥需>=16位且同时包含字母和数字', 'data' => []]);
        }
        if ($apiKey === '') {
            $apiKey = $this->randomToken();
        }

        $oldSha = $app->api_key_sha256;
        try { Redis::del("api_keys:{$oldSha}"); } catch (\Throwable) {}

        $app->api_key_sha256 = hash('sha256', $apiKey);
        $app->save();

        // 已审核通过且未过期：同步写入新密钥的网关记录，保持可用
        if ($app->status === 'approved' && $app->expire_at) {
            $ts = strtotime($app->expire_at . ' UTC');
            if ($ts && $ts > time()) {
                $this->writeRedisKey($app->api_key_sha256, $app->appid, $ts);
            }
        }

        return json(['code' => 0, 'message' => '密钥已重置', 'data' => ['api_key' => $apiKey]]);
    }

    /**
     * @Apidoc\Title("修改应用信息")
     * @Apidoc\Group("客户端门户")
     * @Apidoc\Method("PUT")
     * @Apidoc\Url("/api/app/{id}")
     * @Apidoc\Desc("修改应用名称/用途；被驳回的应用修改后回到待审核状态")
     */
    public function update(Request $request, string $id): Response
    {
        $appId = $this->decodeId($id);
        $app = $appId ? ClientApp::where('id', $appId)->where('client_id', $request->clientId)->first() : null;
        if (!$app) {
            return json(['code' => 404, 'message' => '应用不存在', 'data' => []]);
        }

        $name = trim((string) $request->input('name', ''));
        $purpose = trim((string) $request->input('purpose', ''));
        if ($name !== '') {
            if (mb_strlen($name) > 50) {
                return json(['code' => 422, 'message' => '应用名称过长', 'data' => []]);
            }
            $app->name = $name;
        }
        if ($purpose !== '') {
            if (mb_strlen($purpose) > 200) {
                return json(['code' => 422, 'message' => '申请用途过长', 'data' => []]);
            }
            $app->purpose = $purpose;
        }
        if ($app->status === 'rejected') {
            $app->status = 'pending';
            $app->review_remark = '';
        }
        $app->save();

        return json(['code' => 0, 'message' => '更新成功', 'data' => ['status' => $app->status]]);
    }

    /**
     * @Apidoc\Title("按套餐下单")
     * @Apidoc\Group("客户端门户")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/api/app/{id}/order")
     * @Apidoc\Param("plan_id", type="string", require=true, desc="套餐ID(hashid)")
     * @Apidoc\Param("channel", type="string", require=false, desc="支付渠道: stripe|paypal|crypto|manual", default="manual")
     * @Apidoc\Param("chain", type="string", require=false, desc="虚拟币网络: trc20/bep20/erc20（channel=crypto 时必填）")
     */
    public function order(Request $request, string $id): Response
    {
        $appId = $this->decodeId($id);
        $app = $appId ? ClientApp::where('id', $appId)->where('client_id', $request->clientId)->first() : null;
        if (!$app) {
            return json(['code' => 404, 'message' => '应用不存在', 'data' => []]);
        }
        $planId = $this->decodeId((string) $request->input('plan_id', ''));
        $plan = $planId ? Plan::where('id', $planId)->where('status', 1)->first() : null;
        if (!$plan) {
            return json(['code' => 422, 'message' => '套餐不存在或已停售', 'data' => []]);
        }
        $channel = (string) $request->input('channel', 'manual');
        if (!in_array($channel, PaymentService::CHANNELS, true)) {
            return json(['code' => 422, 'message' => 'channel 必须为 stripe/paypal/crypto/manual', 'data' => []]);
        }
        $chain = '';
        if ($channel === 'crypto') {
            $chain = strtolower((string) $request->input('chain', ''));
            if (!in_array($chain, CryptoService::chains(), true)) {
                return json(['code' => 422, 'message' => 'channel=crypto 时 chain 必须为 trc20/bep20/erc20', 'data' => []]);
            }
        }

        $order = new Order();
        $order->id = SnowflakeService::generate();
        $order->order_no = 'ORD' . $order->id;
        $order->client_id = $request->clientId;
        $order->app_id = $app->id;
        $order->plan_id = $plan->id;
        $order->amount = $plan->price;
        $order->channel = $channel;
        $order->chain = $chain !== '' ? $chain : null;
        $order->status = 'pending';
        $order->save();

        // 应用关联套餐，供管理端审核时确定有效期
        $app->plan_id = $plan->id;
        $app->valid_days = $plan->valid_days;
        $app->save();

        return json(['code' => 0, 'message' => '下单成功', 'data' => [
            'id' => HashidsService::encode($order->id),
            'order_no' => $order->order_no,
            'amount' => $order->amount,
            'plan_name' => $plan->name,
            'channel' => $order->channel,
            'status' => $order->status,
        ]]);
    }

    /**
     * @Apidoc\Title("发起支付")
     * @Apidoc\Group("客户端门户")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/api/order/{id}/pay")
     * @Apidoc\Desc("按订单渠道发起支付：stripe 返回 Checkout Session 跳转链接，paypal 返回 approve 链接，manual 返回模拟支付码由管理员人工确认")
     * @Apidoc\Returned("pay_url", type="string", desc="支付跳转链接（manual 渠道返回 payment_code）")
     */
    public function pay(Request $request, string $id): Response
    {
        $orderId = $this->decodeId($id);
        $order = $orderId ? Order::where('id', $orderId)->where('client_id', $request->clientId)->first() : null;
        if (!$order) {
            return json(['code' => 404, 'message' => '订单不存在', 'data' => []]);
        }
        if ($order->status !== 'pending') {
            return json(['code' => 422, 'message' => '订单状态不允许支付', 'data' => []]);
        }

        // manual 渠道：保持原模拟支付流程，由管理员人工确认
        if ($order->channel === 'manual') {
            $order->status = 'paid';
            $order->paid_at = date('Y-m-d H:i:s');
            $order->save();
            return json(['code' => 0, 'message' => '支付成功，等待管理员确认开通', 'data' => [
                'order_no' => $order->order_no,
                'channel' => 'manual',
                'payment_code' => substr(hash('sha256', $order->order_no), 0, 16),
                'status' => $order->status,
            ]]);
        }

        // crypto 渠道：返回收款地址/金额/memo，用户链上转账后由管理员核验确认
        if ($order->channel === 'crypto') {
            $result = CryptoService::initiate((string) $order->chain, $order->order_no, (int) $order->amount);
            if (!$result['ok']) {
                return json(['code' => 422, 'message' => $result['message'] ?? '支付发起失败', 'data' => []]);
            }
            $order->chain = $result['chain'];
            $order->crypto_amount = $result['amount'];
            $order->memo = $result['memo'];
            $order->save();
            return json(['code' => 0, 'message' => '支付发起成功，请向收款地址转账', 'data' => [
                'order_no' => $order->order_no,
                'channel' => 'crypto',
                'chain' => $result['chain'],
                'address' => $result['address'],
                'amount' => $result['amount'],
                'coin' => $result['coin'],
                'memo' => $result['memo'],
                'status' => $order->status,
            ]]);
        }

        $plan = $order->plan;
        if (!$plan) {
            return json(['code' => 422, 'message' => '套餐不存在', 'data' => []]);
        }
        $base = rtrim((string) getenv('PORTAL_URL'), '/');
        if ($base === '') {
            $scheme = $request->header('x-forwarded-proto', 'https');
            $base = "{$scheme}://{$request->host()}";
        }
        $successUrl = "{$base}/portal/order/{$order->order_no}?paid=1";
        $cancelUrl = "{$base}/portal/order/{$order->order_no}";

        $result = $order->channel === 'stripe'
            ? PaymentService::stripeCheckout($order, $plan, $successUrl, $cancelUrl)
            : PaymentService::paypalOrder($order, $plan, $successUrl, $cancelUrl);
        if (!$result['ok']) {
            return json(['code' => $result['code'], 'message' => $result['message'], 'data' => []]);
        }

        return json(['code' => 0, 'message' => '支付发起成功，请完成支付', 'data' => [
            'order_no' => $order->order_no,
            'channel' => $order->channel,
            'pay_url' => $result['url'],
            'status' => $order->status,
        ]]);
    }

    /**
     * 写入网关 Redis 密钥记录（实际键名前缀 logistics: 由 support\Redis 统一加）
     */
    private function writeRedisKey(string $sha, string $appid, int $expireTs): bool
    {
        try {
            $payload = json_encode([
                'appid' => $appid,
                'status' => 'approved',
                'expire_at' => $expireTs,
            ], JSON_UNESCAPED_UNICODE);
            Redis::setex("api_keys:{$sha}", max($expireTs - time(), 1), $payload);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
