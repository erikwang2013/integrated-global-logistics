// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// M2 查询网关：对外 /v1（X-API-Key）→ 限流 → Redis 缓存 → 按 carrier 熔断
// → RoundRobin 转发 PHP worker（/internal/*，X-Internal-Token）→ 写缓存返回。
use axum::{
    Json, Router,
    extract::{Path, State},
    http::{HeaderMap, HeaderName, HeaderValue, Request, StatusCode},
    middleware::{self, Next},
    response::{IntoResponse, Response},
    routing::{get, post},
};
use ecat::App;
use ecat_circuit_breaker::{CircuitBreakerLayer, CircuitBreakerService};
use ecat_client::{LoadBalancer, RoundRobin, ServiceResolver, StaticResolver};
use ecat_config::{ConfigSource, FileSource};
use ecat_data::Cache;
use ecat_data_redis::RedisCache;
use ecat_health::HealthRegistry;
use ecat_metrics::{MetricsLayer, metrics_router};
use ecat_middleware::{
    LoggingLayer, RateLimitStore, RedisRateLimitStore, TracingLayer,
};
use ecat_transport_http::HttpServer;
use prometheus::{IntCounterVec, Opts};
use serde::{Deserialize, Serialize};
use serde_json::{Value, json};
use std::{
    collections::HashMap,
    error::Error,
    future::Future,
    pin::Pin,
    sync::{Arc, Mutex, OnceLock},
    time::Duration,
};
use tower::{Layer, Service, ServiceBuilder};

// ── 配置 ──

#[derive(Debug, Clone, Deserialize)]
struct GatewayConfig {
    port: String,
    redis_url: String,
    internal_token: String,
    #[serde(default = "default_key_prefix")]
    key_prefix: String,
    api_keys: Vec<String>,
    workers: Vec<String>,
    #[serde(default = "default_timeout_ms")]
    timeout_ms: u64,
    #[serde(default)]
    rate_limit: RateLimitCfg,
    #[serde(default)]
    cache: CacheCfg,
    #[serde(default)]
    carrier_cache_ttl: HashMap<String, u64>,
    #[serde(default = "default_detect_ttl")]
    detect_ttl_secs: u64,
    #[serde(default = "default_carriers_ttl")]
    carriers_cache_ttl_secs: u64,
    #[serde(default)]
    breaker: BreakerCfg,
}

fn default_timeout_ms() -> u64 { 15_000 }
fn default_key_prefix() -> String { "logistics:".to_string() }
fn default_detect_ttl() -> u64 { 300 }
fn default_carriers_ttl() -> u64 { 600 }

#[derive(Debug, Clone, Deserialize)]
struct RateLimitCfg {
    #[serde(default = "default_rl_max")]
    max_requests: u32,
    #[serde(default = "default_rl_window")]
    window_secs: u64,
}
fn default_rl_max() -> u32 { 100 }
fn default_rl_window() -> u64 { 60 }
impl Default for RateLimitCfg {
    fn default() -> Self { Self { max_requests: default_rl_max(), window_secs: default_rl_window() } }
}

#[derive(Debug, Clone, Deserialize)]
struct CacheCfg {
    #[serde(default = "default_cache_ttl")]
    default_ttl_secs: u64,
}
fn default_cache_ttl() -> u64 { 300 }
impl Default for CacheCfg {
    fn default() -> Self { Self { default_ttl_secs: default_cache_ttl() } }
}

#[derive(Debug, Clone, Deserialize)]
struct BreakerCfg {
    #[serde(default = "default_ratio")]
    failure_ratio: f64,
    #[serde(default = "default_window")]
    window_secs: u64,
    #[serde(default = "default_probes")]
    half_open_probes: u32,
    #[serde(default = "default_open")]
    open_secs: u64,
}
fn default_ratio() -> f64 { 0.5 }
fn default_window() -> u64 { 30 }
fn default_probes() -> u32 { 3 }
fn default_open() -> u64 { 60 }
impl Default for BreakerCfg {
    fn default() -> Self {
        Self {
            failure_ratio: default_ratio(),
            window_secs: default_window(),
            half_open_probes: default_probes(),
            open_secs: default_open(),
        }
    }
}

// ── 状态 ──

struct ForwardReq {
    state: Arc<AppState>,
    carrier: String,
    tracking_no: String,
}

/// 上游传输失败（超时/连接拒绝/worker 不可达）。与熔断器自身的
/// io::Error（OPEN 快速失败）区分，便于调用侧分别返回 502/503。
#[derive(Debug)]
struct UpstreamError(String);

impl std::fmt::Display for UpstreamError {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        write!(f, "{}", self.0)
    }
}

impl std::error::Error for UpstreamError {}

impl From<reqwest::Error> for UpstreamError {
    fn from(e: reqwest::Error) -> Self { Self(e.to_string()) }
}

impl From<std::io::Error> for UpstreamError {
    fn from(e: std::io::Error) -> Self { Self(e.to_string()) }
}

type BreakerInner = tower::util::ServiceFn<
    fn(ForwardReq) -> Pin<Box<dyn Future<Output = Result<reqwest::Response, UpstreamError>> + Send>>,
>;

type BreakerService = CircuitBreakerService<BreakerInner>;

#[derive(Clone)]
struct AppState {
    cfg: Arc<GatewayConfig>,
    cache: Arc<RedisCache>,
    rate: Arc<dyn RateLimitStore>,
    resolver: Arc<dyn ServiceResolver>,
    balancer: Arc<dyn LoadBalancer>,
    client: reqwest::Client,
    breakers: Arc<Breakers>,
}

/// 按 carrier 维度各持一个 CircuitBreakerService（ecat-circuit-breaker 的
/// 熔断状态在 Layer::layer 生成的 service 内），首次使用该 carrier 时创建。
struct Breakers {
    layer: CircuitBreakerLayer,
    map: Mutex<HashMap<String, BreakerService>>,
}

impl Breakers {
    fn new(cfg: &GatewayConfig) -> Self {
        let layer = CircuitBreakerLayer::new()
            .failure_ratio(cfg.breaker.failure_ratio)
            .window(Duration::from_secs(cfg.breaker.window_secs))
            .half_open_probes(cfg.breaker.half_open_probes)
            .open_duration(Duration::from_secs(cfg.breaker.open_secs))
            // HTTP 非 2xx 计入失败窗口；业务错误（200 + code!=0）不算上游故障
            .classify(|r: &reqwest::Response| r.status().is_server_error());
        Self { layer, map: Mutex::new(HashMap::new()) }
    }

    fn for_carrier(&self, carrier: &str) -> BreakerService {
        let mut map = self.map.lock().unwrap_or_else(|e| e.into_inner());
        map.entry(carrier.to_string())
            .or_insert_with(|| self.layer.layer(tower::service_fn(forward_worker)))
            .clone()
    }
}

/// 熔断内层服务：RoundRobin 选 worker → POST /internal/tracking/query。
/// 传输失败（超时/连接拒绝）返回 Err，由熔断器计入失败窗口。
fn forward_worker(
    req: ForwardReq,
) -> Pin<Box<dyn Future<Output = Result<reqwest::Response, UpstreamError>> + Send>> {
    Box::pin(async move {
        let endpoint = pick_worker(&req.state).await?;
        let body = json!({ "carrier_code": req.carrier, "tracking_no": req.tracking_no });
        let resp = req
            .state
            .client
            .post(format!("{endpoint}/internal/tracking/query"))
            .header("X-Internal-Token", &req.state.cfg.internal_token)
            .json(&body)
            .timeout(req.state.cfg.timeout())
            .send()
            .await?;
        Ok(resp)
    })
}

async fn pick_worker(state: &AppState) -> Result<String, UpstreamError> {
    let endpoints = state
        .resolver
        .resolve("workers")
        .await
        .map_err(UpstreamError)?;
    state
        .balancer
        .pick(&endpoints)
        .ok_or_else(|| UpstreamError("no worker available".into()))
}

// ── 统一响应 ──

#[derive(Serialize)]
struct ApiOk {
    code: i64,
    message: &'static str,
    data: Value,
}

#[derive(Serialize)]
struct ApiError {
    code: i64,
    message: String,
}

fn ok_json(data: Value) -> Response {
    Json(ApiOk { code: 0, message: "ok", data }).into_response()
}

fn err_json(status: StatusCode, code: i64, message: impl Into<String>) -> Response {
    (status, Json(ApiError { code, message: message.into() })).into_response()
}

fn with_cache_header(resp: Response, headers: &[(&str, &str)]) -> Response {
    let mut map = HeaderMap::new();
    for (k, v) in headers {
        map.insert(
            k.parse::<HeaderName>().unwrap(),
            v.parse::<HeaderValue>().unwrap(),
        );
    }
    (map, resp).into_response()
}

// ── 缓存助手（Redis 故障 fail-open：只告警不挡请求）──

async fn cache_get(state: &AppState, key: &str) -> Option<Value> {
    match state.cache.get(key).await {
        Ok(Some(bytes)) => serde_json::from_slice(&bytes).ok(),
        Ok(None) => None,
        Err(e) => {
            tracing::warn!(error = %e, key, "cache get failed; proceeding without cache");
            None
        }
    }
}

async fn cache_set(state: &AppState, key: &str, value: Value, ttl: Duration) {
    let bytes = match serde_json::to_vec(&value) {
        Ok(b) => b,
        Err(e) => {
            tracing::warn!(error = %e, "cache value not serializable");
            return;
        }
    };
    if let Err(e) = state.cache.set(key, &bytes, ttl).await {
        tracing::warn!(error = %e, key, "cache set failed");
    }
}

fn ttl_for(state: &AppState, carrier: &str) -> Duration {
    state
        .cfg
        .carrier_cache_ttl
        .get(carrier)
        .map(|s| Duration::from_secs(*s))
        .unwrap_or(Duration::from_secs(state.cfg.cache.default_ttl_secs))
}

// ── 指标 ──

fn metric(result: &str) {
    static QUERIES: OnceLock<IntCounterVec> = OnceLock::new();
    let cv = QUERIES.get_or_init(|| {
        let cv = IntCounterVec::new(
            Opts::new("tracking_queries_total", "tracking queries by result"),
            &["result"],
        )
        .expect("counter opts are valid");
        if let Err(e) = ecat_metrics::registry().register(Box::new(cv.clone())) {
            tracing::warn!(error = %e, "metric registration failed");
        }
        cv
    });
    cv.with_label_values(&[result]).inc();
}

// ── 鉴权 ──

async fn require_api_key(
    State(state): State<AppState>,
    headers: HeaderMap,
    req: Request<axum::body::Body>,
    next: Next,
) -> Response {
    let key = headers
        .get("x-api-key")
        .and_then(|v| v.to_str().ok())
        .unwrap_or("");
    if state.cfg.api_keys.iter().any(|k| k == key) {
        next.run(req).await
    } else {
        err_json(StatusCode::UNAUTHORIZED, 401, "invalid or missing api key")
    }
}

// ── 对外路由 ──

#[derive(Deserialize)]
struct QueryReq {
    #[serde(default)]
    carrier_code: Option<String>,
    tracking_no: String,
}

async fn tracking_query(
    State(state): State<AppState>,
    headers: HeaderMap,
    Json(req): Json<QueryReq>,
) -> Response {
    let api_key = headers
        .get("x-api-key")
        .and_then(|v| v.to_str().ok())
        .unwrap_or("unknown");
    let rate_key = format!("{}api:{api_key}", state.cfg.key_prefix);
    if state
        .rate
        .check(
            &rate_key,
            state.cfg.rate_limit.max_requests,
            state.cfg.rate_limit.window_secs,
        )
        .await
        .is_err()
    {
        metric("limited");
        return err_json(
            StatusCode::TOO_MANY_REQUESTS,
            429,
            "rate limit exceeded, retry later",
        );
    }

    let tracking_no = req.tracking_no.trim();
    if tracking_no.is_empty() {
        return err_json(StatusCode::BAD_REQUEST, 400, "tracking_no is required");
    }

    let carrier = match req.carrier_code {
        Some(c) if !c.trim().is_empty() => c.trim().to_string(),
        _ => match detect(state.clone(), tracking_no.to_string()).await {
            Ok(c) => c,
            Err(resp) => return resp,
        },
    };

    let cache_key = format!("{}cache:track:{carrier}:{tracking_no}", state.cfg.key_prefix);
    if let Some(data) = cache_get(&state, &cache_key).await {
        metric("hit");
        return with_cache_header(ok_json(data), &[("x-cache", "HIT")]);
    }

    let state2 = Arc::new(state.clone());
    let carrier2 = carrier.clone();
    let no2 = tracking_no.to_string();
    let resp = match tokio::spawn(async move {
        let mut svc = state2.breakers.for_carrier(&carrier2);
        svc.call(ForwardReq {
            state: state2,
            carrier: carrier2,
            tracking_no: no2,
        })
        .await
    })
    .await
    {
        Ok(Ok(resp)) => resp,
        Ok(Err(e)) => {
            if e.downcast_ref::<std::io::Error>().is_some() {
                metric("breaker");
                return err_json(
                    StatusCode::SERVICE_UNAVAILABLE,
                    503,
                    format!("carrier {carrier} is temporarily unavailable"),
                );
            }
            metric("upstream");
            return err_json(
                StatusCode::BAD_GATEWAY,
                502,
                "upstream worker failure",
            );
        }
        Err(_) => {
            return err_json(
                StatusCode::BAD_GATEWAY,
                502,
                "upstream worker failure",
            );
        }
    };

    match parse_worker(resp).await {
        Ok(data) => {
            let ttl = ttl_for(&state, &carrier);
            let query_no = data.get("query_no").and_then(Value::as_str).map(str::to_string);
            if let Some(qno) = query_no {
                let qkey = format!("{}cache:query:{qno}", state.cfg.key_prefix);
                cache_set(&state, &qkey, data.clone(), ttl).await;
            }
            cache_set(&state, &cache_key, data.clone(), ttl).await;
            metric("ok");
            with_cache_header(ok_json(data), &[("x-cache", "MISS")])
        }
        Err(resp) => resp,
    }
}

/// carrier_code 缺省时：查 cache:detect:{no}（短 TTL），未命中转发
/// POST /internal/tracking/detect（不走熔断——无 carrier 维度）。
async fn detect(state: AppState, tracking_no: String) -> Result<String, Response> {
    let cache_key = format!("{}cache:detect:{tracking_no}", state.cfg.key_prefix);
    if let Some(data) = cache_get(&state, &cache_key).await {
        if let Some(c) = data.get("carrier_code").and_then(Value::as_str) {
            return Ok(c.to_string());
        }
    }
    let endpoint = pick_worker(&state).await.map_err(|_| {
        err_json(StatusCode::BAD_GATEWAY, 502, "upstream worker failure")
    })?;
    let body = json!({ "tracking_no": tracking_no });
    let resp = state
        .client
        .post(format!("{endpoint}/internal/tracking/detect"))
        .header("X-Internal-Token", &state.cfg.internal_token)
        .json(&body)
        .timeout(state.cfg.timeout())
        .send()
        .await
        .map_err(|_| err_json(StatusCode::BAD_GATEWAY, 502, "upstream worker failure"))?;
    let data = parse_worker(resp).await?;
    let carrier = data
        .get("carrier_code")
        .and_then(Value::as_str)
        .ok_or_else(|| err_json(StatusCode::BAD_GATEWAY, 502, "carrier detection failed"))?
        .to_string();
    cache_set(&state, &cache_key, data, Duration::from_secs(state.cfg.detect_ttl_secs)).await;
    Ok(carrier)
}

/// 解析 worker 响应：2xx + code==0 → data；否则透传 PHP 结构化错误码
/// （code 为数值，message 不泄露内部细节）；无结构化信息才落 502。
async fn parse_worker(resp: reqwest::Response) -> Result<Value, Response> {
    let status = resp.status();
    let body: Value = resp
        .json()
        .await
        .map_err(|_| err_json(StatusCode::BAD_GATEWAY, 502, "invalid upstream response"))?;
    let code = body
        .get("code")
        .and_then(|c| c.as_i64().or_else(|| c.as_str().and_then(|s| s.parse().ok())))
        .unwrap_or(if status.is_success() { -1 } else { 502 });
    if status.is_success() && code == 0 {
        return Ok(body.get("data").cloned().unwrap_or(Value::Null));
    }
    let message = body
        .get("message")
        .and_then(Value::as_str)
        .unwrap_or("upstream worker failure")
        .to_string();
    let http = match StatusCode::from_u16(code as u16) {
        Ok(s) if s.is_client_error() || s.is_server_error() => s,
        _ => StatusCode::BAD_GATEWAY,
    };
    Err(err_json(http, code, message))
}

async fn tracking_detail(State(state): State<AppState>, Path(query_no): Path<String>) -> Response {
    match cache_get(&state, &format!("{}cache:query:{query_no}", state.cfg.key_prefix)).await {
        Some(data) => ok_json(data),
        None => err_json(StatusCode::NOT_FOUND, 404, "query not found"),
    }
}

async fn carriers_list(State(state): State<AppState>) -> Response {
    let key = format!("{}cache:carriers", state.cfg.key_prefix);
    if let Some(data) = cache_get(&state, &key).await {
        return ok_json(data);
    }
    let endpoint = match pick_worker(&state).await {
        Ok(e) => e,
        Err(_) => return err_json(StatusCode::BAD_GATEWAY, 502, "upstream worker failure"),
    };
    let resp = match state
        .client
        .get(format!("{endpoint}/internal/carriers"))
        .header("X-Internal-Token", &state.cfg.internal_token)
        .timeout(state.cfg.timeout())
        .send()
        .await
    {
        Ok(r) => r,
        Err(_) => return err_json(StatusCode::BAD_GATEWAY, 502, "upstream worker failure"),
    };
    match parse_worker(resp).await {
        Ok(data) => {
            cache_set(
                &state,
                &key,
                data.clone(),
                Duration::from_secs(state.cfg.carriers_cache_ttl_secs),
            )
            .await;
            ok_json(data)
        }
        Err(resp) => resp,
    }
}

// ── 入口 ──

#[tokio::main]
async fn main() -> Result<(), Box<dyn Error + Send + Sync>> {
    let config_path = std::env::var("TRACKING_GATEWAY_CONFIG")
        .unwrap_or_else(|_| "config/config.json".into());
    let source = FileSource::new(config_path.clone());
    let map = source.load().await?;
    let cfg = Arc::new(serde_json::from_value::<GatewayConfig>(Value::Object(
        map.into_iter().collect(),
    ))?);

    let cache = Arc::new(RedisCache::connect(&cfg.redis_url).await?);
    let rate: Arc<dyn RateLimitStore> = Arc::new(RedisRateLimitStore::connect(&cfg.redis_url).await?);
    // ecat-client StaticResolver::add_service 内部用 tokio RwLock::blocking_write，
    // 在 runtime worker 线程直接调用会 panic；block_in_place 移出线程执行
    let resolver: Arc<dyn ServiceResolver> = Arc::new(tokio::task::block_in_place(|| {
        StaticResolver::new().add_service("workers", cfg.workers.clone())
    }));
    let balancer: Arc<dyn LoadBalancer> = Arc::new(RoundRobin::new());
    let client = reqwest::Client::builder()
        // 不跟随重定向：防止上游把内网地址暴露给客户端（SSRF 风格）
        .redirect(reqwest::redirect::Policy::none())
        .build()?;

    let state = AppState {
        cfg: cfg.clone(),
        cache,
        rate,
        resolver,
        balancer,
        client,
        breakers: Arc::new(Breakers::new(&cfg)),
    };

    let middleware = ServiceBuilder::new()
        .layer(MetricsLayer::new())
        .layer(TracingLayer::new("tracking-gateway"))
        .layer(LoggingLayer);

    let api = Router::new()
        .route("/v1/tracking/query", post(tracking_query))
        .route("/v1/tracking/{query_no}", get(tracking_detail))
        .route("/v1/carriers", get(carriers_list))
        .layer(middleware::from_fn_with_state(state.clone(), require_api_key));

    // /v1/health、/metrics 为运维端点，不要求 API-Key
    let health: Router<AppState> = HealthRegistry::new().into_router().with_state(());
    let metrics: Router<AppState> = metrics_router().with_state(());

    let router: Router<()> = api
        .nest("/v1", health)
        .merge(metrics)
        .layer(middleware)
        .with_state(state.clone());

    let http_srv = HttpServer::new(cfg.port.clone()).router(router);

    let path_log = config_path.clone();
    let mut app = App::builder()
        .name("tracking-gateway")
        .version("v0.1.0")
        .server(http_srv)
        .on_start(move || {
            let path_log = path_log.clone();
            async move {
                tracing::info!("tracking-gateway started; config: {}", path_log);
                Ok(())
            }
        })
        .on_stop(|| async {
            tracing::info!("tracking-gateway stopped");
            Ok(())
        })
        .build()?;

    app.run().await?;
    Ok(())
}

impl GatewayConfig {
    fn timeout(&self) -> Duration {
        Duration::from_millis(self.timeout_ms)
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn config_parses_with_defaults() {
        let map: HashMap<String, Value> = serde_json::from_str(
            r#"{
                "port": "0.0.0.0:8080",
                "redis_url": "redis://127.0.0.1:6379",
                "internal_token": "t",
                "api_keys": ["k1"],
                "workers": ["http://127.0.0.1:8787"]
            }"#,
        )
        .unwrap();
        let cfg: GatewayConfig =
            serde_json::from_value(Value::Object(map.into_iter().collect())).unwrap();
        assert_eq!(cfg.timeout_ms, 15_000);
        assert_eq!(cfg.rate_limit.max_requests, 100);
        assert_eq!(cfg.cache.default_ttl_secs, 300);
        assert_eq!(cfg.detect_ttl_secs, 300);
        assert_eq!(cfg.breaker.half_open_probes, 3);
        assert!(cfg.carrier_cache_ttl.is_empty());
    }

    #[test]
    fn config_parses_carrier_ttl_override() {
        let map: HashMap<String, Value> = serde_json::from_str(
            r#"{
                "port": "0.0.0.0:8080",
                "redis_url": "redis://127.0.0.1:6379",
                "internal_token": "t",
                "api_keys": ["k1"],
                "workers": ["http://127.0.0.1:8787"],
                "carrier_cache_ttl": {"sf": 3600}
            }"#,
        )
        .unwrap();
        let cfg: GatewayConfig =
            serde_json::from_value(Value::Object(map.into_iter().collect())).unwrap();
        assert_eq!(cfg.carrier_cache_ttl.get("sf"), Some(&3600));
    }
}
