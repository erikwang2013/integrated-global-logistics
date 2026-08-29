#!/usr/bin/env python3
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
# 运行: python3 example.py （可用 TRACKING_API_KEY / TRACKING_BASE_URL 覆盖默认值）
import os

from tracking_client import TrackingClient, TrackingApiError

client = TrackingClient(
    os.getenv("TRACKING_API_KEY", "demo-api-key"),
    os.getenv("TRACKING_BASE_URL", "http://localhost:8080"),
)


def run(name, fn):
    try:
        print("%s:" % name, fn())
    except TrackingApiError as e:
        print("%s failed: code=%s message=%s (error_code=%s)" % (name, e.code, e.message, e.error_code))


run("carriers", client.list_carriers)
run("query_tracking", lambda: client.query_tracking("LX123456789CN"))
run("get_tracking", lambda: client.get_tracking("demo-query"))
run("subscribe", lambda: client.subscribe("china-post", "https://example.com/cb"))
