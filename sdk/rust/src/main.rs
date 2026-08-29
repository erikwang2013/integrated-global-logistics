// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 运行: TRACKING_API_KEY / TRACKING_BASE_URL 可覆盖默认值；cargo run
use trackingclient::tracking_client::{TrackingApiError, TrackingClient};

fn main() {
    let api_key = std::env::var("TRACKING_API_KEY").unwrap_or_else(|_| "demo-api-key".into());
    let base_url = std::env::var("TRACKING_BASE_URL").unwrap_or_else(|_| "http://localhost:8080".into());
    let client = match TrackingClient::new(&api_key, &base_url) {
        Ok(c) => c,
        Err(e) => {
            eprintln!("init failed: {e}");
            std::process::exit(1);
        }
    };

    run("carriers", || client.list_carriers());
    run("query_tracking", || client.query_tracking("LX123456789CN", None));
    run("get_tracking", || client.get_tracking("demo-query"));
    run("subscribe", || client.subscribe("china-post", "https://example.com/cb", ""));
}

fn run(name: &str, f: impl Fn() -> Result<trackingclient::json::Value, TrackingApiError>) {
    match f() {
        Ok(v) => println!("{name}: {v:?}"),
        Err(e) => println!("{name} failed: code={} message={} error_code={:?}", e.code, e.message, e.error_code),
    }
}
