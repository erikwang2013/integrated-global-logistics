<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\common;

use Erikwang2013\Snowflake\Snowflake;

/**
 * Snowflake ID 生成服务
 * 用于生成全局唯一 BIGINT 主键，替代数据库自增
 *
 * shortcut: 同机多进程用 PID 派生 worker/datacenter；跨主机或 PID 复用超过 1024 时需要显式 env 或 Redis 序列
 */
class SnowflakeService
{
    private static ?Snowflake $instance = null;

    /**
     * 由 PID 派生 worker_id / datacenter_id（各 0-31）
     * @return array{0: int, 1: int} [workerId, datacenterId]
     */
    public static function idsForPid(int $pid): array
    {
        $pid = $pid > 0 ? $pid : 1;
        return [$pid % 32, intdiv($pid, 32) % 32];
    }

    public static function generate(): int
    {
        if (self::$instance === null) {
            $config = config('snowflake', []);
            // config 已按 env > PID 派生算好默认值；配置缺失时再按当前进程兜底
            [$fallbackWorker, $fallbackDatacenter] = self::idsForPid(getmypid() ?: 1);
            self::$instance = new Snowflake(
                workerId: (int)($config['worker_id'] ?? $fallbackWorker),
                datacenterId: (int)($config['datacenter_id'] ?? $fallbackDatacenter),
                epoch: $config['start_timestamp'] ?? null,
            );
        }
        return self::$instance->nextId();
    }
}
