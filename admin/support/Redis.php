<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace support;

use Redis as RedisClient;
use RuntimeException;

/**
 * Redis 工具类 — 单例连接池
 */
class Redis
{
    private const DEFAULT_PREFIX = 'logistics:';

    private static ?string $prefix = null;
    private static ?RedisClient $instance = null;

    private static function getPrefix(): string
    {
        if (self::$prefix === null) {
            $env = getenv('REDIS_PREFIX');
            self::$prefix = ($env !== false && $env !== '') ? rtrim($env, ':') . ':' : self::DEFAULT_PREFIX;
        }
        return self::$prefix;
    }

    private static function getInstance(): RedisClient
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int)(getenv('REDIS_PORT') ?: 6379);
        $pass = getenv('REDIS_PASSWORD') ?: null;
        $db   = (int)(getenv('REDIS_DB') ?: 0);

        $redis = new RedisClient();
        if (!$redis->connect($host, $port)) {
            throw new RuntimeException("Redis connect failed: {$host}:{$port}");
        }
        if ($pass !== null && $pass !== '') {
            $redis->auth($pass);
        }
        if ($db !== 0) {
            $redis->select($db);
        }

        self::$instance = $redis;
        return self::$instance;
    }

    private static function prefixKey(string $key): string
    {
        if (str_starts_with($key, self::getPrefix())) {
            return $key;
        }
        return self::getPrefix() . $key;
    }

    public static function __callStatic(string $name, array $arguments): mixed
    {
        if ($name === 'eval' || $name === 'evalsha') {
            // phpredis 签名：eval(script, args, num_keys)，args 前 num_keys 个为 key
            $numKeys = (int)($arguments[2] ?? 0);
            if (isset($arguments[1]) && is_array($arguments[1])) {
                for ($i = 0; $i < $numKeys; $i++) {
                    if (array_key_exists($i, $arguments[1])) {
                        $arguments[1][$i] = self::prefixKey((string)$arguments[1][$i]);
                    }
                }
            }
        } elseif (!empty($arguments) && is_string($arguments[0])) {
            $arguments[0] = self::prefixKey($arguments[0]);
        }

        try {
            return self::getInstance()->{$name}(...$arguments);
        } catch (\RedisException $e) {
            // 连接失效：丢弃实例，下次调用重连；不自动重试非幂等命令
            self::$instance = null;
            throw $e;
        }
    }
}
