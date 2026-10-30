<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\controller\PaymentWebhookController;
use app\common\CryptoService;
use PHPUnit\Framework\TestCase;

/**
 * 支付流程纯逻辑测试：Stripe 确认条件 / 虚拟币金额唯一化（不依赖 DB/网络）
 */
class PaymentFlowTest extends TestCase
{
    private function stripeEvent(string $type, string $paymentStatus): array
    {
        return ['type' => $type, 'data' => ['object' => ['payment_status' => $paymentStatus]]];
    }

    public function testStripeCompletedPaidConfirms(): void
    {
        $this->assertTrue(PaymentWebhookController::stripeEventConfirmsPayment(
            $this->stripeEvent('checkout.session.completed', 'paid')
        ));
    }

    public function testStripeCompletedUnpaidDoesNotConfirm(): void
    {
        // Klarna/SEPA 等异步方式 completed 时可能还是 unpaid
        $this->assertFalse(PaymentWebhookController::stripeEventConfirmsPayment(
            $this->stripeEvent('checkout.session.completed', 'unpaid')
        ));
    }

    public function testStripeAsyncPaymentSucceededPaidConfirms(): void
    {
        $this->assertTrue(PaymentWebhookController::stripeEventConfirmsPayment(
            $this->stripeEvent('checkout.session.async_payment_succeeded', 'paid')
        ));
    }

    public function testStripeOtherEventsDoNotConfirm(): void
    {
        $this->assertFalse(PaymentWebhookController::stripeEventConfirmsPayment(
            $this->stripeEvent('checkout.session.async_payment_succeeded', 'unpaid')
        ));
        $this->assertFalse(PaymentWebhookController::stripeEventConfirmsPayment(
            $this->stripeEvent('checkout.session.expired', 'paid')
        ));
        $this->assertFalse(PaymentWebhookController::stripeEventConfirmsPayment(
            $this->stripeEvent('payment_intent.succeeded', 'paid')
        ));
        $this->assertFalse(PaymentWebhookController::stripeEventConfirmsPayment([]));
    }

    public function testUniqueAmountSkipsUsedAmounts(): void
    {
        $this->assertSame(99.02, CryptoService::uniqueAmount(99.0, [99.0, 99.01]));
    }

    public function testUniqueAmountFallsBackWhenAllOffsetsUsed(): void
    {
        $used = [];
        for ($cents = 1; $cents <= 99; $cents++) {
            $used[] = round(99.0 + $cents / 100, 2);
        }
        $this->assertSame(99.0, CryptoService::uniqueAmount(99.0, $used));
    }

    public function testUniqueAmountKeepsDistanceFromUsedAmounts(): void
    {
        $used = [10.0, 10.01, 10.02, 10.04];
        $amount = CryptoService::uniqueAmount(10.0, $used);
        foreach ($used as $u) {
            $this->assertGreaterThanOrEqual(0.005, abs($amount - $u));
        }
    }
}
