<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use app\api\v1\controller\AuthController;
use app\middleware\AdminAuth;

/**
 * JWT payload 类型判定（纯数组断言，不连 Redis/DB）
 */
class AuthTokenTest extends TestCase
{
    #[Test]
    public function admin_auth_accepts_legacy_access_and_access_tokens(): void
    {
        // 旧版 access token 无 token_type，需继续兼容
        $this->assertTrue(AdminAuth::isAccessPayload(['sub' => 1]));
        $this->assertTrue(AdminAuth::isAccessPayload(['sub' => 1, 'token_type' => 'access']));
    }

    #[Test]
    public function admin_auth_rejects_refresh_and_client_tokens(): void
    {
        $this->assertFalse(AdminAuth::isAccessPayload(['sub' => 1, 'token_type' => 'refresh']));
        $this->assertFalse(AdminAuth::isAccessPayload(['sub' => 1, 'token_type' => 'client']));
    }

    #[Test]
    public function refresh_accepts_only_refresh_tokens(): void
    {
        $this->assertTrue(AuthController::isRefreshPayload(['sub' => 1, 'token_type' => 'refresh']));
        $this->assertFalse(AuthController::isRefreshPayload(['sub' => 1, 'token_type' => 'access']));
        $this->assertFalse(AuthController::isRefreshPayload(['sub' => 1, 'token_type' => 'client']));
        $this->assertFalse(AuthController::isRefreshPayload(['sub' => 1]));
    }
}
