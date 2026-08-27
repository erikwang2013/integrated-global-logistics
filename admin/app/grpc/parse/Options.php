<?php
declare(strict_types=1);

namespace parse;

final class Options
{
    public static function isInDebugMode(): bool
    {
        return true;
    }

    public static function getHttp2Timeout(): int
    {
        return 60;
    }

    public static function getBodySizeLimit(): int
    {
        return 13107200;
    }

    public static function getHeaderSizeLimit(): int
    {
        return 32768;
    }

    public static function getConcurrentStreamLimit(): int
    {
        return 256;
    }

    public static function getAllowedMethods(): array
    {
        return ["GET", "POST", "PUT", "PATCH", "HEAD", "OPTIONS", "DELETE"];
    }

    public static function isPushEnabled(): bool
    {
        return true;
    }

    public static function logFile(): string
    {
        // 相对路径在 daemon 模式 CWD 下不存在导致 file_put_contents 抛错，改用绝对路径
        return dirname(__DIR__, 3) . '/runtime/grpc/http2.log';
    }
}
