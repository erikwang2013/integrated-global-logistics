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
            return json(['code' => 503, 'message' => 'webhook not configured'], 503);
        }
        if (!PaymentService::verifyStripeSignature($raw, (string) $request->header('stripe-signature', ''), $secret)) {
            return json(['code' => 401, 'message' => 'invalid signature'], 401);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload) || ($payload['type'] ?? '') !== 'checkout.session.completed') {
            return json(['code' => 0, 'message' => 'ok']);
        }
        $orderNo = (string) ($payload['data']['object']['metadata']['order_no'] ?? '');
        return $this->confirmByOrderNo($orderNo);
    }

    public function paypal(Request $request): Response
    {
        $raw = (string) $request->rawBody();
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return json(['code' => 400, 'message' => 'invalid json'], 400);
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
            return json(['code' => 401, 'message' => 'invalid signature'], 401);
        }

        $type = (string) ($payload['event_type'] ?? '');
        if (!in_array($type, ['PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.APPROVED'], true)) {
            return json(['code' => 0, 'message' => 'ok']);
        }
        $orderNo = (string) ($payload['resource']['custom_id'] ?? '');
        return $this->confirmByOrderNo($orderNo);
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
