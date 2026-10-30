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
// 无内置默认值：未配置 INTERNAL_TOKEN 时内部接口拒绝所有请求（fail closed）
$config['internal_token'] = env('INTERNAL_TOKEN', '');

// 承运商请求必须在网关 15s 超时之前返回：6s × 2 次尝试 + 退避 ≈ 12.2s。
// http_errors=false 保持与包默认一致，让 4xx/5xx 由重试客户端按响应处理。
$config['max_retries'] = 1;
$config['http_client'] = new \GuzzleHttp\Client([
    'timeout' => 6.0,
    'connect_timeout' => 3.0,
    'http_errors' => false,
]);

return $config;
