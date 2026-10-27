<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use support\Request;
use support\Response;
use support\Db;
use support\Redis;
use Throwable;

/**
 * @Apidoc\Title("健康检查")
 */
class HealthController
{
    /**
     * @Apidoc\Title("健康检查")
     * @Apidoc\Group("运维管理")
     * @Apidoc\Url("/health")
     * @Apidoc\Desc("系统健康检查，返回各组件状态")
     * @Apidoc\Returned("app", type="string", desc="应用名称")
     * @Apidoc\Returned("version", type="string", desc="版本号")
     * @Apidoc\Returned("php", type="string", desc="PHP版本")
     * @Apidoc\Returned("database", type="string", desc="数据库状态")
     * @Apidoc\Returned("redis", type="string", desc="Redis状态")
     * @Apidoc\Returned("timestamp", type="int", desc="服务器时间戳")
     */
    public function index(Request $request): Response
    {
        return json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'app'           => 'open-admin',
                'version'       => '1.0',
                'php'           => PHP_VERSION,
                'database'      => $this->checkDb(),
                'redis'         => $this->checkRedis(),
                'timestamp'     => time(),
            ],
        ]);
    }

    private function checkDb(): string
    {
        try {
            Db::select('SELECT 1');
            return 'ok';
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    private function checkRedis(): string
    {
        try {
            Redis::ping();
            return 'ok';
        } catch (Throwable) {
            return 'unavailable';
        }
    }

}
