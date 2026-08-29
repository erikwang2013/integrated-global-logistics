<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use app\model\Client;
use support\Request;
use Webman\Http\Response;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTFactory;

/**
 * 客户端门户认证 — 校验 JWT 且 payload.token_type 必须为 client
 * （与 admin JWT 同 secret，靠 token_type 区分，避免互相冒用）
 */
class ClientAuth
{
    private static ?JWT $jwt = null;

    private static function getJWT(): JWT
    {
        if (self::$jwt === null) {
            $config = config('plugin.erikwang2013.jwt.jwt', []);
            self::$jwt = JWTFactory::createFromConfig($config);
        }
        return self::$jwt;
    }

    public function process(Request $request, callable $next): Response
    {
        $token = $request->header('Authorization', '');
        $token = str_replace('Bearer ', '', $token);

        if (empty($token)) {
            return json(['code' => 401, 'message' => '未登录', 'data' => []]);
        }

        try {
            $payload = self::getJWT()->decode($token);
        } catch (\Throwable $e) {
            return json(['code' => 401, 'message' => 'Token已过期或无效', 'data' => []]);
        }

        if (($payload['token_type'] ?? '') !== 'client') {
            return json(['code' => 401, 'message' => '无效的客户端令牌', 'data' => []]);
        }

        $client = Client::find($payload['sub'] ?? 0);
        if (!$client || (int) $client->status !== 1) {
            return json(['code' => 403, 'message' => '账号不存在或已禁用', 'data' => []]);
        }

        $request->clientId = (int) $client->id;
        $request->client = $client;
        return $next($request);
    }
}
