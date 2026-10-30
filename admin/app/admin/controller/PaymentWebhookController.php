<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\common\PaymentService;
use app\model\Order;
use support\Request;
use support\Response;

/**
 * 支付 webhook 回调（公开端点，签名校验后按 order_no 确认订单）
 * POST /api/payment/webhook/stripe
 * POST /api/payment/webhook/paypal
 */
class PaymentWebhookController extends BaseController
{
    public function stripe(Request $request): Response
    {
        $raw = (string) $request->rawBody();
        $secret = PaymentService::getConfig('stripe_webhook_secret');
        if ($secret === '') {
            return json(['code' => 503, 'message' => 'webhook not configured'])->withStatus(503);
        }
        if (!PaymentService::verifyStripeSignature($raw, (string) $request->header('stripe-signature', ''), $secret)) {
            return json(['code' => 401, 'message' => 'invalid signature'])->withStatus(401);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload) || !self::stripeEventConfirmsPayment($payload)) {
            return json(['code' => 0, 'message' => 'ok']);
        }
        $orderNo = (string) ($payload['data']['object']['metadata']['order_no'] ?? '');
        return $this->confirmByOrderNo($orderNo);
    }

    /**
     * Stripe 事件是否代表已收款：completed / async_payment_succeeded 且 payment_status=paid
     * Klarna/SEPA 等异步方式 completed 时可能仍为 unpaid，不能据此开通
     */
    public static function stripeEventConfirmsPayment(array $payload): bool
    {
        $type = (string) ($payload['type'] ?? '');
        if (!in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            return false;
        }
        return (string) ($payload['data']['object']['payment_status'] ?? '') === 'paid';
    }

    public function paypal(Request $request): Response
    {
        $raw = (string) $request->rawBody();
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return json(['code' => 400, 'message' => 'invalid json'])->withStatus(400);
        }

        $headers = [
            'transmission_id' => (string) $request->header('paypal-transmission-id', ''),
            'transmission_time' => (string) $request->header('paypal-transmission-time', ''),
            'cert_url' => (string) $request->header('paypal-cert-url', ''),
            'auth_algo' => (string) $request->header('paypal-auth-algo', ''),
            'transmission_sig' => (string) $request->header('paypal-transmission-sig', ''),
        ];
        $verify = PaymentService::verifyPaypalWebhook($headers, $payload);
        if (!$verify['ok'] || $verify['status'] !== 'SUCCESS') {
            return json(['code' => 401, 'message' => 'invalid signature'])->withStatus(401);
        }

        $type = (string) ($payload['event_type'] ?? '');
        $resource = is_array($payload['resource'] ?? null) ? $payload['resource'] : [];
        $units = is_array($resource['purchase_units'] ?? null) ? $resource['purchase_units'] : [];

        // APPROVED 只是买家授权，必须 capture 才真正扣款；capture 失败返回 502 让 PayPal 重试
        if ($type === 'CHECKOUT.ORDER.APPROVED') {
            $orderNo = (string) ($units[0]['custom_id'] ?? ($resource['custom_id'] ?? ''));
            // 本地订单不存在时不扣款，避免产生无主收款
            if ($orderNo === '' || !Order::where('order_no', $orderNo)->exists()) {
                return json(['code' => 0, 'message' => 'ok']);
            }
            $capture = PaymentService::capturePaypalOrder((string) ($resource['id'] ?? ''));
            if (!$capture['ok']) {
                return json(['code' => 502, 'message' => 'capture failed'])->withStatus(502);
            }
            if (($capture['status'] ?? '') !== 'COMPLETED') {
                return json(['code' => 0, 'message' => 'ok']);
            }
            return $this->confirmByOrderNo($orderNo);
        }

        // 扣款完成事件：status 必须是 COMPLETED
        if ($type === 'PAYMENT.CAPTURE.COMPLETED') {
            if ((string) ($resource['status'] ?? '') !== 'COMPLETED') {
                return json(['code' => 0, 'message' => 'ok']);
            }
            $orderNo = (string) ($resource['custom_id'] ?? ($units[0]['custom_id'] ?? ''));
            return $this->confirmByOrderNo($orderNo);
        }

        return json(['code' => 0, 'message' => 'ok']);
    }

    private function confirmByOrderNo(string $orderNo): Response
    {
        if ($orderNo === '') {
            return json(['code' => 0, 'message' => 'ok']);
        }
        $order = Order::where('order_no', $orderNo)->first();
        if ($order) {
            PaymentService::confirmOrder($order);
        }
        return json(['code' => 0, 'message' => 'ok']);
    }
}
