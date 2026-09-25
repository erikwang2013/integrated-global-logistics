<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\exception;

use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 全局异常处理。
 *
 * 在框架原行为之上，为**浏览器页面**渲染带项目宠物 E-Cat 的错误页；
 * API 请求（Accept: application/json / XHR）与调试模式一律委托父类，
 * 保持 JSON 错误体与堆栈信息原样输出，不改变对外错误契约。
 */
class Handler extends \support\exception\Handler
{
    public function render(Request $request, Throwable $exception): Response
    {
        // API 请求 / 调试模式：保持框架原行为（JSON 错误体、堆栈信息）
        if ($this->debug || $request->expectsJson()) {
            return parent::render($request, $exception);
        }

        $code = (int)$exception->getCode();
        $status = $code >= 400 && $code <= 599 ? $code : 500;

        return self::page($status, '服务开小差了', '请求处理失败，请稍后重试。');
    }

    /**
     * 宠物错误页（404 兜底与 5xx 共用）。
     *
     * @param int    $status   HTTP 状态码
     * @param string $heading  标题
     * @param string $message  说明文案
     */
    public static function page(int $status, string $heading, string $message): Response
    {
        $heading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
            <title>{$status} — 开放管理后台</title>
            <link rel="icon" type="image/svg+xml" href="/favicon.svg">
            <style>
                *{margin:0;padding:0;box-sizing:border-box}
                body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f0f2f5;color:#333;min-height:100vh;display:flex;align-items:center;justify-content:center}
                .box{background:#fff;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.08);width:460px;max-width:95vw;padding:36px 40px;text-align:center}
                .pet{display:block;width:128px;height:128px;margin:0 auto 4px}
                .code{font-size:34px;font-weight:700;color:#2B5CD9;letter-spacing:1px}
                h1{font-size:19px;margin:6px 0 8px;color:#1a1a2e}
                p{font-size:13px;color:#888;line-height:1.7}
                a{display:inline-block;margin-top:22px;padding:10px 30px;background:#1890ff;color:#fff;border-radius:6px;font-size:14px;text-decoration:none}
                a:hover{background:#40a9ff}
            </style>
        </head>
        <body>
            <div class="box">
                <img class="pet" src="/mascot.svg" alt="E-Cat 项目宠物" width="128" height="128">
                <div class="code">{$status}</div>
                <h1>{$heading}</h1>
                <p>{$message}</p>
                <a href="/">返回首页</a>
            </div>
        </body>
        </html>
        HTML;

        return new Response($status, ['Content-Type' => 'text/html;charset=utf-8'], $html);
    }
}
