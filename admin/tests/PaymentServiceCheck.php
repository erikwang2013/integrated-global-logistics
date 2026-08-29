<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

use app\common\PaymentService;

require_once __DIR__ . '/bootstrap.php';

/**
 * PaymentService 纯逻辑自检：Stripe 签名校验（不依赖 DB/网络）
 * 运行：php tests/PaymentServiceCheck.php
 */
$secret = 'whsec_test_secret';
$raw = '{"type":"checkout.session.completed"}';
$t = (string) time();
$sig = hash_hmac('sha256', $t . '.' . $raw, $secret);
$header = "t={$t},v1={$sig},v0=ignored";

assert(PaymentService::verifyStripeSignature($raw, $header, $secret) === true);
assert(PaymentService::verifyStripeSignature($raw, "t={$t},v1=fake", $secret) === false);
assert(PaymentService::verifyStripeSignature($raw, $header, 'wrong_secret') === false);
assert(PaymentService::verifyStripeSignature($raw, '', $secret) === false);
// 过期时间戳（重放）应拒绝
$old = 't=' . (time() - 3600) . ',v1=' . hash_hmac('sha256', (time() - 3600) . '.' . $raw, $secret);
assert(PaymentService::verifyStripeSignature($raw, $old, $secret) === false);

echo "PaymentServiceCheck: all assertions passed\n";
