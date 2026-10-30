<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\TrackingEvent;
use app\model\TrackingQuery;
use support\Redis;
use support\Request;
use support\Response;
use Throwable;

/**
 * 承运商 webhook 回调接收（POST /api/callback/{carrier}）
 * 承运商私有格式差异较大，首版按通用形状 {tracking_no, events:[{event_code,event_desc,location,event_time}]} 解析
 */
class CallbackController extends BaseController
{
    public function receive(Request $request, string $carrier): Response
    {
        // 承运商白名单：以包配置中声明的承运商代码为准
        $logisticsConfig = config('logistics');
        $carrierConfig = is_array($logisticsConfig) ? ($logisticsConfig[$carrier] ?? null) : null;
        if (!is_array($carrierConfig)) {
            return json(['code' => 404, 'message' => 'carrier not found'])->withStatus(404);
        }

        $raw = (string) $request->rawBody();
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return json(['code' => 400, 'message' => 'invalid json body'])->withStatus(400);
        }

        // 签名校验：未配置 webhook_secret 默认拒绝，只有显式 CALLBACK_ALLOW_UNSIGNED=1 才放行
        // sign = sha256(secret + payload 去除 sign 字段后的紧凑 JSON)；sign 随 payload 发送，故只能对去 sign 后的内容签名
        $secret = (string) ($carrierConfig['webhook_secret'] ?? '');
        if ($secret === '') {
            if (getenv('CALLBACK_ALLOW_UNSIGNED') !== '1') {
                return json(['code' => 401, 'message' => 'callback secret not configured'])->withStatus(401);
            }
        } elseif (!self::verifySignature($payload, $secret)) {
            return json(['code' => 401, 'message' => 'invalid signature'])->withStatus(401);
        }

        $trackingNo = trim((string) ($payload['tracking_no'] ?? ''));
        $events = $payload['events'] ?? [];
        if ($trackingNo === '' || !is_array($events) || empty($events)) {
            return json(['code' => 400, 'message' => 'tracking_no and non-empty events required'])->withStatus(400);
        }
        // 单次回调事件数量上限，避免超大请求拖垮落库
        $events = array_slice($events, 0, 100);

        $normalized = [];
        $deduped = 0;
        foreach ($events as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $event = [
                'event_code' => mb_substr((string) ($ev['event_code'] ?? ''), 0, 64),
                'event_desc' => mb_substr((string) ($ev['event_desc'] ?? ''), 0, 500),
                'location'   => mb_substr((string) ($ev['location'] ?? ''), 0, 200),
                'event_time' => mb_substr(((string) ($ev['event_time'] ?? '')) ?: date('Y-m-d H:i:s'), 0, 32),
            ];
            $eventId = $this->generateId();
            // 唯一键(tracking_no,event_code,event_time)去重：承运商重发同事件时跳过落库
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

            $normalized[] = $event;
        }

        if (empty($normalized)) {
            // 全部事件均为重复（已处理过）→ 视为成功
            if ($deduped > 0) {
                return json(['code' => 0, 'message' => 'ok', 'deduped' => $deduped]);
            }
            return json(['code' => 400, 'message' => 'no valid event'])->withStatus(400);
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

        // 事件已落库 → 失效网关缓存，强制下次上游重新查询（best-effort，失败不影响回调响应）
        try {
            Redis::del("logistics:cache:track:{$carrier}:{$trackingNo}", "logistics:cache:detect:{$trackingNo}");
        } catch (Throwable $e) {
            error_log('[callback] cache invalidation failed: ' . $e->getMessage());
        }

        return json(['code' => 0, 'message' => 'ok']);
    }

    /**
     * 校验回调签名：sha256(secret + 去 sign 后的紧凑 JSON)，常量时间比较
     */
    public static function verifySignature(array $payload, string $secret): bool
    {
        if ($secret === '' || !isset($payload['sign'])) {
            return false;
        }
        $signPayload = $payload;
        unset($signPayload['sign']);
        $canonical = json_encode($signPayload, JSON_UNESCAPED_UNICODE);
        return hash_equals(hash('sha256', $secret . $canonical), (string) $payload['sign']);
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
