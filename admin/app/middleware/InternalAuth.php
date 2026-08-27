<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 内部接口认证（e-cat → PHP worker）
 *
 * - 校验请求头 X-Internal-Token 与 config/logistics.php 共享密钥（hash_equals 防时序攻击）
 * - 拒绝公网来源 IP（REMOTE_ADDR 非内网/回环 → 403）
 * - 不落 OperationLog、不走 RBAC
 */
class InternalAuth implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $token = $request->header('X-Internal-Token', '');
        $expected = (string) config('logistics.internal_token', '');

        if ($expected === '' || !hash_equals($expected, $token)) {
            return $this->deny(401, 'invalid internal token', 'INTERNAL_UNAUTHORIZED');
        }

        $ip = (string) $request->connection->getRemoteIp();
        if (!self::isInternalIp($ip)) {
            return $this->deny(403, 'internal api only accessible from private network', 'INTERNAL_FORBIDDEN');
        }

        return $handler($request);
    }

    private function deny(int $status, string $message, string $errorCode): Response
    {
        return json([
            'code'         => $status,
            'message'      => $message,
            'error_code'   => $errorCode,
            'error_message' => $message,
        ], JSON_UNESCAPED_UNICODE)->withStatus($status);
    }

    private static function isInternalIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) {
                return false;
            }
            return ($long & 0xFF000000) === 0x7F000000    // 127/8 回环
                || ($long & 0xFF000000) === 0x0A000000    // 10/8
                || ($long & 0xFFF00000) === 0xAC100000    // 172.16/12
                || ($long & 0xFFFF0000) === 0xC0A80000    // 192.168/16
                || ($long & 0xFFFF0000) === 0xA9FE0000;   // 169.254/16 链路本地
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $ip === '::1'
                || $ip === '::ffff:127.0.0.1'
                || str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd') // fc00::/7 ULA
                || str_starts_with($ip, 'fe80:');                            // 链路本地
        }
        return false;
    }
}
