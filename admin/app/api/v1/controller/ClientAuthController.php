<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\common\SnowflakeService;
use app\common\HashidsService;
use app\common\EncryptionService;
use app\model\Client;
use support\Redis;
use support\Request;
use support\Response;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTFactory;
use Throwable;

/**
 * @Apidoc\Title("客户端认证")
 */
class ClientAuthController
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

    /**
     * @Apidoc\Title("客户端注册")
     * @Apidoc\Group("客户端认证")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/api/v1/auth/register")
     * @Apidoc\Desc("客户端门户注册，需先通过验证码校验")
     * @Apidoc\Param("username", type="string", require=true, desc="用户名(3-50位)")
     * @Apidoc\Param("password", type="string", require=true, desc="密码(6-32位，RSA加密后Base64或明文)")
     * @Apidoc\Param("captcha_key", type="string", require=true, desc="验证码key（需先通过 /api/captcha/verify）")
     * @Apidoc\Param("contact_name", type="string", require=false, desc="联系人姓名")
     * @Apidoc\Param("contact_phone", type="string", require=false, desc="联系电话")
     * @Apidoc\Param("contact_email", type="string", require=false, desc="联系邮箱")
     */
    public function register(Request $request): Response
    {
        $username = trim((string) $request->input('username', ''));
        $captchaKey = (string) $request->input('captcha_key', '');
        $contactName = trim((string) $request->input('contact_name', ''));
        $contactPhone = trim((string) $request->input('contact_phone', ''));
        $contactEmail = trim((string) $request->input('contact_email', ''));

        if ($username === '' || strlen($username) < 3 || strlen($username) > 50) {
            return json(['code' => 422, 'message' => '用户名格式不正确', 'data' => []]);
        }
        if ($captchaKey === '') {
            return json(['code' => 422, 'message' => '验证码参数缺失', 'data' => []]);
        }
        try {
            if (!Redis::get("captcha_verified:{$captchaKey}")) {
                return json(['code' => 422, 'message' => '请先完成验证码校验', 'data' => []]);
            }
            Redis::del("captcha_verified:{$captchaKey}");
        } catch (\Throwable) {}

        $password = EncryptionService::decryptTransmission((string) $request->input('password', ''));
        if (strlen($password) < 6 || strlen($password) > 32) {
            return json(['code' => 422, 'message' => '密码长度需为6-32位', 'data' => []]);
        }
        if (mb_strlen($contactName) > 50) {
            return json(['code' => 422, 'message' => '联系人姓名过长', 'data' => []]);
        }
        if (strlen($contactPhone) > 30) {
            return json(['code' => 422, 'message' => '联系电话过长', 'data' => []]);
        }
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            return json(['code' => 422, 'message' => '邮箱格式不正确', 'data' => []]);
        }
        if (Client::where('username', $username)->exists()) {
            return json(['code' => 422, 'message' => '用户名已存在', 'data' => []]);
        }

        $client = new Client();
        $client->id = SnowflakeService::generate();
        $client->username = $username;
        $client->password = password_hash($password, PASSWORD_BCRYPT);
        $client->contact_name = $contactName;
        $client->contact_phone = $contactPhone;
        $client->contact_email = $contactEmail;
        $client->status = 1;
        $client->save();

        return json(['code' => 0, 'message' => '注册成功', 'data' => ['id' => HashidsService::encode($client->id)]]);
    }

    /**
     * @Apidoc\Title("客户端登录")
     * @Apidoc\Group("客户端认证")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/api/v1/auth/login")
     * @Apidoc\Desc("客户端门户登录（请求体带 client=1 区分管理端登录）")
     * @Apidoc\Param("username", type="string", require=true, desc="用户名")
     * @Apidoc\Param("password", type="string", require=true, desc="密码(RSA加密后Base64或明文)")
     * @Apidoc\Returned("access_token", type="string", desc="访问令牌")
     * @Apidoc\Returned("expires_in", type="int", desc="过期时间(秒)")
     */
    public function login(Request $request): Response
    {
        $username = trim((string) $request->input('username', ''));
        if ($username === '' || strlen($username) > 50) {
            return json(['code' => 422, 'message' => '用户名格式不正确', 'data' => []]);
        }

        // 账号锁定检查（5次失败/15分钟）
        $lockKey = "client_lock:{$username}";
        try {
            if (Redis::get($lockKey)) {
                return json(['code' => 429, 'message' => '账号已锁定，请15分钟后再试', 'data' => []]);
            }
        } catch (\Throwable) {}

        $password = EncryptionService::decryptTransmission((string) $request->input('password', ''));
        $user = Client::where('username', $username)->first();

        if ($password === '' || strlen($password) < 6 || strlen($password) > 32 || !$user || !password_verify($password, $user->password)) {
            try {
                $failKey = "client_login_fail:{$username}";
                $fails = Redis::incr($failKey);
                if ($fails === 1) Redis::expire($failKey, 900);
                if ($fails >= 5) {
                    Redis::setex($lockKey, 900, '1');
                    Redis::del($failKey);
                    return json(['code' => 429, 'message' => '账号已锁定，请15分钟后再试', 'data' => []]);
                }
            } catch (\Throwable) {}
            return json(['code' => 401, 'message' => '用户名或密码错误', 'data' => []]);
        }

        try { Redis::del("client_login_fail:{$username}"); Redis::del($lockKey); } catch (\Throwable) {}

        if ((int) $user->status === 0) {
            return json(['code' => 403, 'message' => '账号已禁用', 'data' => []]);
        }

        $tokenExpire = (int) (config('plugin.erikwang2013.jwt.jwt.default_expire') ?: 7200);
        $token = self::getJWT()->encode([
            'sub' => $user->id,
            'username' => $user->username,
            'token_type' => 'client',
        ]);

        return json([
            'code' => 0,
            'message' => '登录成功',
            'data' => [
                'access_token' => $token,
                'expires_in' => $tokenExpire,
                'user' => ['id' => HashidsService::encode($user->id), 'username' => $user->username],
            ],
        ]);
    }
}
