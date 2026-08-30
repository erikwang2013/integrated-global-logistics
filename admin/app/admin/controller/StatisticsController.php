<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\common\PaymentService;
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

    /**
     * @Apidoc\Title("订单统计")
     * @Apidoc\Group("统计报表")
     * @Apidoc\Url("/admin/order/statistics")
     * @Apidoc\Desc("订单统计：总览/按日/按渠道/按套餐TOP10")
     * @Apidoc\Param("days", type="int", require=false, desc="统计天数", default="30")
     * @Apidoc\Returned("overview", type="object", desc="总览")
     * @Apidoc\Returned("by_day", type="array", desc="按日统计")
     * @Apidoc\Returned("by_channel", type="array", desc="按渠道统计")
     * @Apidoc\Returned("by_plan", type="array", desc="按套餐TOP10")
     */
    public function order(Request $request): Response
    {
        $days = (int) $request->input('days', 30);
        $days = min(max($days, 1), 90);

        // 日期零点起算（含今天），SQL 隐式转零点，与 by_day 序列口径一致
        $since = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $params = [$since];

        $overviewRow = Db::select(
            "SELECT COUNT(*) AS total_orders,
                    SUM(status = 'paid') AS paid_count,
                    SUM(status = 'pending') AS pending_count,
                    SUM(status = 'cancelled') AS cancelled_count,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) AS paid_amount
             FROM logistics_order WHERE created_at >= ?",
            $params
        )[0] ?? null;
        $overview = $overviewRow ? [
            'total_orders' => (int) $overviewRow->total_orders,
            'paid_count' => (int) $overviewRow->paid_count,
            'pending_count' => (int) $overviewRow->pending_count,
            'cancelled_count' => (int) $overviewRow->cancelled_count,
            'paid_amount' => round($overviewRow->paid_amount / 100, 2),
            'paid_rate' => $this->rate($overviewRow->paid_count, $overviewRow->total_orders),
        ] : $this->emptyOrderOverview();

        // 生成日期序列（参照 DashboardController::getTrends，正序含今天）
        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $dates[] = date('Y-m-d', strtotime("+{$i} days", strtotime($since)));
        }

        $byDayRows = Db::select(
            "SELECT DATE(created_at) AS date,
                    COUNT(*) AS orders,
                    SUM(status = 'paid') AS paid_count,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) AS paid_amount
             FROM logistics_order WHERE created_at >= ?
             GROUP BY DATE(created_at)",
            $params
        );
        $dayMap = [];
        foreach ($byDayRows as $r) {
            $dayMap[$r->date] = $r;
        }
        $byDay = array_map(function ($date) use ($dayMap) {
            $r = $dayMap[$date] ?? null;
            return [
                'date' => $date,
                'orders' => $r ? (int) $r->orders : 0,
                'paid_count' => $r ? (int) $r->paid_count : 0,
                'paid_amount' => $r ? round($r->paid_amount / 100, 2) : 0.0,
            ];
        }, $dates);

        $byChannelRows = Db::select(
            "SELECT channel,
                    COUNT(*) AS orders,
                    SUM(status = 'paid') AS paid_count,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) AS paid_amount
             FROM logistics_order WHERE created_at >= ?
             GROUP BY channel",
            $params
        );
        $channelMap = [];
        foreach ($byChannelRows as $r) {
            $channelMap[$r->channel] = $r;
        }
        $byChannel = array_map(function ($channel) use ($channelMap) {
            $r = $channelMap[$channel] ?? null;
            return [
                'channel' => $channel,
                'orders' => $r ? (int) $r->orders : 0,
                'paid_count' => $r ? (int) $r->paid_count : 0,
                'paid_amount' => $r ? round($r->paid_amount / 100, 2) : 0.0,
            ];
        }, PaymentService::CHANNELS);

        $byPlan = Db::select(
            "SELECT COALESCE(p.name, '未知套餐') AS plan_name,
                    COUNT(*) AS orders,
                    COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.amount END), 0) AS paid_amount
             FROM logistics_order o
             LEFT JOIN logistics_plan p ON p.id = o.plan_id
             WHERE o.created_at >= ?
             GROUP BY p.name ORDER BY orders DESC LIMIT 10",
            $params
        );

        return $this->success([
            'overview' => $overview,
            'by_day' => $byDay,
            'by_channel' => $byChannel,
            'by_plan' => array_map(fn($r) => [
                'plan_name' => $r->plan_name,
                'orders' => (int) $r->orders,
                'paid_amount' => round($r->paid_amount / 100, 2),
            ], $byPlan),
        ]);
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

    private function emptyOrderOverview(): array
    {
        return [
            'total_orders' => 0,
            'paid_count' => 0,
            'pending_count' => 0,
            'cancelled_count' => 0,
            'paid_amount' => 0.0,
            'paid_rate' => 0.0,
        ];
    }
}
