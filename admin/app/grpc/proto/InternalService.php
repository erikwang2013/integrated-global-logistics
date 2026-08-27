<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace proto;

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
use Google\Protobuf\Internal\Message;
use Internal\Grpc\CarrierEntry;
use Internal\Grpc\CarriersRequest;
use Internal\Grpc\CarriersResponse;
use Internal\Grpc\DetectRequest;
use Internal\Grpc\DetectResponse;
use Internal\Grpc\QueryRequest;
use Internal\Grpc\QueryResponse;
use Internal\Grpc\TrackingEvent;
use Throwable;

/**
 * e-cat 查询网关 → PHP worker 池的内部 gRPC 服务（契约见 infrastructure/tracking-gateway/proto/internal.proto）。
 * 由 parse\Grpc::loadHook() 扫描静态属性注册路由；业务错误全部走响应 code 字段（grpc-status 恒 0），
 * 语义与旧 HTTP/JSON 内部契约一致（0 成功 / 400 / 404 / 401 / 5001 carrier_error / 500）。
 */
class InternalService
{
    private const REGISTRY_FILE = 'vendor/erikwang2013/global-logistics/src/Resources/carrier-registry.php';

    /** 上游异常 → [error_code, 数值 code]；顺序即优先级（子类先匹配） */
    private const ERROR_MAP = [
        TrackingNotFoundException::class => ['TRACKING_NOT_FOUND', 404],
        CarrierNotFoundException::class  => ['CARRIER_NOT_FOUND', 404],
        AuthException::class             => ['CARRIER_AUTH_ERROR', 502],
        NetworkException::class          => ['CARRIER_NETWORK_ERROR', 502],
        LogisticsException::class        => ['CARRIER_ERROR', 502],
    ];

    public static $Streaming = [
        'simple' => [
            '/internal.v1.InternalService/Query',
            '/internal.v1.InternalService/Detect',
            '/internal.v1.InternalService/Carriers',
        ],
    ];

    public static $Route = [
        '/internal.v1.InternalService/Query'    => [self::class, 'query'],
        '/internal.v1.InternalService/Detect'   => [self::class, 'detect'],
        '/internal.v1.InternalService/Carriers' => [self::class, 'carriers'],
    ];

    public static $Parameter = [
        '/internal.v1.InternalService/Query'    => QueryRequest::class,
        '/internal.v1.InternalService/Detect'   => DetectRequest::class,
        '/internal.v1.InternalService/Carriers' => CarriersRequest::class,
    ];

    public static function query(QueryRequest $request, array $headers): QueryResponse
    {
        $response = new QueryResponse();
        try {
            if (!self::authorized($headers)) {
                return self::error($response, 401, 'unauthorized', 'UNAUTHORIZED', 'invalid x-internal-token');
            }
            $code = trim((string) $request->getCarrierCode());
            $no = trim((string) $request->getTrackingNo());
            $credentialId = $request->getCredentialId() !== '' ? (int) $request->getCredentialId() : null;

            if ($code === '') {
                return self::error($response, 400, 'carrier_code is required', 'INVALID_PARAMS', 'carrier_code is required');
            }
            if ($no === '') {
                return self::error($response, 400, 'tracking_no is required', 'INVALID_PARAMS', 'tracking_no is required');
            }

            $channel = self::resolveChannel($code);
            if ($channel === null) {
                return self::error($response, 404, "carrier \"{$code}\" not registered", 'CARRIER_NOT_FOUND', "carrier \"{$code}\" not registered");
            }

            $logisticsConfig = config('logistics');
            if ($credentialId !== null) {
                $credential = CarrierCredential::find($credentialId);
                if (!$credential || !$credential->carrier || $credential->carrier->code !== $code) {
                    return self::error($response, 400, 'credential_id does not match carrier_code', 'INVALID_PARAMS', 'credential_id does not match carrier_code');
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
                $errorCode = self::mapError($e);
                self::persist((string) SnowflakeService::generate(), $code, $no, $credentialId, 'fail', null, null, $costMs, $errorCode, $e->getMessage());
                $codeOut = in_array($errorCode, ['INVALID_PARAMS', 'INTERNAL_ERROR'], true) ? self::mapStatus($errorCode) : 5001;
                return self::error($response, $codeOut, 'carrier_error', $errorCode, $e->getMessage());
            }
            $costMs = (int) round((microtime(true) - $start) * 1000);

            $queryNo = (string) SnowflakeService::generate();
            $data = self::standardize($tracking, $queryNo);
            self::persist($queryNo, $code, $no, $credentialId, 'success', $data, $tracking, $costMs, null, null);

            $events = [];
            foreach ($data['events'] as $event) {
                $events[] = (new TrackingEvent())
                    ->setOccurredAt($event['occurred_at'])
                    ->setLocation($event['location'])
                    ->setDescription($event['description'])
                    ->setStatus($event['status']);
            }

            return $response
                ->setCode(0)
                ->setMessage('ok')
                ->setQueryNo($data['query_no'])
                ->setCarrierCode($data['carrier_code'])
                ->setTrackingNo($data['tracking_no'])
                ->setStatus($data['status'])
                ->setDeliveredAt($data['delivered_at'])
                ->setEstimatedDeliveryAt($data['estimated_delivery_at'])
                ->setLatestDescription($data['latest_description'])
                ->setRawStatus($data['raw_status'])
                ->setEvents($events);
        } catch (Throwable $e) {
            return self::error($response, 500, 'internal error', 'INTERNAL_ERROR', $e->getMessage());
        }
    }

    public static function detect(DetectRequest $request, array $headers): DetectResponse
    {
        $response = new DetectResponse();
        try {
            if (!self::authorized($headers)) {
                return self::error($response, 401, 'unauthorized', 'UNAUTHORIZED', 'invalid x-internal-token');
            }
            $no = trim((string) $request->getTrackingNo());
            if ($no === '') {
                return self::error($response, 400, 'tracking_no is required', 'INVALID_PARAMS', 'tracking_no is required');
            }

            try {
                Logistics::configure(config('logistics'));
                $detection = Logistics::detect($no);
            } catch (CarrierNotFoundException $e) {
                return self::error($response, 404, 'carrier not detected', 'CARRIER_NOT_DETECTED', $e->getMessage());
            } catch (Throwable $e) {
                return self::error($response, 500, 'internal error', 'INTERNAL_ERROR', $e->getMessage());
            }

            return $response
                ->setCode(0)
                ->setMessage('ok')
                ->setCarrierCode($detection->carrierCode)
                ->setChannel($detection->channel->value)
                ->setConfidence(1.0);
        } catch (Throwable $e) {
            return self::error($response, 500, 'internal error', 'INTERNAL_ERROR', $e->getMessage());
        }
    }

    public static function carriers(CarriersRequest $request, array $headers): CarriersResponse
    {
        $response = new CarriersResponse();
        try {
            if (!self::authorized($headers)) {
                return self::error($response, 401, 'unauthorized', 'UNAUTHORIZED', 'invalid x-internal-token');
            }
            $registry = require base_path() . '/' . self::REGISTRY_FILE;
            $list = [];
            foreach ($registry as $channel => $carriers) {
                foreach (array_keys($carriers) as $code) {
                    $list[] = (new CarrierEntry())->setCarrierCode($code)->setChannel($channel);
                }
            }
            return $response->setCode(0)->setMessage('ok')->setCarriers($list);
        } catch (Throwable $e) {
            return self::error($response, 500, 'internal error', 'INTERNAL_ERROR', $e->getMessage());
        }
    }

    /** x-internal-token 共享密钥校验（头信息由 parse\Grpc 作为第二参数传入） */
    private static function authorized(array $headers): bool
    {
        $token = $headers['x-internal-token'] ?? $headers['X-Internal-Token'] ?? '';
        $expected = config('logistics')['internal_token'] ?? '';
        return hash_equals((string) $expected, (string) $token);
    }

    private static function error(Message $response, int $code, string $message, string $errorCode, ?string $errorMessage = null): Message
    {
        $response->setCode($code);
        $response->setMessage($message);
        // CarriersResponse 等无 error_code/error_message 字段的响应不设置，避免抛异常导致连接崩溃
        if (method_exists($response, 'setErrorCode')) {
            $response->setErrorCode($errorCode);
        }
        if (method_exists($response, 'setErrorMessage')) {
            $response->setErrorMessage($errorMessage !== null ? mb_substr($errorMessage, 0, 2000) : $message);
        }
        return $response;
    }

    /** @return 'domestic'|'international'|null */
    private static function resolveChannel(string $code): ?string
    {
        $registry = require base_path() . '/' . self::REGISTRY_FILE;
        foreach (['domestic', 'international'] as $channel) {
            if (isset($registry[$channel][$code])) {
                return $channel;
            }
        }
        return null;
    }

    private static function mapError(Throwable $e): string
    {
        foreach (self::ERROR_MAP as $class => [$errorCode]) {
            if ($e instanceof $class) {
                return $errorCode;
            }
        }
        return 'INTERNAL_ERROR';
    }

    private static function mapStatus(string $errorCode): int
    {
        foreach (self::ERROR_MAP as [$code, $status]) {
            if ($code === $errorCode) {
                return $status;
            }
        }
        return 500;
    }

    /** 标准化 Tracking（含 events），与对外 /v1 契约对齐 */
    private static function standardize(Tracking $tracking, string $queryNo): array
    {
        return [
            'query_no'               => $queryNo,
            'carrier_code'           => $tracking->carrierCode,
            'tracking_no'            => $tracking->trackingNo,
            'status'                 => $tracking->status->value,
            'delivered_at'           => $tracking->deliveredAt?->format('c') ?? '',
            'estimated_delivery_at'  => $tracking->estimatedDeliveryAt?->format('c') ?? '',
            'latest_description'     => $tracking->latestDescription,
            'raw_status'             => $tracking->rawStatus,
            'events'                 => array_map(static fn ($event) => [
                'occurred_at' => $event->occurredAt?->format('c') ?? '',
                'location'    => $event->location,
                'description' => $event->description,
                'status'      => $event->status->value,
            ], $tracking->events),
        ];
    }

    /** 查询落库 logistics_tracking_query；落库失败不影响响应 */
    private static function persist(
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
            error_log('[internal/grpc] persist failed: ' . $e->getMessage());
        }
    }
}
