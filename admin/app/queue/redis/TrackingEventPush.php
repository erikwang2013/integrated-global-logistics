<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\queue\redis;

use app\model\CallbackSubscription;
use Throwable;
use Webman\RedisQueue\Client as QueueClient;
use Webman\RedisQueue\Consumer;
use Webman\RedisQueue\Redis;

/**
 * 消费 tracking_event_push：按订阅推送 webhook（HMAC-SHA256 签名，失败按 1/5/30s 退避重试）
 * 幂等：推送成功才写 Redis key logistics:push:{event_id}:{subscription_id}（7d 过期），重复消费直接跳过
 */
class TrackingEventPush implements Consumer
{
    public $queue = 'tracking_event_push';
    public $connection = 'default';

    private const RETRY_DELAYS = [1, 5, 30];
    private const FAIL_LOG = '/runtime/logs/tracking-push-fail.log';

    /** 内网/回环地址段（ip2long 起止），推送前拦截防 SSRF，与 Rust 侧规则一致 */
    private const BLOCKED_RANGES = [
        [0, 16777215],            // 0.0.0.0/8
        [167772160, 184549375],   // 10.0.0.0/8
        [2886729728, 2887778303], // 172.16.0.0/12
        [3232235520, 3232301055], // 192.168.0.0/16
        [2851995648, 2852061183], // 169.254.0.0/16
        [2130706432, 2147483647], // 127.0.0.0/8
    ];

    public function consume($data): void
    {
        $eventId = (string) ($data['event_id'] ?? '');
        $subId = (int) ($data['subscription_id'] ?? 0);
        $idemKey = "logistics:push:{$eventId}:{$subId}";

        try {
            // 已推送成功过（或手动重推时已被其他消费者处理）→ 跳过
            if (Redis::exists($idemKey)) {
                return;
            }

            $sub = CallbackSubscription::find($subId);
            if (!$sub || (int) $sub->status !== 1 || $sub->callback_url === '') {
                return; // 订阅不存在/已停用，放弃
            }

            $payload = [
                'event_id'    => $eventId,
                'carrier_code'=> $data['carrier_code'] ?? '',
                'tracking_no' => $data['tracking_no'] ?? '',
                'event_code'  => $data['event_code'] ?? '',
                'event_desc'  => $data['event_desc'] ?? '',
                'location'    => $data['location'] ?? '',
                'event_time'  => $data['event_time'] ?? '',
                'push_at'     => date('Y-m-d H:i:s'),
            ];
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $sign = hash_hmac('sha256', $body, (string) $sub->secret);

            [$ok, $error] = $this->post($sub->callback_url, $body, $sign);
            if (!$ok) {
                $this->logFail($eventId, $subId, $error);
            } else {
                Redis::setnx($idemKey, 1);
                Redis::expire($idemKey, 7 * 86400);
                $now = date('Y-m-d H:i:s');
                $sub->last_push_at = $now;
                $sub->last_success_at = $now;
                $sub->save();
                return;
            }
        } catch (Throwable $e) {
            $this->logFail($eventId, $subId, 'exception: ' . $e->getMessage());
        }

        // 失败重试：1/5/30s 退避，超过 max_retry 记失败日志（供手动重推）
        $retry = (int) ($data['retry'] ?? 0) + 1;
        $maxRetry = isset($sub) && $sub ? (int) $sub->max_retry : 3;
        if ($retry <= $maxRetry) {
            $delay = self::RETRY_DELAYS[min($retry - 1, count(self::RETRY_DELAYS) - 1)];
            $data['retry'] = $retry;
            QueueClient::send($this->queue, $data, $delay);
        } else {
            $this->logFail($eventId, $subId, 'give up after ' . $maxRetry . ' retries');
        }
    }

    /** @return array{0: bool, 1: string} [是否成功, 错误信息] */
    private function post(string $url, string $body, string $sign): array
    {
        if ($this->isBlockedUrl($url)) {
            return [false, 'blocked callback_url (internal/loopback address)'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Signature: ' . $sign,
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $resp = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err !== '') {
            return [false, 'curl: ' . $err];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return [false, "http {$httpCode}: " . mb_substr((string) $resp, 0, 200)];
        }
        return [true, ''];
    }

    /** host 解析后任一地址落入内网/回环段即拦截；解析失败一律拦截 */
    private function isBlockedUrl(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ips = $host !== '' ? gethostbynamel($host) : false;
        if ($ips === false) {
            return true;
        }
        foreach ($ips as $ip) {
            $long = ip2long($ip);
            if ($long !== false) {
                foreach (self::BLOCKED_RANGES as [$from, $to]) {
                    if ($long >= $from && $long <= $to) {
                        return true;
                    }
                }
            } elseif (strtolower($ip) === '::1') {
                return true;
            }
        }
        return false;
    }

    private function logFail(string $eventId, int $subId, string $reason): void
    {
        $line = sprintf("[%s] event_id=%s subscription_id=%d %s\n", date('Y-m-d H:i:s'), $eventId, $subId, $reason);
        @file_put_contents(base_path() . self::FAIL_LOG, $line, FILE_APPEND | LOCK_EX);
    }
}
