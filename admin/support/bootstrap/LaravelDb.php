<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace support\bootstrap;

use Webman\Database\Initializer;

/**
 * Eloquent 连接初始化（webman/database 插件 bootstrap）
 * 注册 Illuminate Database Capsule，使所有 Eloquent 模型可用 config('database') 连接
 */
class LaravelDb
{
    public static function start($worker = null): void
    {
        Initializer::init(config('database'));
    }
}
