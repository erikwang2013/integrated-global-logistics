# 安全架构设计文档

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 纵深防御全景

系统采用 7 层纵深防御模型，从外到内层层过滤恶意请求，确保任意单层失效时仍有后续防线兜底。

整个中间件链按以下顺序执行（见 `config/middleware.php`）：

```
请求 → Cors → Locale(Accept-Language) → SecurityMiddleware (erikwang2013/security-php, 31种检测器) → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| 层 | 中间件/机制 | 防护目标 |
|----|--------|---------|
| 1 | SecurityMiddleware (erikwang2013/security-php) | 31 种攻击检测 + HTTP 方法校验 + 请求体大小限制 + Content-Type 校验 + CSRF + IP 攻击升级黑名单 |
| 2 | Cors | 跨域安全 + 响应安全头注入 |
| 3 | RateLimit | Redis 滑动窗口限流，防暴力破解 |
| 4 | AdminAuth | JWT 认证 + 黑名单登出 |
| 5 | AdminPermission | RBAC method.path 粒度鉴权 |
| 6 | OperationLog | 操作审计 + 来源端追踪 |
| 7 | 数据加密 | Hashids ID 混淆 + Encryptable DB 加密 + EncryptionService 传输加密 |

管理端 UI（Layui）另有独立的输入校验，后端不做信任，每一层独立防御。

---

## 2. 攻击检测引擎 (erikwang2013/security-php)

攻击检测已从自研 SecurityMiddleware (erikwang2013/security-php) 迁移至 `erikwang2013/security-php` v1.1+ 专用安全包，提供 **31 种检测器**，覆盖 5 大攻击类别。

### 2.1 检测器分类

**注入攻击 (11种):** XSS、SQL注入、命令注入、NoSQL注入、LDAP注入、XPath注入、JNDI/Log4Shell、SSI服务端包含、GraphQL注入、SSTI模板注入

**协议与请求攻击 (9种):** SSRF、XXE、HTTP响应头注入、Host头攻击、Request Smuggling、Open Redirect、CORS绕过、WebSocket劫持、DNS Rebinding

**HTTP协议层校验 (6种):** HTTP方法校验(405)、请求体大小限制(413)、Content-Type校验(415)、CSRF Origin检查、IP攻击升级黑名单、敏感数据泄露检测

**数据与序列化攻击 (5种):** PHP反序列化、CSV公式注入、邮件头注入、JWT攻击（结构化分析）、JS Prototype Pollution

**文件与路径攻击 (2种):** 路径遍历、恶意文件上传

### 2.2 处理模式

每个检测器独立支持两种模式：`block` 拦截（返回配置状态码）/ `log` 仅记录（`header_injection`、`ssti`、`nosql_injection` 默认 log 防误报）。

### 2.3 IP 攻击升级黑名单

同一 IP 在 60 秒内触发 5 次攻击检测 → 自动封禁 15 分钟。存储后端可选 Redis（分布式）、File（单机JSON）或 Cache（高并发独立文件），当前配置为 Redis 存储。

### 2.4 安全日志

文件位置：`runtime/logs/security.log`（自动轮转，10MB/文件）

---

## 4. 响应安全头

所有头在 `Cors` 中间件中注入，通过 `$response->withHeaders()` 追加到每个响应。

| 头 | 值 | 作用 |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | 允许任意源跨域（内网管理后台场景） |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | 允许的方法集合 |
| Access-Control-Allow-Headers | `Authorization,Content-Type` | 允许的自定义头 |
| Access-Control-Max-Age | `86400` | 预检请求缓存 24 小时 |
| X-Content-Type-Options | `nosniff` | 禁止浏览器 MIME 嗅探 |
| X-Frame-Options | `DENY` | 禁止所有 iframe 嵌入，防点击劫持 |
| X-XSS-Protection | `1; mode=block` | 启用浏览器内置 XSS 过滤器并拦截页面渲染 |
| Referrer-Policy | `strict-origin-when-cross-origin` | 同源发完整 URL，跨域仅发域名 |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | 全站禁用摄像头/麦克风/定位 API |

OPTIONS 预检请求直接返回 204 空响应，不进入后续中间件链。

### 4.2 Content-Security-Policy (CSP)

与其他安全头一起在 Cors 中间件中注入，提供深度防御，限制浏览器可加载和执行的资源来源。

| 头 | 值 | 作用 |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | 限制脚本/样式/图片/连接/框架/表单等资源来源 |
| X-Permitted-Cross-Domain-Policies | `none` | 禁止 Adobe Flash/PDF 等跨域策略文件加载 |

CSP 策略要点：
- `default-src 'self'`：默认仅允许同源资源
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`：允许同源脚本 + 内联脚本（Layui 管理端必需）
- `frame-ancestors 'none'`：禁止被任何页面 iframe 嵌入，与 X-Frame-Options: DENY 双保险
- `base-uri 'self'`：限制 `<base>` 标签只能指向同源
- `form-action 'self'`：限制表单只能提交到同源

---

## 5. 限流策略

### 算法

Redis Sorted Set 滑动窗口 + Lua 原子化脚本，关键操作：

```lua
-- KEYS[1]=限流键；ARGV[1..5]=windowStart, limit, now, member, ttl
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, ARGV[1])
local count = redis.call('ZCARD', KEYS[1])
if count >= tonumber(ARGV[2]) then return {0, count} end
redis.call('ZADD', KEYS[1], ARGV[3], ARGV[4])
redis.call('EXPIRE', KEYS[1], ARGV[5])
return {1, count + 1}
```

Lua 脚本在 Redis 服务端单线程执行，**天然原子化**，消除 TOCTOU（Time-of-check to Time-of-use）竞态条件。

> v1.2.0 修复了 phpredis `eval` 参数签名（`eval(script, args, num_keys)`）——此前参数顺序错误导致限流实际未生效；现在 `RateLimit::buildEvalArgs()` 显式返回 `[args, 1]` 并有测试覆盖。Redis 不可用时仍 fail-open。

### 限流配置

| 路由 | 限制 | 窗口 | 场景 |
|------|------|------|------|
| 默认（所有路由） | 60 次/分钟 | 60s | 通用 API |
| `/api/v1/auth/login` | 10 次/分钟 | 60s | 登录（防暴力破解） |
| `/api/v1/auth/register` | 5 次/分钟 | 60s | 注册（防批量注册） |

### 响应头

触发限流时返回 HTTP 429 及 JSON body：
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

所有响应（含正常响应）携带以下头：

| 头 | 说明 |
|----|------|
| X-RateLimit-Limit | 当前窗口允许的最大请求数 |
| X-RateLimit-Remaining | 当前窗口剩余可用请求数 |
| X-RateLimit-Reset | 窗口重置的 Unix 时间戳 |
| Retry-After | 仅限流时携带，建议等待秒数 |

### 降级策略

Redis 异常（连接超时、不可用等）时 **fail-open**：

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis down, 放行所有请求
}
```

宁可短时间丧失限流保护，也不阻断正常业务请求。

### 5.4 账号锁定机制

登录接口在速率限制的基础上，额外增加了**账号锁定**机制，防止针对特定用户的定向暴力破解。

**锁定流程**（按用户名 + IP，避免锁死其他来源的正常用户）：

```
登录失败 → Redis INCR login_fail:{username}:{ip} TTL=900s
连续 5 次失败 → Redis SETEX account_lock:{username}:{ip} 900 1
            → 返回 429 "账号已被锁定，请15分钟后再试"
            → 清除计数器 DEL login_fail:{username}:{ip}
```

**锁定期间行为**：

锁定期间所有登录请求直接返回 429，不进行密码校验，完全阻止暴力破解尝试。

**配置常量**：

| 常量 | 值 | 含义 |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | 最大连续失败次数 |
| LOCKOUT_DURATION | 900 | 锁定持续时间（秒），即 15 分钟 |

注意：账号锁定键为 `用户名 + IP`，同一来源对同一用户名的定向爆破会被锁定，也不会因其他来源的失败而锁死正常账号。与 IP 限流（10次/分钟）叠加形成双重防护：
- IP 层面：10 次/分钟限流阻止高频尝试
- 账号层面：同一用户名 + IP 连续 5 次失败锁定 15 分钟，阻止定向暴力破解

---

## 6. 认证与鉴权

### 6.1 JWT 认证

AdminAuth 中间件实现，挂载在需要认证的路由组上。

**参数配置**（`config/plugin/erikwang2013/jwt/jwt`，由 `.env` 注入）：

| 参数 | 值 | 说明 |
|------|-----|------|
| 算法 | HS256 | HMAC-SHA256 对称签名 |
| 密钥 | `JWT_SECRET_KEY` | 环境变量注入，无代码默认值；安装器生成，缺失或旧公开默认值拒绝启动 |
| access_token TTL | 7200s (2h) | `JWT_DEFAULT_EXPIRE` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_EXPIRE` |
| 签发者 | `open-admin` | `JWT_ISSUER` |
| 受众 | `open-admin` | `JWT_AUDIENCE` |

**Token 提取**：从 `Authorization: Bearer <token>` 头提取，strip `Bearer ` 前缀得到原始 JWT。

**认证流程**：
1. 空 token → 直接 401 `{"code": 401, "message": "未登录"}`
2. 检查 Redis 黑名单 `jwt_blacklist:{md5(token)}` → 命中 → 401 `Token已失效，请重新登录`
3. JWT decode → 失败（过期/签名不匹配） → 401 `Token已过期或无效`
4. 校验 `token_type`：AdminAuth 仅接受 `access`（缺失视为旧版 access，兼容过渡）；`refresh` / `client` 一律 401
5. 查询管理员状态：不存在或 `status != 1` → 401 `账号不存在或已被禁用`（禁用立即失效）
6. 成功 → 注入 `$request->adminId` 和 `$request->adminUsername`

**刷新与轮换**：`/api/v1/auth/refresh` 只接受 `token_type=refresh`。旧 refresh 通过 Redis `SET NX` 原子占用，并发重放只有一个请求成功；成功刷新后旧 refresh 写入黑名单（TTL 为剩余有效期），同时返回新的 access + refresh。access token 不能用于刷新，client token 与 admin token 互不通用。

**黑名单机制**：用户登出时，将 `md5(token)` 写入 Redis，TTL 设为 JWT 剩余有效期。Redis 故障时黑名单检查被跳过（fail-open），此时已登出的 Token 仍可短期使用，但 JWT 本身的短期有效期（2h）作为兜底保护；禁用账号的校验直接查库，不依赖 Redis。

### 6.2 并发会话限制

为防止 Token 泄露后被多设备滥用，系统限制同一用户同时持有的有效 Token 数量。

**限制逻辑**：

```
登录成功 → 签发新 Token
         → 清理已过期成员: Redis ZREMRANGEBYSCORE user_tokens:{userId} 0 now
         → 将新 Token 写入 ZSET: Redis ZADD user_tokens:{userId} <exp> <md5(token)>
         → 若 ZCARD > 3（MAX_CONCURRENT_SESSIONS）:
            → 取 score 最小（最早过期）的成员，ZREM 并从集合删除
            → Redis SETEX jwt_blacklist:{md5(oldest_token)} <remaining_ttl> 1
```

**配置常量**：

| 常量 | 值 | 含义 |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | 同一用户最大并发 Token 数 |

**被挤下线场景**：当用户在第 4 台设备登录时，第 1 台设备的 Token 被强制加入黑名单，后续请求返回 401 "Token已失效，请重新登录"。

登出时当前 Token 从 ZSET 移除；Token 自然过期后，成员在下一次登录的过期清理中被移除。

### 6.3 RBAC 权限模型

AdminPermission 中间件实现。

**数据模型**：User -> Role -> Permission 三层关联

- `logistics_admin_user` (用户表)
- `logistics_admin_user_role` (用户-角色关联表)
- `logistics_admin_role` (角色表)
- `logistics_admin_role_permission` (角色-权限关联表)
- `logistics_admin_permission` (权限表)

**权限类型**：
| type | 含义 | 示例 |
|------|------|------|
| 1 | 菜单权限 | 控制左侧导航可见性 |
| 2 | 按钮权限 | 控制页面内操作按钮 (新增/编辑/删除) |
| 3 | API 权限 | 控制后端接口调用 |

API 权限标识格式：`{method}.{path}`

例如：
- `post.admin/user` — 创建用户
- `put.admin/user` — 编辑用户
- `delete.admin/user` — 删除用户
- `get.admin/user` — 查看用户列表

**鉴权流程**：
1. `$request->adminId` 为空 → 401 `未登录`（不允许绕过认证直接进入管理路由）
2. 获取用户 → 角色（跳过 `status=0` 的禁用角色）→ 权限列表
3. 超级管理员（`slug = '*'`）→ 直接放行
4. 构造 `strtolower(method) . '.' . trim(path, '/')` → 对比权限列表
5. 匹配失败 → 403 `{"code": 403, "message": "无权限访问"}`

**二次确认**：BaseController 提供 `confirmPassword()` 方法，敏感操作（删除用户、数据导出等）在 Controller 层额外要求输入当前密码，防止会话劫持后被未授权操作。

---

## 7. 审计日志

### 7.1 操作日志

OperationLog 中间件对 POST / PUT / DELETE 请求自动记录操作日志。GET 请求不记录。

**记录字段**：

| 字段 | 来源 | 说明 |
|------|------|------|
| id | SnowflakeService::generate() | 全局唯一 ID |
| user_id | `$request->adminId` | 操作者 ID，未登录为 0 |
| action | `$request->method()` | 等同于 method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | 请求路径 |
| ip | `$request->getRealIp()` | 客户端真实 IP |
| source | detectSource() | 客户端来源平台 |
| input | 请求 body（脱敏后 JSON） | 操作提交数据 |
| created_at | `date('Y-m-d H:i:s')` | 操作时间 |

**敏感字段过滤**：递归遍历请求体，以下字段的值替换为 `***`：

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**来源端检测**（`detectSource()`）：按优先级：

1. 优先读取 `X-Client-Platform` 自定义头（原生客户端显式声明）
2. 降级到 User-Agent 字符串推断（`detectSource()` 方法检测顺序）：

| 平台 | UA 关键字 |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| Web | 兜底默认值 |

**容错**：日志写入异常不阻塞业务请求（`catch (\Throwable)` 静默吞掉）。

### 7.2 安全日志

**文件位置**：`runtime/logs/security.log`；记录攻击拦截日志（攻击类别、IP、路径、字段、来源、payload 前 200 字符）与 IP 封禁通知（被封 IP、触发次数）。日志权限为 `FILE_APPEND | LOCK_EX`，确保并发安全写入。

---

## 8. 数据保护

系统采用三层数据保护策略，对应数据流转的三个阶段。

### 8.1 传输层 — EncryptionService

`EncryptionService` 使用 `erikwang2013/encryption` 包，对 API 请求/响应中的敏感字段进行加解密。

**技术细节**：
- 算法：`aes-256-cbc-hmac`（自带 HMAC 签名防篡改）
- 密钥：`ENCRYPTION_KEY` 环境变量，自动对齐到 32 字节
- 用于：客户端与 API 之间传输手机号、身份证号等字段

**脱敏工具方法**：
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com`（用户名超 2 字符）或 `a**@example.com`

### 8.2 存储层 — Encryptable Cast

`AdminUser` 模型使用 `Erikwang2013\Encryptable\Encryptable` Eloquent cast，对应字段：

- `email` → cast 为 Encryptable，自动加解密
- `phone` → cast 为 Encryptable，自动加解密  
- `id_card` → cast 为 Encryptable，自动加解密

写入数据库时自动加密为密文，读取时自动解密为明文。数据库存储列类型为 `VARCHAR(500)`，密文以 base64 形式存储。

**密钥体系**：与传输层加密（`ENCRYPTION_KEY`）独立使用 `ENCRYPTABLE_KEY`，一个密钥泄露不会导致另一层失效。

密钥轮换：`ENCRYPTION_PREVIOUS_KEYS` 环境变量支持历史密钥列表（逗号分隔），读取旧数据时尝试历史密钥解密，写回时使用当前密钥重新加密。

### 8.3 展示层 — ID 混淆与脱敏

**Hashids ID 混淆**：`HashidsService` 使用 `erikwang2013/hashids` 包。

- 对外 API 返回的数据库 BIGINT ID 编码为 hash 字符串（如 `xK3mN9qR2pL7wV8b`）
- 客户端请求时传入 hash 字符串，后台自动解码为原始 ID
- 当前 `config/hashids.php` 的 `main` 连接盐值为空、最小长度为 0；Hashids 只增加枚举成本，**不是安全边界**，越权访问由 JWT + RBAC 拦截
- 盐值可按环境在 `config/hashids.php` 配置；修改盐值会使已发出的 hashid 链接失效
- BaseController 提供 `encodeId()`, `decodeId()`, `encodeIds()` 便捷方法

**导出脱敏**：Excel/PDF 导出时（ExportController），敏感字段统一脱敏：
- 手机号：`138****1234`
- 邮箱：`a***@example.com`
- 身份证：完全遮盖为 `********`

---

## 9. 密钥管理

所有密钥通过 `.env` 环境变量注入；JWT / 数据库加密 / 传输加密 / 内部令牌不再有代码默认值。安装器自动生成随机密钥并写入 `.env`（Docker 同步 `.env.docker`）。

`support/bootstrap/Secrets.php` 在进程启动时校验：已安装环境（存在 `runtime/install.lock`）缺失 `JWT_SECRET_KEY` / `ENCRYPTABLE_KEY` / `ENCRYPTION_KEY`，或仍等于旧版公开默认值时拒绝启动；`APP_DEBUG=true` 时仅记录告警。`INTERNAL_TOKEN` 不在该处校验：PHP 侧为空时内部接口 fail-closed，网关侧为空时拒绝启动。开发环境可临时开启调试，生产环境不得依赖此降级。

| 环境变量 | 用途 | 生成/格式 | 生产要求 |
|----------|------|-----------|---------|
| `JWT_SECRET_KEY` | JWT 签名密钥 | 安装器生成；`openssl rand -hex 32` | 必填，无默认值 |
| `JWT_ALGORITHM` | JWT 签名算法 | `HS256` | 保持 HS256 |
| `ENCRYPTION_KEY` | API 传输层加密密钥 | 32 字符随机串；`openssl rand -hex 16` | 必填，与存储密钥不同 |
| `ENCRYPTABLE_KEY` | DB 存储层加密密钥 | 32 字符随机串；`openssl rand -hex 16` | 必填，与传输密钥不同 |
| `INTERNAL_TOKEN` | 网关 → PHP worker 共享密钥 | 安装器生成随机串 | 必填；为空时内部 gRPC fail-closed |
| `ENCRYPTION_PREVIOUS_KEYS` | 历史加密密钥列表 | 逗号分隔 | 仅迁移旧数据时临时配置 |
| `SNOWFLAKE_DATACENTER_ID` / `SNOWFLAKE_WORKER_ID` | Snowflake 节点 ID (0-31) | 默认由 PID 派生 | 跨主机部署必须显式分配 |
| `HASHIDS_SALT` | ID 编码盐值（`.env` 模板中存在，当前 `config/hashids.php` 未读取） | 盐值为空 | 仅混淆，不是安全边界 |

**安全要求**：
- `.env` / `.env.docker` 已加入 `.gitignore`，严禁提交到版本库
- `.env.example` 是公开模板文件，不包含真实密钥；其中的旧公开默认值不可用于生产
- 已安装环境缺失密钥或仍使用旧公开默认值将拒绝启动，不再“自动兜底”
- 手工生成：JWT / 内部令牌用 `openssl rand -hex 32`；`ENCRYPTABLE_KEY` / `ENCRYPTION_KEY` 需 32 字符，用 `openssl rand -hex 16`
- 轮换加密密钥：新密钥写入 `ENCRYPTABLE_KEY`，旧密钥加入 `ENCRYPTION_PREVIOUS_KEYS`，读取旧数据后触发重加密，完成后再移除旧密钥

### 密钥存储隔离

| 层 | 配置键 | 密钥环境变量 |
|----|--------|-------------|
| 传输加密 | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| 存储加密 | `config/encryptable.php` / 插件 `previous_keys` | `ENCRYPTABLE_KEY` / `ENCRYPTION_PREVIOUS_KEYS` |
| ID 混淆 | `config/hashids.php` → `connections.main.salt` | 当前默认空；非安全边界 |
| JWT 签名 | `config/plugin/erikwang2013/jwt/jwt.php` | `JWT_SECRET_KEY` |
| 内部 gRPC | `config/logistics.php` → `internal_token` | `INTERNAL_TOKEN` |

---

## 10. security.txt (RFC 9116)

系统在 `/.well-known/security.txt` 提供符合 RFC 9116 标准的安全联系信息端点，方便安全研究人员快速找到报告渠道：

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

字段说明：Contact 为漏洞报告邮箱；Expires 为过期时间（需定期更新）；Preferred-Languages 为沟通语言；Canonical 为规范 URL；Policy 为披露政策链接。该端点不受限流、认证等中间件限制，任何人都可直接访问。

---

## 11. Nginx 安全配置

项目提供 `docs/nginx-security.conf`（裸机参考）与 `docs/nginx-docker.conf`（Docker 编排实际挂载）两份 Nginx 配置。

**包含的安全措施**：

| 配置项 | 作用 |
|--------|------|
| `server_tokens off` | 隐藏 Nginx 版本号 |
| `client_max_body_size 10m` | 限制请求体大小，与 SecurityMiddleware (erikwang2013/security-php) 协同 |
| `limit_req_zone` | Nginx 层面的请求频率限制 |
| `limit_conn_zone` | 并发连接数限制 |
| `add_header` 安全头 | 在 Nginx 层面追加 X-XSS-Protection 等安全头 |
| `if ($request_method)` | Nginx 层面拒绝非标准 HTTP 方法 |
| `proxy_set_header X-Forwarded-For $remote_addr` | **覆盖**而非追加客户端自带 XFF，防止伪造来源 IP 绕过限流 |
| SSL/TLS 配置 | 现代 TLS 1.2/1.3 配置，禁用弱加密套件 |
| 隐藏后端头 | `proxy_hide_header` 移除 webman 版本等敏感头 |
| 数据服务端口 | MySQL / Redis 不发布宿主端口，仅容器网络可达 |

**使用方式**：Docker 部署直接挂载 `docs/nginx-docker.conf`；裸机部署将 `docs/nginx-security.conf` 合并到 Nginx server 块中，根据实际域名和证书路径调整。

---

## 12. 威胁模型

### 12.1 已防护威胁

| 威胁类型 | 攻击向量 | 防御层次 |
|----------|---------|---------|
| HTTP 方法滥用 | TRACE/TRACK XST 攻击、CONNECT 隧道代理、WebDAV 方法探测 | SecurityMiddleware http_method 检测器 405 方法白名单 |
| 定向暴力破解 | 针对特定用户反复尝试密码 | 账号锁定 (5次失败锁定15分钟) + RateLimit (登录 10/min) + Captcha |
| 暴力破解 | 分布式 IP 反复尝试用户名/密码 | RateLimit (登录 10/min) + Captcha |
| XSS 跨站脚本 | `<script>`, onerror, javascript: | SecurityMiddleware (erikwang2013/security-php) (5 种模式) + X-XSS-Protection 响应头 + CSP |
| SQL 注入 | UNION SELECT, OR 1=1, 注释绕过 | SecurityMiddleware (erikwang2013/security-php) (6 种模式) + Eloquent ORM 参数化查询 |
| CSRF 跨站请求伪造 | 恶意网站代发请求 | SecurityMiddleware (erikwang2013/security-php) Origin/Referer 校验 |
| 路径遍历 | `../../etc/passwd` | SecurityMiddleware (erikwang2013/security-php) 路径遍历模式 + UploadController 扩展名白名单 |
| 命令注入 | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityMiddleware (erikwang2013/security-php) (4 种模式) |
| 会话劫持 | 窃取 JWT Token | JWT 短期有效 (2h) + 黑名单登出 + 敏感操作二次密码确认 |
| ID 枚举 | 遍历数字 ID 猜测数据量 | Hashids 混淆为随机字符串 |
| 数据泄露 | DB 拖库 / 中间人 / 日志泄露 | 三层加密/脱敏 + OperationLog 敏感字段过滤 |
| DoS 攻击 | 超大请求体 / 高频请求 | 请求体 10MB 限制 + RateLimit 60/min + IP 黑名单 |
| 权限提升 | 低权限用户访问管理接口 | RBAC method.path 粒度鉴权；AdminPermission 无 adminId 直接 401 |
| 文件上传攻击 | shell.php.png 双扩展名 | SecurityMiddleware (erikwang2013/security-php) 恶意文件检测 |
| Refresh Token 重放 | 窃取 refresh token 后并发刷新 | refresh 单次使用（Redis `SET NX` 原子占用）+ 旧 token 黑名单轮换；access/refresh/client 类型严格区分 |
| 已禁用账号继续访问 | 管理员被禁用后旧 token 仍有效 | AdminAuth 每次请求查库校验 `status=1`，禁用/删除立即 401 |
| 未签名回调注入 | 直接 POST 伪造承运商轨迹事件 | 未配置 `webhook_secret` 默认 401；仅 `CALLBACK_ALLOW_UNSIGNED=1` 开发放行；签名常量时间比较 |
| 支付回调重放 / 重复链上交易 | 重复确认订单或复用 tx_id | Stripe 要求 `payment_status=paid`；PayPal 先 capture 且只对本地订单扣款；确认事务 + 行锁；`orders.tx_id` 唯一索引，重复交易 422 |
| 配置密钥泄露 | 配置列表/操作日志回显明文密钥 | 列表对 secret/password/private_key/api_key/app_key/access_key/token 打码；`***` 回写忽略；操作日志对 `/admin/config` 的 value 打码 |
| 内部接口暴露 | 外部直接调用 gRPC 查询 | 默认仅监听 `127.0.0.1:8792`；空 `INTERNAL_TOKEN` fail-closed；网关空 token 拒绝启动 |
| 来源 IP 伪造 | 客户端自带 X-Forwarded-For 绕过限流 | Nginx 以 `$remote_addr` 覆盖写入 `X-Forwarded-For`，不追加客户端值 |

### 12.2 已知局限

| 局限 | 影响范围 | 缓解措施 |
|------|---------|---------|
| CSRF 保护仅对浏览器有效 | 非浏览器客户端（curl, Postman, 移动 App）可跳过 Origin/Referer 检查 | 非浏览器客户端天然不受 CSRF 攻击；依赖 JWT 认证替代 Cookie |
| Redis 不可用时限流和黑名单降级为 fail-open | 攻击者可绕过限流和高频拦截 | 监控 Redis 可用性告警；IP 黑名单支持 file/redis/cache 三后端可降级 |
| 无独立 WAF 引擎 | 基于正则匹配的检测，非专用 WAF 规则引擎 | 生产环境建议前置 Nginx ModSecurity 或 Cloudflare WAF |
| JWT 无状态无法主动失效 | Token 未过期前无法从服务端主动吊销（除黑名单外） | 黑名单 + 短期 2h TTL + 禁用账号每请求查库，降低风险窗口 |
| 管理员端点未单独限流 | 管理接口与普通接口共用 60/min 默认阈值，仅登录/注册有更严格限制 | 管理员操作频率天然低；高敏感接口另需密码二次确认，后续可按路径单独配置 |
| 网关无 single-flight | 缓存未命中时并发请求可能重复转发上游 | 承运商 5xx / 网络失败写入 15s 负缓存，故障期间抑制重复请求；正常并发穿透待后续实现请求合并 |
| Hashids 盐默认空 | ID 可被枚举，不能作为访问控制 | 授权由 JWT + RBAC 执行；生产可在 `config/hashids.php` 设置盐值 |
| 导出临时文件未自动清理 | `runtime/tmp/export_*` 可能长期残留 | 文件仅含脱敏数据；建议运维定期清理 runtime/tmp |
| 查询记录无保留策略 | `logistics_tracking_query` 持续增长 | 按业务需要自行归档/清理，当前未内置 TTL |
| Rust workspace 依赖绝对路径 | `ecat-security` 仍引用作者机器的绝对路径（1.0.8），标准环境 `cargo check` 可能失败 | 构建网关前检查 workspace 依赖；CI/部署环境需替换为可用路径或发布版 |
| 安装器未做完整 MySQL 端到端测试 | 特定 MySQL 版本/权限下安装向导可能失败 | 安装前备份；失败时查看应用日志并手动核对 `.env` 与表结构 |
| PCRE 回溯限制 | 包内置 1,000,000 回溯上限+finally恢复，极端复杂输入仍有性能风险 | 请求体大小限制 (10MB) 兜底 |

---

## 13. 客户端门户与支付安全（M7–M10）

**13.1 客户端密钥（M7）**：X-API-Key 由客户端自设（≥16 位），数据库存 `sha256(api_key)`、明文仅创建时返回一次；appid 为服务端生成的公开标识；客户端 JWT（`token_type=client`）由 ClientAuth 中间件校验，与 admin JWT 隔离；刷新接口只接受 refresh token，access/client token 不能用于刷新。

**13.2 支付 webhook 验签（M8/M10）**：Stripe Checkout 以 HMAC-SHA256 验签，仅 `payment_status=paid` 的 `checkout.session.completed` / `async_payment_succeeded` 确认订单；PayPal Orders v2 调用 `/v1/notifications/verify-webhook-signature` 验签，APPROVED 只是授权，先 `/capture` 且只对本地存在订单扣款，`PAYMENT.CAPTURE.COMPLETED` 才确认；manual 渠道保持 pending，由管理员确认，不自动置 paid；确认逻辑使用事务 + 应用行锁，Redis 密钥写入在事务提交后执行；`orders.tx_id` 唯一索引拒绝重复链上交易（422）。支付密钥（stripe_secret_key / paypal_secret 等）经 `Encryptable` 加密存入 `logistics_system_config`。

**13.3 虚拟币到账验证（M9）**：USDT 支持 TRC20 / BEP20 / ERC20 三链；TRC20 经 Tronscan 公共 API 自动验证到账，金额在基础价上做 0.01–0.99 唯一化偏移，重复发起复用已展示金额；BEP20 / ERC20 由人工确认。

**13.4 网关攻击检测（M11）**：`ecat-security` SecurityBodyLayer 已集成至 tracking-gateway（注入 / 协议 / 数据序列化 / 文件 / 敏感数据泄露攻击检测），攻击载荷在网关层即被拦截返回安全错误（HandleErrorLayer 消化 SecurityError）；应用层 `erikwang2013/security-php` 继续兜底，两层串行互补。

**13.5 CDN 安全（M12）**：Cloudflare 免费版全站 HTTPS + 双层 WAF（边缘托管规则集挡 DDoS / 已知漏洞 / 扫描，网关 `ecat-security` 挡应用层攻击载荷）；Tunnel 回源源站零公网暴露（备选：回源 IP 段白名单 + 自定义回源头 + mTLS）；回调（`/api/callback/*`、`/api/payment/webhook/*`）走仅 DNS 独立子域直连回源，CDN 故障不丢单；网关限流按 API-Key 哈希计数与来源 IP 无关，CDN 边缘 IP 不改变限流语义；API-Key 鉴权端点一律 no-store 防跨用户缓存串号；「CF-Connecting-IP」仅在绑定回源白名单时可信；Nginx 以覆盖方式写入 `X-Forwarded-For`，不信任客户端自带值。

**13.6 承运商回调（M3）**：`/api/callback/{carrier}` 先做承运商白名单校验；未配置 `webhook_secret` 时默认返回 401，只有显式 `CALLBACK_ALLOW_UNSIGNED=1` 的开发环境才放行；签名算法为 `sha256(secret + 去 sign 字段后的紧凑 JSON)`，使用 `hash_equals` 常量时间比较；单次事件上限 100 条，字段按长度截断；`(tracking_no, event_code, event_time)` 唯一键去重；参数/签名错误返回真实 HTTP 400/401/404，重复事件视为成功。

**13.7 内部 gRPC（e-cat → PHP worker）**：PHP 侧默认监听 `127.0.0.1:8792`（`INTERNAL_GRPC_LISTEN` 可覆盖），worker 进程数默认 8（`INTERNAL_GRPC_WORKERS` 可覆盖）；所有 RPC 必须携带 `x-internal-token`，`INTERNAL_TOKEN` 为空时 fail-closed 拒绝全部请求；网关 `internal_token` 为空时拒绝启动。网关与 PHP 的令牌必须一致，且通过容器/内网传输，不暴露公网。

**13.8 配置与错误详情**：配置列表对 `secret` / `password` / `private_key` / `api_key` / `app_key` / `access_key` / `token` 类键打码，`webhook_url`、`*_id` 等非密钥配置保持可读；更新时忽略 `***` 回写，避免覆盖真实密钥；操作日志对 `/admin/config` 的 `value` 字段打码；上游承运商异常详情只写服务端日志，API 响应只返回通用错误文案。
