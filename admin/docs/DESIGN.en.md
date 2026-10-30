> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](DESIGN.md) | [English](DESIGN.en.md)

# Open Admin — Design Document

> For detailed Mermaid architecture diagrams, see [ARCHITECTURE.en.md](ARCHITECTURE.en.md) (renders automatically on GitHub/GitLab/VS Code).

## 1. System Architecture

> **Feature list**: auth (login/register/refresh/logout + account lockout + session limit) | dashboard (Redis cached) | user CRUD + batch + import | roles & permissions (RBAC) | system config | operation audit (8 platform sources) | files (upload + export + masking) | security (18 layers of defense) | ops (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Layui Admin (Browser)│  │  API Clients (HTTP/JSON)     │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │  File Store  │  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Backend Architecture

### 2.1 Layered Design

| Layer | Directory | Responsibilities |
|---|------|------|
| Routing | `config/route.php` | URL-to-controller mapping, middleware binding, versioned routes |
| Middleware | `app/middleware/` | Attack blocking (SecurityFilter), rate limiting (RateLimit), authentication (JWT), authorization (RBAC) |
| Controllers | 14 total: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs (admin) + Captcha/Auth (API v1) | Request parameter validation, business logic calls, response formatting |
| Business services | `app/service/` | Reusable business logic (reserved) |
| Data models | `app/model/` | ORM mapping, relations, field encryption |
| Common utilities | `app/common/` | Hashids, Snowflake, Encryption services |

### 2.2 Request Lifecycle

```
客户端请求
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 匹配
  │
  ▼
中间件链:
  Locale ──────────────► Accept-Language / ?lang= 语言检测
  │
  ▼
  SecurityFilter ──────► HTTP方法检查 → 405 (仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截 (403)
  ▼
  RateLimit ───────────► Redis 滑动窗口限流
  │ (失败返回 429 + Retry-After 头)
  ▼
  AdminAuth ──────────► JWT 验证，注入 $request->adminId
  │ (失败返回 401)
  ▼
  AdminPermission ────► RBAC 权限校验（Redis 60s 缓存）
  │ (失败返回 403)
  ▼
  OperationLog ───────► 操作日志记录 (POST/PUT/DELETE)，自动检测来源端
  │
  ▼
Controller::method()
  │
  ├─► 参数验证 (validator)
  ├─► 敏感操作确认 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 操作 (自动 encryptable 加解密)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 ID Lifecycle

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Data Encryption System

```
传输层 (encryption)     — AES-256-CBC，独立密钥
Storage layer (encryptable) — AES-256-CBC with random IV + HMAC (`ENCRYPTABLE_CIPHER` default aes-256-cbc), independent key, automatic via Model $casts
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. Database Design

### 3.1 ER Relationships

```
logistics_admin_user ──┬── logistics_admin_user_role ──┬── logistics_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    logistics_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    logistics_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           logistics_operation_log
             (操作日志)

logistics_system_config (系统配置) — 独立表
```

### 3.2 Core Table Structures

| Table | Field count | Description |
|------|-------|------|
| `logistics_admin_user` | 14 | Admin users; phone/email/id_card stored encrypted; soft delete supported |
| `logistics_admin_role` | 7 | Roles; slug unique |
| `logistics_admin_permission` | 10 | Permission tree (parent_id self-reference); type: 1=menu 2=button 3=API |
| `logistics_admin_user_role` | 2 | User-role many-to-many pivot table |
| `logistics_admin_role_permission` | 2 | Role-permission many-to-many pivot table |
| `logistics_system_config` | 8 | Key-value config; group+key jointly unique |
| `logistics_operation_log` | 9 | Operation audit logs (incl. client source) |

### 3.3 Primary Key Conventions

- Type: `BIGINT UNSIGNED NOT NULL`
- Property: **non-auto-increment**, generated at the application layer by the Snowflake algorithm
- Benefits: globally unique, distributed-friendly, monotonically increasing for index-friendly access, does not expose business volume
- Config: datacenter_id(0-31) + worker_id(0-31), supports 1024 concurrent nodes
- Defaults: when `SNOWFLAKE_DATACENTER_ID` / `SNOWFLAKE_WORKER_ID` are not set, both are derived from the process PID (datacenter `intdiv(pid, 32) % 32`, worker `pid % 32`), so multiple HTTP/gRPC workers on one host do not generate duplicate IDs. Set the environment variables explicitly for multi-host deployments or when PID reuse may exceed 1024 nodes.

## 4. API Design

### 4.1 URL Conventions

```
公开接口:  /api/v1/captcha/{generate|verify}
           /api/v1/auth/{login|register|refresh}

管理端:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

资源路由:
  GET    /admin/user          → 列表
  POST   /admin/user          → 创建
  GET    /admin/user/{hashid} → 详情
  PUT    /admin/user/{hashid} → 更新
  DELETE /admin/user/{hashid} → 删除（需密码确认）

系统配置:  /admin/config[/{hashid}]
操作日志:  /admin/log
个人中心:  /admin/profile[/password|/logout]
导入:     /admin/import/users
上传:     /admin/upload
批量:     /admin/user/batch/{destroy|status}
文档:     /api/docs     (OpenAPI 3.0)
健康:     /health
```

### 4.2 API Versioning Strategy

API version numbers live directly in the URL path (`/api/v1/*`), resolved by route groups to the matching controllers under `app/api/{version}/controller/`:

| Mechanism | Description |
|------|------|
| URL shape | `POST /api/v1/auth/login` — the version is the first path segment; no request header needed |
| Resolution | `Route::group('/api/v1', ...)` in `config/route.php` binds the version directory directly; the `v()` helper resolves controller classes by version |
| Directory | Controllers organized by version: `app/api/{version}/controller/` |
| Unknown version | Path matches no route group → 404 |

Extension example — adding a v2 API:
1. Create controllers under `app/api/v2/controller/...`
2. Append `Route::group('/api/v2', ...)` in `config/route.php` (reusing the `v('v2', ...)` helper)
3. The v1 group and existing requests are unaffected

```bash
curl /api/v1/auth/login   # v1 (current)
curl /api/v2/auth/login   # v2 (after adding the group)
```

### 4.3 Rate Limiting Strategy

Based on the Redis Sorted Set sliding window algorithm, executed as atomic Lua scripts:

| Endpoint | Limit |
|------|------|
| Default | 60/min per IP/route |
| POST /api/v1/auth/login | 10/min |
| POST /api/v1/auth/register | 5/min |

Exceeding the limit returns 429; response headers include X-RateLimit-Limit / Remaining / Reset / Retry-After. The Redis `eval` call uses the phpredis argument order (`eval(script, args, num_keys)`); an earlier signature mistake made limits fail open, so deployments upgraded from older versions will now see 429s that were previously skipped. Login failures additionally lock the username + IP pair for 15 minutes after 5 attempts. The bundled Docker Nginx config overwrites `X-Forwarded-For` with the peer address, so the client IP used for these keys cannot be spoofed; the bare-metal reference appends, so the outermost proxy must overwrite it.

### 4.4 Unified Response

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Meaning | Trigger |
|------|---------|------|
| 0 | Success | Normal response |
| 400 | Bad request | Incorrect request format |
| 401 | Unauthenticated | Token missing/expired/invalid |
| 403 | Forbidden | User role lacks the required permission |
| 404 | Not found | Resource not found |
| 422 | Validation failed | Form params violate rules / password confirmation failed |
| 500 | Server error | Unexpected exception |

### 4.5 Authentication Flow (with Click Captcha)

```
客户端                               服务端
  │                                    │
  │  ① POST /api/v1/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), texts}    │
  │                                    │
  │  ② 用户点击图中文字位置              │
  │                                    │
  │  ③ POST /api/v1/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 Permission Model (RBAC)

```
  用户 ──┬── 角色 ──┬── 权限
  User     Role      Permission
                 │
                 ├── type=1: 菜单 (控制侧边栏可见)
                 ├── type=2: 按钮 (控制页面内操作)
                 └── type=3: API  (控制接口访问)

  权限标识格式: {method}.{path}
  例: get.admin/user  post.admin/user  delete.admin/user
  超级管理员标识: * (跳过所有权限检查)
```

### 4.7 Password Re-Confirmation for Sensitive Operations

Sensitive operations such as deleting users, roles, and permissions require the current user's password in the request body for identity re-verification:

```
客户端                           服务端
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 密码错误返回 422
  │                                │ → 密码正确继续执行
  │◄── 200 { code: 0 }           │
```

The frontend shows a confirmation dialog before triggering a delete operation, collects the user's password, then sends the request.

## 5. Frontend Design

### 5.1 Admin UI (Layui)

- Admin pages and static assets live under `public/`, rendered directly by PHP controllers using bundled Layui components.
- Login, dashboard, users, roles, config, logs, and profile are Layui pages; Layui is the only admin UI.

## 6. Security Design

### 6.1 Defense in Depth

| Layer | Measure |
|------|------|
| Method restriction | SecurityFilter HTTP method whitelist; only GET/POST/PUT/DELETE/OPTIONS/HEAD allowed, non-standard methods return 405 |
| Attack blocking | SecurityFilter middleware detects/blocks XSS, SQL injection, path traversal, command injection, CSRF |
| Human verification | Click captcha, mandatory for login/register |
| Account lockout | 5 consecutive login failures lock the account for 15 min; 429 returned while locked |
| Session limit | Max 3 concurrent tokens per user; oldest token auto-blacklisted when exceeded |
| Rate limiting | RateLimit middleware, Redis sliding window, atomic Lua |
| CSP | Content-Security-Policy header restricts resource origins, prevents XSS and data injection |
| Operation confirmation | Sensitive ops such as delete require the current user's password |
| Transport | HTTPS + JWT Bearer Token |
| API IDs | Hashids encrypted, real IDs cannot be reverse-engineered externally |
| Request body | AES-256-CBC sensitive field encryption |
| Database | BIGINT primary keys (no auto-increment exposed) |
| Database | AES-256-CBC + random IV + HMAC encrypted storage of sensitive fields |
| Authentication | JWT HS256, 2h expiry + refresh token |
| Token lifecycle | access / refresh / client token types strictly separated; refresh tokens are single-use (atomic Redis SET NX + blacklist rotation); disabled admins are blocked on every request |
| Secrets | startup fails in an installed environment when `JWT_SECRET_KEY` / `ENCRYPTABLE_KEY` / `ENCRYPTION_KEY` is missing or still the old public default; no code defaults |
| Internal interface | gRPC listens on `127.0.0.1:8792` by default with 8 workers; empty `INTERNAL_TOKEN` fails closed |
| Config output | secret config values masked as `***`; `***` write-back ignored; `system_config` cannot be exported; operation log masks `/admin/config` values |
| Authorization | RBAC with method.path granularity (missing adminId returns 401) |
| Audit | OperationLog records all operations (incl. automatic client source detection) |

### 6.2 Key Management

```
JWT_SECRET_KEY      → JWT signing key; no code default, generated by the installer or 64+ random chars
ENCRYPTABLE_KEY     → DB storage encryption key, 32 chars; independent from the transport key
ENCRYPTION_KEY      → API transport encryption key, 32 chars
INTERNAL_TOKEN      → gateway → PHP worker gRPC shared secret; empty means reject all internal calls
ENCRYPTION_PREVIOUS_KEYS → comma-separated old keys used only for reading/rotating legacy data
config/hashids.php salt → optional ID-obfuscation salt; empty by default (no env binding)
SCOUT_HOSTS         → ES address, internal network (commercial edition)
```

`JWT_SECRET_KEY`, `ENCRYPTABLE_KEY` and `ENCRYPTION_KEY` are validated at startup (`support\bootstrap\Secrets`). In an installed environment a missing key, or a value equal to the old public default, aborts startup; `APP_DEBUG=true` downgrades that to a warning for local development only. `INTERNAL_TOKEN` also has no default, but it is enforced at the internal interface: an empty value makes the gRPC service reject every request, and the gateway refuses to start. The installer generates all four random values into `.env` and syncs them to `.env.docker` when running under Docker. If existing rows were encrypted with the old public `ENCRYPTABLE_KEY` (`open-admin-db-encryption-key-32b`), set `ENCRYPTION_PREVIOUS_KEYS` to that value and re-encrypt before removing it.

Hashids is ID obfuscation, not an authorization boundary: the salt is empty by default, so IDs must not be treated as secret or as an access-control mechanism.

### 6.3 Sensitive Data Protection

| Scenario | Field | Measure |
|------|------|------|
| List display | phone | Masked: 138****1234 |
| List display | email | Masked: a***@example.com |
| Detail view | phone/email | Requires decryption endpoint |
| Excel export | phone/email | Exported masked |
| PDF export | All fields | Masked + non-removable copyright watermark |
| Storage | phone/email/id_card | Encrypted to ciphertext via encryptable |

## 7. Export Design

### 7.1 Excel Export

```
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 PDF Export

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. Deployment Architecture

### 8.1 Recommended Topology

```
Management plane:
  Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + Redis
                       Static files: public/

Query plane:
  Client → tracking-gateway (Rust, :8080)
         → gRPC h2c 127.0.0.1:8792 × 8 workers, x-internal-token
         → PHP worker → carrier adapters
```

### 8.2 Docker Compose (Recommended for Production)

`admin/docker-compose.yml` orchestrates all services of the topology above:

| Service | Image/Build | Port | Description |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 (host-published) | Reverse proxy + static files + Gzip; config `docs/nginx-docker.conf` |
| `app` | Local `Dockerfile` build | 8787 (internal only) | PHP 8.3 + OPcache + webman; internal gRPC `127.0.0.1:8792` × 8 workers |
| `mysql` | mysql:8.0 | 3306 (not host-published) | Primary database, persistent volume; DB host is `mysql` |
| `redis` | redis:7-alpine | 6379 (not host-published) | Cache / rate limiting / captcha |

MySQL and Redis are reachable only on the Compose network; do not add host port mappings. Nginx proxies `app:8787` and overwrites `X-Forwarded-For` with the real client address.

There are no default secrets to replace in `docker-compose.yml`. The installer generates `JWT_SECRET_KEY`, `ENCRYPTABLE_KEY`, `ENCRYPTION_KEY` and `INTERNAL_TOKEN` into `.env`, syncs them into `.env.docker` under Docker, and sets `APP_DEBUG=false`. Do not use the old public defaults from `.env.example`. After installation, recreate the app container so the new keys are loaded:

```bash
cp .env.docker .env   # optional: supplies Compose interpolation variables
docker compose up -d --force-recreate app
```

For a non-Docker deployment, run `php start.php restart -d` instead. The gateway's `internal_token` (or `TRACKING_GATEWAY_INTERNAL_TOKEN`) must match `INTERNAL_TOKEN`; the gateway refuses to start with an empty token.

### 8.3 CI/CD

GitHub Actions CI is defined in `.github/workflows/ci.yml`:
- PHP syntax check (`php -l`)
- PHPUnit unit tests

### 8.4 Database Backup

`database/backup/backup.sh` — mysqldump + gzip backup, auto-clears backups older than 30 days.
`database/backup/restore.sh` — interactive selection and restore of backups.

### 8.5 Monitoring

The `GET /metrics` endpoint (`MetricsController`) exposes 5 gauge metrics in Prometheus text format: total HTTP requests, active users, database/Redis connection status, and memory usage.

### 8.6 Environment Requirements

| Component | Minimum version | Recommended config |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ OPcache enabled |
| MySQL | 8.0+ | 8.0+ master-slave replication |
| Redis | 6.x | 7.x sentinel mode |
| Nginx | 1.20+ | Reverse proxy + gzip + SSL |
