<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

/**
 * 物流聚合配置
 * 凭证来自包配置（config/plugin/erikwang2013/global-logistics/app.php，按承运商代码配置），
 * 不再读写数据库凭证表；结构同 global-logistics 包 config/logistics.php 模板，
 * 供 Logistics::configure() 直接使用。回调验签密钥可在对应承运商数组里加
 * 'webhook_secret' => env('...')；未配置则维持白名单放行。
 */

$config = config('plugin.erikwang2013.global-logistics.app', []);

// 上游模板以 '' 占位可选字段；过滤空值后适配器回落内置默认端点/空凭证，
// 避免把空 endpoint 当 URL 使用（Guzzle 相对 URI 报错）
$config = array_map(
    static fn ($v) => is_array($v) ? array_filter($v, static fn ($x) => $x !== '' && $x !== null) : $v,
    $config
);

// 内部接口共享密钥（e-cat → PHP worker，X-Internal-Token 头）；
// 生产环境必须通过 INTERNAL_TOKEN 环境变量覆盖默认值
$config['internal_token'] = env('INTERNAL_TOKEN', 'lg-internal-8f3a2c9e6b1d4f7a');

return $config;
