<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\Client;
use app\model\ClientApp;
use app\model\Order;
use app\model\Plan;
use support\Redis;
use support\Request;
use support\Response;
use Throwable;

/**
 * @Apidoc\Title("客户端管理")
 */
class ClientController extends BaseController
{
    /**
     * 应用剩余有效天数（expire_at 存 UTC）
     */
    private function daysLeft(?string $expireAt): int
    {
        if (!$expireAt) {
            return 0;
        }
        $ts = strtotime($expireAt . ' UTC');
        return $ts ? max(0, (int) ceil(($ts - time()) / 86400)) : 0;
    }

    /**
     * @Apidoc\Title("客户端列表")
     * @Apidoc\Group("客户端管理")
     * @Apidoc\Url("/admin/client")
     * @Apidoc\Desc("分页获取客户端列表，含每个客户端的全部应用申请信息（密钥状态、套餐、有效期、订单）")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = trim((string) $request->input('keyword', ''));

        $query = Client::query();
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                  ->orWhere('contact_name', 'like', "%{$keyword}%")
                  ->orWhere('contact_phone', 'like', "%{$keyword}%")
                  ->orWhere('contact_email', 'like', "%{$keyword}%");
            });
        }

        $total = $query->count();
        $clients = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $clientIds = $clients->pluck('id')->all();
        $apps = ClientApp::whereIn('client_id', $clientIds)->orderBy('id', 'desc')->get();
        $appIds = $apps->pluck('id')->all();
        $orders = Order::whereIn('app_id', $appIds)->orderBy('id', 'desc')->get()->groupBy('app_id');
        $plans = Plan::pluck('name', 'id');

        $list = $clients->map(function ($client) use ($apps, $orders, $plans) {
            $clientApps = $apps->where('client_id', $client->id)->values()->map(function ($app) use ($orders, $plans) {
                return $this->encodeIds([
                    'id' => $app->id,
                    'appid' => $app->appid,
                    'name' => $app->name,
                    'purpose' => $app->purpose,
                    'status' => $app->status,
                    'plan_name' => $plans->get($app->plan_id, ''),
                    'valid_days' => $app->valid_days,
                    'expire_at' => $app->expire_at,
                    'days_left' => $this->daysLeft($app->expire_at),
                    'review_remark' => $app->review_remark,
                    'created_at' => $app->created_at,
                    'orders' => ($orders->get($app->id) ?? collect())->values()->map(fn($o) => $this->encodeIds([
                        'id' => $o->id,
                        'order_no' => $o->order_no,
                        'plan_name' => $plans->get($o->plan_id, ''),
                        'amount' => $o->amount,
                        'status' => $o->status,
                        'paid_at' => $o->paid_at,
                        'created_at' => $o->created_at,
                    ]))->all(),
                ]);
            })->all();

            return $this->encodeIds([
                'id' => $client->id,
                'username' => $client->username,
                'contact_name' => $client->contact_name,
                'contact_phone' => $client->contact_phone,
                'contact_email' => $client->contact_email,
                'status' => $client->status,
                'created_at' => $client->created_at,
                'apps' => $clientApps,
            ]);
        });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @Apidoc\Title("应用列表")
     * @Apidoc\Group("客户端管理")
     * @Apidoc\Url("/admin/client/app")
     * @Apidoc\Desc("按状态筛选应用申请（pending/approved/rejected/disabled）")
     * @Apidoc\Param("status", type="string", require=false, desc="状态筛选")
     * @Apidoc\Param("keyword", type="string", require=false, desc="搜索(appid/名称/用户名)")
     */
    public function apps(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $status = (string) $request->input('status', '');
        $keyword = trim((string) $request->input('keyword', ''));

        $query = ClientApp::with('client', 'plan');
        if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected', 'disabled'], true)) {
            $query->where('status', $status);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('appid', 'like', "%{$keyword}%")
                  ->orWhere('name', 'like', "%{$keyword}%")
                  ->orWhereHas('client', fn($c) => $c->where('username', 'like', "%{$keyword}%"));
            });
        }

        $total = $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($app) {
                return $this->encodeIds([
                    'id' => $app->id,
                    'client_id' => $app->client_id,
                    'client_username' => $app->client->username ?? '',
                    'appid' => $app->appid,
                    'name' => $app->name,
                    'purpose' => $app->purpose,
                    'status' => $app->status,
                    'plan_name' => $app->plan->name ?? '',
                    'valid_days' => $app->valid_days,
                    'expire_at' => $app->expire_at,
                    'days_left' => $this->daysLeft($app->expire_at),
                    'review_remark' => $app->review_remark,
                    'created_at' => $app->created_at,
                ]);
            });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @Apidoc\Title("审核应用")
     * @Apidoc\Group("客户端管理")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/admin/client/app/{id}/review")
     * @Apidoc\Desc("approve: 按套餐有效期写入网关Redis生效；reject: 记录驳回原因")
     * @Apidoc\Param("action", type="string", require=true, desc="approve|reject")
     * @Apidoc\Param("remark", type="string", require=false, desc="审核备注（驳回原因）")
     */
    public function review(Request $request, string $id): Response
    {
        $appId = $this->decodeId($id);
        $app = $appId ? ClientApp::find($appId) : null;
        if (!$app) {
            return $this->fail('应用不存在', 404);
        }
        $action = (string) $request->input('action', '');
        if (!in_array($action, ['approve', 'reject'], true)) {
            return $this->fail('action 必须为 approve 或 reject', 422);
        }
        if ($app->status !== 'pending') {
            return $this->fail('仅待审核的应用可操作', 422);
        }
        $remark = trim((string) $request->input('remark', ''));
        if (mb_strlen($remark) > 255) {
            return $this->fail('审核备注过长', 422);
        }

        $app->reviewed_by = (int) ($request->adminId ?? 0);
        $app->reviewed_at = date('Y-m-d H:i:s');

        if ($action === 'approve') {
            $plan = $app->plan_id ? Plan::find($app->plan_id) : null;
            $days = $plan ? (int) $plan->valid_days : (int) $app->valid_days;
            if ($days <= 0) {
                return $this->fail('应用未关联有效套餐，无法通过审核', 422);
            }
            $expireTs = time() + $days * 86400;
            $app->valid_days = $days;
            $app->expire_at = gmdate('Y-m-d H:i:s', $expireTs);
            $app->review_remark = '';
            $app->status = 'approved';
            if (!$this->writeRedisKey($app->api_key_sha256, $app->appid, $expireTs)) {
                return $this->fail('网关密钥写入失败，请稍后重试', 500);
            }
        } else {
            $app->review_remark = $remark;
            $app->status = 'rejected';
            try { Redis::del("api_keys:{$app->api_key_sha256}"); } catch (\Throwable) {}
        }
        $app->save();

        return $this->success(['id' => $id, 'status' => $app->status]);
    }

    /**
     * @Apidoc\Title("禁用应用")
     * @Apidoc\Group("客户端管理")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/admin/client/app/{id}/disable")
     * @Apidoc\Desc("禁用应用并同步删除网关Redis密钥记录")
     */
    public function disable(Request $request, string $id): Response
    {
        $appId = $this->decodeId($id);
        $app = $appId ? ClientApp::find($appId) : null;
        if (!$app) {
            return $this->fail('应用不存在', 404);
        }
        if ($app->status === 'disabled') {
            return $this->success(['id' => $id, 'status' => $app->status]);
        }

        $app->status = 'disabled';
        $app->save();
        try { Redis::del("api_keys:{$app->api_key_sha256}"); } catch (\Throwable) {}

        return $this->success(['id' => $id, 'status' => $app->status]);
    }

    /**
     * 写入网关 Redis 密钥记录（实际键名前缀 logistics: 由 support\Redis 统一加）
     */
    private function writeRedisKey(string $sha, string $appid, int $expireTs): bool
    {
        try {
            $payload = json_encode([
                'appid' => $appid,
                'status' => 'approved',
                'expire_at' => $expireTs,
            ], JSON_UNESCAPED_UNICODE);
            Redis::setex("api_keys:{$sha}", max($expireTs - time(), 1), $payload);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
