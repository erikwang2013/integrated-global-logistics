#!/usr/bin/env php
<?php
// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 运行: php example.php （可用 TRACKING_API_KEY / TRACKING_BASE_URL 环境变量覆盖默认值）
require __DIR__ . "/TrackingClient.php";

$client = new TrackingClient(
    getenv("TRACKING_API_KEY") ?: "demo-api-key",
    getenv("TRACKING_BASE_URL") ?: "http://localhost:8080"
);

function run($name, $fn) {
    try {
        $result = $fn();
        echo "{$name}: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    } catch (TrackingApiError $e) {
        echo "{$name} failed: code={$e->code} message={$e->getMessage()} error_code={$e->error_code}\n";
    }
}

run("carriers", function () use ($client) { return $client->list_carriers(); });
run("query_tracking", function () use ($client) { return $client->query_tracking("LX123456789CN"); });
run("get_tracking", function () use ($client) { return $client->get_tracking("demo-query"); });
run("subscribe", function () use ($client) { return $client->subscribe("china-post", "https://example.com/cb"); });
