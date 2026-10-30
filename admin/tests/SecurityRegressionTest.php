<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use app\admin\controller\CallbackController;
use app\admin\controller\ConfigController;
use app\common\EncryptionService;
use app\common\SnowflakeService;
use support\Request;
use Webman\Config;

/**
 * 安全回归：配置打码、回调签名、Snowflake 多进程 ID 派生
 */
class SecurityRegressionTest extends TestCase
{
    #[Test]
    public function config_mask_value_masks_secret_keys(): void
    {
        $keys = ['payment_secret', 'api_key', 'api-key', 'apikey', 'access_token', 'db_password', 'private_key', 'webhook_secret'];
        foreach ($keys as $key) {
            $this->assertSame('***', ConfigController::maskValue($key, 'plain-value'), "{$key} 应打码");
        }
    }

    #[Test]
    public function config_mask_value_keeps_normal_values(): void
    {
        $this->assertSame('https://example.com', ConfigController::maskValue('site_url', 'https://example.com'));
        $this->assertSame('https://example.com/hook', ConfigController::maskValue('webhook_url', 'https://example.com/hook'));
        $this->assertSame('WH-123', ConfigController::maskValue('paypal_webhook_id', 'WH-123'));
        $this->assertSame('10', ConfigController::maskValue('page_size', '10'));
        $this->assertSame('', ConfigController::maskValue('site_name', ''));
    }

    #[Test]
    public function callback_signature_verification(): void
    {
        $payload = ['tracking_no' => 'YT123', 'events' => [['event_code' => 'IN_TRANSIT']]];
        $secret = 'test-secret';
        $canonical = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $signed = $payload;
        $signed['sign'] = hash('sha256', $secret . $canonical);

        $this->assertTrue(CallbackController::verifySignature($signed, $secret), '正确签名应通过');

        $tampered = $signed;
        $tampered['tracking_no'] = 'TAMPERED';
        $this->assertFalse(CallbackController::verifySignature($tampered, $secret), '篡改内容应失败');

        $this->assertFalse(CallbackController::verifySignature($payload, $secret), '缺少 sign 应失败');
        $this->assertFalse(CallbackController::verifySignature($signed, ''), '空 secret 应失败');
    }

    #[Test]
    public function callback_without_secret_is_rejected_by_default(): void
    {
        $carrier = null;
        foreach ((array) config('logistics', []) as $code => $cfg) {
            if (is_array($cfg) && (string) ($cfg['webhook_secret'] ?? '') === '') {
                $carrier = $code;
                break;
            }
        }
        if ($carrier === null) {
            $this->markTestSkipped('没有未配置 webhook_secret 的承运商可用于验证 fail-closed');
        }

        $original = getenv('CALLBACK_ALLOW_UNSIGNED');
        putenv('CALLBACK_ALLOW_UNSIGNED');
        try {
            $body = json_encode(['tracking_no' => 'T1', 'events' => [['event_code' => 'IN_TRANSIT']]]);
            $raw = "POST /api/callback/{$carrier} HTTP/1.1\r\nHost: localhost\r\n"
                . "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body;
            $response = (new CallbackController())->receive(new Request($raw), (string) $carrier);
            $result = json_decode($response->rawBody(), true);

            $this->assertSame(401, $response->getStatusCode(), '未配置密钥且未显式放行时应拒绝回调');
            $this->assertSame('callback secret not configured', $result['message']);
        } finally {
            putenv($original === false ? 'CALLBACK_ALLOW_UNSIGNED' : 'CALLBACK_ALLOW_UNSIGNED=' . $original);
        }
    }

    #[Test]
    public function encryption_service_rejects_empty_key(): void
    {
        $configRef = new \ReflectionProperty(Config::class, 'config');
        $cacheRef = new \ReflectionProperty(Config::class, 'flatCache');
        $instanceRef = new \ReflectionProperty(EncryptionService::class, 'instance');

        $originalConfig = $configRef->getValue();
        $originalInstance = $instanceRef->getValue();

        try {
            $config = $originalConfig;
            $config['encryption']['key'] = '';
            $configRef->setValue(null, $config);
            $cacheRef->setValue(null, []);
            $instanceRef->setValue(null, null);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('ENCRYPTION_KEY is not configured');
            EncryptionService::encrypt('secret');
        } finally {
            $configRef->setValue(null, $originalConfig);
            $cacheRef->setValue(null, []);
            $instanceRef->setValue(null, $originalInstance);
        }
    }

    #[Test]
    public function snowflake_pid_derivation_is_in_range_and_distinct(): void
    {
        foreach ([0, 1, 31, 32, 123, 124, 1023, 65535] as $pid) {
            [$workerId, $datacenterId] = SnowflakeService::idsForPid($pid);
            $this->assertGreaterThanOrEqual(0, $workerId);
            $this->assertLessThanOrEqual(31, $workerId);
            $this->assertGreaterThanOrEqual(0, $datacenterId);
            $this->assertLessThanOrEqual(31, $datacenterId);
        }

        // 相邻 PID 至少一个分量不同，保证同机多进程不共用序列
        [$w1, $d1] = SnowflakeService::idsForPid(123);
        [$w2, $d2] = SnowflakeService::idsForPid(124);
        $this->assertTrue($w1 !== $w2 || $d1 !== $d2, '相邻 PID 派生的 ID 分量不应完全相同');
    }
}
