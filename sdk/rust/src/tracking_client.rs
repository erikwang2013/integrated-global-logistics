// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// e-cat 查询网关对外 API 客户端 — 零第三方依赖（std TcpStream 手写 HTTP + json.rs）
use crate::json::{self, Value};
use std::error::Error;
use std::fmt;
use std::io::{Read, Write};
use std::net::TcpStream;
use std::time::Duration;

/// 网关业务错误或网络失败；code=-1 表示网络层错误（message 为原因）。
#[derive(Debug, Clone)]
pub struct TrackingApiError {
    pub code: i64,
    pub message: String,
    pub error_code: Option<String>,
    pub error_message: Option<String>,
    pub http_status: Option<u16>,
}

impl fmt::Display for TrackingApiError {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        write!(f, "code={} message={}", self.code, self.message)
    }
}

impl Error for TrackingApiError {}

/// 封装网关四个端点；方法返回信封 data 的 Value（Object/Array/Null）。
pub struct TrackingClient {
    api_key: String,
    host: String,
    port: u16,
    timeout: Duration,
}

impl TrackingClient {
    /// 构造客户端；base_url 形如 http://host:port（仅支持明文 http，https 请放反向代理后）。
    pub fn new(api_key: &str, base_url: &str) -> Result<Self, String> {
        let base_url = base_url.trim_end_matches('/');
        let rest = base_url
            .strip_prefix("http://")
            .ok_or("base_url 需为 http://host[:port]（零依赖不支持 https）")?;
        let (host, port) = match rest.rsplit_once(':') {
            Some((h, p)) if p.chars().all(|c| c.is_ascii_digit()) => {
                (h.to_string(), p.parse::<u16>().unwrap_or(80))
            }
            _ => (rest.to_string(), 80u16),
        };
        Ok(TrackingClient {
            api_key: api_key.to_string(),
            host,
            port,
            timeout: Duration::from_secs(10),
        })
    }

    /// 超时自定义（默认 10 秒）。
    pub fn with_timeout(mut self, timeout: Duration) -> Self {
        self.timeout = timeout;
        self
    }

    /// 轨迹查询，carrier_code 可选。
    pub fn query_tracking(&self, tracking_no: &str, carrier_code: Option<&str>) -> Result<Value, TrackingApiError> {
        let mut payload = format!("{{\"tracking_no\":\"{}\"", json_escape(tracking_no));
        if let Some(c) = carrier_code {
            payload.push_str(&format!(",\"carrier_code\":\"{}\"", json_escape(c)));
        }
        payload.push('}');
        self.request("POST", "/v1/tracking/query", Some(&payload))
    }

    /// 按查询号取上次结果。
    pub fn get_tracking(&self, query_no: &str) -> Result<Value, TrackingApiError> {
        self.request("GET", &format!("/v1/tracking/{}", path_escape(query_no)), None)
    }

    /// 承运商清单。
    pub fn list_carriers(&self) -> Result<Value, TrackingApiError> {
        self.request("GET", "/v1/carriers", None)
    }

    /// 注册回调订阅，返回 {subscription_id, secret}。
    pub fn subscribe(
        &self,
        carrier_code: &str,
        callback_url: &str,
        event_type: &str,
    ) -> Result<Value, TrackingApiError> {
        let et = if event_type.is_empty() { "tracking.update" } else { event_type };
        let payload = format!(
            "{{\"carrier_code\":\"{}\",\"callback_url\":\"{}\",\"event_type\":\"{}\"}}",
            json_escape(carrier_code),
            json_escape(callback_url),
            json_escape(et)
        );
        self.request("POST", "/v1/subscriptions", Some(&payload))
    }

    fn request(&self, method: &str, path: &str, payload: Option<&str>) -> Result<Value, TrackingApiError> {
        let body = payload.unwrap_or("");
        let mut head = format!(
            "{} {} HTTP/1.1\r\nHost: {}:{}\r\nX-API-Key: {}\r\nConnection: close\r\n",
            method, path, self.host, self.port, self.api_key
        );
        if payload.is_some() {
            head.push_str(&format!(
                "Content-Type: application/json\r\nContent-Length: {}\r\n",
                body.len()
            ));
        }
        head.push_str("\r\n");

        let mut stream = TcpStream::connect((self.host.as_str(), self.port))
            .map_err(|e| net_err(format!("connect: {e}")))?;
        stream
            .set_read_timeout(Some(self.timeout))
            .and_then(|_| stream.set_write_timeout(Some(self.timeout)))
            .map_err(|e| net_err(format!("set_timeout: {e}")))?;
        let mut raw = head.into_bytes();
        raw.extend_from_slice(body.as_bytes());
        stream.write_all(&raw).map_err(|e| net_err(format!("write: {e}")))?;
        let mut resp = Vec::new();
        stream.read_to_end(&mut resp).map_err(|e| net_err(format!("read: {e}")))?;
        let text = String::from_utf8_lossy(&resp);
        let (head_part, body_text) = text
            .split_once("\r\n\r\n")
            .ok_or_else(|| invalid("malformed HTTP response"))?;

        let http_status = head_part
            .lines()
            .next()
            .and_then(|l| l.split_whitespace().nth(1))
            .and_then(|s| s.parse::<u16>().ok())
            .ok_or_else(|| invalid("missing HTTP status"))?;

        let body_owned = if head_part.to_ascii_lowercase().contains("transfer-encoding: chunked") {
            dechunk(body_text)
        } else {
            body_text.to_string()
        };

        let env = json::parse(&body_owned).map_err(|e| invalid(&format!("bad JSON: {e}")))?;
        let code = env.get("code").and_then(Value::as_f64).unwrap_or(0.0) as i64;
        if code != 0 {
            return Err(TrackingApiError {
                code,
                message: env.get("message").and_then(Value::as_str).unwrap_or("").to_string(),
                error_code: env.get("error_code").and_then(Value::as_str).map(String::from),
                error_message: env.get("error_message").and_then(Value::as_str).map(String::from),
                http_status: Some(http_status),
            });
        }
        Ok(env.get("data").cloned().unwrap_or(Value::Null))
    }
}

fn net_err(msg: String) -> TrackingApiError {
    TrackingApiError { code: -1, message: format!("network error: {msg}"), error_code: None, error_message: None, http_status: None }
}

fn invalid(msg: &str) -> TrackingApiError {
    TrackingApiError { code: -1, message: msg.to_string(), error_code: None, error_message: None, http_status: None }
}

fn dechunk(s: &str) -> String {
    let mut out = String::new();
    let mut rest = s;
    loop {
        let (size_line, tail) = match rest.split_once("\r\n") {
            Some(x) => x,
            None => break,
        };
        let size = usize::from_str_radix(size_line.trim().split(';').next().unwrap_or("0"), 16).unwrap_or(0);
        if size == 0 {
            break;
        }
        out.push_str(&tail[..size.min(tail.len())]);
        if tail.len() < size + 2 {
            break;
        }
        rest = &tail[size + 2..];
    }
    out
}

fn json_escape(s: &str) -> String {
    s.replace('\\', "\\\\")
        .replace('"', "\\\"")
        .replace('\n', "\\n")
        .replace('\r', "\\r")
        .replace('\t', "\\t")
}

fn path_escape(s: &str) -> String {
    let mut out = String::with_capacity(s.len());
    for b in s.bytes() {
        if b.is_ascii_alphanumeric() || b"-._~".contains(&b) {
            out.push(b as char);
        } else {
            out.push_str(&format!("%{:02X}", b));
        }
    }
    out
}
