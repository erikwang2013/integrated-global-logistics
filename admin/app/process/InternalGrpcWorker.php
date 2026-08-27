<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use parse\Grpc;

/**
 * 内部 gRPC worker（e-cat 查询网关 → PHP worker 池；h2c unary，仅内网监听）。
 * webman 不调用 Grpc::run()：onConnect 由 Http2 构造函数设置，
 * 路由注册（loadHook）推迟到 onWorkerStart —— 每进程执行一次，此时配置已加载。
 */
class InternalGrpcWorker extends Grpc
{
    public function __construct($socketName = null, array $contextOption = [])
    {
        // handler 实例由 Container::make 自动装配，socketName 为 null —— 空串不会注册监听
        parent::__construct($socketName ?? '', 'proto', $contextOption);
    }

    public function onWorkerStart($worker): void
    {
        self::loadHook();
        echo '[' . $worker->name . '] ' . count(self::$route) . " grpc routes loaded\n";
    }
}
