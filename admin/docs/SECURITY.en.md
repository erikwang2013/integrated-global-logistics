> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](SECURITY.md) | [English](SECURITY.en.md) | [한국어](SECURITY.ko.md) | [Русский](SECURITY.ru.md) | [Deutsch](SECURITY.de.md) | [Français](SECURITY.fr.md) | [Español](SECURITY.es.md) | [Português](SECURITY.pt.md) | [हिन्दी](SECURITY.hi.md) | [العربية](SECURITY.ar.md) | [বাংলা](SECURITY.bn.md) | [Bahasa Indonesia](SECURITY.id.md) | [日本語](SECURITY.ja.md)

# Security Architecture Design

## 1. Defense-in-Depth Overview

The system adopts a 7-layer defense-in-depth model that filters malicious requests layer by layer from outside in, ensuring that if any single layer fails, subsequent defenses still provide a fallback.

The full middleware chain executes in the following order (see `config/middleware.php`):

```
请求 → Cors → Locale(Accept-Language) → SecurityMiddleware (erikwang2013/security-php, 31种检测器) → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Layer | Middleware/Mechanism | Defense target |
|----|--------|---------|
| 1 | SecurityMiddleware (erikwang2013/security-php) | 31 attack detectors + HTTP method validation + request body size limit + Content-Type validation + CSRF + IP attack-escalation blacklist |
| 2 | Cors | Cross-origin security + security response header injection |
| 3 | RateLimit | Redis sliding window rate limiting, prevents brute force |
| 4 | AdminAuth | JWT authentication + blacklist logout |
| 5 | AdminPermission | RBAC method.path granularity authorization |
| 6 | OperationLog | Operation audit + client source tracking |
| 7 | Data encryption | Hashids ID obfuscation + Encryptable DB encryption + EncryptionService transport encryption |

The frontend (Flutter) performs its own independent input validation; the backend never trusts it, and every layer defends independently.

---

## 2. Attack Detection Engine

## 2. Attack Detection Engine (erikwang2013/security-php)

Attack detection has been migrated from the in-house SecurityMiddleware to the dedicated `erikwang2013/security-php` v1.1+ security package, providing **31 detectors** across 5 major attack categories.

### 2.1 Detector Categories

**Injection attacks (11):** XSS, SQL injection, command injection, NoSQL injection, LDAP injection, XPath injection, JNDI/Log4Shell, SSI server-side includes, GraphQL injection, SSTI template injection

**Protocol & request attacks (9):** SSRF, XXE, HTTP response header injection, Host header attack, Request Smuggling, Open Redirect, CORS bypass, WebSocket hijacking, DNS Rebinding

**HTTP protocol-layer validation (6):** HTTP method validation (405), request body size limit (413), Content-Type validation (415), CSRF Origin check, IP attack-escalation blacklist, sensitive data leak detection

**Data & serialization attacks (5):** PHP deserialization, CSV formula injection, email header injection, JWT attacks (structural analysis), JS Prototype Pollution

**File & path attacks (2):** path traversal, malicious file upload

### 2.2 Handling Modes

Each detector independently supports two modes:
- `block` — blocks immediately upon detection, returning the configured status code
- `log` — only logs without blocking (`header_injection`, `ssti`, `nosql_injection` default to log mode to prevent false positives)

### 2.3 IP Attack-Escalation Blacklist

An IP that triggers 5 attack detections within 60 seconds is automatically banned for 15 minutes. The storage backend is selectable among Redis (distributed), File (single-machine JSON), or Cache (high-concurrency separate files); the current config uses Redis storage.

### 2.4 Security Logs

File location: `runtime/logs/security.log` (auto-rotated, 10MB per file)

---

## 4. Response Security Headers

All headers are injected in the `Cors` middleware and appended to every response via `$response->withHeaders()`.

| Header | Value | Purpose |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Allows any origin to cross-origin request (intranet admin panel scenario) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Allowed method set |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | Allowed custom headers |
| Access-Control-Max-Age | `86400` | Preflight request cache for 24 hours |
| X-Content-Type-Options | `nosniff` | Disables browser MIME sniffing |
| X-Frame-Options | `DENY` | Blocks all iframe embedding, prevents clickjacking |
| X-XSS-Protection | `1; mode=block` | Enables the browser's built-in XSS filter and blocks page rendering |
| Referrer-Policy | `strict-origin-when-cross-origin` | Sends the full URL on same-origin, domain only on cross-origin |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Disables camera/microphone/geolocation APIs site-wide |

OPTIONS preflight requests return an empty 204 response directly without entering the subsequent middleware chain.

### 4.2 Content-Security-Policy (CSP)

Injected in the Cors middleware together with the other security headers, providing defense in depth by limiting the resource origins the browser may load and execute.

| Header | Value | Purpose |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Restricts resource origins for scripts/styles/images/connections/frames/forms etc. |
| X-Permitted-Cross-Domain-Policies | `none` | Blocks Adobe Flash/PDF cross-domain policy file loading |

CSP policy highlights:
- `default-src 'self'`: defaults to same-origin resources only
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: allows same-origin scripts + inline scripts (required by Flutter Web) + eval (required for Flutter Web debugging)
- `frame-ancestors 'none'`: blocks iframe embedding by any page; double protection with X-Frame-Options: DENY
- `base-uri 'self'`: restricts the `<base>` tag to same-origin only
- `form-action 'self'`: restricts form submissions to same-origin only

---

## 5. Rate Limiting Strategy

### Algorithm

Redis Sorted Set sliding window + atomic Lua scripts; key operations:

```lua
-- 1. 清理窗口外的旧记录
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. 检查当前窗口计数
local count = redis.call('ZCARD', KEYS[1])
-- 3. 超限则返回 {0, count}，未超限则 ZADD 并返回 {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- 随机后缀避免同毫秒覆盖
redis.call('EXPIRE', KEYS[1], window + 10)
```

Lua scripts execute single-threaded on the Redis server, making them **inherently atomic** and eliminating TOCTOU (Time-of-check to Time-of-use) race conditions.

### Rate Limit Configuration

| Route | Limit | Window | Scenario |
|------|------|------|------|
| Default (all routes) | 60/min | 60s | General API |
| `/api/auth/login` | 10/min | 60s | Login (brute-force protection) |
| `/api/auth/register` | 5/min | 60s | Register (anti bulk registration) |

### Response Headers

When rate limiting triggers, HTTP 429 is returned with a JSON body:
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

All responses (including normal ones) carry the following headers:

| Header | Description |
|----|------|
| X-RateLimit-Limit | Max requests allowed in the current window |
| X-RateLimit-Remaining | Requests remaining in the current window |
| X-RateLimit-Reset | Unix timestamp when the window resets |
| Retry-After | Only present when rate limited; suggested seconds to wait |

### Degradation Strategy

When Redis is unhealthy (connection timeout, unavailable, etc.), the system **fails open**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis down, 放行所有请求
}
```

Better to temporarily lose rate-limit protection than to block legitimate business requests.

### 5.4 Account Lockout Mechanism

On top of rate limiting, the login endpoint adds an **account lockout** mechanism to prevent targeted brute-force attacks against specific users.

**Lockout flow**:

```
登录失败 → Redis INCR account_lockout:{userId} TTL=900s
连续 5 次失败 → Redis SETEX account_locked:{userId} 900 1
            → 返回 429 "账号已被锁定，请15分钟后再试"
            → 清除计数器 DEL account_lockout:{userId}
```

**Behavior during lockout**:

During the lockout, all login requests return 429 directly without password verification, fully blocking brute-force attempts.

**Config constants**:

| Constant | Value | Meaning |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Max consecutive failures |
| LOCKOUT_DURATION | 900 | Lockout duration (seconds), i.e. 15 minutes |

Note: account lockout is based on `userId`, not IP, so attackers cannot bypass it by changing IPs. Combined with IP rate limiting (10/min) it forms two layers of protection:
- IP layer: the 10/min rate limit blocks distributed brute force
- Account layer: the 5-failure lockout blocks targeted brute force

---

## 6. Authentication and Authorization

### 6.1 JWT Authentication

Implemented by the AdminAuth middleware, mounted on route groups that require authentication.

**Configuration** (`config/plugin/erikwang2013/jwt/jwt`, injected from `.env`):

| Parameter | Value | Description |
|------|-----|------|
| Algorithm | HS256 | HMAC-SHA256 symmetric signing |
| Secret | `JWT_SECRET` | Injected via environment variable; must be changed in production |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Issuer | `open-admin` | `JWT_ISSUER` |
| Audience | `open-admin` | `JWT_AUDIENCE` |

**Token extraction**: taken from the `Authorization: Bearer <token>` header; strip the `Bearer ` prefix to get the raw JWT.

**Authentication flow**:
1. Empty token → 401 immediately `{"code": 401, "message": "未登录"}`
2. Check the Redis blacklist `jwt_blacklist:{md5(token)}` → hit → 401 `Token已失效，请重新登录`
3. JWT decode → failure (expired/signature mismatch) → 401 `Token已过期或无效`
4. Success → inject `$request->adminId` and `$request->adminUsername`

**Blacklist mechanism**: on logout, `md5(token)` is written to Redis with the TTL set to the JWT's remaining validity. When Redis fails, blacklist checks are skipped (fail-open); a logged-out token may remain usable briefly, but the JWT's short validity (2h) acts as a fallback safeguard.

### 6.2 Concurrent Session Limit

To prevent leaked tokens from being abused across multiple devices, the system limits how many valid tokens a single user can hold concurrently.

**Limiting logic**:

```
登录成功 → 签发新 Token
         → 查询当前用户有效 Token 数量: Redis SCARD user_tokens:{userId}
         → 若数量 >= 3（MAX_CONCURRENT_SESSIONS）:
            → 按创建时间升序排列，移除最旧的 Token:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → 将新 Token 加入集合: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Config constants**:

| Constant | Value | Meaning |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Max concurrent tokens per user |

**Forced logout scenario**: when a user logs in on a 4th device, the token from the 1st device is forcibly blacklisted, and subsequent requests return 401 "Token已失效，请重新登录".

On logout, the current token is removed from the set. When a token expires naturally, the Redis key expires automatically and the set membership shrinks accordingly.

### 6.3 RBAC Permission Model

Implemented by the AdminPermission middleware.

**Data model**: three-level association User -> Role -> Permission

- `logistics_admin_user` (users table)
- `logistics_admin_user_role` (user-role association table)
- `logistics_admin_role` (roles table)
- `logistics_admin_role_permission` (role-permission association table)
- `logistics_admin_permission` (permissions table)

**Permission types**:
| type | Meaning | Example |
|------|------|------|
| 1 | Menu permission | Controls left navigation visibility |
| 2 | Button permission | Controls in-page action buttons (add/edit/delete) |
| 3 | API permission | Controls backend endpoint access |

API permission identifier format: `{method}.{path}`

For example:
- `post.admin/user` — create user
- `put.admin/user` — edit user
- `delete.admin/user` — delete user
- `get.admin/user` — view user list

**Authorization flow**:
1. `$request->adminId` empty → allow (route has no auth prerequisite configured)
2. Get user → roles (skip disabled roles with `status=0`) → permission list
3. Super admin (`slug = '*'`) → allow directly
4. Build `strtolower(method) . '.' . trim(path, '/')` → compare against the permission list
5. No match → 403 `{"code": 403, "message": "无权限访问"}`

**Password re-confirmation**: BaseController provides `confirmPassword()`. Sensitive operations (deleting users, data export, etc.) additionally require the current password at the controller layer, preventing unauthorized actions after session hijacking.

---

## 7. Audit Logs

### 7.1 Operation Logs

The OperationLog middleware automatically records operation logs for POST / PUT / DELETE requests. GET requests are not logged.

**Recorded fields**:

| Field | Source | Description |
|------|------|------|
| id | SnowflakeService::generate() | Globally unique ID |
| user_id | `$request->adminId` | Operator ID; 0 when not logged in |
| action | `$request->method()` | Same as method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Request path |
| ip | `$request->getRealIp()` | Client real IP |
| source | detectSource() | Client source platform |
| input | Request body (masked JSON) | Submitted operation data |
| created_at | `date('Y-m-d H:i:s')` | Operation time |

**Sensitive field filtering**: the request body is traversed recursively, and the values of the following fields are replaced with `***`:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Client source detection** (`detectSource()`), in priority order:

1. First reads the `X-Client-Platform` custom header (explicitly declared by native clients)
2. Falls back to User-Agent string inference (detection order in the `detectSource()` method):

| Platform | UA keywords |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Fallback default |

**Fault tolerance**: logging errors do not block business requests (swallowed silently via `catch (\Throwable)`).

### 7.2 Security Logs

**File location**: `runtime/logs/security.log`

**Logged content**:
- Attack block logs: attack category, IP, path, field, source, payload snippet (first 200 chars)
- IP ban notifications: banned IP, trigger count

Logs are written with `FILE_APPEND | LOCK_EX` to ensure concurrency-safe writes.

---

## 8. Data Protection

The system uses a three-layer data protection strategy corresponding to the three stages of data flow.

### 8.1 Transport Layer — EncryptionService

`EncryptionService` uses the `erikwang2013/encryption` package to encrypt/decrypt sensitive fields in API requests/responses.

**Technical details**:
- Algorithm: `aes-256-cbc-hmac` (includes HMAC signing against tampering)
- Key: `ENCRYPTION_KEY` environment variable, auto-aligned to 32 bytes
- Used for: transmitting phone numbers, ID card numbers, etc. between clients and the API

**Masking helpers**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (username over 2 characters) or `a**@example.com`

### 8.2 Storage Layer — Encryptable Cast

The `AdminUser` model uses the `Erikwang2013\Encryptable\Encryptable` Eloquent cast on the following fields:

- `email` → cast as Encryptable, auto encrypt/decrypt
- `phone` → cast as Encryptable, auto encrypt/decrypt
- `id_card` → cast as Encryptable, auto encrypt/decrypt

Writes are automatically encrypted to ciphertext; reads are automatically decrypted to plaintext. The database column type is `VARCHAR(500)`, and ciphertext is stored in base64.

**Key separation**: `ENCRYPTABLE_KEY` is used independently of the transport-layer key (`ENCRYPTION_KEY`), so leaking one key does not compromise the other layer.

Key rotation: the `ENCRYPTION_PREVIOUS_KEYS` environment variable supports a comma-separated list of historical keys; old data is decrypted with historical keys on read and re-encrypted with the current key on write.

### 8.3 Display Layer — ID Obfuscation and Masking

**Hashids ID obfuscation**: `HashidsService` uses the `erikwang2013/hashids` package.

- Database BIGINT IDs returned by the public API are encoded as hash strings (e.g. `xK3mN9qR2pL7wV8b`)
- Clients send the hash string in requests; the backend decodes it to the raw ID automatically
- Salt injected via the `HASHIDS_SALT` environment variable; different salts produce completely different encode/decode results
- Minimum hash length is 16 characters, using a 62-character alphanumeric alphabet
- BaseController provides the convenience methods `encodeId()`, `decodeId()`, `encodeIds()`

**Export masking**: when exporting Excel/PDF (ExportController), sensitive fields are uniformly masked:
- Phone: `138****1234`
- Email: `a***@example.com`
- ID card: fully masked as `********`

---

## 9. Key Management

All keys are injected via `.env` environment variables; config files read them with `getenv()` and include fallback defaults (safe for development only).

| Environment variable | Purpose | Package | Production requirement |
|----------|------|-----|---------|
| JWT_SECRET | JWT signing key | erikwang2013/jwt-webman | Random string of 64+ characters |
| JWT_ALGORITHM | JWT signing algorithm | Same as above | Keep HS256 |
| HASHIDS_SALT | ID encoding salt | erikwang2013/hashids | Random string |
| SNOWFLAKE_DATACENTER_ID | Datacenter ID (0-31) | erikwang2013/snowflake-php | Keep default for a single datacenter |
| ENCRYPTION_KEY | API transport-layer encryption key | erikwang2013/encryption | 32-byte random string |
| ENCRYPTABLE_KEY | DB storage-layer encryption key | erikwang2013/encryptable | 32-byte random string, different from the transport key |

**Security requirements**:
- `.env` is in `.gitignore`; committing it to the repository is strictly forbidden
- `.env.example` is a public template and contains no real keys
- All default keys **must** be replaced with random strings in production
- Use `openssl rand -base64 32` to generate keys

### Key Storage Isolation

| Layer | Config key | Key environment variable |
|----|--------|-------------|
| Transport encryption | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Storage encryption | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| ID obfuscation | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| JWT signing | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

The system serves an RFC 9116-compliant security contact information endpoint at `/.well-known/security.txt`, making it easy for security researchers to find a reporting channel when they discover vulnerabilities.

**Access**:

```
GET /.well-known/security.txt
```

**Response content**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Field descriptions**:

| Field | Description |
|------|------|
| Contact | Contact for reporting security vulnerabilities |
| Expires | File expiry date; must be updated periodically |
| Preferred-Languages | Preferred communication languages |
| Canonical | Canonical URL of this file |
| Policy | Link to security policy / vulnerability disclosure policy |

This endpoint is not subject to middleware such as rate limiting or authentication; anyone can access it directly.

---

## 11. Nginx Security Configuration

The project provides `docs/nginx-security.conf` as a security hardening reference config for the production Nginx reverse proxy.

**Included security measures**:

| Config item | Purpose |
|--------|------|
| `server_tokens off` | Hides the Nginx version number |
| `client_max_body_size 10m` | Limits request body size, coordinated with SecurityMiddleware (erikwang2013/security-php) |
| `limit_req_zone` | Request rate limiting at the Nginx layer |
| `limit_conn_zone` | Concurrent connection limit |
| `add_header` security headers | Appends security headers such as X-XSS-Protection at the Nginx layer |
| `if ($request_method)` | Rejects non-standard HTTP methods at the Nginx layer |
| SSL/TLS configuration | Modern TLS 1.2/1.3 configuration, weak cipher suites disabled |
| Hiding backend headers | `proxy_hide_header` removes sensitive headers such as the webman version |

**Usage**: merge the config from `docs/nginx-security.conf` into your Nginx server block, adjusting for your actual domain and certificate paths.

---

## 12. Threat Model

### 12.1 Mitigated Threats

| Threat type | Attack vector | Defense layers |
|----------|---------|---------|
| HTTP method abuse | TRACE/TRACK XST attacks, CONNECT tunnel proxies, WebDAV method probing | SecurityMiddleware http_method detector, 405 method whitelist |
| Targeted brute force | Repeated password attempts against a specific user | Account lockout (5 failures lock for 15 min) + RateLimit (login 10/min) + Captcha |
| Brute force | Distributed IPs repeatedly trying username/password | RateLimit (login 10/min) + Captcha |
| XSS cross-site scripting | `<script>`, onerror, javascript: | SecurityMiddleware (erikwang2013/security-php) (5 modes) + X-XSS-Protection response header + CSP |
| SQL injection | UNION SELECT, OR 1=1, comment bypass | SecurityMiddleware (erikwang2013/security-php) (6 modes) + Eloquent ORM parameterized queries |
| CSRF cross-site request forgery | Malicious websites forging requests | SecurityMiddleware (erikwang2013/security-php) Origin/Referer validation |
| Path traversal | `../../etc/passwd` | SecurityMiddleware (erikwang2013/security-php) path traversal patterns + UploadController extension whitelist |
| Command injection | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityMiddleware (erikwang2013/security-php) (4 modes) |
| Session hijacking | Stealing the JWT token | Short-lived JWT (2h) + blacklist logout + password re-confirmation for sensitive operations |
| ID enumeration | Enumerating numeric IDs to guess data volume | Hashids obfuscation to random strings |
| Data leakage | DB dump / man-in-the-middle / log leakage | Three-layer encryption/masking + OperationLog sensitive field filtering |
| DoS attacks | Oversized request bodies / high-frequency requests | 10MB request body limit + RateLimit 60/min + IP blacklist |
| Privilege escalation | Low-privilege users accessing admin endpoints | RBAC method.path granularity authorization |
| File upload attacks | shell.php.png double extension | SecurityMiddleware (erikwang2013/security-php) malicious file detection |

### 12.2 Known Limitations

| Limitation | Impact | Mitigation |
|------|---------|---------|
| CSRF protection works only for browsers | Non-browser clients (curl, Postman, mobile apps) can skip Origin/Referer checks | Non-browser clients are naturally immune to CSRF; rely on JWT authentication instead of cookies |
| Rate limiting and blacklist degrade to fail-open when Redis is unavailable | Attackers can bypass rate limiting and high-frequency blocking | Monitor Redis availability with alerts; IP blacklist supports file/redis/cache backends for degradation |
| No standalone WAF engine | Regex-based detection, not a dedicated WAF rule engine | Recommended: Nginx ModSecurity or Cloudflare WAF in front in production |
| Stateless JWT cannot be actively invalidated | Tokens cannot be revoked server-side before expiry (except via blacklist) | Blacklist + short 2h TTL narrows the risk window |
| No special rate limit for admin endpoints | Admin endpoints share the 60/min default limit with normal endpoints | Admin operation frequency is naturally low; no distinction needed for now |
| PCRE backtracking limit | The package has a built-in 1,000,000 backtracking cap with finally-based recovery; extremely complex inputs still pose a performance risk | Request body size limit (10MB) as a fallback |
## 13. Client Portal & Payment Security (M7–M10)
Stripe / PayPal webhook signature verification (HMAC-SHA256 / verify-webhook-signature); payment keys encrypted via `Encryptable` in `logistics_system_config`; USDT TRC20 auto-verified via Tronscan, BEP20 / ERC20 manual; X-API-Key client-set (≥16 chars) stored as sha256. Gateway attack detection (M11): `ecat-security` SecurityBodyLayer integrated into the gateway, attack payloads blocked at the gateway layer. **13.5 CDN security (M12)**: Cloudflare free plan full-site HTTPS + dual-layer WAF (edge managed rules + gateway application-layer detection); Tunnel origin keeps the source zero-exposed; callbacks go via DNS-only subdomain direct connection to avoid order loss on CDN outage; rate limiting counts by X-API-Key, unaffected by CDN edge IPs; authenticated endpoints always no-store to prevent cross-user cache mixing. **13.6 CDN provider credential management (M13)**: CDN provider profiles (access_key / access_secret / domain list) stored in the `logistics_cdn_provider` table; secret fields encrypted via `Encryptable`; maintained via `/admin/cdn/provider` CRUD.
