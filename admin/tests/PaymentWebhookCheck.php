<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

use app\common\PaymentService;

require_once __DIR__ . '/bootstrap.php';

/**
 * M8 验证补充：Stripe webhook 签名校验（纯函数，无 DB/网络依赖）
 * 运行：php -d zend.assertions=1 tests/PaymentWebhookCheck.php
 */

$secret = 'whsec_test_' . bin2hex(random_bytes(8));
$raw = '{"type":"checkout.session.completed","data":{"object":{"metadata":{"order_no":"ORD123"}}}}';
$t = (string) time();
$sig = hash_hmac('sha256', $t . '.' . $raw, $secret);

// 1. 正确签名 → true
assert(PaymentService::verifyStripeSignature($raw, "t={$t},v1={$sig}", $secret) === true);

// 2. body 篡改 → false
assert(PaymentService::verifyStripeSignature($raw . ' ', "t={$t},v1={$sig}", $secret) === false);

// 3. 签名篡改 → false
assert(PaymentService::verifyStripeSignature($raw, "t={$t},v1=" . strrev($sig), $secret) === false);

// 4. 密钥不匹配 → false
assert(PaymentService::verifyStripeSignature($raw, "t={$t},v1={$sig}", $secret . 'x') === false);

// 5. 过期时间戳（6 分钟前，签名按该时间戳计算）→ false
$tOld = (string) (time() - 360);
$sigOld = hash_hmac('sha256', $tOld . '.' . $raw, $secret);
assert(PaymentService::verifyStripeSignature($raw, "t={$tOld},v1={$sigOld}", $secret) === false);

// 6. 未来时间戳（超 5 分钟容差）→ false
$tFuture = (string) (time() + 360);
$sigFuture = hash_hmac('sha256', $tFuture . '.' . $raw, $secret);
assert(PaymentService::verifyStripeSignature($raw, "t={$tFuture},v1={$sigFuture}", $secret) === false);

// 7. 头缺 t 或 v1 → false
assert(PaymentService::verifyStripeSignature($raw, "v1={$sig}", $secret) === false);
assert(PaymentService::verifyStripeSignature($raw, "t={$t}", $secret) === false);

// 8. 头内字段顺序无关（v1 在前）→ true
assert(PaymentService::verifyStripeSignature($raw, "v1={$sig},t={$t}", $secret) === true);

echo "PaymentWebhookCheck: all assertions passed\n";
