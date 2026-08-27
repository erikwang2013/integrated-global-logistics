<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controllers\internal;

use support\Request;
use support\Response;

/**
 * 内部承运商注册表（e-cat 缓存 10min 供对外 /v1/carriers）
 *
 * GET /internal/carriers → 包内 Resources/carrier-registry.php 的承运商清单
 */
class InternalCarrierController
{
    private const REGISTRY_FILE = 'vendor/erikwang2013/global-logistics/src/Resources/carrier-registry.php';

    public function carriers(Request $request): Response
    {
        $registry = require base_path() . '/' . self::REGISTRY_FILE;

        $list = [];
        foreach ($registry as $channel => $carriers) {
            foreach (array_keys($carriers) as $code) {
                $list[] = ['carrier_code' => $code, 'channel' => $channel];
            }
        }

        return json(['code' => 0, 'message' => 'ok', 'data' => ['carriers' => $list]], JSON_UNESCAPED_UNICODE);
    }
}
