<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controllers\internal;

use app\common\SnowflakeService;
use app\model\Carrier;
use app\model\CarrierCredential;
use app\model\TrackingQuery;
use GlobalLogistics\Exceptions\AuthException;
use GlobalLogistics\Exceptions\CarrierNotFoundException;
use GlobalLogistics\Exceptions\LogisticsException;
use GlobalLogistics\Exceptions\NetworkException;
use GlobalLogistics\Exceptions\TrackingNotFoundException;
use GlobalLogistics\Logistics;
use GlobalLogistics\Models\Tracking;
use support\Request;
use support\Response;
use Throwable;

/**
 * 内部轨迹查询/识别（e-cat → PHP worker）
 *
 * POST /internal/tracking/query  {carrier_code, tracking_no, credential_id?}
 * POST /internal/tracking/detect {tracking_no}
 */
class InternalQueryController
{
    private const REGISTRY_FILE = 'vendor/erikwang2013/global-logistics/src/Resources/carrier-registry.php';

    /** 上游异常 → [error_code, http status]；顺序即优先级（子类先匹配） */
    private const ERROR_MAP = [
        TrackingNotFoundException::class => ['TRACKING_NOT_FOUND', 404],
        CarrierNotFoundException::class  => ['CARRIER_NOT_FOUND', 404],
        AuthException::class             => ['CARRIER_AUTH_ERROR', 502],
        NetworkException::class          => ['CARRIER_NETWORK_ERROR', 502],
        LogisticsException::class        => ['CARRIER_ERROR', 502],
    ];

    public function query(Request $request): Response
    {
        $payload = json_decode($request->rawBody(), true) ?? [];
        $code = trim((string) ($payload['carrier_code'] ?? ''));
        $no = trim((string) ($payload['tracking_no'] ?? ''));
        $credentialId = isset($payload['credential_id']) && $payload['credential_id'] !== '' && $payload['credential_id'] !== null
            ? (int) $payload['credential_id'] : null;

        if ($code === '') {
            return $this->fail(400, 'carrier_code is required', 'INVALID_PARAMS', $code, $no, $credentialId, 0);
        }
        if ($no === '') {
            return $this->fail(400, 'tracking_no is required', 'INVALID_PARAMS', $code, $no, $credentialId, 0);
        }

        $channel = $this->resolveChannel($code);
        if ($channel === null) {
            return $this->fail(404, "carrier \"{$code}\" not registered", 'CARRIER_NOT_FOUND', $code, $no, $credentialId, 0);
        }

        $logisticsConfig = config('logistics');
        if ($credentialId !== null) {
            $credential = CarrierCredential::find($credentialId);
            if (!$credential || !$credential->carrier || $credential->carrier->code !== $code) {
                return $this->fail(400, 'credential_id does not match carrier_code', 'INVALID_PARAMS', $code, $no, $credentialId, 0);
            }
            $override = is_array($credential->extra) ? $credential->extra : [];
            if ($credential->app_key !== '') {
                $override['app_key'] = $credential->app_key;
            }
            if ($credential->app_secret !== '') {
                $override['app_secret'] = $credential->app_secret;
            }
            $logisticsConfig[$code] = array_merge($logisticsConfig[$code] ?? [], $override);
        }

        $start = microtime(true);
        try {
            Logistics::configure($logisticsConfig);
            $tracking = $channel === 'domestic'
                ? Logistics::domestic($code)->queryTrack($no)
                : Logistics::international($code)->queryTrack($no);
        } catch (Throwable $e) {
            $costMs = (int) round((microtime(true) - $start) * 1000);
            $errorCode = $this->mapError($e);
            return $this->fail(
                $this->mapStatus($errorCode), 'carrier_error', $errorCode,
                $code, $no, $credentialId, $costMs, $e->getMessage()
            );
        }
        $costMs = (int) round((microtime(true) - $start) * 1000);

        $queryNo = (string) SnowflakeService::generate();
        $data = $this->standardize($tracking, $queryNo);
        $this->persist($queryNo, $code, $no, $credentialId, 'success', $data, $tracking, $costMs, null, null);

        return json(['code' => 0, 'message' => 'ok', 'data' => $data], JSON_UNESCAPED_UNICODE);
    }

    public function detect(Request $request): Response
    {
        $payload = json_decode($request->rawBody(), true) ?? [];
        $no = trim((string) ($payload['tracking_no'] ?? ''));
        if ($no === '') {
            return json([
                'code' => 400, 'message' => 'tracking_no is required',
                'error_code' => 'INVALID_PARAMS', 'error_message' => 'tracking_no is required',
            ], JSON_UNESCAPED_UNICODE)->withStatus(400);
        }

        try {
            Logistics::configure(config('logistics'));
            $detection = Logistics::detect($no);
        } catch (CarrierNotFoundException $e) {
            return json([
                'code' => 404, 'message' => 'carrier not detected',
                'error_code' => 'CARRIER_NOT_DETECTED', 'error_message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE)->withStatus(404);
        } catch (Throwable $e) {
            return json([
                'code' => 500, 'message' => 'internal error',
                'error_code' => 'INTERNAL_ERROR', 'error_message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE)->withStatus(500);
        }

        return json([
            'code' => 0, 'message' => 'ok',
            'data' => [
                'carrier_code' => $detection->carrierCode,
                'channel'      => $detection->channel->value,
                'confidence'   => 1.0,
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /** @return 'domestic'|'international'|null */
    private function resolveChannel(string $code): ?string
    {
        $registry = require base_path() . '/' . self::REGISTRY_FILE;
        foreach (['domestic', 'international'] as $channel) {
            if (isset($registry[$channel][$code])) {
                return $channel;
            }
        }
        return null;
    }

    private function mapError(Throwable $e): string
    {
        foreach (self::ERROR_MAP as $class => [$errorCode]) {
            if ($e instanceof $class) {
                return $errorCode;
            }
        }
        return 'INTERNAL_ERROR';
    }

    private function mapStatus(string $errorCode): int
    {
        foreach (self::ERROR_MAP as [$code, $status]) {
            if ($code === $errorCode) {
                return $status;
            }
        }
        return 500;
    }

    /** 标准化 Tracking JSON（含 events），与对外 /v1 契约对齐 */
    private function standardize(Tracking $tracking, string $queryNo): array
    {
        return [
            'query_no'               => $queryNo,
            'carrier_code'           => $tracking->carrierCode,
            'tracking_no'            => $tracking->trackingNo,
            'status'                 => $tracking->status->value,
            'delivered_at'           => $tracking->deliveredAt?->format('c'),
            'estimated_delivery_at'  => $tracking->estimatedDeliveryAt?->format('c'),
            'latest_description'     => $tracking->latestDescription,
            'raw_status'             => $tracking->rawStatus,
            'events'                 => array_map(static fn ($event) => [
                'occurred_at' => $event->occurredAt?->format('c'),
                'location'    => $event->location,
                'description' => $event->description,
                'status'      => $event->status->value,
            ], $tracking->events),
        ];
    }

    /** 查询落库 logistics_tracking_query；落库失败不影响响应 */
    private function persist(
        string $queryNo, string $code, string $no, ?int $credentialId, string $status,
        ?array $result, ?Tracking $tracking, int $costMs, ?string $errorCode, ?string $errorMessage
    ): void {
        try {
            TrackingQuery::create([
                'id'             => SnowflakeService::generate(),
                'query_no'       => $queryNo,
                'carrier_id'     => Carrier::where('code', $code)->value('id') ?? 0,
                'carrier_code'   => $code,
                'tracking_no'    => $no,
                'credential_id'  => $credentialId ?? 0,
                'status'         => $status,
                'result'         => $result,
                'raw_response'   => $tracking !== null ? json_encode($tracking->raw ?: [], JSON_UNESCAPED_UNICODE) : null,
                'query_source'   => 'api',
                'cost_ms'        => $costMs,
                'error_code'     => $errorCode,
                'error_message'  => $errorMessage !== null ? mb_substr($errorMessage, 0, 2000) : null,
            ]);
        } catch (Throwable $e) {
            error_log('[internal/tracking/query] persist failed: ' . $e->getMessage());
        }
    }

    private function fail(
        int $status, string $message, string $errorCode,
        string $code, string $no, ?int $credentialId, int $costMs, ?string $errorMessage = null
    ): Response {
        if (mb_strlen($errorMessage ?? '') > 2000) {
            $errorMessage = mb_substr($errorMessage, 0, 2000);
        }
        // 参数类错误（400）不入库（无实际查询发生）；其余失败照常落库
        if ($status >= 400 && $errorCode !== 'INVALID_PARAMS') {
            $this->persist(
                (string) SnowflakeService::generate(), $code, $no, $credentialId,
                'fail', null, null, $costMs, $errorCode, $errorMessage
            );
        }
        // 上游/承运商类错误统一 code=5001（carrier_error，§5.1 契约数值化）；参数/内部错误保留数值 code
        $isCarrierError = !in_array($errorCode, ['INVALID_PARAMS', 'INTERNAL_ERROR'], true);
        return json([
            'code'          => $isCarrierError ? 5001 : $status,
            'message'       => $message,
            'error_code'    => $errorCode,
            'error_message' => $errorMessage ?? $message,
        ], JSON_UNESCAPED_UNICODE)->withStatus($status);
    }
}
