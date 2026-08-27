<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace support\bootstrap;

use Erikwang2013\Encryptable\Bridge\Webman\WebmanPluginEncryptableConfig;
use Erikwang2013\Encryptable\Encryption;
use ReflectionClass;
use Webman\Database\Initializer;

/**
 * Eloquent 连接初始化（webman/database 插件 bootstrap）
 * 注册 Illuminate Database Capsule，使所有 Eloquent 模型可用 config('database') 连接
 */
class LaravelDb
{
    public static function start($worker = null): void
    {
        // Encryptable cast 在无 DI 容器（CLI/gRPC worker）时走 fallback config，
        // 注入插件配置（key 来自 config/plugin/erikwang2013/encryptable/app.php，可用 ENCRYPTABLE_KEY 覆盖）
        Encryption::setFallbackConfig(new WebmanPluginEncryptableConfig());
        // Initializer.php 文件底部在 include 时会用空 config 预跑一次 init，
        // 提前置位 initialized 守卫导致后续真实初始化被跳过，这里重置守卫
        $prop = (new ReflectionClass(Initializer::class))->getProperty('initialized');
        $prop->setValue(null, false);
        Initializer::init(config('database'));
    }
}
