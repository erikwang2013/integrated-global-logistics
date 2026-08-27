<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

/**
 * 物流聚合配置
 * 凭证加载：优先 logistics_carrier_credential 表（每承运商取第一个启用凭证，覆盖对应配置项），
 * 包默认配置（config/plugin/erikwang2013/global-logistics/app.php）兜底。
 * 结构同 global-logistics 包 config/logistics.php 模板，供 Logistics::configure() 直接使用。
 */

use app\model\CarrierCredential;

$config = config('plugin.erikwang2013.global-logistics.app', []);

// 上游模板以 '' 占位可选字段；过滤空值后适配器回落内置默认端点/空凭证，
// 避免把空 endpoint 当 URL 使用（Guzzle 相对 URI 报错）
$config = array_map(
    static fn ($v) => is_array($v) ? array_filter($v, static fn ($x) => $x !== '' && $x !== null) : $v,
    $config
);

try {
    $rows = CarrierCredential::query()
        ->join('carrier', 'carrier.id', '=', 'carrier_credential.carrier_id')
        ->where('carrier_credential.status', 1)
        ->where('carrier.status', 1)
        ->orderBy('carrier_credential.id', 'asc')
        ->get(['carrier_credential.app_key', 'carrier_credential.app_secret', 'carrier_credential.extra', 'carrier.code']);

    $merged = [];
    foreach ($rows as $row) {
        if (isset($merged[$row->code])) {
            continue; // 每承运商仅取第一个启用凭证
        }
        $extra = is_array($row->extra) ? $row->extra : [];
        $credential = [];
        foreach ($extra as $k => $v) {
            if ($v !== '' && $v !== null) {
                $credential[$k] = $v;
            }
        }
        if ($row->app_key !== '') {
            $credential['app_key'] = $row->app_key;
        }
        if ($row->app_secret !== '') {
            $credential['app_secret'] = $row->app_secret;
        }
        $merged[$row->code] = array_merge($config[$row->code] ?? [], $credential);
    }
    $config = array_merge($config, $merged);
} catch (\Throwable $e) {
    // 数据库未初始化（安装向导阶段）时静默回退包默认配置
}

// 内部接口共享密钥（e-cat → PHP worker，X-Internal-Token 头）；
// 生产环境必须通过 INTERNAL_TOKEN 环境变量覆盖默认值
$config['internal_token'] = env('INTERNAL_TOKEN', 'lg-internal-8f3a2c9e6b1d4f7a');

return $config;
