# 物流聚合平台实施规划
<img src="diagrams/mascot.svg" alt="E-Cat 项目吉祥物" width="220" align="right">

> 架构师规划文档 · 2026-08-27
> 依据：`admin`（PHP webman 管理后台，已有 RBAC/JWT/加密/导出体系）+ `infrastructure`（Rust e-cat 框架，55+ crates 微服务全栈）+ `erikwang2013/global-logistics`（PHP 包，209 家承运商轨迹查询统一门面，本地位于 `/home/wwwroot/erikwang2013/global-logistics`）。

---

## 1. 系统架构图

```
┌─────────────────────────────────────────────────────────────────────┐
│                          客户端（商户 / C 端）                         │
│               POST /v1/tracking/query  ·  GET /v1/tracking/{no}      │
│               GET /v1/carriers · POST /v1/subscriptions              │
└───────────────────────────────┬─────────────────────────────────────┘
                                │ API-Key 鉴权
┌───────────────────────────────▼─────────────────────────────────────┐
│                infrastructure — e-cat 查询网关（Rust）                 │
│  tracking-gateway crate（workspace 新成员）                           │
│                                                                     │
│  ① ecat-middleware RateLimitLayer（Redis 限流，按 API-Key/IP）        │
│  ② ecat-data-redis 缓存命中  cache:track:{carrier}:{no}             │
│  ③ ecat-circuit-breaker 按承运商熔断（半开探测，故障快速失败）           │
│  ④ ecat-client LoadBalancer（RoundRobin）→ PHP worker 池            │
│  ⑤ ecat-metrics /metrics（Prometheus）· ecat-health /health         │
└───────────────┬──────────────────────────────┬──────────────────────┘
                │ gRPC InternalService（仅内网，       │ rpc Carriers
                │ 共享密钥头 x-internal-token）       │（registry 同步，e-cat 缓存）
                │ rpc Query / Detect / Subscribe     │
                ▼                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│            admin — PHP webman worker 池（无状态，可水平扩展）           │
│  app/grpc InternalService → GlobalLogistics\Logistics 门面          │
│  domestic($code)/international($code)->queryTrack($no)              │
│  凭证从 logistics_carrier_credential 读取（Encryptable 加密存储）           │
│  查询结果落 logistics_tracking_query · 标准化 JSON 返回                    │
└───────┬──────────────────────────────────────┬──────────────────────┘
        │ Guzzle（RetryingClient，包内自带重试）   │ 承运商 webhook 回调
        ▼                                        ▼
┌───────────────┐                      ┌──────────────────────────────┐
│  209 家承运商  │                      │ /api/callback/{carrier}       │
│  上游 API      │                      │ 白名单 + 签名校验 → 落库        │
│  (SF/ZTO/...  │                      │ logistics_tracking_event →         │
│   45国内+164国际)│                      │ 更新查询记录 → webman 队列异步   │
└───────────────┘                      │ 推送商户回调 URL（重试退避）     │
                                       └──────────────────────────────┘

管理面（浏览器）→ /admin/*（JWT + AdminPermission RBAC + OperationLog）
  carrier / carrier-credential / tracking-query / callback-subscription / statistics
```

## 2. 职责划分

| 面 | 载体 | 职责 |
|---|---|---|
| 管理面 | admin（PHP webman） | 承运商档案 CRUD、密钥管理（加密存储）、查询记录、统计报表、回调订阅配置、RBAC 权限（沿用 `logistics_admin_permission` slug 约定与 `AdminPermission` 中间件） |
| 高性能面 | infrastructure（Rust e-cat） | 对外轨迹查询 API、API-Key 鉴权、Redis 缓存、限流、按承运商熔断、worker 负载均衡、Prometheus 指标 |

**e-cat 复用 209 家 PHP 适配器的方案（推荐）：e-cat 作网关/聚合层，PHP worker 池跑 global-logistics 门面。**

理由：
1. 209 个适配器是 PHP（`src/Carriers/Domestic` 45 家 + `International` 164 家），Rust 重写是数月工程且丧失上游包持续更新收益 —— 不可能接受。
2. e-cat 生态恰好覆盖高频层全部诉求：`ecat-client`（LoadBalancer/ServiceResolver）、`ecat-circuit-breaker`、`ecat-middleware`（RateLimitLayer + RedisRateLimitStore）、`ecat-data-redis`（缓存）、`ecat-metrics`。它不需要懂承运商协议，只需要一个稳定的内部契约。
3. 缓存命中、限流拒绝、熔断快速失败都在 e-cat 侧完成，PHP worker 只承接真实查询流量，水平扩展只需加 worker。
4. 内部契约面极小（一个 queryTrack 端点 + 一个 registry 端点），跨语言耦合可控；凭证永不下发到 e-cat，安全边界清晰。

备选方案评估（否决）：
- **Rust 全量重写适配器**：209 个适配器 + 各家协议文档维护，数月工程，否决。
- **PHP gRPC 服务（ecat-transport-grpc）**：PHP gRPC 生态弱（需 grpc 扩展 + protobuf 编译链），相对 HTTP/JSON 内部契约无收益，否决。
- **PHP 直接对外 + e-cat 仅代理**：丢失统一缓存/限流/熔断语义，否决。

## 3. 数据流

### 3.1 查询链路（同步）

```
客户端 → e-cat 网关
  → API-Key 鉴权（M2 首版：Header `X-API-Key`，key 存 logistics_system_config 或独立表）
  → RateLimitLayer（Redis，按 key + 窗口）
  → 缓存查找 cache:track:{carrier}:{no}（TTL 按承运商 cache_ttl 配置，5~60min）
    → 命中：直接返回（附带 X-Cache: HIT）
  → 未命中：
    → ecat-circuit-breaker 检查（按 carrier 维度；OPEN 则 503 快速失败，半开后放行探针）
    → ecat-client RoundRobin 从 worker 池选一个 → gRPC InternalService.Query
    → PHP worker：读凭证 → Logistics::domestic/international($code)->queryTrack($no)
      （包内 RetryingClient 自带 2 次重试）
    → 落库 logistics_tracking_query → 返回标准化 JSON → e-cat 写缓存 → 响应
```

- `carrier_code` 缺省时：e-cat 先查 `cache:detect:{no}`（短 TTL 5min），未命中调 gRPC `InternalService.Detect`（复用包内 Detector）。
- 上游承运商失败：PHP 返回结构化错误码 → e-cat 熔断计数 → 客户端收到统一错误结构（`code/carrier_error`），不泄露内部细节。

### 3.2 回调链路（异步，M3）

```
承运商 webhook → admin /api/callback/{carrier}（无 RBAC，白名单路由 + 签名校验）
  → 解析载荷（复用包内订阅/解析能力或按 carrier 适配）
  → 落库 logistics_tracking_event + 更新 logistics_tracking_query 最新状态
  → 写入 webman 队列（app/queue/redis 已有）
  → 异步消费者：按 logistics_callback_subscription 推送到商户回调 URL
    （HMAC 签名 + 幂等键 + 指数退避重试 + 手动重推入口）
```

> ponytail: 首版回调推送放 PHP 队列而非 e-cat —— 事件解析与数据都在 PHP 侧，跨语言传事件无收益；若推送吞吐成为瓶颈（万级/分钟以上），再把消费者迁到 e-cat（ecat-mq + retry 中间件），外部契约不变。

## 4. 数据库设计（新增，遵循 logistics_ 前缀 / snowflake 主键 / created_at+updated_at 规范）

| 表 | 字段要点 | 说明 |
|---|---|---|
| `logistics_carrier` | id, code(uk), name, channel(domestic/international), country, logo, status, timeout_ms, cache_ttl, sort, remark | 承运商档案；channel 对应包内 `Channel::Domestic/International` |
| `logistics_carrier_credential` | id, carrier_id(idx), name, app_key, app_secret, extra(json), status | 密钥加密存储（Encryptable cast，同 AdminUser.phone 模式）；extra 存各家私有参数（customer/company/端等） |
| `logistics_tracking_query` | id, query_no(uk), carrier_id, carrier_code(idx), tracking_no(idx), credential_id, status(success/fail), result(json), raw_response(text), query_source(api/admin/webhook), cost_ms, error_code, error_message, created_at(idx) | 查询记录，高频写；result 存标准化 Tracking（含 events） |
| `logistics_callback_subscription` | id, carrier_id(idx), callback_url, secret, event_type, status, max_retry, last_push_at, last_success_at | 回调订阅配置 |
| `logistics_tracking_event`（M3） | id, tracking_no(idx), carrier_code(idx), event_code, event_desc, location, event_time, raw_payload, created_at(idx) | 事件明细（回调落库） |
| `logistics_client`（M7） | id, username(uk), password(bcrypt), contact_name, contact_phone, contact_email, status | 客户端账号（门户注册/登录，JWT `token_type=client`，与 admin JWT 隔离） |
| `logistics_client_app`（M7） | id, client_id(idx), appid(uk), name, purpose, api_key_sha256, plan_id, valid_days, expire_at, review_remark, reviewed_by, reviewed_at, status(pending/approved/rejected/disabled) | 客户端应用；X-API-Key 仅存 sha256（明文仅创建时返回一次）；审核通过后按套餐计有效期 |
| `logistics_plan`（M7） | id, name, price(分), valid_days, status | 套餐（种子：体验 0 元/7 天、基础 99 元/30 天、专业 399 元/365 天） |
| `logistics_order`（M8/M9） | id, order_no(uk), client_id, app_id, plan_id, amount, status(pending/paid/cancelled), channel(stripe/paypal/crypto/manual), chain(trc20/bep20/erc20), crypto_amount, tx_id, paid_at | 订单；支付渠道 + 虚拟币链与链上交易哈希 |
| `logistics_cdn_provider`（M13） | id, code(uk), name, access_key, access_secret, extra(json), domains(json), status, sort, remark | CDN 服务商凭证（access_key/access_secret `Encryptable` 加密存储） |

RBAC 权限种子（沿用 slug 约定，插入 `logistics_admin_permission`）：
- 菜单（type=1）：物流服务商 carrier、查询记录 tracking-query、回调订阅 callback-subscription、统计报表 tracking-statistics
- API（type=3）：`get.admin/carrier`、`post.admin/carrier`、`put.admin/carrier`、`delete.admin/carrier`、`get.admin/carrier/credential`、`post.admin/carrier/credential`、`put.admin/carrier/credential`、`delete.admin/carrier/credential`、`get.admin/tracking/query`、`get.admin/callback/subscription`、`post.admin/callback/subscription`、`put.admin/callback/subscription`、`delete.admin/callback/subscription`、`get.admin/tracking/statistics`

## 5. API 契约

### 5.1 对外（客户端 → e-cat，`/v1` 前缀，`X-API-Key` 头）

| 方法 | 路径 | 说明 |
|---|---|---|
| POST | `/v1/tracking/query` | body `{carrier_code?, tracking_no}`；carrier_code 缺省走 detect；返回 `{query_no, carrier_code, tracking_no, status, events[]}` |
| GET | `/v1/tracking/{query_no}` | 最近一次查询结果（缓存） |
| GET | `/v1/carriers` | 支持的承运商列表（e-cat 缓存 /internal/carriers 结果） |
| POST | `/v1/subscriptions`（M3） | 创建回调订阅 `{carrier_code, callback_url, event_type}` |
| GET | `/v1/health` | 健康检查（e-cat ecat-health） |
| GET | `/v1/openapi.json` | OpenAPI 3.0 文档（公共端点，无需密钥） |

统一响应：`{code: 0, message: "ok", data: {...}}`；错误 `{code: 4xx/5xx 段, message}`（限流 429、熔断 503、承运商错误 carrier_error）。

> 客户端 SDK：五份零依赖 SDK 位于仓库 `sdk/` 目录（Python / PHP / Node.js / Go / Rust），用法与示例见 [sdk/README.md](../sdk/README.md)。

#### 5.1.1 客户端门户 API（admin app `/api/*`，需客户端 JWT `token_type=client`，非 e-cat）

| 方法 | 路径 | 说明 |
|---|---|---|
| POST | `/api/auth/register`、`/api/auth/login`（`client=1` 走 ClientAuthController） | 客户端注册 / 登录，返回客户端 JWT（`/api/auth/refresh` 共用） |
| GET | `/api/plan` | 套餐列表 |
| GET/POST | `/api/app` | 应用列表 / 创建（自设 X-API-Key ≥16 位，sha256 落库） |
| PUT | `/api/app/{id}`、`/api/app/{id}/key` | 更新应用 / 重置密钥（新明文仅返回一次） |
| POST | `/api/app/{id}/order` | 应用下单 |
| POST | `/api/order/{id}/pay` | 发起支付（stripe / paypal / crypto） |

### 5.2 内部（e-cat → PHP worker，gRPC，仅内网，共享密钥头 `x-internal-token`）

proto 定义于 `infrastructure/tracking-gateway/proto/internal.proto`（`internal.v1.InternalService`），PHP 服务端为 `admin/app/grpc`（webman grpc server，`InternalService::authorized` 以 `hash_equals` 校验共享密钥），**不落 OperationLog、不走 RBAC**。

| RPC | 请求 → 响应 | 说明 |
|---|---|---|
| `Query` | `QueryRequest{carrier_code, tracking_no, credential_id?}` → `QueryResponse{code, message, error_code, error_message, query_no, carrier_code, tracking_no, status, delivered_at, estimated_delivery_at, latest_description, raw_status, events[]}` | 标准化 Tracking 或错误码 |
| `Detect` | `DetectRequest{tracking_no}` → `DetectResponse{code, message, error_code, error_message, carrier_code, channel, confidence}` | 复用包内 Detector |
| `Carriers` | `CarriersRequest{}` → `CarriersResponse{code, message, carriers[]}` | 承运商注册表（包内 `Resources/carrier-registry.php`），e-cat 缓存 10min 供 /v1/carriers |
| `Subscribe`（M3） | `SubscribeRequest{carrier_code, callback_url, event_type}` → `SubscribeResponse{code, message, subscription_id, secret, error_code, error_message}` | 创建订阅（写 logistics_callback_subscription），secret 供商户 HMAC 验签 |

### 5.3 管理端（admin，走现有 RBAC 中间件链）

- `Route::resource('/carrier', CarrierController::class)` + `Route::resource('/carrier/credential', CarrierCredentialController::class)`
- `Route::get('/tracking/query', TrackingQueryController@index)`（分页 + carrier_code/tracking_no/status/时间筛选）
- `Route::resource('/callback/subscription', CallbackSubscriptionController::class)`
- `Route::get('/tracking/statistics', StatisticsController@index)`（M4：按日/承运商聚合查询量、成功率、平均耗时）
- `Route::get('/client', ClientController@index)` + `Route::get('/client/app', ClientController@apps)` + `Route::post('/client/app/{id}/review', ClientController@review)` + `Route::post('/client/app/{id}/disable', ClientController@disable)`（M7：客户端列表、应用审核/禁用）
- `Route::resource('/plan', PlanController::class)`（M7：套餐 CRUD）
- `Route::get('/order', OrderController@index)` + `Route::post('/order/{id}/confirm|cancel', OrderController@confirm|cancel)`（M8：订单查询、确认/取消兜底）
- `Route::resource('/cdn/provider', CdnProviderController::class)`（M13：CDN 服务商凭证 CRUD）

控制器范式照抄 `UserController`：`BaseController` 的 `success/fail/generateId/encodeIds/decodeId`、Webman\Validation\Validator、Apidoc 注解。

## 6. e-cat 网关 crate 依赖（infrastructure/workspace 新成员 `tracking-gateway`）

| crate | 用途 |
|---|---|
| `ecat` | App 骨架（builder + 生命周期，同 examples/helloworld） |
| `ecat-transport-http` | axum HttpServer（对外 + 管理端口） |
| `ecat-middleware` | LoggingLayer / TracingLayer / RecoveryLayer / TimeoutLayer / RateLimitLayer + `RedisRateLimitStore`（feature `redis`） |
| `ecat-client` | LoadBalancer（RoundRobin）+ StaticResolver（worker 端点列表；后续可换 Consul/Etcd resolver） |
| `ecat-circuit-breaker` | 按 carrier 熔断（failure_ratio / window / half_open_probes / open_duration） |
| `ecat-data-redis` | 轨迹缓存 + 限流存储 |
| `ecat-config` | worker 列表、限流阈值、TTL 配置 |
| `ecat-metrics` | /metrics（Prometheus 指标） |
| `ecat-health` | 健康检查 |
| `ecat-tracing` / `ecat-logging` | 链路追踪与结构化日志 |
| `ecat-auth`（M4 可换自研） | API-Key 鉴权 |
| `ecat-security`（M11） | SecurityBodyLayer 攻击检测（注入 / 协议 / 数据序列化 / 文件 / 敏感数据泄露），HandleErrorLayer 消化 SecurityError |
| `ecat-openapi`（M4） | 对外 API 文档 |
| 生态依赖 | tokio、axum、serde/serde_json、reqwest（转发 worker）、tower、thiserror、tracing |

## 7. 实施阶段与里程碑

| 阶段 | 内容 | 产出物 | 完成标志 |
|---|---|---|---|
| **M1 管理后台扩展** | 表 + 模型 + 控制器 + 路由 + RBAC 权限种子 + 凭证加密 | install.sql 增量 DDL 与权限种子；模型 Carrier/CarrierCredential/TrackingQuery/CallbackSubscription；控制器与路由注册；`composer require erikwang2013/global-logistics` 并配置 | 后台可对承运商/密钥/订阅 CRUD，权限按 slug 生效，admin 全量回归通过 |
| **M2 查询网关** ✅ | workspace 新 crate `tracking-gateway` + PHP gRPC 端点 + 缓存 + LB + 限流 + 熔断 + 对外 API | `infrastructure/tracking-gateway/`（Cargo.toml 加入 workspace members）；`app/grpc/`（InternalService gRPC server，Query/Detect/Carriers/Subscribe）；e-cat 对外 /v1 接口；/metrics；docker-compose 串联 | 已达成：`cargo build --offline` 通过；端到端验证 —— detect 识别 ✓、query 直达顺丰上游并返回结构化 carrier_error ✓、gateway 转发 + 熔断链路 ✓、落库 logistics_tracking_query ✓、Redis 前缀 `logistics:` ✓、internal 401/参数校验 ✓ |
| **M3 回调与订阅** ✅ | webhook 接收 + 事件落库 + 异步推送 + 订阅管理 | logistics_tracking_event 表；`/api/callback/{carrier}` 白名单路由 + 签名校验；webman 队列消费者（重试退避 + 幂等）；订阅 CRUD（admin + 对外 POST /v1/subscriptions） | 已达成：回调订阅闭环（Subscribe gRPC + 对外 /v1/subscriptions + HMAC 验签 + 幂等推送） |
| **M4 监控与统计** ✅ | 统计报表 + 指标面板 + 告警 | `/admin/tracking/statistics` API；e-cat /metrics + Grafana 面板；失败率/熔断告警规则；`ecat-openapi` 文档 | 已达成：查询量、成功率、耗时、承运商分布可视化；异常可告警 |
| **M5 对外可观测与文档** ✅ | 对外 OpenAPI 文档 + 缓存失效 + 凭证加权路由 | `GET /v1/openapi.json`（公共端点）；webhook 回调触发 Redis 缓存失效；多凭证加权路由；五份对外 SDK（`sdk/`，Python/PHP/Node.js/Go/Rust） | 已达成：OpenAPI 3.0 文档可访问；回调到达缓存即时失效；凭证按权重分发 |
| **M6 对外 SDK** ✅ | 五份零依赖客户端 SDK + 文档 | `sdk/` 目录：Python / PHP / Node.js / Go / Rust 五份零依赖客户端（query_tracking / get_tracking / list_carriers / subscribe）；`sdk/README.md` 统一用法文档 | 已达成：五份 SDK 拷贝即用，各语言初始化与调用示例齐备 |
| **M7 客户端门户** ✅ | 客户端注册/登录 + 应用/套餐/订单 API + 管理端审核 + 网关密钥校验 | `client` / `client_app` / `plan` / `order` 表；`/api/v1/auth/register|login`（client JWT，`token_type=client`，与 admin JWT 隔离）+ `/api/plan`、`/api/app` CRUD、`/api/app/{id}/key` 重置、`/api/app/{id}/order`、`/api/order/{id}/pay`；管理端应用审核；网关按 `api_keys:{sha256(key)}` 校验 X-API-Key | 已达成：注册 → 建应用（X-API-Key 客户端自设 ≥16 位，sha256 落库、明文仅创建时返回一次）→ 选套餐下单 → 支付 → 网关凭密钥放行查询 |
| **M8 支付** ✅ | Stripe / PayPal 双渠道支付 + webhook 验签 + admin 订单管理 | Stripe Checkout + PayPal Orders v2；`PaymentWebhookController` 验签（HMAC-SHA256 / verify-webhook-signature）自动确认订单；`OrderController` 订单管理；支付密钥 `Encryptable` 加密存 `system_config` | 已达成：下单 → 支付 → webhook 验签确认全链路；admin 手动兜底防丢单 |
| **M9 虚拟币** ✅ | 主流虚拟币支付渠道 | USDT TRC20 / BEP20 / ERC20 三链；`CryptoService`（TRC20 经 Tronscan API 自动验证到账，BEP20 / ERC20 人工确认）；订单记录 chain / crypto_amount / tx_id | 已达成：crypto 下单 → 到账验证 → 确认订单闭环 |
| **M10 支付方式配置** ✅ | Stripe 支付方式配置化扩展 | `stripe_payment_methods` 配置（card / apple_pay / google_pay / link / klarna / ideal / bancontact / giropay / sofort / eps / p24 / sepa_debit / acss_debit / afterpay_clearpay），默认 card / apple_pay / google_pay；`PaymentService::stripePaymentMethods` 读取 | 已达成：支付方式经 `system_config` 配置即生效，无需改代码 |
| **M11 安全中间件** ✅ | 网关攻击检测中间件 | `ecat-security` SecurityBodyLayer 集成至 tracking-gateway（HandleErrorLayer 消化 SecurityError）；注入 / 协议 / 数据序列化 / 文件 / 敏感数据泄露攻击检测 | 已达成：攻击载荷在网关层即被拦截并返回安全错误；应用层安全包继续兜底 |
| **M12 CDN 方案** ✅ | Cloudflare 免费版接入方案 + 网关缓存头落地 | 第 8 章完整方案（选型对比、Tunnel 回源、回调子域直连、缓存策略、成本估算、备案路径）；网关公开端点输出 `Cache-Control: public, max-age=N` | 已达成：方案评审通过并落稿；Cache-Control 响应头已合入网关（coder-cdn M1） |
| **M13 CDN 服务商管理** ✅ | CDN 服务商配置管理 | `cdn_provider` 表（access_key / access_secret `Encryptable` 加密存储，domains json）+ `CdnProviderController` CRUD（`/admin/cdn/provider`） | 已达成：服务商 / 域名 / 密钥管理可配置，密钥加密落库 |

**里程碑**：M1 完成（管理面可用）→ **M2 完成（核心查询链路可演示，MVP，2026-08-28）** → **M3 完成（回调闭环）** → **M4 完成（可观测）** → **M5 完成（对外 OpenAPI 文档 + 缓存失效）** → **M6 完成（五份对外 SDK）** → **M7 完成（客户端门户）** → **M8 完成（支付）** → **M9 完成（虚拟币）** → **M10 完成（支付方式配置）** → **M11 完成（网关安全中间件）** → **M12 完成（CDN 方案 + 缓存头落地）** → **M13 完成（CDN 服务商管理）**。

## 8. CDN 接入方案（M12 里程碑）

> 方案来源：architect-cdn 设计（已评审通过）；以下按评审修正落稿：限流按 X-API-Key 计数与 IP 无关；`/v1/carriers` 有鉴权不缓存；回调走仅 DNS 子域直连；网关输出 Cache-Control 优先；CDN 阶段 M2 不设限流修复项。

### 8.1 现状盘点

- 公网入口：`admin/docker-compose.yml` nginx:alpine 反代 PHP webman（80/443）；tracking-gateway 裸监听 `0.0.0.0:8080`（HTTP），经 gRPC 转发 PHP worker 池（127.0.0.1:8792）。
- 对外 API：`/v1/*`，X-API-Key 鉴权 + Redis 缓存（X-Cache: HIT）+ 限流（100 req/60s）+ 按承运商熔断；已有 `ecat-security` SecurityBodyLayer WAF 中间件。
- 回调：`POST /api/callback/{carrier}` + 支付 webhook（Stripe/PayPal/crypto），HMAC 验签。
- 静态资源：admin/public（Flutter Web、apidoc、img）、docs/diagrams SVG；SDK 分发主渠道走 GitHub Releases（已具备）。
- 域名 erik.xyz，服务器海外、未备案（推断）——直接制约国内 CDN 选型（见 8.2 中国场景专项）。

### 8.2 选型对比

| 方案 | 全球覆盖 | 中国访问 | 成本量级 | 免费额度 | 回源支持 |
|---|---|---|---|---|---|
| **Cloudflare 免费版** | 330+ PoP anycast，全球最广 | 无大陆节点（香港/台湾/日本边缘，RTT 约 50-300ms，偶有干扰） | **$0**（合理使用内不限流量） | 免费 TLS/HTTP3/基础 WAF/10 条缓存规则/DDoS 防护 | 强：Tunnel（源站零暴露）、mTLS 回源、自定义回源头 |
| AWS CloudFront | 600+ PoP | 无大陆节点 | 超出后 ~$0.085/GB + $0.01/万请求 | 1TB/月 + 10M 请求/月 | 好；WAF 另购付费 |
| 阿里云 / 腾讯云 CDN | 大陆节点最强 | **大陆最快**，但硬性要求 ICP 备案 + 实名 | 大陆 ~¥0.2/GB 或流量包（¥10/100GB） | 无实质免费 | 好 |
| Gcore | 180+ PoP | 一般 | ~$0.03-0.06/GB | 少量试用 | 好 |
| Azure Front Door / Fastly / Akamai | 全球 | 一般 | 企业级，贵 | Fastly 50GB/月、其余基本无 | 复杂 |

**推荐组合：Cloudflare 免费版（主）+ 未来备案后阿里/腾讯（大陆补充）**。个人项目流量规模（K 级请求/天、GB 级/月）下 Cloudflare 免费版 100% 覆盖且 $0 成本，TLS/WAF/缓存/DDoS 全家桶；备案前国内厂商 CDN 不可用。

**中国场景专项**：
- 大陆 CDN 硬性要求：域名 ICP 备案 + 云厂商实名。海外服务器 + 未备案域名 → 阿里/腾讯 CDN 不可用。
- 未备案前现实路径：大陆用户走 Cloudflare 香港/日本边缘（免费）；大陆流量明显上涨（日均千级 UV+）再走备案（周期 2-4 周），备案后加国内 CDN 做大陆加速、海外保持 Cloudflare。
- 不建议：购买「回国专线/CDN 回国」类服务，贵且合规灰色。

### 8.3 推荐架构

```
                    ┌─ 静态: /public /apidoc /docs /sdk ── 边缘缓存 30d immutable（文件名带版本 hash）
用户 ──→ Cloudflare ─┤
  (DNS 全量橙色代理   ├─ 公开动态: /v1/health /v1/openapi.json ── 边缘缓存 60-300s
   边缘 TLS+WAF      ├─ 鉴权动态: /v1/tracking/query /v1/carriers 等 ── no-store（缓存归网关 Redis）
   +HTTP/2/3)        └─ 管理面: /admin/* ── no-store（JWT 会话）
                          │
              ┌───────────┴───────────┐
    Cloudflare Tunnel（推荐）        仅 DNS 直连子域（回调专用，防 CDN 故障丢单）
    cloudflared 出站，源站零暴露      callback.erik.xyz ──→ /api/callback/* /api/payment/webhook/*
              └───────────┬───────────┘
                  nginx (admin 80/443)      tracking-gateway (:8080 HTTP)
                        └──────── PHP worker 池 (127.0.0.1:8792) ← gRPC ────────┘
```

要点：
- 缓存语义由**网关响应头主导**（补充 3）：公开端点由网关输出 `Cache-Control: public, max-age=N`，优于在 CF 侧逐条配置缓存规则；5xx 一律不缓存。CDN 边缘尊重源站缓存头即可。
- 回调路径（补充 2）：`/api/callback/*`、`/api/payment/webhook/*` 使用**仅 DNS 独立子域**（如 callback.erik.xyz）直连回源，不经过 CDN 代理——CDN 故障不丢支付/承运商回调；DNS 记录不点亮橙色代理。
- API-Key 鉴权端点一律 no-store：相同 URL 不同 X-API-Key 的用户若共享 CDN 缓存条目会数据串号；不要尝试用 Cache Key 加 X-API-Key（维度爆炸、命中率归零）。

### 8.4 缓存策略

| 路径 | 缓存 | TTL | 说明 |
|---|---|---|---|
| /public/* /apidoc/* /docs/* /sdk/* | 是 | 30d immutable | 文件名带版本 hash，新版本即新 URL，免手动 purge |
| /v1/health、/v1/openapi.json | 是 | 60-300s | 仅有的公开可缓存端点 |
| /v1/carriers | 否（no-store） | — | 有鉴权（X-API-Key），不缓存 |
| /v1/tracking/query、/v1/subscriptions* | 否（no-store） | — | 缓存归网关 Redis 层（X-Cache: HIT） |
| /admin/* | 否 | — | JWT 会话 |
| /api/callback/*、/api/payment/webhook/* | 否 | — | 仅 DNS 直连，不经 CDN |
| 全部 5xx | 否 | — | 一律不缓存 |

### 8.5 安全要点

1. **双层 WAF**：Cloudflare 托管规则集挡 DDoS、已知漏洞、常见扫描（网络层 + 签名层）；`ecat-security` SecurityBodyLayer 继续挡应用层自定义规则（URI/header/body 攻击载荷）。两层串行、互补不替代，全部保留；免费版开基础托管规则即可，不叠自定义规则（误杀风险大于收益）。
2. **Tunnel 回源（首选）**：Cloudflare Tunnel（免费）cloudflared 出站连 CF，源站零公网暴露，无 IP 白名单维护成本。备选：防火墙只放行 Cloudflare 回源 IP 段 + 自定义回源共享头 + Authenticated Origin Pulls（mTLS）。源站防火墙关闭公网 80/443/8080 直连（除运维 SSH）。
3. **证书与协议**：边缘证书 CF 全托管自动签发；源站回源模式用 **Full (strict)**（Tunnel 场景默认端到端加密，回源 HTTP-only 则 Full）；HTTP/2/3 + QUIC 免费版边缘自动支持，源站零改造。
4. **防 DNS 直连绕过**：DNS 记录全部走橙色代理；源站公网端口关闭 + Tunnel/回源白名单双保险。
5. **限流不受 CDN 影响（勘误 1）**：网关限流按 X-API-Key 计数（key `api:{api_key}`，100 req/60s），与来源 IP 无关——CDN 边缘 IP 不改变限流语义，**无需为接入 CDN 改限流代码**。「CF-Connecting-IP」仅在需要按真实客户端 IP 区分（统计/日志）时为**可选增强**，且必须与回源白名单绑定（只信任来自 Cloudflare 回源 IP 段的该头），否则伪造头可绕过。
6. **回调链路**：回调经仅 DNS 独立子域直连回源（见 8.3）；Stripe 验签使用 raw body，需确认 nginx 透传原始请求体（不被代理层改写/压缩破坏验签载荷）。

### 8.6 实施阶段（CDN 内部阶段编号 M1-M3）

| 阶段 | 内容 | 完成标志 |
|---|---|---|
| **M1 静态加速 + Tunnel**（1-2 天） | erik.xyz 接入 Cloudflare（DNS 全量橙色代理）→ 免费 TLS 全站 HTTPS → 静态资源 30d immutable 缓存规则 → 源站接入 Tunnel → 网关公开端点输出 `Cache-Control: public, max-age=N`（5xx 不缓存） | 静态资源边缘命中率 >90%；全站 HTTPS；公网直连 8080/443 不可达；现网功能回归通过 |
| **M2 回调子域直连 + 缓存策略精细化**（1-2 天） | `/api/callback/*`、`/api/payment/webhook/*` 迁至仅 DNS 独立子域直连回源；/v1 缓存策略落地（公开端点边缘缓存、鉴权端点 no-store）；确认 Stripe raw body 经 nginx 透传 | 回调经直连子域收单正常（CDN 宕机演练不丢单）；公开端点 X-Cache: HIT；鉴权端点零缓存命中 |
| **M3 国内厂商 + WAF 联动与观测**（按需） | 备案完成后接阿里/腾讯大陆 CDN（海外保持 Cloudflare）；CF 托管 WAF 规则开启与调参；回源/命中率/错误率接入现有 Grafana（仓库已有 grafana-dashboard.json）；发布脚本集成 purge API（新版本上线自动清旧缓存） | 大陆访问 RTT 达标；攻击拦截有日志可查；CDN 指标看板可用；发布流程含自动 purge |

### 8.7 成本估算

| 场景 | 月成本 |
|---|---|
| Cloudflare 免费版（当前规模） | **$0** |
| 静态流量暴涨（>1TB/月，个人项目几乎不可能） | 切 CloudFront 免费 1TB 兜底，超出 ~$85/月量级 |
| 备案后 + 阿里/腾讯大陆 CDN | 大陆流量包 ¥10/100GB 量级，K 级日流量实际 ¥0-1/月 |
| 不接入 CDN | 现状裸奔：无 TLS、无 DDoS 防护、无静态缓存 |

**结论**：现有规模下 Cloudflare 免费版 $0 全覆盖，收益为免费 TLS + 全球加速 + 基础安全；唯一代码动作是网关为公开端点补 `Cache-Control` 响应头（可选增强 CF-Connecting-IP 需配回源白名单）。国内加速是合规（备案）问题而非技术问题。

### 8.8 中国备案路径

1. 大陆 CDN 硬性要求：域名 ICP 备案 + 云厂商实名，二者缺一不可。
2. 前置条件：域名完成实名认证；备案主体需在国内（个人或企业）；当前服务器在海外，备案要求接入商在国内——需将静态资源/站点迁至国内厂商主机或对象存储，或新购国内轻量服务器承接静态加速。
3. 流程：提交备案申请（云厂商控制台）→ 管局审核（周期约 2-4 周）→ 备案号下发后接入阿里/腾讯 CDN，海外保持 Cloudflare。
4. 备案前过渡：大陆用户走 Cloudflare 香港/日本边缘；不建议灰色「回国专线」类服务。

## 9. 风险与简化取舍

| 风险 | 对策 |
|---|---|
| 209 家适配器质量参差、个别上游不稳定 | 熔断 + 超时 + 包内 RetryingClient 重试；M2 兜底：返回结构化 carrier_error，客户端可降级展示 |
| 凭证泄露 | Encryptable 加密落库；凭证只存在于 PHP 侧，内部契约仅传 carrier_code（多凭证场景传 credential_id，e-cat 不持凭证明文） |
| e-cat 与 PHP 耦合（registry/detector 规则版本漂移） | 通过 gRPC `Carriers` 定期同步 registry，e-cat 不硬编码承运商清单 |
| 回调丢失 / 重复推送 | at-least-once + 幂等键 + 指数退避重试 + 手动重推入口（M3 简化实现，不做精确一次） |
| 内部 API 被外部探测 | gRPC 端口仅监听内网 + 共享密钥头（`hash_equals` 校验）+ 拒绝公网来源 IP |

**简化取舍**：缓存 TTL 静态配置（不做事件驱动失效，M4 可加）；detect 端点首版仅服务缺省 carrier_code 场景；回调推送留在 PHP 队列（吞吐不足再迁 e-cat）；多凭证路由策略、灰度、配额细分留到 M4 之后。
