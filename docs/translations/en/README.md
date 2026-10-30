# Logistics Aggregation Platform (Integrated Global Logistics)
![E-Cat 项目吉祥物](../../diagrams/mascot.svg)

One-stop platform for global logistics tracking: the **admin management console** (PHP webman + Layui) hosts the management plane and the query worker pool, the **e-cat high-frequency gateway** (long-running Rust process) carries the query traffic, and the **global-logistics unified facade** (PHP adapters for 209 carriers) lets you query the whole world through a single entry.

> Languages: [English](README.md) · [中文](../../../README.md)

## Project Introduction

![Project introduction](diagrams/intro.svg)

The logistics aggregation platform unifies tracking queries from **209** courier / postal carriers worldwide into one platform: merchants and C-side clients pass in only a tracking number, and the platform automatically identifies the domestic / international channel and the carrier — no need to care about each carrier's protocol differences (signature, OAuth2, XML/JSON, status mapping).

The platform is composed of three components working together:

- **admin management console** (PHP webman v2 + Layui) — management plane and PHP worker pool: query records, statistics reports, client portal, complete RBAC / JWT / operation audit system;
- **tracking-gateway high-frequency gateway** (Rust e-cat framework) — the first entry of the external query API: Redis cache, rate limiting, per-carrier circuit breaking, worker load balancing; it only handles the high-frequency plane and knows nothing about carrier protocols;
- **global-logistics unified facade** (PHP package) — adapters for 209 carriers (45 domestic + 164 international), 187 tracking-number auto-detection rules, 7 unified status semantics of `TrackStatus`.

**Current progress**: the admin plane (carrier / query records / statistics), query gateway (full external API chain), inbound carrier callback ingestion, monitoring & statistics, external OpenAPI docs, client portal (register / app / plan / order), payments (Stripe / PayPal / crypto), gateway security middleware, and CDN acceleration (Cloudflare + cache headers) are complete. **v1.2.0 is a breaking security-hardening release**: mandatory secrets with a startup check, no default admin account, strict JWT token-type separation, single-use refresh rotation, fail-closed callbacks, PayPal capture and unique transaction IDs, a loopback-only internal gRPC channel, and config masking. The client → e-cat → worker → carrier tracking query chain is demonstrable.

**Project pet E-Cat** — a cat wearing a tracking-radar cap and holding a parcel. A single self-contained SVG vector with breathing / blinking / tail-wagging / radar-sweep animations, with `prefers-reduced-motion` fallback. `docs/diagrams/mascot.svg` is the single source of the character; it is wired into the admin install wizard (`/install`), the 404 / 5xx error pages, the API docs corner badge, and the site favicon.

## Project Description

![Project description](diagrams/description.svg)

- **One entry**: `Logistics::track($trackingNo)` automatically identifies the domestic / international channel and carrier; the business layer only deals with one shape;
- **Auto detection**: 187 tracking-number regex rules are order-sensitive and prioritize domestic channels; for unrecognized scenarios, `domestic()` / `international()` can be called explicitly;
- **Unified status**: each carrier's varied raw statuses are mapped to a unified `TrackStatus` enum (pending pickup / in transit / out for delivery / delivered / exception / returned / unrecognized);
- **Global coverage**: the four major couriers DHL, FedEx, UPS, USPS and the national postal S10 systems of various countries (Europe, Latin America & Caribbean, Africa & Middle East, Asia-Pacific);
- **External API**: the e-cat query gateway provides API-Key authentication, Redis cache hits (`X-Cache: HIT`), rate limiting 429, per-carrier circuit breaking 503, worker failure 502, RoundRobin worker load balancing;
- **Client portal & billing** (M7–M10): client register / login (client JWT isolated from admin), app management with self-set X-API-Key, plan / order API; Stripe / PayPal plus USDT TRC20 / BEP20 / ERC20 crypto payments, Stripe payment methods (Apple Pay / Google Pay / Klarna / SEPA etc.) configurable at runtime. Manual orders stay `pending` until an admin confirms them; gateway webhooks confirm only a locally existing order (PayPal requires `CAPTURE.COMPLETED`, Stripe requires `payment_status=paid`);
- **CDN acceleration** (M12): Cloudflare free plan full-site HTTPS + edge caching for static assets;
- **Zero hard-coded keys**: all keys are injected via configuration, and the database layer stores them as Encryptable ciphertext — code and keys are fully separated.

## Project Architecture

![Project architecture](diagrams/architecture.svg)

Query chain: **client → e-cat query gateway → PHP worker pool → 209 carriers**.

The e-cat gateway (Rust) handles API-Key authentication, Redis cache hits, rate limiting, per-carrier circuit breaking and RoundRobin load balancing for the external API; cache hits, rate-limit rejections and circuit-breaker fast-fails all happen on the e-cat side, and PHP workers only carry real query traffic — horizontal scaling is just adding workers. The gateway calls the PHP worker pool over a private gRPC service (`InternalService.Query` / `Detect` / `Carriers`) on `127.0.0.1:8792` by default, authenticated with the shared `x-internal-token`; the PHP side starts 8 gRPC workers by default.

**The plan of e-cat reusing 209 PHP adapters**: the 209 adapters are PHP (`src/Carriers/Domestic` 45 + `International` 164); rewriting them in Rust would be months of work and would lose the benefit of continuous upstream package updates. e-cat does not need to understand carrier protocols — it depends only on the gRPC `InternalService` contract (`Query` / `Detect` / `Carriers`). Credentials are never delivered to e-cat, keeping a clear security boundary.

Management plane (browser) → `/admin/*`: JWT + RBAC permissions + operation audit, covering carrier / carrier-credential / tracking-query / callback-subscription / statistics / client / client-app / plan / order / cdn-provider. The query gateway and the management plane share one Redis namespace: PHP writes `logistics:api_keys:{sha256}` and the gateway reads the same key, so an approved app takes effect without restarting either process.

## Project Structure

![Project structure](diagrams/structure.svg)

```
integrated-global-logistics/
├── admin/                          # PHP webman v2 管理后台 + worker 池
│   ├── app/
│   │   ├── admin/controller/       # 管理端控制器（carrier、tracking、统计等）
│   │   ├── api/                    # API v1（验证码 / 登录 / 刷新令牌）
│   │   ├── common/                 # Hashids / Snowflake / 加密脱敏服务 / 支付服务
│   │   ├── middleware/             # 安全过滤 / 限流 / JWT / RBAC / 审计
│   │   ├── model/                  # 数据模型（logistics_ 前缀，snowflake 主键）
│   │   └── process/                # HTTP worker + internal gRPC worker
│   ├── config/                     # 配置（含中文注释）
│   ├── database/
│   │   └── install.sql             # SQL 安装脚本（表结构 + 权限种子，不创建默认管理员）
│   ├── support/bootstrap/Secrets.php # 启动时校验必需密钥
│   └── docs/                       # API / 架构 / 设计 / 安全文档（含 nginx-docker.conf）
├── infrastructure/                 # Rust e-cat 微服务框架 + 查询网关
│   ├── ecat/                       # 聚合 crate（App 骨架）
│   ├── ecat-transport-http/        # axum HTTP 传输
│   ├── ecat-data-redis/            # 轨迹缓存 + 限流存储
│   ├── ecat-client/                # RoundRobin 负载均衡
│   └── tracking-gateway/           # 对外查询网关（workspace 新成员）
└── docs/                           # 平台规划与图示
    ├── diagrams/                   # SVG 架构图（本 README 引用）
    ├── translations/               # English README
```

## Lifecycle

![Lifecycle](diagrams/lifecycle.svg)

**Query chain (synchronous)**: client → API-Key auth (SHA-256 key lookup in `logistics:api_keys:{sha256}`) → Redis rate limiting (key derived from the API-key hash) → shared cache lookup `logistics:cache:track:{carrier}:{tracking_no}` (return on hit, `X-Cache: HIT`, and backfill the current key's detail cache) → 15-second negative cache check → circuit-breaker check (503 fast-fail on OPEN; carrier 5xx counts toward the breaker) → RoundRobin worker selection → gRPC `InternalService.Query` over h2c `127.0.0.1:8792` with `x-internal-token` → the `Logistics` facade in the PHP worker (carrier HTTP timeout 6s, 1 retry = 2 attempts, ~12.2s worst case, inside the gateway's 15s budget) → 209 carriers → persist to `logistics_tracking_query` + write cache (`logistics:cache:query:{api_key_hash}:{query_no}`, 7-day TTL) → return standardized JSON.

**Callback chain (asynchronous)**: carrier webhook → `/api/callback/{carrier}` whitelist route + HMAC-SHA256 signature verification → persist to `logistics_tracking_event` (unique key dedupe) + update the query record. A carrier without `webhook_secret` is rejected with HTTP 401 unless `CALLBACK_ALLOW_UNSIGNED=1` is explicitly set for development; one callback accepts at most 100 events and truncates oversized fields. Outbound merchant push has been removed; consume `logistics_tracking_event` directly if event notification is needed.


## Security

![Security](diagrams/security.svg)

Layered defense in depth, key points:

- **Gateway layer** (tracking-gateway): API-Key authentication, Redis rate limiting keyed by a hash of the API key, per-carrier circuit breaking, SSRF protection (whitelist resolution of worker endpoints); the internal gRPC listener defaults to `127.0.0.1:8792` (`INTERNAL_GRPC_LISTEN`) and requires the shared `x-internal-token`; both PHP and the gateway refuse to run with an empty `INTERNAL_TOKEN`, and the gateway also refuses to start with an empty `internal_token` (`TRACKING_GATEWAY_INTERNAL_TOKEN`); credential isolation — e-cat holds no plaintext credentials;
- **Application layer** (admin): JWT + blacklist (2h access / 14d refresh), strict token-type separation (`access` / `refresh` / `client`), single-use refresh rotation (atomic Redis `SET NX` + blacklist), disabled admins blocked on every request, RBAC method.path-granularity permissions, full-chain operation audit; `SecurityFilter` blocks XSS / SQL injection / CSRF / command injection / path traversal; sensitive data stored encrypted with `Encryptable` + masked export; login locked for 15 minutes after 5 failures (keyed by username + IP) + click captcha; the Docker nginx config overwrites `X-Forwarded-For` so clients cannot spoof their IP;
- **Secrets**: `JWT_SECRET_KEY`, `ENCRYPTABLE_KEY`, `ENCRYPTION_KEY` and `INTERNAL_TOKEN` have no code defaults; the installer generates random values into `.env` (and syncs `.env.docker` under Docker); an installed environment refuses to start when `JWT_SECRET_KEY`, `ENCRYPTABLE_KEY` or `ENCRYPTION_KEY` is missing or still equal to an old public default (`APP_DEBUG=true` only warns), while an empty `INTERNAL_TOKEN` makes the internal gRPC service reject every request; the old public defaults in `.env.example` must never be used in production;
- **Callback security**: whitelist route + HMAC-SHA256 signature verification, fail-closed when `webhook_secret` is missing (HTTP 401; `CALLBACK_ALLOW_UNSIGNED=1` is development-only), unique-key dedupe for at-least-once delivery, max 100 events per callback with field truncation, real HTTP 4xx/5xx responses instead of always-200;
- **Unified error semantics**: rate limit 429, circuit break 503, worker failure 502 — upstream exception details stay in server logs and clients receive generic errors.
- **Payment security** (M8/M10): Stripe / PayPal webhook verification (HMAC-SHA256 / verify-webhook-signature); orders are confirmed only for a locally existing order and only on a real payment event (Stripe `payment_status=paid` including `async_payment_succeeded`; PayPal `PAYMENT.CAPTURE.COMPLETED`, with `CHECKOUT.ORDER.APPROVED` triggering `/capture`); manual orders stay `pending` until an admin confirms them; confirmation is transactional with an app row lock and Redis is written after commit; `orders.tx_id` has a unique index and a duplicate transaction returns 422; TRC20 payment amounts carry a unique 0.01–0.99 offset and repeated `pay` reuses the displayed amount; payment keys stored encrypted via `Encryptable` in `logistics_system_config`;
- **Crypto payment verification** (M9): USDT TRC20 auto-verified via the Tronscan API; BEP20 / ERC20 confirmed manually;
- **Client key security** (M7): X-API-Key is client-set (≥16 chars), stored as sha256 — plaintext returned only once at creation; client JWTs (token_type=client) isolated from admin JWTs;
- **Gateway attack detection** (M11): `ecat-security` SecurityBodyLayer integrated into the gateway (injection / protocol / data serialization / file / sensitive-data leak detectors); attack payloads are blocked at the gateway layer, with the application-layer security package as backstop;
- **CDN security** (M12): Cloudflare free plan full-site HTTPS + dual-layer WAF (edge managed rules + gateway application-layer detection); Tunnel origin keeps the source zero-exposed; callbacks go through DNS-only subdomain direct connection to avoid order loss on CDN outage; rate limiting counts by X-API-Key, unaffected by CDN edge IPs; authenticated endpoints are always no-store to prevent cross-user cache mixing;

## Features

![Platform features](diagrams/description.svg)

- **Aggregated tracking queries: one tracking number across the globe — 187 number-pattern rules auto-detect the domestic/international channel and carrier, 209 carrier adapters unify output into 7 standard `TrackStatus` states;**
- **Multi-carrier integration: 45 domestic + 164 international adapters, full coverage of DHL / FedEx / UPS / USPS and national posts S10; carrier credentials are injected via the `global-logistics` config template, platform/payment secrets are encrypted at rest with Encryptable, and no real keys are hardcoded;**
- **Admin RBAC: JWT + blacklist + method.path granular permissions + full audit trail, security filter blocks XSS / SQL injection / CSRF / command injection;**
- **Payment closed loop: Stripe / PayPal plus USDT TRC20 / BEP20 / ERC20, webhook signature verification auto-confirms orders (manual orders require admin confirmation), duplicate `tx_id` rejected, payment methods take effect via config;**
- **Client portal & plans: register / login / app management / plans / orders API, self-set X-API-Key, client JWT fully isolated from admin, single-use refresh tokens;**
- **API gateway protection: API-Key auth, Redis rate limiting (429), per-carrier circuit breaker (503), worker failure (502), SSRF protection, attack payloads blocked at the gateway layer;**
- **CDN secure delivery: Cloudflare free plan full-site HTTPS + dual WAF + edge cache, Tunnel origin with zero public exposure;**

## One-Click Install

Recommended: one-command Docker Compose deployment — starts 4 services (Nginx / PHP / MySQL / Redis) with health checks and data persistence:

```bash
bash install.sh
```

After cloning the repository:

```bash
cd integrated-global-logistics   # enter project root
bash install.sh                  # port 80 by default, override with NGINX_PORT=8080
```

The script checks the Docker environment, starts all services and polls `http://localhost/health` (up to 120 seconds); once ready, visit `http://localhost/install` to complete the installation wizard. In Docker the database host is **`mysql`** (the service name, not `127.0.0.1`). The wizard creates the database tables, seeds permissions, and creates the admin account you choose — there is no default account. It also generates random values for `JWT_SECRET_KEY`, `ENCRYPTABLE_KEY`, `ENCRYPTION_KEY` and `INTERNAL_TOKEN` in `.env`, and syncs them into `.env.docker` under Docker. MySQL and Redis host ports are not published to the host, and only Nginx (80/443) is exposed.

After the wizard finishes, recreate the app container so the new keys take effect:

```bash
cd admin && docker compose up -d --force-recreate app
```

For a non-Docker deployment, restart the worker instead: `php start.php restart -d`. Then copy the generated `INTERNAL_TOKEN` into the query gateway's `internal_token` (or its `TRACKING_GATEWAY_INTERNAL_TOKEN` environment variable); the PHP worker and the gateway must share the same value. See [admin/README.md](../../../admin/README.md) for the detailed Docker Compose deployment.

## Quick Start

**admin management console** (PHP webman):

```bash
cd admin
composer install
php start.php start
```

After startup, open the installation wizard in your browser to complete database initialization and admin creation: `http://localhost:8787/install` (default port 8787, changeable in `config/server.php`). For manual (non-installer) deployments, provide `JWT_SECRET_KEY`, `ENCRYPTABLE_KEY` and `ENCRYPTION_KEY` in `.env`; generate the JWT key with `openssl rand -hex 32` and the two encryption keys with `openssl rand -hex 16` (they must be exactly 32 characters), and set `INTERNAL_TOKEN` for gRPC. There are no code defaults. After installation, restart the worker (`php start.php restart -d`) so the generated keys and database settings are loaded.

**infrastructure query gateway** (Rust e-cat):

```bash
cd infrastructure
cargo build --offline
TRACKING_GATEWAY_CONFIG=tracking-gateway/config/config.json ./target/debug/tracking-gateway
```

The gateway listens on `0.0.0.0:8080` by default; point `workers` at the PHP gRPC endpoint (`127.0.0.1:8792` by default) and set the same `internal_token` as `INTERNAL_TOKEN` (or export `TRACKING_GATEWAY_INTERNAL_TOKEN`). It refuses to start with an empty token. See [Known Limitations](#known-limitations) for the Rust workspace path dependency before building in a fresh environment.

For detailed deployment, see [admin/README.md](../../../admin/README.md) (Docker Compose orchestrates 4 services: Nginx / PHP / MySQL / Redis) and the implementation plan document.

## Upgrade to v1.2.0 (Breaking Changes)

v1.2.0 hardens authentication, secrets, the gateway and payments. Back up the database first, then apply the following before starting the new version:

1. **Required secrets**: set `JWT_SECRET_KEY`, `ENCRYPTABLE_KEY` (32 chars), `ENCRYPTION_KEY` (32 chars) and `INTERNAL_TOKEN` in `.env`. Code defaults were removed; an installed environment refuses to start when `JWT_SECRET_KEY`, `ENCRYPTABLE_KEY` or `ENCRYPTION_KEY` is missing or still the old public default (`APP_DEBUG=true` only warns), while an empty `INTERNAL_TOKEN` makes the internal gRPC service reject every request. Generate the JWT key with `openssl rand -hex 32` and each 32-character encryption key with `openssl rand -hex 16` (the installer does this automatically for new deployments and syncs `.env.docker` under Docker).
2. **Existing encrypted data**: if data was encrypted with the old public `ENCRYPTABLE_KEY=open-admin-db-encryption-key-32b`, add `ENCRYPTION_PREVIOUS_KEYS=open-admin-db-encryption-key-32b`, start the app so decryption works, then re-encrypt the affected rows with the new key and remove the previous key only after verification. Without the previous key, decryption fails.
3. **Default admin removed**: `install.sql` no longer seeds `erik/123456`; the installer no longer pre-fills `admin888`, creates the admin with prepared statements, binds the `super_admin` role, and refuses to reinstall while an admin exists. Keep `runtime/install.lock`. If you are upgrading an existing database, verify or remove the old default account and change any known default credentials; back up before re-running the installer.
4. **Callback secret**: configure `webhook_secret` for each carrier. A callback for a carrier without a secret now returns HTTP 401 by default; `CALLBACK_ALLOW_UNSIGNED=1` is development-only. Callback responses now use real HTTP 4xx/5xx status codes.
5. **Database**: `logistics_order.tx_id` now has a unique index (`uk_tx_id`). Check for duplicate non-null `tx_id` values before upgrading; a duplicate transaction later returns 422.
6. **Gateway token**: `INTERNAL_TOKEN` must match the gateway's `internal_token` (or `TRACKING_GATEWAY_INTERNAL_TOKEN`). The gateway refuses to start with an empty token, and the PHP internal service rejects every request when its token is empty. The gRPC listener defaults to loopback `127.0.0.1:8792` (`INTERNAL_GRPC_LISTEN`) with 8 workers (`INTERNAL_GRPC_WORKERS`); do not expose it publicly.
7. **Restart after install**: Docker uses `docker compose up -d --force-recreate app`; non-Docker uses `php start.php restart -d`. Old processes would otherwise keep the previous empty keys and database configuration.
8. **Rate limiting now enforces**: the phpredis `eval` argument signature was fixed, so limits that previously failed open now return 429. Rate-limit keys use the API-key hash, the login lockout is keyed by username + IP, and the bundled Docker nginx config overwrites `X-Forwarded-For`.
9. **Gateway errors**: the old `5001` carrier error code is gone; carrier/upstream failures use HTTP `502`, the gateway error set is `400/401/404/429/502/503/500`, and upstream details stay in logs.
10. **Docker networking**: MySQL and Redis host ports are no longer published; Nginx proxies `app:8787` and overwrites `X-Forwarded-For`. Use the bundled `admin/docs/nginx-docker.conf`.

## Known Limitations

- Gateway single-flight is not implemented; concurrent misses may hit the carrier more than once. A 15-second negative cache limits repeated failures only.
- The Rust workspace still declares an absolute path dependency on `/home/wwwroot/erikwang2013/security-rust` (1.0.8); `cargo check`/`cargo build` fail in standard environments that do not have that path.
- The installer has not been exercised end-to-end against a fresh MySQL instance in CI.
- The Hashids salt is empty by default (`config/hashids.php`); Hashids is ID obfuscation, not a security boundary. Do not rely on it for authorization.
- Export temp-file cleanup and `logistics_tracking_query` retention are not implemented; plan disk/table maintenance.
- Manual crypto payments (BEP20 / ERC20) still require admin confirmation.

## Documentation

- [admin/docs/API.md](../../../admin/docs/API.md) — API reference (unified response format, error codes, authentication flow, rate-limit policies, middleware chain)
- [admin/docs/ARCHITECTURE.md](../../../admin/docs/ARCHITECTURE.md) — architecture design
- [admin/docs/DESIGN.md](../../../admin/docs/DESIGN.md) — design document
- [admin/docs/SECURITY.md](../../../admin/docs/SECURITY.md) — security architecture
- [admin/README.md](../../../admin/README.md) — full admin console documentation (tech stack, database conventions, deployment, CI/CD)

## Translations

- [English](README.md)
- [中文](../../../README.md)

## Open Source Is Not Easy — Support Welcome

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](../../weixinpay.png) | ![Alipay](../../alipay.png) |

## 项目授权 / Project License

Copyright (c) 2026 erik erik@erik.xyz — https://erik.xyz

- 本项目仅供个人学习、研究使用。
- 未经作者书面授权，禁止任何形式的商业使用（包括但不限于商业部署、付费服务、二次销售、SaaS 运营及以本项目为基础提供商业服务）。
- 商业授权请联系 **erik@erik.xyz**。
- 未经授权的商业使用，作者将在全球范围内依法追究法律责任，并保留终身追责的权利。
- 第三方组件仍遵循其各自原有的许可证；视觉资产适用仓库内的 ASSETS-LICENSE.md（如适用）。

**English:** This project is provided for personal study and research only. Commercial use of any kind requires prior written authorization from the author (erik@erik.xyz). Unauthorized commercial use will be pursued for legal liability worldwide, and the author reserves the right to pursue such liability for life. Third-party components remain under their original licenses; visual assets are governed by ASSETS-LICENSE.md where applicable.
