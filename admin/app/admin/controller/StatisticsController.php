<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use support\Db;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("统计报表")
 */
class StatisticsController extends BaseController
{
    /**
     * @Apidoc\Title("查询统计")
     * @Apidoc\Group("统计报表")
     * @Apidoc\Url("/admin/tracking/statistics")
     * @Apidoc\Desc("轨迹查询统计：总览/按日/按承运商，仅统计成功与失败状态")
     * @Apidoc\Param("days", type="int", require=false, desc="统计天数", default="7")
     * @Apidoc\Param("carrier_code", type="string", require=false, desc="承运商代码")
     * @Apidoc\Returned("overview", type="object", desc="总览")
     * @Apidoc\Returned("by_day", type="array", desc="按日统计")
     * @Apidoc\Returned("by_carrier", type="array", desc="按承运商TOP10")
     */
    public function index(Request $request): Response
    {
        $days = (int) $request->input('days', 7);
        $days = min(max($days, 1), 90);
        $carrierCode = trim((string) $request->input('carrier_code', ''));

        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $where = 'created_at >= ? AND status IN (?, ?)';
        $params = [$since, 'success', 'fail'];
        if ($carrierCode !== '') {
            $where .= ' AND carrier_code = ?';
            $params[] = $carrierCode;
        }

        $overviewRow = Db::select(
            "SELECT COUNT(*) AS total_queries,
                    SUM(status = 'success') AS success_count,
                    SUM(status = 'fail') AS fail_count,
                    ROUND(AVG(cost_ms), 2) AS avg_cost_ms
             FROM logistics_tracking_query WHERE {$where}",
            $params
        )[0] ?? null;
        $overview = $overviewRow ? [
            'total_queries' => (int) $overviewRow->total_queries,
            'success_count' => (int) $overviewRow->success_count,
            'fail_count' => (int) $overviewRow->fail_count,
            'success_rate' => $this->rate($overviewRow->success_count, $overviewRow->total_queries),
            'avg_cost_ms' => (float) ($overviewRow->avg_cost_ms ?? 0),
        ] : $this->emptyOverview();

        $byDay = Db::select(
            "SELECT DATE(created_at) AS date,
                    COUNT(*) AS queries,
                    SUM(status = 'success') AS success_count,
                    ROUND(AVG(cost_ms), 2) AS avg_cost_ms
             FROM logistics_tracking_query WHERE {$where}
             GROUP BY DATE(created_at) ORDER BY date DESC",
            $params
        );

        $byCarrier = Db::select(
            "SELECT carrier_code,
                    COUNT(*) AS queries,
                    SUM(status = 'success') AS success_count,
                    ROUND(AVG(cost_ms), 2) AS avg_cost_ms
             FROM logistics_tracking_query WHERE {$where}
             GROUP BY carrier_code ORDER BY queries DESC LIMIT 10",
            $params
        );

        return $this->success([
            'overview' => $overview,
            'by_day' => array_map(fn($r) => [
                'date' => $r->date,
                'queries' => (int) $r->queries,
                'success_count' => (int) $r->success_count,
                'success_rate' => $this->rate($r->success_count, $r->queries),
                'avg_cost_ms' => (float) ($r->avg_cost_ms ?? 0),
            ], $byDay),
            'by_carrier' => array_map(fn($r) => [
                'carrier_code' => $r->carrier_code,
                'queries' => (int) $r->queries,
                'success_count' => (int) $r->success_count,
                'success_rate' => $this->rate($r->success_count, $r->queries),
                'avg_cost_ms' => (float) ($r->avg_cost_ms ?? 0),
            ], $byCarrier),
        ]);
    }

    private function rate($part, $total): float
    {
        $total = (int) $total;
        return $total > 0 ? round((int) $part * 100 / $total, 2) : 0.0;
    }

    private function emptyOverview(): array
    {
        return [
            'total_queries' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'success_rate' => 0.0,
            'avg_cost_ms' => 0.0,
        ];
    }
}
