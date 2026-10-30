<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use app\middleware\RateLimit;

class RateLimitArgsTest extends TestCase
{
    #[Test]
    public function build_eval_args_matches_phpredis_signature(): void
    {
        $result = RateLimit::buildEvalArgs('rate_limit:1.2.3.4:_api_v1_auth_login', 1000, 10, 61000, 'member-abc', 70);

        $this->assertCount(2, $result);
        $this->assertIsArray($result[0]);
        // phpredis: eval(script, args, num_keys)，1 个 key
        $this->assertSame(1, $result[1]);

        // KEYS[1]=key；ARGV[1..5]=windowStart, limit, now, member, ttl
        $this->assertCount(6, $result[0]);
        $this->assertSame('rate_limit:1.2.3.4:_api_v1_auth_login', $result[0][0]);
        $this->assertSame(1000, $result[0][1]);
        $this->assertSame(10, $result[0][2]);
        $this->assertSame(61000, $result[0][3]);
        $this->assertSame('member-abc', $result[0][4]);
        $this->assertSame(70, $result[0][5]);
    }

    #[Test]
    public function build_eval_args_keeps_args_in_order(): void
    {
        $result = RateLimit::buildEvalArgs('k', 1, 2, 3, 'm', 4);

        $this->assertSame(['k', 1, 2, 3, 'm', 4], $result[0]);
    }
}
