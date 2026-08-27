<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\common\EncryptionService;
use app\model\CallbackSubscription;
use app\model\Carrier;
use app\model\TrackingEvent;
use support\Request;
use support\Response;
use Webman\RedisQueue\Client as QueueClient;
use Webman\RedisQueue\Redis;
use Webman\Validation\Validator;

/**
 * @Apidoc\Title("回调订阅管理")
 */
class CallbackSubscriptionController extends BaseController
{
    /**
     * @Apidoc\Title("订阅列表")
     * @Apidoc\Group("回调订阅管理")
     * @Apidoc\Url("/admin/callback/subscription")
     * @Apidoc\Desc("分页获取回调订阅列表，按承运商筛选")
     * @Apidoc\Param("page", type="int", require=false, desc="页码", default="1")
     * @Apidoc\Param("limit", type="int", require=false, desc="每页条数", default="15")
     * @Apidoc\Param("carrier_id", type="string", require=false, desc="承运商hashid")
     * @Apidoc\Param("status", type="int", require=false, desc="状态筛选(0禁用1启用)")
     * @Apidoc\Returned("list", type="array", desc="订阅列表")
     * @Apidoc\Returned("total", type="int", desc="总数")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $status = $request->input('status');

        $query = CallbackSubscription::query()->with('carrier:id,code,name');
        if ($request->input('carrier_id') !== null && $request->input('carrier_id') !== '') {
            $query->where('carrier_id', $this->decodeId($request->input('carrier_id')));
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'desc')
                      ->get()
                      ->map(function ($subscription) {
                          $data = $this->encodeIds($subscription->toArray(), ['id', 'carrier_id']);
                          if (isset($data['carrier']['id'])) {
                              $data['carrier']['id'] = $this->encodeId((int) $data['carrier']['id']);
                          }
                          return $data;
                      });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @Apidoc\Title("创建订阅")
     * @Apidoc\Group("回调订阅管理")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/admin/callback/subscription")
     * @Apidoc\Desc("创建新的回调订阅")
     * @Apidoc\Param("carrier_id", type="string", require=true, desc="承运商hashid")
     * @Apidoc\Param("callback_url", type="string", require=true, desc="回调URL")
     * @Apidoc\Param("secret", type="string", require=false, desc="回调签名密钥")
     * @Apidoc\Param("event_type", type="string", require=false, desc="事件类型", default="tracking.update")
     * @Apidoc\Param("status", type="int", require=false, desc="状态", default="1")
     * @Apidoc\Param("max_retry", type="int", require=false, desc="最大重试次数", default="3")
     */
    public function store(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'carrier_id' => 'required|string',
            'callback_url' => 'required|string|max:500',
            'secret' => 'string|max:255',
            'event_type' => 'string|max:50',
            'status' => 'in:0,1',
            'max_retry' => 'integer|min:0|max:10',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        if (!$this->validCallbackUrl($request->input('callback_url'))) {
            return $this->fail('callback_url 必须为 http/https 且不超过 500 字符', 422);
        }

        $carrierId = $this->decodeId($request->input('carrier_id'));
        if (!Carrier::where('id', $carrierId)->exists()) {
            return $this->fail('承运商不存在', 422);
        }

        $subscription = new CallbackSubscription();
        $subscription->id = $this->generateId();
        $subscription->carrier_id = $carrierId;
        $subscription->callback_url = $request->input('callback_url');
        $subscription->secret = EncryptionService::decryptTransmission($request->input('secret', ''));
        $subscription->event_type = $request->input('event_type', 'tracking.update');
        $subscription->status = (int) $request->input('status', 1);
        $subscription->max_retry = (int) $request->input('max_retry', 3);
        $subscription->save();

        return $this->success($this->encodeIds($subscription->toArray(), ['id', 'carrier_id']), '创建成功');
    }

    /**
     * @Apidoc\Title("订阅详情")
     * @Apidoc\Group("回调订阅管理")
     * @Apidoc\Url("/admin/callback/subscription/{id}")
     * @Apidoc\Desc("获取指定订阅的详细信息")
     * @Apidoc\Param("id", type="string", require=true, desc="订阅hashid")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $subscription = CallbackSubscription::find($id);
        if (!$subscription) {
            return $this->fail('订阅不存在', 404);
        }

        return $this->success($this->encodeIds($subscription->toArray(), ['id', 'carrier_id']));
    }

    /**
     * @Apidoc\Title("更新订阅")
     * @Apidoc\Group("回调订阅管理")
     * @Apidoc\Method("PUT")
     * @Apidoc\Url("/admin/callback/subscription/{id}")
     * @Apidoc\Desc("更新指定订阅的信息")
     * @Apidoc\Param("id", type="string", require=true, desc="订阅hashid")
     * @Apidoc\Param("callback_url", type="string", require=false, desc="回调URL")
     * @Apidoc\Param("secret", type="string", require=false, desc="回调签名密钥")
     * @Apidoc\Param("event_type", type="string", require=false, desc="事件类型")
     * @Apidoc\Param("status", type="int", require=false, desc="状态")
     * @Apidoc\Param("max_retry", type="int", require=false, desc="最大重试次数")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $subscription = CallbackSubscription::find($id);
        if (!$subscription) {
            return $this->fail('订阅不存在', 404);
        }

        $validator = Validator::make($request->all(), [
            'callback_url' => 'string|max:500',
            'secret' => 'string|max:255',
            'event_type' => 'string|max:50',
            'status' => 'in:0,1',
            'max_retry' => 'integer|min:0|max:10',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        if ($request->input('callback_url') !== null && !$this->validCallbackUrl($request->input('callback_url'))) {
            return $this->fail('callback_url 必须为 http/https 且不超过 500 字符', 422);
        }

        foreach (['callback_url', 'event_type'] as $field) {
            if ($request->input($field) !== null) {
                $subscription->$field = $request->input($field);
            }
        }
        if ($request->input('secret') !== null && $request->input('secret') !== '') {
            $subscription->secret = EncryptionService::decryptTransmission($request->input('secret'));
        }
        if ($request->input('status') !== null) {
            $subscription->status = (int) $request->input('status');
        }
        if ($request->input('max_retry') !== null) {
            $subscription->max_retry = (int) $request->input('max_retry');
        }
        $subscription->save();

        return $this->success($this->encodeIds($subscription->toArray(), ['id', 'carrier_id']), '更新成功');
    }

    /**
     * @Apidoc\Title("删除订阅")
     * @Apidoc\Group("回调订阅管理")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Url("/admin/callback/subscription/{id}")
     * @Apidoc\Desc("删除指定订阅")
     * @Apidoc\Param("id", type="string", require=true, desc="订阅hashid")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $subscription = CallbackSubscription::find($id);
        if (!$subscription) {
            return $this->fail('订阅不存在', 404);
        }

        $subscription->delete();

        return $this->success([], '删除成功');
    }

    /**
     * @Apidoc\Title("重推事件")
     * @Apidoc\Group("回调订阅管理")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/admin/callback/subscription/retry/{event_id}")
     * @Apidoc\Desc("将指定轨迹事件重新入队推送；已推送成功的订阅由消费者幂等跳过")
     * @Apidoc\Param("event_id", type="string", require=true, desc="事件ID")
     */
    public function retry(Request $request, string $event_id): Response
    {
        $event = TrackingEvent::find($event_id);
        if (!$event) {
            return $this->fail('事件不存在', 404);
        }

        $carrierId = Carrier::where('code', $event->carrier_code)->value('id');
        $subscriptions = CallbackSubscription::where('carrier_id', $carrierId)->where('status', 1)->get();
        $count = 0;
        foreach ($subscriptions as $sub) {
            // 清除幂等键，避免手动重推被消费者跳过
            Redis::del("logistics:push:{$event->id}:{$sub->id}");
            QueueClient::send('tracking_event_push', [
                'event_id'       => (string) $event->id,
                'subscription_id'=> $sub->id,
                'carrier_code'   => $event->carrier_code,
                'tracking_no'    => $event->tracking_no,
                'event_code'     => $event->event_code,
                'event_desc'     => $event->event_desc,
                'location'       => $event->location,
                'event_time'     => (string) $event->event_time,
            ]);
            $count++;
        }

        return $this->success(['queued' => $count], '已重新入队');
    }

    /** callback_url 统一校验：http/https 协议白名单 + 长度 ≤500（与 gRPC Subscribe 及 Rust 侧一致） */
    private function validCallbackUrl(mixed $url): bool
    {
        return is_string($url) && $url !== '' && strlen($url) <= 500
            && filter_var($url, FILTER_VALIDATE_URL)
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }
}
