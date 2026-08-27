# 物流聚合平台实施规划

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
                │ 内部契约（仅内网，共享密钥头）        │ GET /internal/carriers
                │ POST /internal/tracking/query   │（registry 同步，e-cat 缓存）
                ▼                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│            admin — PHP webman worker 池（无状态，可水平扩展）           │
│  internal 控制器 → GlobalLogistics\Logistics 门面                    │
│  domestic($code)/international($code)->queryTrack($no)              │
│  凭证从 erik_carrier_credential 读取（Encryptable 加密存储）           │
│  查询结果落 erik_tracking_query · 标准化 JSON 返回                    │
└───────┬──────────────────────────────────────┬──────────────────────┘
        │ Guzzle（RetryingClient，包内自带重试）   │ 承运商 webhook 回调
        ▼                                        ▼
┌───────────────┐                      ┌──────────────────────────────┐
│  209 家承运商  │                      │ /api/callback/{carrier}       │
│  上游 API      │                      │ 白名单 + 签名校验 → 落库        │
│  (SF/ZTO/...  │                      │ erik_tracking_event →         │
│   45国内+164国际)│                      │ 更新查询记录 → webman 队列异步   │
└───────────────┘                      │ 推送商户回调 URL（重试退避）     │
                                       └──────────────────────────────┘

管理面（浏览器）→ /admin/*（JWT + AdminPermission RBAC + OperationLog）
  carrier / carrier-credential / tracking-query / callback-subscription / statistics
```

## 2. 职责划分

| 面 | 载体 | 职责 |
|---|---|---|
| 管理面 | admin（PHP webman） | 承运商档案 CRUD、密钥管理（加密存储）、查询记录、统计报表、回调订阅配置、RBAC 权限（沿用 `erik_admin_permission` slug 约定与 `AdminPermission` 中间件） |
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
  → API-Key 鉴权（M2 首版：Header `X-API-Key`，key 存 erik_system_config 或独立表）
  → RateLimitLayer（Redis，按 key + 窗口）
  → 缓存查找 cache:track:{carrier}:{no}（TTL 按承运商 cache_ttl 配置，5~60min）
    → 命中：直接返回（附带 X-Cache: HIT）
  → 未命中：
    → ecat-circuit-breaker 检查（按 carrier 维度；OPEN 则 503 快速失败，半开后放行探针）
    → ecat-client RoundRobin 从 worker 池选一个 → POST /internal/tracking/query
    → PHP worker：读凭证 → Logistics::domestic/international($code)->queryTrack($no)
      （包内 RetryingClient 自带 2 次重试）
    → 落库 erik_tracking_query → 返回标准化 JSON → e-cat 写缓存 → 响应
```

- `carrier_code` 缺省时：e-cat 先查 `cache:detect:{no}`（短 TTL 5min），未命中调 `POST /internal/tracking/detect`（复用包内 Detector）。
- 上游承运商失败：PHP 返回结构化错误码 → e-cat 熔断计数 → 客户端收到统一错误结构（`code/carrier_error`），不泄露内部细节。

### 3.2 回调链路（异步，M3）

```
承运商 webhook → admin /api/callback/{carrier}（无 RBAC，白名单路由 + 签名校验）
  → 解析载荷（复用包内订阅/解析能力或按 carrier 适配）
  → 落库 erik_tracking_event + 更新 erik_tracking_query 最新状态
  → 写入 webman 队列（app/queue/redis 已有）
  → 异步消费者：按 erik_callback_subscription 推送到商户回调 URL
    （HMAC 签名 + 幂等键 + 指数退避重试 + 手动重推入口）
```

> ponytail: 首版回调推送放 PHP 队列而非 e-cat —— 事件解析与数据都在 PHP 侧，跨语言传事件无收益；若推送吞吐成为瓶颈（万级/分钟以上），再把消费者迁到 e-cat（ecat-mq + retry 中间件），外部契约不变。

## 4. 数据库设计（新增，遵循 erik_ 前缀 / snowflake 主键 / created_at+updated_at 规范）

| 表 | 字段要点 | 说明 |
|---|---|---|
| `erik_carrier` | id, code(uk), name, channel(domestic/international), country, logo, status, timeout_ms, cache_ttl, sort, remark | 承运商档案；channel 对应包内 `Channel::Domestic/International` |
| `erik_carrier_credential` | id, carrier_id(idx), name, app_key, app_secret, extra(json), status | 密钥加密存储（Encryptable cast，同 AdminUser.phone 模式）；extra 存各家私有参数（customer/company/端等） |
| `erik_tracking_query` | id, query_no(uk), carrier_id, carrier_code(idx), tracking_no(idx), credential_id, status(success/fail), result(json), raw_response(text), query_source(api/admin/webhook), cost_ms, error_code, error_message, created_at(idx) | 查询记录，高频写；result 存标准化 Tracking（含 events） |
| `erik_callback_subscription` | id, carrier_id(idx), callback_url, secret, event_type, status, max_retry, last_push_at, last_success_at | 回调订阅配置 |
| `erik_tracking_event`（M3） | id, tracking_no(idx), carrier_code(idx), event_code, event_desc, location, event_time, raw_payload, created_at(idx) | 事件明细（回调落库） |

RBAC 权限种子（沿用 slug 约定，插入 `erik_admin_permission`）：
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

统一响应：`{code: 0, message: "ok", data: {...}}`；错误 `{code: 4xx/5xx 段, message}`（限流 429、熔断 503、承运商错误 carrier_error）。

### 5.2 内部（e-cat → PHP worker，仅内网，共享密钥头 `X-Internal-Token`）

| 方法 | 路径 | 说明 |
|---|---|---|
| POST | `/internal/tracking/query` | `{carrier_code, tracking_no, credential_id?}` → 标准化 Tracking JSON 或错误码 |
| POST | `/internal/tracking/detect` | `{tracking_no}` → `{carrier_code, channel, confidence}`（复用 Detector） |
| GET | `/internal/carriers` | 承运商注册表（包内 `Resources/carrier-registry.php`），e-cat 缓存 10min 供 /v1/carriers |
| POST | `/internal/subscriptions`（M3） | 创建订阅（写 erik_callback_subscription） |

内部路由挂在 `/internal` 前缀，中间件：`AdminAuth` 之外的独立 `InternalAuth`（校验共享密钥 + 仅内网来源），**不落 OperationLog、不走 RBAC**。

### 5.3 管理端（admin，走现有 RBAC 中间件链）

- `Route::resource('/carrier', CarrierController::class)` + `Route::resource('/carrier/credential', CarrierCredentialController::class)`
- `Route::get('/tracking/query', TrackingQueryController@index)`（分页 + carrier_code/tracking_no/status/时间筛选）
- `Route::resource('/callback/subscription', CallbackSubscriptionController::class)`
- `Route::get('/tracking/statistics', StatisticsController@index)`（M4：按日/承运商聚合查询量、成功率、平均耗时）

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
| `ecat-openapi`（M4） | 对外 API 文档 |
| 生态依赖 | tokio、axum、serde/serde_json、reqwest（转发 worker）、tower、thiserror、tracing |

## 7. 实施阶段与里程碑

| 阶段 | 内容 | 产出物 | 完成标志 |
|---|---|---|---|
| **M1 管理后台扩展** | 表 + 模型 + 控制器 + 路由 + RBAC 权限种子 + 凭证加密 | install.sql 增量 DDL 与权限种子；模型 Carrier/CarrierCredential/TrackingQuery/CallbackSubscription；控制器与路由注册；`composer require erikwang2013/global-logistics` 并配置 | 后台可对承运商/密钥/订阅 CRUD，权限按 slug 生效，admin 全量回归通过 |
| **M2 查询网关** | workspace 新 crate `tracking-gateway` + PHP internal 端点 + 缓存 + LB + 限流 + 熔断 + 对外 API | `infrastructure/tracking-gateway/`（Cargo.toml 加入 workspace members）；`app/controllers/internal/`（InternalAuth 中间件 + query/detect/carriers 端点）；e-cat 对外 /v1 三接口；/metrics；docker-compose 串联 | 端到端：客户端 → e-cat → worker → 承运商 → 缓存命中/限流/熔断均按预期工作；`cargo build` + admin 测试通过 |
| **M3 回调与订阅** | webhook 接收 + 事件落库 + 异步推送 + 订阅管理 | erik_tracking_event 表；`/api/callback/{carrier}` 白名单路由 + 签名校验；webman 队列消费者（重试退避 + 幂等）；订阅 CRUD（admin + 对外 POST /v1/subscriptions） | 承运商回调可触发商户 URL 推送，失败重试，重复回调幂等 |
| **M4 监控与统计** | 统计报表 + 指标面板 + 告警 | `/admin/tracking/statistics` API；e-cat /metrics + Grafana 面板；失败率/熔断告警规则；`ecat-openapi` 文档 | 查询量、成功率、耗时、承运商分布可视化；异常可告警 |

**里程碑**：M1 完成（管理面可用）→ M2 完成（核心查询链路可演示，MVP）→ M3（回调闭环）→ M4（可观测）。

## 8. 风险与简化取舍

| 风险 | 对策 |
|---|---|
| 209 家适配器质量参差、个别上游不稳定 | 熔断 + 超时 + 包内 RetryingClient 重试；M2 兜底：返回结构化 carrier_error，客户端可降级展示 |
| 凭证泄露 | Encryptable 加密落库；凭证只存在于 PHP 侧，内部契约仅传 carrier_code（多凭证场景传 credential_id，e-cat 不持凭证明文） |
| e-cat 与 PHP 耦合（registry/detector 规则版本漂移） | 通过 `/internal/carriers` 定期同步 registry，e-cat 不硬编码承运商清单 |
| 回调丢失 / 重复推送 | at-least-once + 幂等键 + 指数退避重试 + 手动重推入口（M3 简化实现，不做精确一次） |
| 内部 API 被外部探测 | `/internal` 仅监听内网 + 共享密钥头 + 拒绝公网来源 IP |

**简化取舍**：缓存 TTL 静态配置（不做事件驱动失效，M4 可加）；detect 端点首版仅服务缺省 carrier_code 场景；回调推送留在 PHP 队列（吞吐不足再迁 e-cat）；多凭证路由策略、灰度、配额细分留到 M4 之后。
