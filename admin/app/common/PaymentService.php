<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\common;

use app\model\Order;
use app\model\Plan;
use app\model\SystemConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use support\Db;
use support\Redis;
use Throwable;

/**
 * 支付服务：Stripe/PayPal 网关调用（Guzzle 手写 REST）、密钥读取、订单确认共享逻辑
 * 密钥存 logistics_system_config（group=payment），value 字段经 Encryptable cast 加密
 */
class PaymentService
{
    private const TIMEOUT = 15;

    public const CHANNELS = ['stripe', 'paypal', 'crypto', 'manual'];

    private const STRIPE_PAYMENT_METHODS_WHITELIST = [
        'card', 'apple_pay', 'google_pay', 'link', 'klarna', 'ideal', 'bancontact',
        'giropay', 'sofort', 'eps', 'p24', 'sepa_debit', 'acss_debit', 'afterpay_clearpay',
    ];

    private const STRIPE_PAYMENT_METHODS_DEFAULT = ['card', 'apple_pay', 'google_pay'];

    public static function getConfig(string $key): string
    {
        $value = SystemConfig::where('group', 'payment')->where('key', $key)->value('value');
        return is_string($value) ? trim($value) : '';
    }

    private static function paypalBase(): string
    {
        return getenv('PAYPAL_MODE') === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * Stripe Checkout Session 创建，返回 ['ok'=>true,'url'=>..] 或 ['ok'=>false,'code'=>..]
     */
    public static function stripeCheckout(Order $order, Plan $plan, string $successUrl, string $cancelUrl): array
    {
        $secret = self::getConfig('stripe_secret_key');
        if ($secret === '') {
            return ['ok' => false, 'code' => 'payment_config_missing', 'message' => '支付配置缺失，请联系管理员'];
        }
        try {
            $client = new Client(['timeout' => self::TIMEOUT]);
            $resp = $client->post('https://api.stripe.com/v1/checkout/sessions', [
                'auth' => [$secret, ''],
                'form_params' => [
                    'mode' => 'payment',
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'payment_method_types' => self::stripePaymentMethods(),
                    'metadata' => ['order_no' => $order->order_no],
                    'line_items' => [[
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => 'usd',
                            'unit_amount' => $order->amount,
                            'product_data' => ['name' => $plan->name],
                        ],
                    ]],
                ],
            ]);
            $url = (string) (json_decode((string) $resp->getBody(), true)['url'] ?? '');
            if ($url === '') {
                return ['ok' => false, 'code' => 'payment_gateway_error', 'message' => '支付网关返回异常，请稍后重试'];
            }
            return ['ok' => true, 'url' => $url];
        } catch (Throwable $e) {
            error_log('[payment] stripe checkout failed: ' . $e->getMessage());
            return ['ok' => false, 'code' => 'payment_gateway_error', 'message' => '支付网关调用失败，请稍后重试'];
        }
    }

    /**
     * Stripe 支付方式白名单过滤：配置 JSON 数组非法/白名单外/为空时回退默认
     */
    private static function stripePaymentMethods(): array
    {
        $default = self::STRIPE_PAYMENT_METHODS_DEFAULT;
        $decoded = json_decode(self::getConfig('stripe_payment_methods'), true);
        if (!is_array($decoded)) {
            return $default;
        }
        $methods = array_values(array_filter(
            array_map('strval', $decoded),
            static fn (string $m): bool => in_array($m, self::STRIPE_PAYMENT_METHODS_WHITELIST, true)
        ));
        return $methods === [] ? $default : $methods;
    }

    /**
     * PayPal Orders v2 创建，返回 approve 链接
     */
    public static function paypalOrder(Order $order, Plan $plan, string $returnUrl, string $cancelUrl): array
    {
        $clientId = self::getConfig('paypal_client_id');
        $secret = self::getConfig('paypal_secret');
        if ($clientId === '' || $secret === '') {
            return ['ok' => false, 'code' => 'payment_config_missing', 'message' => '支付配置缺失，请联系管理员'];
        }
        try {
            $client = new Client(['timeout' => self::TIMEOUT]);
            $token = self::paypalAccessToken($client, $clientId, $secret);
            if ($token === '') {
                return ['ok' => false, 'code' => 'payment_gateway_error', 'message' => '支付网关认证失败，请稍后重试'];
            }
            $resp = $client->post(self::paypalBase() . '/v2/checkout/orders', [
                'headers' => ['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'],
                'json' => [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        // custom_id 必须挂在 purchase_unit 上，webhook 才读得到订单号
                        'reference_id' => $order->order_no,
                        'custom_id' => $order->order_no,
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => number_format($order->amount / 100, 2, '.', ''),
                        ],
                    ]],
                    'application_context' => [
                        'return_url' => $returnUrl,
                        'cancel_url' => $cancelUrl,
                        'brand_name' => $plan->name,
                        'user_action' => 'PAY_NOW',
                    ],
                ],
            ]);
            $data = json_decode((string) $resp->getBody(), true);
            $approveUrl = '';
            foreach (($data['links'] ?? []) as $link) {
                if (($link['rel'] ?? '') === 'approve') {
                    $approveUrl = (string) ($link['href'] ?? '');
                    break;
                }
            }
            if ($approveUrl === '') {
                return ['ok' => false, 'code' => 'payment_gateway_error', 'message' => '支付网关返回异常，请稍后重试'];
            }
            return ['ok' => true, 'url' => $approveUrl];
        } catch (Throwable $e) {
            error_log('[payment] paypal order failed: ' . $e->getMessage());
            return ['ok' => false, 'code' => 'payment_gateway_error', 'message' => '支付网关调用失败，请稍后重试'];
        }
    }

    /**
     * PayPal 订单收款（capture）：APPROVED 只是授权，必须 capture 才真正扣款
     * 返回 ['ok'=>true,'status'=>'COMPLETED','capture_id'=>..]；ORDER_ALREADY_CAPTURED 视为成功（幂等）
     */
    public static function capturePaypalOrder(string $paypalOrderId): array
    {
        $clientId = self::getConfig('paypal_client_id');
        $secret = self::getConfig('paypal_secret');
        if ($clientId === '' || $secret === '' || $paypalOrderId === '') {
            return ['ok' => false, 'code' => 'payment_config_missing', 'message' => '支付配置缺失，请联系管理员'];
        }
        try {
            $client = new Client(['timeout' => self::TIMEOUT]);
            $token = self::paypalAccessToken($client, $clientId, $secret);
            if ($token === '') {
                return ['ok' => false, 'code' => 'payment_gateway_error', 'message' => '支付网关认证失败，请稍后重试'];
            }
            $resp = $client->post(self::paypalBase() . '/v2/checkout/orders/' . rawurlencode($paypalOrderId) . '/capture', [
                'headers' => ['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'],
                'body' => '{}',
            ]);
            $data = json_decode((string) $resp->getBody(), true);
            return [
                'ok' => true,
                'status' => (string) ($data['status'] ?? ''),
                'capture_id' => (string) ($data['purchase_units'][0]['payments']['captures'][0]['id'] ?? ''),
            ];
        } catch (Throwable $e) {
            // 重复 capture 返回 422 ORDER_ALREADY_CAPTURED：已扣过款，按成功处理
            if ($e instanceof RequestException && $e->getResponse()?->getStatusCode() === 422) {
                $data = json_decode((string) $e->getResponse()->getBody(), true);
                foreach (($data['details'] ?? []) as $detail) {
                    if (($detail['issue'] ?? '') === 'ORDER_ALREADY_CAPTURED') {
                        return ['ok' => true, 'status' => 'COMPLETED', 'capture_id' => ''];
                    }
                }
            }
            error_log('[payment] paypal capture failed: ' . $e->getMessage());
            return ['ok' => false, 'code' => 'payment_gateway_error', 'message' => '支付网关调用失败，请稍后重试'];
        }
    }

    /**
     * Stripe 签名校验：t=..,v1=.. 头，HMAC-SHA256(时间戳.rawBody, webhook_secret)
     */
    public static function verifyStripeSignature(string $raw, string $header, string $secret): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $item) {
            [$k, $v] = array_pad(explode('=', $item, 2), 2, '');
            $parts[$k] = $v;
        }
        $t = (string) ($parts['t'] ?? '');
        $v1 = (string) ($parts['v1'] ?? '');
        if ($t === '' || $v1 === '') {
            return false;
        }
        // 时间戳容差 5 分钟，防重放
        if (abs((int) $t - time()) > 300) {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $t . '.' . $raw, $secret), $v1);
    }

    /**
     * PayPal webhook 验签（POST /v1/notifications/verify-webhook-signature）
     */
    public static function verifyPaypalWebhook(array $headers, array $event): array
    {
        $webhookId = self::getConfig('paypal_webhook_id');
        $clientId = self::getConfig('paypal_client_id');
        $secret = self::getConfig('paypal_secret');
        if ($webhookId === '' || $clientId === '' || $secret === '') {
            return ['ok' => false, 'status' => 'config_missing'];
        }
        foreach (['transmission_id', 'transmission_time', 'cert_url', 'auth_algo', 'transmission_sig'] as $h) {
            if (($headers[$h] ?? '') === '') {
                return ['ok' => false, 'status' => 'missing_header'];
            }
        }
        try {
            $client = new Client(['timeout' => self::TIMEOUT]);
            $token = self::paypalAccessToken($client, $clientId, $secret);
            if ($token === '') {
                return ['ok' => false, 'status' => 'auth_failed'];
            }
            $resp = $client->post(self::paypalBase() . '/v1/notifications/verify-webhook-signature', [
                'headers' => ['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'],
                'json' => [
                    'transmission_id' => $headers['transmission_id'],
                    'transmission_time' => $headers['transmission_time'],
                    'cert_url' => $headers['cert_url'],
                    'auth_algo' => $headers['auth_algo'],
                    'transmission_sig' => $headers['transmission_sig'],
                    'webhook_id' => $webhookId,
                    'webhook_event' => $event,
                ],
            ]);
            $status = (string) (json_decode((string) $resp->getBody(), true)['verification_status'] ?? 'FAILURE');
            return ['ok' => $status === 'SUCCESS', 'status' => $status];
        } catch (Throwable $e) {
            error_log('[payment] paypal webhook verify failed: ' . $e->getMessage());
            return ['ok' => false, 'status' => 'gateway_error'];
        }
    }

    /**
     * 订单确认（共享逻辑）：pending→paid + 已通过审核的应用按套餐续期 + 重写网关 Redis 密钥
     * 事务 + 条件更新抢占，防止并发 webhook 重复续期；非 pending 订单幂等跳过
     */
    public static function confirmOrder(Order $order, ?string $remark = null): array
    {
        $redisWrite = null;
        $result = Db::transaction(function () use ($order, $remark, &$redisWrite) {
            $affected = Order::where('id', $order->id)->where('status', 'pending')->update([
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
            ]);
            if ($affected === 0) {
                return ['ok' => true, 'skipped' => true, 'status' => $order->status];
            }
            $order->refresh();

            // 同一 app 可能有多笔并发确认，锁住应用行避免 expire_at 丢失更新
            $app = $order->app()->lockForUpdate()->first();
            if (!$app || $app->status !== 'approved') {
                return ['ok' => true, 'skipped' => false, 'renewed' => false];
            }
            $plan = $order->plan;
            $days = $plan ? (int) $plan->valid_days : 0;
            if ($days <= 0) {
                return ['ok' => true, 'skipped' => false, 'renewed' => false];
            }

            $oldTs = $app->expire_at ? strtotime($app->expire_at . ' UTC') : 0;
            $expireTs = max($oldTs ?: 0, time()) + $days * 86400;
            $app->valid_days = $days;
            $app->expire_at = gmdate('Y-m-d H:i:s', $expireTs);
            if ($remark !== null && $remark !== '') {
                $app->review_remark = $remark;
            }
            $app->save();

            // Redis 写在事务提交后，避免 DB 回滚后留下已生效的网关 key
            $redisWrite = [
                'sha' => (string) $app->api_key_sha256,
                'appid' => (string) $app->appid,
                'expire_at' => $expireTs,
            ];

            return ['ok' => true, 'skipped' => false, 'renewed' => true];
        });

        if (is_array($redisWrite)) {
            try {
                $payload = json_encode([
                    'appid' => $redisWrite['appid'],
                    'status' => 'approved',
                    'expire_at' => $redisWrite['expire_at'],
                ], JSON_UNESCAPED_UNICODE);
                Redis::setex("api_keys:{$redisWrite['sha']}", max($redisWrite['expire_at'] - time(), 1), $payload);
            } catch (Throwable) {}
        }

        return $result;
    }

    private static function paypalAccessToken(Client $client, string $clientId, string $secret): string
    {
        $resp = $client->post(self::paypalBase() . '/v1/oauth2/token', [
            'auth' => [$clientId, $secret],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);
        return (string) (json_decode((string) $resp->getBody(), true)['access_token'] ?? '');
    }
}
