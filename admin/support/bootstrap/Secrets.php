<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace support\bootstrap;

/**
 * 启动时校验密钥类环境变量
 * 已安装环境缺失密钥时拒绝启动，避免用空密钥签名 JWT / 加密数据
 */
class Secrets
{
    /** 历史版本中公开的默认值，视同未配置 */
    private const KNOWN_DEFAULTS = [
        'JWT_SECRET_KEY' => 'open-admin-jwt-secret-change-in-production',
        'ENCRYPTABLE_KEY' => 'open-admin-db-encryption-key-32b',
        'ENCRYPTION_KEY' => 'open-admin-api-encryption-key32b',
    ];

    public static function start($worker = null): void
    {
        $lock = function_exists('runtime_path')
            ? runtime_path('install.lock')
            : dirname(__DIR__, 2) . '/runtime/install.lock';
        // 尚未安装：安装器负责生成密钥，避免 Docker 在 /install 前无法启动
        if (!is_file($lock)) {
            return;
        }

        $missing = [];
        foreach (self::KNOWN_DEFAULTS as $key => $default) {
            $value = getenv($key);
            if ($value === false || $value === '' || $value === $default) {
                $missing[] = $key;
            }
        }
        if ($missing === []) {
            return;
        }

        $message = sprintf(
            '[secrets] 缺少安全密钥 %s，请执行 `openssl rand -hex 32` 生成后写入 .env（已安装环境禁止使用空密钥或公开默认值）',
            implode(', ', $missing)
        );
        if (filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)) {
            // 仅本地/测试环境：调试模式下降级为警告
            error_log($message . '；APP_DEBUG 已开启，继续启动');
            return;
        }
        throw new \RuntimeException($message);
    }
}
