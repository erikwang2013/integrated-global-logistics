// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// M2 查询网关：对外 /v1（X-API-Key）→ 限流 → Redis 缓存 → 按 carrier 熔断
// → RoundRobin gRPC 转发 PHP worker（internal.v1.InternalService，x-internal-token）→ 写缓存返回。
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
use ecat_middleware::{LoggingLayer, RateLimitStore, RedisRateLimitStore, TracingLayer};
use ecat_transport_http::HttpServer;
use prometheus::{IntCounterVec, Opts};
use serde::{Deserialize, Serialize};
use serde_json::{Value, json};
use std::{
    collections::HashMap,
    error::Error,
    future::Future,
    pin::Pin,
    str::FromStr,
    sync::{Arc, Mutex, OnceLock},
    time::Duration,
};
use tonic::transport::Channel;
use tower::{Layer, Service, ServiceBuilder};

pub mod pb {
    tonic::include_proto!("internal.v1");
}

mod openapi;

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

fn default_timeout_ms() -> u64 {
    15_000
}
fn default_key_prefix() -> String {
    "logistics:".to_string()
}
fn default_detect_ttl() -> u64 {
    300
}
fn default_carriers_ttl() -> u64 {
    600
}

#[derive(Debug, Clone, Deserialize)]
struct RateLimitCfg {
    #[serde(default = "default_rl_max")]
    max_requests: u32,
    #[serde(default = "default_rl_window")]
    window_secs: u64,
}
fn default_rl_max() -> u32 {
    100
}
fn default_rl_window() -> u64 {
    60
}
impl Default for RateLimitCfg {
    fn default() -> Self {
        Self {
            max_requests: default_rl_max(),
            window_secs: default_rl_window(),
        }
    }
}

#[derive(Debug, Clone, Deserialize)]
struct CacheCfg {
    #[serde(default = "default_cache_ttl")]
    default_ttl_secs: u64,
}
fn default_cache_ttl() -> u64 {
    300
}
impl Default for CacheCfg {
    fn default() -> Self {
        Self {
            default_ttl_secs: default_cache_ttl(),
        }
    }
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
fn default_ratio() -> f64 {
    0.5
}
fn default_window() -> u64 {
    30
}
fn default_probes() -> u32 {
    3
}
fn default_open() -> u64 {
    60
}
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

struct SubscribeForwardReq {
    state: Arc<AppState>,
    carrier: String,
    callback_url: String,
    event_type: String,
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

impl From<tonic::Status> for UpstreamError {
    fn from(e: tonic::Status) -> Self {
        Self(e.to_string())
    }
}

impl From<tonic::transport::Error> for UpstreamError {
    fn from(e: tonic::transport::Error) -> Self {
        Self(e.to_string())
    }
}

impl From<std::io::Error> for UpstreamError {
    fn from(e: std::io::Error) -> Self {
        Self(e.to_string())
    }
}

type BreakerInner = tower::util::ServiceFn<
    fn(
        ForwardReq,
    ) -> Pin<Box<dyn Future<Output = Result<pb::QueryResponse, UpstreamError>> + Send>>,
>;

type BreakerService = CircuitBreakerService<BreakerInner>;

type SubscribeBreakerInner = tower::util::ServiceFn<
    fn(
        SubscribeForwardReq,
    ) -> Pin<Box<dyn Future<Output = Result<pb::SubscribeResponse, UpstreamError>> + Send>>,
>;

type SubscribeBreakerService = CircuitBreakerService<SubscribeBreakerInner>;

#[derive(Clone)]
struct AppState {
    cfg: Arc<GatewayConfig>,
    cache: Arc<RedisCache>,
    rate: Arc<dyn RateLimitStore>,
    resolver: Arc<dyn ServiceResolver>,
    balancer: Arc<dyn LoadBalancer>,
    /// worker endpoint → 复用连接的 tonic Channel（worker 列表稳定，惰性建立）
    channels: Arc<Mutex<HashMap<String, Channel>>>,
    breakers: Arc<Breakers>,
}

/// 按 carrier 维度各持一个 CircuitBreakerService（ecat-circuit-breaker 的
/// 熔断状态在 Layer::layer 生成的 service 内），首次使用该 carrier 时创建。
/// classify 按 Any downcast 匹配响应类型，Query/Subscribe 类型不同，
/// 各自持独立 Layer 与 map（同一 carrier 的熔断状态互不影响，保持简单）。
struct Breakers {
    layer: CircuitBreakerLayer,
    sub_layer: CircuitBreakerLayer,
    map: Mutex<HashMap<String, BreakerService>>,
    sub_map: Mutex<HashMap<String, SubscribeBreakerService>>,
}

impl Breakers {
    fn new(cfg: &GatewayConfig) -> Self {
        let layer = CircuitBreakerLayer::new()
            .failure_ratio(cfg.breaker.failure_ratio)
            .window(Duration::from_secs(cfg.breaker.window_secs))
            .half_open_probes(cfg.breaker.half_open_probes)
            .open_duration(Duration::from_secs(cfg.breaker.open_secs))
            // 传输失败（Err）计入失败窗口；业务错误（Ok + code!=0）不算上游故障
            .classify(|r: &Result<pb::QueryResponse, UpstreamError>| r.is_err());
        let sub_layer = CircuitBreakerLayer::new()
            .failure_ratio(cfg.breaker.failure_ratio)
            .window(Duration::from_secs(cfg.breaker.window_secs))
            .half_open_probes(cfg.breaker.half_open_probes)
            .open_duration(Duration::from_secs(cfg.breaker.open_secs))
            .classify(|r: &Result<pb::SubscribeResponse, UpstreamError>| r.is_err());
        Self {
            layer,
            sub_layer,
            map: Mutex::new(HashMap::new()),
            sub_map: Mutex::new(HashMap::new()),
        }
    }

    fn for_carrier(&self, carrier: &str) -> BreakerService {
        let mut map = self.map.lock().unwrap_or_else(|e| e.into_inner());
        map.entry(carrier.to_string())
            .or_insert_with(|| self.layer.layer(tower::service_fn(forward_worker)))
            .clone()
    }

    fn sub_for_carrier(&self, carrier: &str) -> SubscribeBreakerService {
        let mut map = self.sub_map.lock().unwrap_or_else(|e| e.into_inner());
        map.entry(carrier.to_string())
            .or_insert_with(|| {
                self.sub_layer
                    .layer(tower::service_fn(forward_subscribe_worker))
            })
            .clone()
    }
}

/// 熔断内层服务：RoundRobin 选 worker → gRPC Query。
/// 传输失败（超时/连接拒绝）返回 Err，由熔断器计入失败窗口。
fn forward_worker(
    req: ForwardReq,
) -> Pin<Box<dyn Future<Output = Result<pb::QueryResponse, UpstreamError>> + Send>> {
    Box::pin(async move {
        let endpoint = pick_worker(&req.state).await?;
        let mut client = pb::internal_service_client::InternalServiceClient::new(
            channel_for(&req.state, &endpoint).await?,
        );
        let resp = client
            .query(grpc_req(
                &req.state,
                pb::QueryRequest {
                    carrier_code: req.carrier,
                    tracking_no: req.tracking_no,
                    credential_id: String::new(),
                },
            ))
            .await
            .map_err(UpstreamError::from)?
            .into_inner();
        Ok(resp)
    })
}

/// 熔断内层服务（Subscribe）：RoundRobin 选 worker → gRPC Subscribe。
fn forward_subscribe_worker(
    req: SubscribeForwardReq,
) -> Pin<Box<dyn Future<Output = Result<pb::SubscribeResponse, UpstreamError>> + Send>> {
    Box::pin(async move {
        let endpoint = pick_worker(&req.state).await?;
        let mut client = pb::internal_service_client::InternalServiceClient::new(
            channel_for(&req.state, &endpoint).await?,
        );
        let resp = client
            .subscribe(grpc_req(
                &req.state,
                pb::SubscribeRequest {
                    carrier_code: req.carrier,
                    callback_url: req.callback_url,
                    event_type: req.event_type,
                },
            ))
            .await
            .map_err(UpstreamError::from)?
            .into_inner();
        Ok(resp)
    })
}

/// worker endpoint → 复用连接的 Channel。首次使用建立连接（持锁 await，
/// 创建仅一次，后续 clone 走 Arc 共享）。
async fn channel_for(state: &AppState, endpoint: &str) -> Result<Channel, UpstreamError> {
    {
        let map = state.channels.lock().unwrap_or_else(|e| e.into_inner());
        if let Some(ch) = map.get(endpoint) {
            return Ok(ch.clone());
        }
    }
    let ch = tonic::transport::Endpoint::from_shared(endpoint.to_string())
        .map_err(UpstreamError::from)?
        .timeout(state.cfg.timeout())
        .connect()
        .await
        .map_err(UpstreamError::from)?;
    let mut map = state.channels.lock().unwrap_or_else(|e| e.into_inner());
    map.entry(endpoint.to_string())
        .or_insert_with(|| ch.clone());
    Ok(ch)
}

/// 统一注入共享密钥头 x-internal-token（与 PHP worker 端校验一致）
fn grpc_req<T>(state: &AppState, msg: T) -> tonic::Request<T> {
    let mut req = tonic::Request::new(msg);
    if let Ok(v) = tonic::metadata::MetadataValue::from_str(&state.cfg.internal_token) {
        req.metadata_mut().insert("x-internal-token", v);
    }
    req
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
    Json(ApiOk {
        code: 0,
        message: "ok",
        data,
    })
    .into_response()
}

fn err_json(status: StatusCode, code: i64, message: impl Into<String>) -> Response {
    (
        status,
        Json(ApiError {
            code,
            message: message.into(),
        }),
    )
        .into_response()
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

#[derive(Deserialize)]
struct ApiKeyRecord {
    #[allow(dead_code)]
    appid: String,
    status: String,
    expire_at: i64,
}

fn sha256_hex(input: &str) -> String {
    use sha2::{Digest, Sha256};
    let digest = Sha256::digest(input.as_bytes());
    digest.iter().map(|b| format!("{b:02x}")).collect()
}

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
    if key.is_empty() {
        return err_json(StatusCode::UNAUTHORIZED, 401, "invalid or missing api key");
    }
    // 静态数组快速路径（demo key），随后走 Redis 动态校验
    if state.cfg.api_keys.iter().any(|k| k == key) {
        return next.run(req).await;
    }
    // Redis 校验：命中则校验 status=approved 且 expire_at>now；未命中 401；
    // 连接错误 fail-closed（与 NotFound 区分，避免 Redis 故障时放行）
    let rkey = format!("{}api_keys:{}", state.cfg.key_prefix, sha256_hex(key));
    match state.cache.get(&rkey).await {
        Ok(Some(raw)) => match serde_json::from_slice::<ApiKeyRecord>(&raw) {
            Ok(rec) => {
                let now = std::time::SystemTime::now()
                    .duration_since(std::time::UNIX_EPOCH)
                    .map(|d| d.as_secs() as i64)
                    .unwrap_or(0);
                if rec.status == "approved" && rec.expire_at > now {
                    next.run(req).await
                } else {
                    err_json(StatusCode::UNAUTHORIZED, 401, "api key disabled or expired")
                }
            }
            Err(_) => err_json(StatusCode::UNAUTHORIZED, 401, "invalid or missing api key"),
        },
        Ok(None) => err_json(StatusCode::UNAUTHORIZED, 401, "invalid or missing api key"),
        Err(e) => {
            tracing::warn!(error = %e, "api key redis check failed, failing closed");
            err_json(StatusCode::UNAUTHORIZED, 401, "invalid or missing api key")
        }
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

    let cache_key = format!(
        "{}cache:track:{carrier}:{tracking_no}",
        state.cfg.key_prefix
    );
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
            return err_json(StatusCode::BAD_GATEWAY, 502, "upstream worker failure");
        }
        Err(_) => {
            return err_json(StatusCode::BAD_GATEWAY, 502, "upstream worker failure");
        }
    };

    match parse_query(resp) {
        Ok(data) => {
            let ttl = ttl_for(&state, &carrier);
            let query_no = data
                .get("query_no")
                .and_then(Value::as_str)
                .map(str::to_string);
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
/// gRPC Detect（不走熔断——无 carrier 维度）。
async fn detect(state: AppState, tracking_no: String) -> Result<String, Response> {
    let cache_key = format!("{}cache:detect:{tracking_no}", state.cfg.key_prefix);
    if let Some(data) = cache_get(&state, &cache_key).await {
        if let Some(c) = data.get("carrier_code").and_then(Value::as_str) {
            return Ok(c.to_string());
        }
    }
    let endpoint = pick_worker(&state)
        .await
        .map_err(|_| err_json(StatusCode::BAD_GATEWAY, 502, "upstream worker failure"))?;
    let mut client = pb::internal_service_client::InternalServiceClient::new(
        channel_for(&state, &endpoint)
            .await
            .map_err(|_| err_json(StatusCode::BAD_GATEWAY, 502, "upstream worker failure"))?,
    );
    let resp = match client
        .detect(grpc_req(&state, pb::DetectRequest { tracking_no }))
        .await
    {
        Ok(r) => r.into_inner(),
        Err(_) => {
            return Err(err_json(
                StatusCode::BAD_GATEWAY,
                502,
                "upstream worker failure",
            ));
        }
    };
    let data = parse_detect(resp)?;
    let carrier = data
        .get("carrier_code")
        .and_then(Value::as_str)
        .ok_or_else(|| err_json(StatusCode::BAD_GATEWAY, 502, "carrier detection failed"))?
        .to_string();
    cache_set(
        &state,
        &cache_key,
        data,
        Duration::from_secs(state.cfg.detect_ttl_secs),
    )
    .await;
    Ok(carrier)
}

/// 解析 gRPC Query 响应：code==0 → 旧 HTTP 契约的 data JSON；否则透传
/// PHP 结构化错误码（code 为数值，message 不泄露内部细节）。
fn parse_query(resp: pb::QueryResponse) -> Result<Value, Response> {
    if resp.code == 0 {
        return Ok(json!({
            "query_no": resp.query_no,
            "carrier_code": resp.carrier_code,
            "tracking_no": resp.tracking_no,
            "status": resp.status,
            "delivered_at": resp.delivered_at,
            "estimated_delivery_at": resp.estimated_delivery_at,
            "latest_description": resp.latest_description,
            "raw_status": resp.raw_status,
            "events": resp.events.iter().map(|e| json!({
                "occurred_at": e.occurred_at,
                "location": e.location,
                "description": e.description,
                "status": e.status,
            })).collect::<Vec<_>>(),
        }));
    }
    Err(worker_error(resp.code, &resp.message, Some((&resp.error_code, &resp.error_message))))
}

fn parse_detect(resp: pb::DetectResponse) -> Result<Value, Response> {
    if resp.code == 0 {
        return Ok(json!({
            "carrier_code": resp.carrier_code,
            "channel": resp.channel,
            "confidence": resp.confidence,
        }));
    }
    Err(worker_error(resp.code, &resp.message, None))
}

fn parse_carriers(resp: pb::CarriersResponse) -> Result<Value, Response> {
    if resp.code == 0 {
        return Ok(json!(
            resp.carriers
                .iter()
                .map(|c| json!({ "carrier_code": c.carrier_code, "channel": c.channel }))
                .collect::<Vec<_>>()
        ));
    }
    Err(worker_error(resp.code, &resp.message, None))
}

/// 业务错误码 → HTTP 状态（4xx/5xx 原样映射，其余落 502）；
/// error 携带上游返回的 error_code/error_message，非空时并入 JSON（诊断用）
fn worker_error(code: i32, message: &str, error: Option<(&str, &str)>) -> Response {
    let http = match StatusCode::from_u16(code as u16) {
        Ok(s) if s.is_client_error() || s.is_server_error() => s,
        _ => StatusCode::BAD_GATEWAY,
    };
    let mut body = json!({ "code": code, "message": message });
    if let Some((ec, em)) = error {
        body["error_code"] = json!(ec);
        body["error_message"] = json!(em);
    }
    (http, Json(body)).into_response()
}

#[derive(Deserialize)]
struct SubscribeReq {
    carrier_code: String,
    callback_url: String,
    #[serde(default)]
    event_type: String,
}

/// SSRF 防护：仅允许 http/https 且 host 为公网域名或公网 IP。
/// 拒绝回环/私网/链路本地/文档网段（字面 IP 判断，不做 DNS 解析）。
fn valid_callback_url(url: &str) -> bool {
    let Some(rest) = url
        .strip_prefix("http://")
        .or_else(|| url.strip_prefix("https://"))
    else {
        return false;
    };
    if rest.is_empty() {
        return false;
    }
    let host = if let Some(after_bracket) = rest.strip_prefix('[') {
        after_bracket.split(']').next().unwrap_or("")
    } else {
        rest.split(['/', '?', '#']).next().unwrap_or("")
    };
    if host.is_empty() || host == "localhost" {
        return false;
    }
    // 带端口的 IP（127.0.0.1:8080）整体解析失败，剥离端口后再判
    let ip = host
        .parse::<std::net::IpAddr>()
        .ok()
        .or_else(|| host.rsplit_once(':').and_then(|(h, _)| h.parse().ok()));
    match ip {
        Some(ip) => {
            if ip.is_loopback() || ip.is_unspecified() {
                return false;
            }
            // 私网/链路本地按具体类型判断（IpAddr 枚举级方法未稳定，见 rust#27709）；
            // IPv4-mapped（::ffff:x.y.z.w）归一化为 IPv4 再判断
            let blocked = match ip {
                std::net::IpAddr::V4(v4) => v4.is_private() || v4.is_link_local(),
                // 私网 IPv6（fc00::/7）无稳定 API，reviewer 清单全为 IPv4 网段，暂不覆盖
                std::net::IpAddr::V6(v6) => match v6.to_ipv4_mapped() {
                    Some(v4) => v4.is_loopback() || v4.is_private() || v4.is_link_local(),
                    None => v6.is_unicast_link_local(),
                },
            };
            !blocked
        }
        // 域名按公网处理（不解析 DNS；防重绑定需在回调推送侧做 IP 白名单）
        None => true,
    }
}

/// POST /v1/subscriptions：注册回调订阅 → 转发 PHP worker（走 carrier 熔断）。
async fn subscribe(State(state): State<AppState>, Json(req): Json<SubscribeReq>) -> Response {
    let carrier = req.carrier_code.trim().to_string();
    if carrier.is_empty() {
        return err_json(StatusCode::BAD_REQUEST, 400, "carrier_code is required");
    }
    let callback_url = req.callback_url.trim().to_string();
    if callback_url.len() > 500 {
        return err_json(
            StatusCode::BAD_REQUEST,
            400,
            "callback_url too long (max 500 chars)",
        );
    }
    if !valid_callback_url(&callback_url) {
        return err_json(
            StatusCode::BAD_REQUEST,
            400,
            "callback_url must be an http(s) url targeting a public host",
        );
    }
    let event_type = if req.event_type.trim().is_empty() {
        "tracking.update".to_string()
    } else {
        req.event_type.trim().to_string()
    };

    let state2 = Arc::new(state.clone());
    let carrier2 = carrier.clone();
    let resp = match tokio::spawn(async move {
        let mut svc = state2.breakers.sub_for_carrier(&carrier2);
        svc.call(SubscribeForwardReq {
            state: state2,
            carrier: carrier2,
            callback_url,
            event_type,
        })
        .await
    })
    .await
    {
        Ok(Ok(resp)) => resp,
        Ok(Err(e)) => {
            if e.downcast_ref::<std::io::Error>().is_some() {
                return err_json(
                    StatusCode::SERVICE_UNAVAILABLE,
                    503,
                    format!("carrier {carrier} is temporarily unavailable"),
                );
            }
            return err_json(StatusCode::BAD_GATEWAY, 502, "upstream worker failure");
        }
        Err(_) => return err_json(StatusCode::BAD_GATEWAY, 502, "upstream worker failure"),
    };

    if resp.code == 0 {
        ok_json(json!({
            "subscription_id": resp.subscription_id,
            "secret": resp.secret,
        }))
    } else {
        worker_error(resp.code, &resp.message, Some((&resp.error_code, &resp.error_message)))
    }
}

async fn tracking_detail(State(state): State<AppState>, Path(query_no): Path<String>) -> Response {
    match cache_get(
        &state,
        &format!("{}cache:query:{query_no}", state.cfg.key_prefix),
    )
    .await
    {
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
    let mut client = match channel_for(&state, &endpoint).await {
        Ok(ch) => pb::internal_service_client::InternalServiceClient::new(ch),
        Err(_) => return err_json(StatusCode::BAD_GATEWAY, 502, "upstream worker failure"),
    };
    let resp = match client
        .carriers(grpc_req(&state, pb::CarriersRequest {}))
        .await
    {
        Ok(r) => r.into_inner(),
        Err(_) => return err_json(StatusCode::BAD_GATEWAY, 502, "upstream worker failure"),
    };
    match parse_carriers(resp) {
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
    let config_path =
        std::env::var("TRACKING_GATEWAY_CONFIG").unwrap_or_else(|_| "config/config.json".into());
    let source = FileSource::new(config_path.clone());
    let map = source.load().await?;
    let cfg = Arc::new(serde_json::from_value::<GatewayConfig>(Value::Object(
        map.into_iter().collect(),
    ))?);

    let cache = Arc::new(RedisCache::connect(&cfg.redis_url).await?);
    let rate: Arc<dyn RateLimitStore> =
        Arc::new(RedisRateLimitStore::connect(&cfg.redis_url).await?);
    // ecat-client StaticResolver::add_service 内部用 tokio RwLock::blocking_write，
    // 在 runtime worker 线程直接调用会 panic；block_in_place 移出线程执行
    let resolver: Arc<dyn ServiceResolver> = Arc::new(tokio::task::block_in_place(|| {
        StaticResolver::new().add_service("workers", cfg.workers.clone())
    }));
    let balancer: Arc<dyn LoadBalancer> = Arc::new(RoundRobin::new());

    let state = AppState {
        cfg: cfg.clone(),
        cache,
        rate,
        resolver,
        balancer,
        channels: Arc::new(Mutex::new(HashMap::new())),
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
        .route("/v1/subscriptions", post(subscribe))
        .layer(middleware::from_fn_with_state(
            state.clone(),
            require_api_key,
        ));

    // /v1/health、/v1/openapi.json、/metrics 为公共端点，不要求 API-Key
    let health: Router<AppState> = HealthRegistry::new().into_router().with_state(());
    let openapi: Router<AppState> = Router::new()
        .route("/openapi.json", get(openapi::openapi_json))
        .with_state(());
    let metrics: Router<AppState> = metrics_router().with_state(());

    let router: Router<()> = api
        .nest("/v1", health.merge(openapi))
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
    fn callback_url_validation() {
        assert!(valid_callback_url("https://example.com/hook"));
        assert!(valid_callback_url("http://example.com:8080/hook"));
        assert!(valid_callback_url("https://8.8.8.8/hook"));
        assert!(!valid_callback_url(""));
        assert!(!valid_callback_url("ftp://example.com/hook"));
        assert!(!valid_callback_url("javascript:alert(1)"));
        assert!(!valid_callback_url("http://127.0.0.1:9999/hook"));
        assert!(!valid_callback_url("http://[::1]/hook"));
        assert!(!valid_callback_url("http://10.0.0.1/hook"));
        assert!(!valid_callback_url("http://172.16.0.1/hook"));
        assert!(!valid_callback_url("http://192.168.1.1/hook"));
        assert!(!valid_callback_url(
            "http://169.254.169.254/latest/meta-data"
        ));
        assert!(!valid_callback_url("http://localhost/hook"));
        assert!(!valid_callback_url("http://[::ffff:127.0.0.1]/hook"));
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
