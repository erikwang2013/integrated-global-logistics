<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\CallbackSubscription;
use app\model\Carrier;
use app\model\CarrierCredential;
use app\model\TrackingEvent;
use app\model\TrackingQuery;
use support\Request;
use support\Response;
use Webman\RedisQueue\Client as QueueClient;

/**
 * 承运商 webhook 回调接收（POST /api/callback/{carrier}）
 * 承运商私有格式差异较大，首版按通用形状 {tracking_no, events:[{event_code,event_desc,location,event_time}]} 解析
 */
class CallbackController extends BaseController
{
    public function receive(Request $request, string $carrier): Response
    {
        // 承运商白名单
        $carrierModel = Carrier::where('code', $carrier)->where('status', 1)->first();
        if (!$carrierModel) {
            return json(['code' => 404, 'message' => 'carrier not found'], 404);
        }

        $raw = (string) $request->rawBody();
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return json(['code' => 400, 'message' => 'invalid json body'], 400);
        }

        // 签名校验：配置了 webhook_secret 的承运商必须携带有效签名，否则 401；未配置则白名单放行
        // sign = sha256(secret + payload 去除 sign 字段后的紧凑 JSON)；sign 随 payload 发送，故只能对去 sign 后的内容签名
        $secret = CarrierCredential::where('carrier_id', $carrierModel->id)
            ->where('status', 1)->value('extra');
        $secret = is_string($secret) ? json_decode($secret, true) : (is_array($secret) ? $secret : []);
        $secret = (string) ($secret['webhook_secret'] ?? '');
        if ($secret !== '') {
            $signPayload = $payload;
            unset($signPayload['sign']);
            $canonical = json_encode($signPayload, JSON_UNESCAPED_UNICODE);
            if (!isset($payload['sign']) || !hash_equals(hash('sha256', $secret . $canonical), (string) $payload['sign'])) {
                return json(['code' => 401, 'message' => 'invalid signature'], 401);
            }
        }

        $trackingNo = trim((string) ($payload['tracking_no'] ?? ''));
        $events = $payload['events'] ?? [];
        if ($trackingNo === '' || !is_array($events) || empty($events)) {
            return json(['code' => 400, 'message' => 'tracking_no and non-empty events required'], 400);
        }

        $subscriptions = CallbackSubscription::where('carrier_id', $carrierModel->id)
            ->where('status', 1)->get();

        $normalized = [];
        $deduped = 0;
        foreach ($events as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $event = [
                'event_code' => (string) ($ev['event_code'] ?? ''),
                'event_desc' => (string) ($ev['event_desc'] ?? ''),
                'location'   => (string) ($ev['location'] ?? ''),
                'event_time' => (string) ($ev['event_time'] ?? '') ?: date('Y-m-d H:i:s'),
            ];
            $eventId = $this->generateId();
            // 唯一键(tracking_no,event_code,event_time)去重：承运商重发同事件时跳过落库与推送
            $inserted = TrackingEvent::query()->insertOrIgnore([[
                'id'           => $eventId,
                'tracking_no'  => $trackingNo,
                'carrier_code' => $carrier,
                'event_code'   => $event['event_code'],
                'event_desc'   => $event['event_desc'],
                'location'     => $event['location'],
                'event_time'   => $event['event_time'],
                'raw_payload'  => $raw,
            ]]);
            if ($inserted === 0) {
                $deduped++;
                continue;
            }

            // 推入队列，由消费者按订阅推送（含重试）
            foreach ($subscriptions as $sub) {
                QueueClient::send('tracking_event_push', [
                    'event_id'       => $eventId,
                    'subscription_id'=> $sub->id,
                    'carrier_code'   => $carrier,
                    'tracking_no'    => $trackingNo,
                    'event_code'     => $event['event_code'],
                    'event_desc'     => $event['event_desc'],
                    'location'       => $event['location'],
                    'event_time'     => $event['event_time'],
                ]);
            }
            $normalized[] = $event;
        }

        if (empty($normalized)) {
            // 全部事件均为重复（已处理过）→ 视为成功
            if ($deduped > 0) {
                return json(['code' => 0, 'message' => 'ok', 'deduped' => $deduped]);
            }
            return json(['code' => 400, 'message' => 'no valid event'], 400);
        }

        // 更新该运单最新一条查询记录为成功，result 呈现最新事件与标准化状态
        $latest = TrackingQuery::where('tracking_no', $trackingNo)->orderByDesc('id')->first();
        if ($latest) {
            $last = $normalized[count($normalized) - 1];
            $latest->status = 'success';
            $latest->query_source = 'webhook';
            $latest->result = [
                'status'             => self::mapStatus($last['event_code']),
                'latest_description' => $last['event_desc'],
                'raw_status'         => $last['event_code'],
                'events'             => array_map(static fn ($e) => [
                    'occurred_at' => $e['event_time'],
                    'location'    => $e['location'],
                    'description' => $e['event_desc'],
                    'status'      => $e['event_code'],
                ], $normalized),
            ];
            $latest->save();
        }

        return json(['code' => 0, 'message' => 'ok']);
    }

    /** event_code → 标准 tracking 状态（TrackStatus 枚举值），未识别默认 UNKNOWN */
    private static function mapStatus(string $code): string
    {
        return match (strtoupper($code)) {
            'SIGNED', 'DELIVERED'                      => 'DELIVERED',
            'PICKUP', 'OUT_FOR_DELIVERY', 'ARRIVED'    => 'OUT_FOR_DELIVERY',
            'IN_TRANSIT', 'SHIPPED', 'DEPARTED'        => 'IN_TRANSIT',
            'RETURN', 'RETURNED'                       => 'RETURNED',
            'EXCEPTION', 'FAILED', 'UNDELIVERED'       => 'EXCEPTION',
            default                                    => 'UNKNOWN',
        };
    }
}
