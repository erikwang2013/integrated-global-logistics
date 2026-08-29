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
                    'custom_id' => $order->order_no,
                    'purchase_units' => [[
                        'reference_id' => $order->order_no,
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
     * 幂等：非 pending 订单直接跳过
     */
    public static function confirmOrder(Order $order, ?string $remark = null): array
    {
        if ($order->status !== 'pending') {
            return ['ok' => true, 'skipped' => true, 'status' => $order->status];
        }

        $order->status = 'paid';
        $order->paid_at = date('Y-m-d H:i:s');
        $order->save();

        $app = $order->app;
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

        try {
            $payload = json_encode([
                'appid' => $app->appid,
                'status' => 'approved',
                'expire_at' => $expireTs,
            ], JSON_UNESCAPED_UNICODE);
            Redis::setex("api_keys:{$app->api_key_sha256}", max($expireTs - time(), 1), $payload);
        } catch (Throwable) {}

        return ['ok' => true, 'skipped' => false, 'renewed' => true];
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
