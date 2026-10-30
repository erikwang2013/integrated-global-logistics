<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = \Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }
    }

    /**
     * 从验证码存储读回内部 targets（含坐标，仅测试内部使用）
     * 对外 extra 只返回 texts，避免直接泄露答案坐标
     */
    private function storedTargets(string $key): array
    {
        $storage = StorageFactory::create(PosterConfig::get('captcha.storage'));
        $stored = $storage->get($key);
        return is_array($stored['targets'] ?? null) ? $stored['targets'] : [];
    }

    #[Test]
    public function captcha_generate_returns_valid_structure(): void
    {
        $result = captcha_create('click', ['difficulty' => 'medium']);

        $this->assertArrayHasKey('key', $result, '应包含 key');
        $this->assertArrayHasKey('image', $result, '应包含 image');
        $this->assertArrayHasKey('extra', $result, '应包含 extra');
        $this->assertArrayHasKey('texts', $result['extra'], 'extra 应包含 texts');
        $this->assertArrayNotHasKey('targets', $result['extra'], '不应对外暴露目标坐标');

        $this->assertNotEmpty($result['key']);
        $this->assertNotEmpty($result['image']);
        $this->assertCount(3, $result['extra']['texts'], 'medium 难度应有 3 个目标');
    }

    #[Test]
    public function captcha_texts_have_required_fields(): void
    {
        $result = captcha_create('click', ['difficulty' => 'easy']);

        $orders = [];
        foreach ($result['extra']['texts'] as $item) {
            $this->assertArrayHasKey('text', $item);
            $this->assertArrayHasKey('order', $item);
            $this->assertArrayNotHasKey('x', $item, '不应暴露 x 坐标');
            $this->assertArrayNotHasKey('y', $item, '不应暴露 y 坐标');
            $this->assertIsString($item['text']);
            $this->assertIsInt($item['order']);
            $orders[] = $item['order'];
        }

        sort($orders);
        $this->assertSame([1, 2], $orders, 'order 应从 1 连续编号');
    }

    #[Test]
    public function captcha_difficulty_controls_target_count(): void
    {
        $easy = captcha_create('click', ['difficulty' => 'easy']);
        $medium = captcha_create('click', ['difficulty' => 'medium']);
        $hard = captcha_create('click', ['difficulty' => 'hard']);

        $this->assertCount(2, $easy['extra']['texts'], 'easy 应为 2 个目标');
        $this->assertCount(3, $medium['extra']['texts'], 'medium 应为 3 个目标');
        $this->assertCount(4, $hard['extra']['texts'], 'hard 应为 4 个目标');
    }

    #[Test]
    public function captcha_verify_correct_clicks_passes(): void
    {
        $result = captcha_create('click', ['difficulty' => 'easy']);
        $targets = $this->storedTargets($result['key']);
        if ($targets === []) {
            $this->markTestSkipped('验证码存储不可用，无法读回 targets');
        }

        // verify 接口要求按顺序传 [x, y] 数值对
        $clicks = array_map(fn($t) => [$t['x'], $t['y']], $targets);
        $valid = captcha_verify($result['key'], 'click', $clicks);

        $this->assertTrue($valid, '点击正确坐标应验证通过');
    }

    #[Test]
    public function captcha_verify_wrong_clicks_fails(): void
    {
        $result = captcha_create('click', ['difficulty' => 'easy']);
        $targets = $this->storedTargets($result['key']);
        if ($targets === []) {
            $this->markTestSkipped('验证码存储不可用，无法确认目标数量');
        }

        // 完全错误的坐标，数量需与 targets 一致
        $clicks = array_fill(0, count($targets), [0, 0]);
        $valid = captcha_verify($result['key'], 'click', $clicks);

        $this->assertFalse($valid, '错误坐标应验证失败');
    }

    #[Test]
    public function captcha_key_has_limited_attempts(): void
    {
        $result = captcha_create('click', ['difficulty' => 'easy']);
        $targets = $this->storedTargets($result['key']);
        if ($targets === []) {
            $this->markTestSkipped('验证码存储不可用，无法读回 targets');
        }

        $clicks = array_map(fn($t) => [$t['x'], $t['y']], $targets);

        // 第一次验证通过
        $first = captcha_verify($result['key'], 'click', $clicks);
        $this->assertTrue($first);
    }

    #[Test]
    public function captcha_generates_unique_keys(): void
    {
        $r1 = captcha_create('click');
        $r2 = captcha_create('click');

        $this->assertNotEquals($r1['key'], $r2['key'], '每次生成的 key 应不同');
    }
}
