<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EnvConfigTest extends TestCase
{
    protected function setUp(): void
    {
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = \Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }
    }

    #[Test]
    public function env_file_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../.env');
    }

    #[Test]
    public function env_example_file_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../.env.example');
    }

    #[Test]
    public function getenv_reads_env_variables(): void
    {
        // 不依赖开发机 .env：自己设置变量并断言 getenv 可读
        $original = getenv('APP_NAME');
        putenv('APP_NAME=test-env');
        $this->assertSame('test-env', getenv('APP_NAME'), 'getenv 应读到刚设置的变量');
        putenv($original === false ? 'APP_NAME' : 'APP_NAME=' . $original);
    }

    #[Test]
    public function getenv_fallback_pattern_works(): void
    {
        $name = 'OPEN_ADMIN_TEST_TEMP_VAR';
        putenv($name . '=temporary');
        $this->assertSame('temporary', getenv($name) ?: 'FALLBACK_OK');

        // 清理后应走 fallback
        putenv($name);
        $val = getenv($name) ?: 'FALLBACK_OK';
        $this->assertEquals('FALLBACK_OK', $val);
    }

    #[Test]
    public function config_env_keys_exist_in_dotenv(): void
    {
        // 检查版本控制内的 .env.example；.env 属于本机私密文件，不参与断言
        $example = __DIR__ . '/../.env.example';
        if (!is_file($example) || !is_readable($example)) {
            $this->markTestSkipped('.env.example 不存在或不可读');
        }

        $envContent = (string) file_get_contents($example);
        preg_match_all('/^([A-Z_][A-Z0-9_]*)=/m', $envContent, $matches);
        $envKeys = array_flip($matches[1]);

        // .env.example 中沿用的旧变量名
        $aliases = [
            'JWT_SECRET_KEY'      => 'JWT_SECRET',
            'JWT_DEFAULT_EXPIRE'  => 'JWT_TTL',
            'JWT_REFRESH_EXPIRE'  => 'JWT_REFRESH_TTL',
        ];
        // 可留空的可选变量（空值时使用内置默认行为）
        $optional = ['RSA_PRIVATE_KEY', 'INTERNAL_GRPC_LISTEN', 'INTERNAL_GRPC_WORKERS'];

        $configFiles = glob(__DIR__ . '/../config/*.php');
        $missingKeys = [];

        foreach ($configFiles as $file) {
            $content = file_get_contents($file);
            preg_match_all("/getenv\('([A-Z_][A-Z0-9_]*)'\)/", $content, $m);
            foreach ($m[1] as $key) {
                if (isset($envKeys[$key]) || in_array($key, $optional, true)) {
                    continue;
                }
                $alias = $aliases[$key] ?? null;
                if ($alias !== null && isset($envKeys[$alias])) {
                    continue;
                }
                $missingKeys[] = basename($file) . ": $key";
            }
        }

        $missingKeys = array_unique($missingKeys);
        $this->assertEmpty($missingKeys, '以下 env key 在 .env.example 中缺失: ' . implode(', ', $missingKeys));
    }

    #[Test]
    public function critical_config_types(): void
    {
        $this->assertIsNumeric(getenv('JWT_TTL') ?: 7200, 'JWT_TTL 应为数字');
        $this->assertIsNumeric(getenv('DB_PORT') ?: 3306, 'DB_PORT 应为数字');
        $this->assertIsString(getenv('JWT_SECRET_KEY') ?: 'x', 'JWT_SECRET_KEY 应为字符串');
        $this->assertIsString(getenv('HASHIDS_SALT') ?: 'x', 'HASHIDS_SALT 应为字符串');
    }
}
