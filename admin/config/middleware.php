<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 全局中间件配置
 *
 * 以下中间件对所有请求生效，按注册顺序依次执行。
 * 执行顺序: Cors → SecurityMiddleware (erikwang2013/security-php) → RateLimit → {路由组中间件} → Controller
 * API 版本不在此处理：版本号位于 URL 路径（/api/v1/*），由路由分组直接解析
 */

return [
    '@' => [
        app\middleware\Cors::class,
        app\middleware\Locale::class,
        app\middleware\SecurityFilter::class,  // 基于 erikwang2013/security-php
        app\middleware\RateLimit::class,
    ],
];
