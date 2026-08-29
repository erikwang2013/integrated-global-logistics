<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

use app\common\PaymentService;
use app\model\Order;
use app\model\Plan;
use app\model\SystemConfig;

require_once __DIR__ . '/bootstrap.php';

/**
 * M10 验证：stripe_payment_methods 配置 → payment_method_types 请求体
 * 捕获方式：禁用 curl 强制 Guzzle 走 StreamHandler，注册 https:// 流包装器记录请求体并返回假响应
 * 运行：php -d disable_functions=curl_init,curl_setopt,curl_setopt_array,curl_exec,curl_reset,curl_getinfo,curl_error,curl_errno,curl_close,curl_version,curl_multi_init,curl_multi_exec tests/PaymentServiceMethodsCheck.php
 * 说明：依赖本地 DB（webman .env），测试结束清理配置行
 */

$GLOBALS['captured'] = [];
$GLOBALS['lastBody'] = '';

class M10CaptureWrapper
{
    public $context;
    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        $opts = stream_context_get_options($this->context);
        $GLOBALS['lastBody'] = (string) ($opts['http']['content'] ?? '');
        $GLOBALS['captured'][] = $path;
        $this->data = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n\r\n{\"id\":\"cs_test_capture\",\"url\":\"https://checkout.stripe.com/c/pay/capture123\"}";
        $this->pos = 0;
        return true;
    }
    public function stream_read($count) { $chunk = substr($this->data, $this->pos, $count); $this->pos += strlen($chunk); return $chunk; }
    public function stream_eof(): bool { return $this->pos >= strlen($this->data); }
    public function stream_stat(): array { return []; }
    public function stream_close(): void {}
    public function stream_set_option($o, $v1, $v2): bool { return true; }
}

stream_wrapper_unregister('https');
stream_wrapper_register('https', M10CaptureWrapper::class);

function setConfig(string $key, ?string $value): void
{
    SystemConfig::where('group', 'payment')->where('key', $key)->delete();
    if ($value !== null) {
        $c = new SystemConfig();
        $c->id = (int) (((int) (microtime(true) * 1000)) * 100000 + random_int(0, 99999));
        $c->group = 'payment';
        $c->key = $key;
        $c->value = $value;
        $c->type = 'string';
        $c->description = 'M10 test';
        $c->save();
    }
}

$order = new Order();
$order->order_no = 'ORD' . bin2hex(random_bytes(6));
$order->amount = 990;
$plan = new Plan();
$plan->name = 'Test Plan';

function checkoutMethods(): array
{
    $GLOBALS['captured'] = [];
    $GLOBALS['lastBody'] = '';
    PaymentService::stripeCheckout(
        clone $GLOBALS['order'], clone $GLOBALS['plan'],
        'https://example.com/success', 'https://example.com/cancel'
    );
    // 注意：StreamHandler 无法从用户态包装器读取响应头（$http_response_header 仅原生包装器填充），
    // stripeCheckout 因此返回 payment_gateway_error —— 不影响请求体断言
    if ($GLOBALS['lastBody'] === '') {
        return [];
    }
    parse_str($GLOBALS['lastBody'], $parsed);
    $m = $parsed['payment_method_types'] ?? [];
    return is_array($m) ? array_values($m) : [$m];
}

$GLOBALS['order'] = $order;
$GLOBALS['plan'] = $plan;

setConfig('stripe_secret_key', 'sk_test_fake');

// 1. 无配置 → 默认
setConfig('stripe_payment_methods', null);
assert(checkoutMethods() === ['card', 'apple_pay', 'google_pay']);

// 2. 合法配置全过白名单
setConfig('stripe_payment_methods', json_encode(['card', 'klarna', 'sepa_debit']));
assert(checkoutMethods() === ['card', 'klarna', 'sepa_debit']);

// 3. 非法值 → 丢弃；全部非法 → 回退默认
setConfig('stripe_payment_methods', json_encode(['bitcoin']));
assert(checkoutMethods() === ['card', 'apple_pay', 'google_pay']);

// 4. 空数组 → 默认
setConfig('stripe_payment_methods', '[]');
assert(checkoutMethods() === ['card', 'apple_pay', 'google_pay']);

// 5. 非 JSON → 默认
setConfig('stripe_payment_methods', 'not-json');
assert(checkoutMethods() === ['card', 'apple_pay', 'google_pay']);

// 6. 混合：非法丢弃、合法保留
setConfig('stripe_payment_methods', json_encode(['card', 'bitcoin']));
assert(checkoutMethods() === ['card']);

// 7. 非字符串元素 → strval 后不在白名单 → 丢弃
setConfig('stripe_payment_methods', json_encode(['card', 123]));
assert(checkoutMethods() === ['card']);

// 8. 大小写敏感：大写不在白名单 → 默认
setConfig('stripe_payment_methods', json_encode(['CARD']));
assert(checkoutMethods() === ['card', 'apple_pay', 'google_pay']);

// 9. 部分白名单（apple_pay 子集）
setConfig('stripe_payment_methods', json_encode(['apple_pay']));
assert(checkoutMethods() === ['apple_pay']);

// 10. secret 缺失 → payment_config_missing，不发请求、不泄露
setConfig('stripe_secret_key', null);
setConfig('stripe_payment_methods', null);
$GLOBALS['captured'] = [];
$GLOBALS['lastBody'] = '';
$res = PaymentService::stripeCheckout(
    clone $GLOBALS['order'], clone $GLOBALS['plan'],
    'https://example.com/success', 'https://example.com/cancel'
);
assert(($res['code'] ?? '') === 'payment_config_missing');
assert($GLOBALS['captured'] === []);
assert(strpos($GLOBALS['lastBody'], 'stripe_payment_methods') === false);

// 清理
setConfig('stripe_secret_key', null);
setConfig('stripe_payment_methods', null);

echo "PaymentServiceMethodsCheck: all assertions passed\n";
