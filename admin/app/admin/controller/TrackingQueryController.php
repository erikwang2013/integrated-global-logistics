<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\TrackingQuery;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("轨迹查询记录")
 */
class TrackingQueryController extends BaseController
{
    /**
     * @Apidoc\Title("查询记录列表")
     * @Apidoc\Group("轨迹查询记录")
     * @Apidoc\Url("/admin/tracking/query")
     * @Apidoc\Desc("分页获取轨迹查询记录，支持承运商/运单号/状态/时间筛选")
     * @Apidoc\Param("page", type="int", require=false, desc="页码", default="1")
     * @Apidoc\Param("limit", type="int", require=false, desc="每页条数", default="15")
     * @Apidoc\Param("carrier_code", type="string", require=false, desc="承运商代码")
     * @Apidoc\Param("tracking_no", type="string", require=false, desc="运单号")
     * @Apidoc\Param("status", type="string", require=false, desc="查询状态(success/fail)")
     * @Apidoc\Param("start_time", type="string", require=false, desc="开始时间(Y-m-d H:i:s)")
     * @Apidoc\Param("end_time", type="string", require=false, desc="结束时间(Y-m-d H:i:s)")
     * @Apidoc\Returned("list", type="array", desc="查询记录列表")
     * @Apidoc\Returned("total", type="int", desc="总数")
     * @Apidoc\Returned("page", type="int", desc="当前页码")
     * @Apidoc\Returned("limit", type="int", desc="每页条数")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $carrierCode = trim((string) $request->input('carrier_code', ''));
        $trackingNo = trim((string) $request->input('tracking_no', ''));
        $status = $request->input('status');
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');

        $query = TrackingQuery::query();
        if ($carrierCode !== '') {
            $query->where('carrier_code', $carrierCode);
        }
        if ($trackingNo !== '') {
            $query->where('tracking_no', 'like', "%{$trackingNo}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (string) $status);
        }
        if ($startTime !== null && $startTime !== '') {
            $query->where('created_at', '>=', (string) $startTime);
        }
        if ($endTime !== null && $endTime !== '') {
            $query->where('created_at', '<=', (string) $endTime);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'desc')
                      ->get()
                      ->map(fn($record) => $this->encodeIds($record->toArray(), ['id', 'carrier_id', 'credential_id']));

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }
}
