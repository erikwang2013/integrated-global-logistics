# e-cat 查询网关 SDK
<img src="../docs/diagrams/mascot.svg" alt="E-Cat 项目吉祥物" width="220" align="right">

对外 API 客户端，五份零依赖 SDK：拷贝即用，无第三方包。

- Python：`python/tracking_client.py`（stdlib `urllib.request`，Python 3.7+）
- PHP：`php/TrackingClient.php`（内置 curl，PHP 7.0+）
- Node.js：`js/tracking-client.js`（内置 fetch，Node 18+，CommonJS）
- Go：`go/tracking_client.go`（stdlib `net/http`，Go 1.21+）
- Rust：`rust/src/tracking_client.rs`（std `TcpStream`，零依赖，仅支持明文 http）

## 安装

五份 SDK 均为单文件实现、零第三方依赖，无需 `pip install` / `composer install` / `npm install` / `go get` / `cargo add` —— 拷贝即用：

| 语言 | 文件 | 运行要求 |
|---|---|---|
| Python | `python/tracking_client.py` | Python 3.7+（stdlib `urllib.request`） |
| PHP | `php/TrackingClient.php` | PHP 7.0+（内置 curl） |
| Node.js | `js/tracking-client.js` | Node 18+（内置 fetch，CommonJS） |
| Go | `go/tracking_client.go` | Go 1.21+（stdlib `net/http`） |
| Rust | `rust/src/tracking_client.rs` | Rust 1.56+（std `TcpStream`，零依赖，仅支持明文 http，https 请置于反向代理之后） |

各目录内示例脚本（`example.py` / `example.php` / `example.js` / `go/example` / `rust/`）同样可直接运行。

## 功能

- **轨迹查询**：`query_tracking()` 按单号查询，自动识别承运商，返回统一标准化轨迹；`get_tracking()` 按查询号取上次结果；
- **多承运商**：`list_carriers()` 获取承运商清单，查询时可按 `carrier_code` 指定承运商；
- **回调订阅**：`subscribe()` 注册订阅，承运商轨迹更新时网关回调推送商户 URL；
- **统一错误码**：信封 `code != 0` 即错误，限流 429 / 熔断 503 / 承运商错误 `carrier_error`，异常统一携带 `code` / `message` / `error_code` / `http_status`；
- **零依赖**：五份 SDK 全部只用标准库，跨语言行为一致，密钥由调用方传入、SDK 不落任何密钥。

## 初始化

```python
from tracking_client import TrackingClient
client = TrackingClient("your-api-key", "http://127.0.0.1:8080")
```

```php
require "TrackingClient.php";
$client = new TrackingClient("your-api-key", "http://127.0.0.1:8080");
```

```js
const { TrackingClient } = require("./tracking-client.js");
const client = new TrackingClient("your-api-key", "http://127.0.0.1:8080");
```

```go
import "trackingclient"
client := trackingclient.NewClient("your-api-key", "http://127.0.0.1:8080")
```

```rust
use trackingclient::tracking_client::TrackingClient;
let client = TrackingClient::new("your-api-key", "http://127.0.0.1:8080")?;
```

`api_key` 为网关 `api_keys` 中配置的密钥；`base_url` 为网关地址（默认 `http://localhost:8080`，见 `config/config.json`）。超时默认 10 秒（Python/PHP/JS 用第三参调整，Rust 用 `.with_timeout(Duration)`）。`api_key` 由调用方传入，SDK 不落任何密钥。

Go/Rust 方法返回 data 的原始 JSON：Go 为 `json.RawMessage`（用 `encoding/json` 反序列化），Rust 为 `json::Value`（`get`/`as_str`/`as_array` 取值，见 `rust/src/json.rs`）。Rust 仅支持明文 `http://`，`https` 请置于反向代理之后。

## 方法

| 方法 | 对应端点 | 说明 |
|---|---|---|
| `query_tracking(tracking_no, carrier_code=None)` | `POST /v1/tracking/query` | 轨迹查询，`carrier_code` 可选 |
| `get_tracking(query_no)` | `GET /v1/tracking/{query_no}` | 按查询号取上次结果 |
| `list_carriers()` | `GET /v1/carriers` | 承运商清单 |
| `subscribe(carrier_code, callback_url, event_type="tracking.update")` | `POST /v1/subscriptions` | 注册回调订阅，返回 `{subscription_id, secret}` |

成功时返回信封的 `data` 字段（dict / array / null）。所有请求带 `X-API-Key` 请求头。

## 调用示例

```python
result = client.query_tracking("LX123456789CN")
print(result["status"], result["events"][0]["description"])
```

```php
$carriers = $client->list_carriers();
$sub = $client->subscribe("china-post", "https://example.com/cb");
```

```js
const detail = await client.getTracking(queryNo);
console.log(detail.status);
```

## 错误处理

信封 `code != 0` 或网络失败视为错误，抛出 `TrackingApiError`，异常携带：

- `code` / `message` — 网关业务码与消息（如 `401` 密钥无效、`429` 限流、`503` 熔断、承运商错误码）
- `error_code` / `error_message` — 上游承运商错误详情（诊断用，可能为空）
- `http_status` — HTTP 状态码（网络层错误为 `-1`）

```python
try:
    client.query_tracking("LX123456789CN")
except TrackingApiError as e:
    print(e.code, e.message, e.error_code)
```

```php
try {
    $client->query_tracking("LX123456789CN");
} catch (TrackingApiError $e) {
    echo $e->code, " ", $e->getMessage();
}
```

```js
try {
  await client.queryTracking("LX123456789CN");
} catch (e) {
  console.log(e.code, e.message, e.errorCode);
}
```

限流（429）与熔断（503）建议退避重试；承运商侧错误（`error_code` 非空）无需重试。

## 示例脚本

各目录内示例可直接运行，默认用演示密钥 `demo-api-key` + `http://localhost:8080`，可用环境变量 `TRACKING_API_KEY` / `TRACKING_BASE_URL` 覆盖；跑通四方法全流程（每个方法独立捕获异常，不因上游不可用中断）：

- Python：`python3 example.py`
- PHP：`php example.php`
- Node.js：`node example.js`
- Go：`cd go && go run ./example`
- Rust：`cd rust && cargo run`
