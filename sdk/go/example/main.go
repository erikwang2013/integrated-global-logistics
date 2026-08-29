// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 运行: TRACKING_API_KEY / TRACKING_BASE_URL 可覆盖默认值；go run .
package main

import (
	"encoding/json"
	"fmt"
	"os"

	"trackingclient"
)

func main() {
	apiKey := getenv("TRACKING_API_KEY", "demo-api-key")
	baseURL := getenv("TRACKING_BASE_URL", "http://localhost:8080")
	client := trackingclient.NewClient(apiKey, baseURL)

	run("carriers", func() (json.RawMessage, error) { return client.ListCarriers() })
	run("query_tracking", func() (json.RawMessage, error) { return client.QueryTracking("LX123456789CN", "") })
	run("get_tracking", func() (json.RawMessage, error) { return client.GetTracking("demo-query") })
	run("subscribe", func() (json.RawMessage, error) { return client.Subscribe("china-post", "https://example.com/cb", "") })
}

func run(name string, fn func() (json.RawMessage, error)) {
	data, err := fn()
	if err != nil {
		fmt.Printf("%s failed: %v\n", name, err)
		return
	}
	fmt.Printf("%s: %s\n", name, string(data))
}

func getenv(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}
