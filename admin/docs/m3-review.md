# M3 代码审查（回调与订阅）

> 状态：**基线预审** — 审查时 M3 diff 尚未落地（coder-php #13 pending、coder-gateway #16 in_progress）。
> 本文档 = 基线发现（M3 必须对齐的既有代码）+ M3 diff 落地后的验收清单。
> 基线审查于 2026-08-28，基于 `main @ 1c28cac`。

## 一、基线发现（M3 必须对齐）

### P1

1. **callback_url 无协议白名单** — `admin/app/admin/controller/CallbackSubscriptionController.php:86,154`
   `'callback_url' => 'required|url|max:500'` 仅格式校验，`ftp://`、`file://` 等可通过。
   且队列消费者将向该 URL POST —— 管理员可控 → 内网/回环 SSRF 表面（管理员凭证泄露即内网穿透）。
   **建议**：store/update 增加 `regex:/^https?:\/\//i`；消费者推送前解析 host，拦截内网/回环 IP（至少 warning 日志，或可配置跳过）。

2. **webman redis-queue 插件未安装** — `admin/config/plugin/webman/` 仅有 console/validation。
   计划文档声称 "写入 webman 队列（app/queue/redis 已有）"，实际 `app/queue/redis/` 只有 scout 搜索消费者。
   **建议**：M3 消费者必须用独立 Process（`admin/app/process/`，注册进 `config/process.php`），
   不得在 webman worker 内自写 `BRPOP` 循环阻塞事件循环。

3. **internal_token 有硬编码默认值** — `admin/config/logistics.php:61`
   `env('INTERNAL_TOKEN', 'lg-internal-8f3a2c9e6b1d4f7a')`，未设置 env 即用代码内默认值。
   **建议**：部署检查强制设置 INTERNAL_TOKEN（生产勿用默认值）。M3 Subscribe handler 复用同一 token。

4. **query_source 尚无 'webhook' 值** — `admin/app/grpc/proto/InternalService.php:338` persist() 仅写 'api'。
   计划文档规定 `query_source(api/admin/webhook)`。M3 webhook 链路落库时需新增 'webhook'。

### P2

5. **secret 加密存储合规** — CallbackSubscription model `secret => Encryptable::cast`（加密落库）。
   注意 `show()`/`index()` toArray 会返回解密明文（管理员可见，属设计决策，可接受）。

6. **HPack.php 724 行超 500 行限制** — `admin/app/grpc/hpack/HPack.php`（基线遗留，M3 新增文件须 <500 行）。

7. **授权范式正确** — `InternalService.php:262-265` `authorized()` 用 `hash_equals`。
   **M3 Subscribe handler 必须复用同一方法**，不得另写 `==` 比较。

8. **路由/前缀/版权合规** — 订阅 CRUD 已在 `/admin` group（AdminAuth+AdminPermission+OperationLog，route.php:117-121）；
   表前缀 `logistics_`（database.php:36）；新文件均含版权头。✓

## 一.5、Rust 侧落地审查（2026-08-28，proto/main.rs 已提交，PHP 侧未落地）

### P1

1. **`valid_callback_url` 显式放行内网/回环地址** — `infrastructure/tracking-gateway/src/main.rs` subscribe()，
   且测试 `callback_url_validation` 断言 `http://127.0.0.1:9999/hook` **合法**。
   商户（API-Key 持有者）可控 callback_url → 推送将打向内网，SSRF 表面。
   **建议**：解析 host 拒绝回环/内网 IP（或配置开关 + warning 日志），更新测试用例。

2. **SubscribeResponse 无 subscription_id / secret 字段** — 商户 POST /v1/subscriptions 后
   拿不到订阅标识与签名密钥，无法验证后续收到的回调 HMAC。
   **建议**：契约补 `subscription_id`（secret 若由系统生成也需返回或说明管理路径）。

3. **Rust 侧 callback_url 无长度上限**（仅非空 + http(s) 前缀）—
   **建议**：与 PHP admin 侧对齐 `max:500`，PHP Subscribe handler 必须兜底长度校验。

### P2

4. **event_type 无白名单**（仅空串默认 tracking.update）— PHP 侧需校验合法值。
5. **main.rs 已 1000+ 行**（基线 755 已超 500 限制，M3 +271）— 建议后续拆分。
6. **diff 混入大量 rustfmt 重排噪音**（单行函数展开为多行）— 非功能问题，但增加 review 成本。

### 合规项（已核 ✓）

- `/v1/subscriptions` 位于 `require_api_key` middleware 层下，与其它 /v1 路由一致鉴权
- proto Subscribe 路径 `/internal.v1.InternalService/Subscribe`、包名 internal.v1 一致

## 一.6、PHP 侧落地审查（2026-08-28，webhook/消费者/Subscribe handler 已提交）

### P1

1. **webhook 签名"存在即校验"可绕过** — `admin/app/admin/controller/CallbackController.php:41-49`
   `isset($payload['sign'])` 才校验；不带 sign 直接放行 → 伪造方可注入假轨迹事件并触发推送商户。
   **建议**：webhook_secret 已配置的承运商必须带签名（缺失即 401），或按承运商配置开关强制。

2. **callback_url 三处校验不一致（均缺协议白名单/长度）** —
   - admin 侧 `CallbackSubscriptionController.php:86` `url|max:500`（基线未修，ftp:// 可通过）
   - Subscribe handler `InternalService.php:224` `FILTER_VALIDATE_URL`（接受 ftp://，无长度上限）
   - Rust 侧 valid_callback_url（http(s) 前缀，无长度，见 §一.5 P1-3）
   **建议**：三侧统一 http/https 白名单 + ≤500。

3. **事件落库无幂等去重** — `CallbackController.php:72`：承运商网络重试重发同一事件 →
   重复落库 + 重复推送商户。TrackingEvent 无唯一键。
   **建议**：加 uk(tracking_no,event_code,event_time) 或 raw_payload sha1 唯一键 + INSERT IGNORE。

4. **手动重推被幂等键阻断** — `TrackingEventPush.php:62` 推送成功后写幂等键；
   `CallbackSubscriptionController.php` retry（route.php:123）重推相同载荷，若键已存在
   （曾成功但商户未收到）→ 跳过，重推失效。
   **建议**：retry 入口先 del 幂等键再入队。

5. **推送 SSRF（风险标注）** — `TrackingEventPush.php:89` post($sub->callback_url) 无内网/回环
   拦截；URL 由管理员/商户配置。**建议**：解析 host 拒绝内网 IP（可配置开关 + warning 日志）。

6. **tracking_query 更新不完整** — `CallbackController.php:104-118`：只写 latest_description/
   raw_status/events，缺标准化 status 键；不更新 query_source='webhook'。
   **建议**：按标准 Tracking 结构补齐 status 与 query_source。

### P2

7. 幂等键 `setnx`+`expire` 两步非原子（改用 `setex`）
8. `post()` 同步 curl 阻塞消费者进程（2 进程、单条 10s 超时；吞吐瓶颈时换异步客户端）
9. 失败日志 `@file_put_contents` 无轮转
10. Subscribe event_type 无白名单
11. event_time 时区未归一化（承运商 ISO8601/UTC 与本地 datetime 混存）
12. webhook 路由无请求体大小/限流
13. 队列 send 无失败确认（丢失静默）

### 闭环确认（先前问题已解决 ✓）

- redis-queue 插件已安装（admin/vendor/webman/redis-queue）且消费者独立进程（RedisQueueProcess count=2，不阻塞 HTTP worker）
- SubscribeResponse 已补 `subscription_id` + `secret` 字段（§一.5 P1-2 已修）
- Subscribe handler 复用 hash_equals 鉴权 + carrier 白名单（InternalService.php:212-249）
- /v1/subscriptions 走 require_api_key（Rust 侧）
- proto PHP/Rust 两侧一致（SubscribeRequest/Response 已生成）
- retry 手动重推路由在 /admin group 内（route.php:123，RBAC 合规）
- 版权头/表前缀（logistics_tracking_event）/行数（各新文件 <500 行）合规

## 一.7、E2E 后状态（2026-08-28，tester-m3 报告后）

**新 P0（运行时，非代码审查可见）**：Encryptable key 未注入 Encrypter——config/encryptable.php:15 有 key
（ENCRYPTABLE_KEY/默认值）但库的 EnvEncryptableConfig 只读 ENCRYPTION_KEY → 任何走
`CallbackSubscription::save()` 的创建（Subscribe RPC + admin CRUD）抛 MissingEncryptionKeyException。
修复：bootstrap 时绑定 config('encryptable') 进 Encrypter，或 .env 设 ENCRYPTION_KEY。

**已修复闭环**（coder-php，E2E 验证通过）：
- P1-2 callback_url 协议白名单 + ≤500 — CallbackSubscriptionController validCallbackUrl（store/update）
- P1-4 手动重推清幂等键 + `$event_id` 参数名 — retry 已通（route.php:123）
- P1-5 SSRF 防护 — TrackingEventPush::isBlockedUrl（gethostbynamel 逐 IP 内网段校验，解析失败拦截；DNS rebinding P2 可留）

**待办（E2E 场景 5 证实）**：
- P1-3 事件落库去重 — 事件表加 uk(tracking_no,event_code,event_time) 或 raw_payload sha1 + INSERT IGNORE（同时解决事件表膨胀）
- SSRF 开关 — CALLBACK_ALLOW_PRIVATE env（企业内网回调误杀）
- SubscribeResponse 补 error_code/error_message（异常可诊断性，P2）

## 二、M3 diff 验收清单（落地后逐项核对）

### 安全

- [ ] **proto 契约**：`SubscribeRequest` 字段（carrier_code/tracking_no/callback_url/…）、包名 `internal.v1`、
      路径 `/internal.v1.InternalService/Subscribe`，PHP 侧（`admin/app/grpc/proto/`）与 Rust 侧
      （`infrastructure/tracking-gateway/proto/internal.proto`）一致；PHP 侧 proto 重新生成
- [ ] **Subscribe handler 鉴权**：复用 `hash_equals` 校验 x-internal-token（与 Query/Detect/Carriers 一致）
- [ ] **callback_url 校验**：协议白名单 http/https + 长度上限（≤500，与 admin 侧一致）
- [ ] **carrier 白名单**：Subscribe 中 carrier_code 必须经注册表校验（参照 `resolveChannel`），防任意 carrier 注入
- [ ] **webhook 端点 `/api/callback/{carrier}`**：无鉴权路由 — 必须显式注册（不经 /admin group）；
      carrier 路径参数必须白名单校验（防 SSRF 到任意解析器 / 伪造 carrier 触发重放）
- [ ] **签名校验**：hash_equals 比较；空签名/缺签名必须拒绝；错误不泄露内部细节
- [ ] **推送 HMAC 签名**：secret 解密后用于签名，不放入载荷；载荷含时间戳防重放（可选）
- [ ] **新管理端路由**（手动重推入口等）走 `/admin` group + 权限种子（`logistics_admin_permission` slug 约定）

### 正确性

- [ ] **幂等**：Redis SETNX 键设计（建议 `push:{subscription_id}:{event_id}`）、TTL（如 24h）、
      成功后才删除；防重复推送（worker 重启/重试不产生重复回调）
- [ ] **重试退避**：失败时正确设置 delay（指数退避 2^n × base，上限封顶）；max_retry 语义明确
      （总尝试次数 vs 重试次数），超限后状态标记（如 status 置 0 或失败记录）
- [ ] **事件落库** `logistics_tracking_event`：event_time 时区与 `standardize()` 的 `format('c')` 一致；
      raw_payload 保留原文（不截断/不二次编码丢失）
- [ ] **tracking_query 更新**：最新事件按 event_time 排序取最新一条，更新 status/latest_description；
      query_source='webhook'
- [ ] **落库失败不影响响应**：webhook 收包先 200 再异步处理，或落库失败返回 500 让承运商重发（需明确策略）

### 性能

- [ ] **消费者不阻塞事件循环**：独立 Process（`config/process.php` 注册），非 worker 内 brpop 循环
- [ ] **异步 HTTP 推送**：workerman/http-client 或 async-tcp-connection（同步 Guzzle 会阻塞）
- [ ] **批量订阅遍历**：一个事件对 N 个订阅的推送用并发（如 Async::all），串行推 N 个 URL 会拖垮吞吐

### 规范

- [ ] 新 .php/.rs/.proto 文件含版权头 `Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz`
- [ ] 文件 <500 行；表前缀 logistics_；不用全局函数前缀 `\`
- [ ] `logistics_tracking_event` 迁移文件（表前缀/索引 per 计划文档 §4）

## 遗留 P2 状态（2026-08-28 核对）

- [x] **SubscribeResponse 补 error_code/error_message** — proto 字段 5/6 + protoc 重生成 PHP 类与
      GPBMetadata + Rust `worker_error` 并入 JSON（query/detect/subscribe 生效，carriers 无错误字段）
- [x] **DNS rebinding** — `TrackingEventPush::post()` 解析一次，同一组 IP 做 `hasBlockedIp` 拦截校验 +
      `CURLOPT_RESOLVE` 固定连接目标，杜绝检查与连接之间换址（reflection 校验 7/7）
- [x] **install.sql 同步** — M1 四表（carrier/carrier_credential/tracking_query/callback_subscription）
      DDL + 三组权限种子（19 条）+ super_admin 关联，scratch 库执行验证通过
- [ ] **captcha Imagick 环境依赖** — `captcha_create()` 依赖 php-imagick 扩展；属系统环境依赖，
      agent 无法修复。当前开发机已装 imagick+gd（`php -m` 确认）；生产部署时须在 Dockerfile/安装清单
      显式安装 `php-imagick`，否则验证码接口 500（代码回退 GD 作为替代方案，M4 后可议）
