# Logistics Aggregation Platform (Integrated Global Logistics)
<img src="../../diagrams/mascot.svg" alt="E-Cat 项目吉祥物" width="220" align="right">

One-stop platform for global logistics tracking: the **admin management console** (PHP webman + Flutter) hosts the management plane and the query worker pool, the **e-cat high-frequency gateway** (long-running Rust process) carries the query traffic, and the **global-logistics unified facade** (PHP adapters for 209 carriers) lets you query the whole world through a single entry.

> Languages: [English](/docs/translations/en/README.md) · [한국어](/docs/translations/ko/README.md) · [Русский](/docs/translations/ru/README.md) · [Deutsch](/docs/translations/de/README.md) · [Français](/docs/translations/fr/README.md) · [Español](/docs/translations/es/README.md) · [Português](/docs/translations/pt/README.md) · [हिन्दी](/docs/translations/hi/README.md) · [العربية](/docs/translations/ar/README.md) · [বাংলা](/docs/translations/bn/README.md) · [Bahasa Indonesia](/docs/translations/id/README.md) · [日本語](/docs/translations/ja/README.md) ([Jump to translations](#translations-other-languages))

## Project Introduction

<img src="diagrams/intro.svg" alt="Project introduction" width="100%">

The logistics aggregation platform unifies tracking queries from **209** courier / postal carriers worldwide into one platform: merchants and C-side clients pass in only a tracking number, and the platform automatically identifies the domestic / international channel and the carrier — no need to care about each carrier's protocol differences (signature, OAuth2, XML/JSON, status mapping).

The platform is composed of three components working together:

- **admin management console** (PHP webman v2 + Flutter) — management plane and PHP worker pool: carrier profiles, encrypted key management, query records, statistics reports, callback subscription configuration, complete RBAC / JWT / operation audit system;
- **tracking-gateway high-frequency gateway** (Rust e-cat framework) — the first entry of the external query API: Redis cache, rate limiting, per-carrier circuit breaking, worker load balancing; it only handles the high-frequency plane and knows nothing about carrier protocols;
- **global-logistics unified facade** (PHP package) — adapters for 209 carriers (45 domestic + 164 international), 187 tracking-number auto-detection rules, 7 unified status semantics of `TrackStatus`.

**Current progress**: M1–M13 all complete — M1 admin plane (carrier / credential / query record / subscription CRUD), M2 query gateway (full external API chain), M3 callback subscriptions, M4 monitoring & statistics, M5 external OpenAPI docs, M6 five client SDKs, M7 client portal (register / app / plan / order), M8 payments (Stripe / PayPal), M9 crypto (USDT TRC20 / BEP20 / ERC20), M10 payment method configuration, M11 gateway security middleware, M12 CDN plan (Cloudflare + cache headers), M13 CDN provider management. The client → e-cat → worker → carrier tracking query chain is demonstrable, and the five zero-dependency SDKs are ready to copy and use.

## Project Description

<img src="diagrams/description.svg" alt="Project description" width="100%">

- **One entry**: `Logistics::track($trackingNo)` automatically identifies the domestic / international channel and carrier; the business layer only deals with one shape;
- **Auto detection**: 187 tracking-number regex rules are order-sensitive and prioritize domestic channels; for unrecognized scenarios, `domestic()` / `international()` can be called explicitly;
- **Unified status**: each carrier's varied raw statuses are mapped to a unified `TrackStatus` enum (pending pickup / in transit / out for delivery / delivered / exception / returned / unrecognized);
- **Global coverage**: the four major couriers DHL, FedEx, UPS, USPS and the national postal S10 systems of various countries (Europe, Latin America & Caribbean, Africa & Middle East, Asia-Pacific);
- **External API**: the e-cat query gateway provides API-Key authentication, Redis cache hits (`X-Cache: HIT`), rate limiting 429, per-carrier circuit breaking 503, RoundRobin worker load balancing; five zero-dependency SDKs (Python / PHP / Node.js / Go / Rust) ready to copy and use;
- **Client portal & billing** (M7–M10): client register / login (client JWT isolated from admin), app management with self-set X-API-Key, plan / order API; Stripe / PayPal plus USDT TRC20 / BEP20 / ERC20 crypto payments, Stripe payment methods (Apple Pay / Google Pay / Klarna / SEPA etc.) configurable at runtime;
- **CDN acceleration** (M12/M13): Cloudflare free plan full-site HTTPS + edge caching for static assets, CDN provider / domain / credential management in the admin panel (credentials encrypted);
- **Zero hard-coded keys**: all keys are injected via configuration, and the database layer stores them as Encryptable ciphertext — code and keys are fully separated.

## Project Architecture

<img src="diagrams/architecture.svg" alt="Project architecture" width="100%">

Query chain: **client → e-cat query gateway → PHP worker pool → 209 carriers**.

The e-cat gateway (Rust) handles API-Key authentication, Redis cache hits, rate limiting, per-carrier circuit breaking and RoundRobin load balancing for the external API; cache hits, rate-limit rejections and circuit-breaker fast-fails all happen on the e-cat side, and PHP workers only carry real query traffic — horizontal scaling is just adding workers.

**The plan of e-cat reusing 209 PHP adapters**: the 209 adapters are PHP (`src/Carriers/Domestic` 45 + `International` 164); rewriting them in Rust would be months of work and would lose the benefit of continuous upstream package updates. e-cat does not need to understand carrier protocols — it depends only on a stable internal contract (`/internal/tracking/query` + `/internal/carriers` registry sync). Credentials are never delivered to e-cat, keeping a clear security boundary.

Management plane (browser) → `/admin/*`: JWT + RBAC permissions + operation audit, covering carrier / carrier-credential / tracking-query / callback-subscription / statistics / client / client-app / plan / order / cdn-provider.

## Project Structure

<img src="diagrams/structure.svg" alt="Project structure" width="100%">

```
integrated-global-logistics/
├── admin/                          # PHP webman v2 管理后台 + worker 池
│   ├── app/
│   │   ├── admin/controller/       # 管理端控制器（carrier、tracking、统计等）
│   │   ├── api/                    # API v1（验证码 / 登录 / 刷新令牌）
│   │   ├── common/                 # Hashids / Snowflake / 加密脱敏服务
│   │   ├── middleware/             # 安全过滤 / 限流 / JWT / RBAC / 审计
│   │   └── model/                  # 数据模型（logistics_ 前缀，snowflake 主键）
│   ├── apps/
│   │   ├── flutter/                # Flutter Web 管理后台（PC 风格）
│   │   └── harmonyos/              # HarmonyOS 原生客户端
│   ├── config/                     # 配置（含中文注释）
│   ├── database/
│   │   └── install.sql             # SQL 安装脚本（含权限种子数据）
│   └── docs/                       # API / 架构 / 设计 / 安全文档（12 语言）
├── infrastructure/                 # Rust e-cat 微服务框架 + 查询网关
│   ├── ecat/                       # 聚合 crate（App 骨架）
│   ├── ecat-transport-http/        # axum HTTP 传输
│   ├── ecat-data-redis/            # 轨迹缓存 + 限流存储
│   ├── ecat-client/                # RoundRobin 负载均衡
│   └── tracking-gateway/           # 对外查询网关（workspace 新成员）
├── sdk/                            # 对外 API 客户端 SDK（Python / PHP / Node.js / Go / Rust）
└── docs/                           # 平台规划与图示
    ├── diagrams/                   # SVG 架构图（本 README 引用）
    ├── translations/               # 12 语言 README
    └── logistics-aggregation-platform-plan.md  # 实施规划
```

## Lifecycle

<img src="diagrams/lifecycle.svg" alt="Lifecycle" width="100%">

**Query chain (synchronous)**: client → API-Key auth → Redis rate limiting → cache lookup (return on hit, `X-Cache: HIT`) → circuit-breaker check (503 fast-fail on OPEN) → RoundRobin worker selection → the `Logistics` facade in the PHP worker (the in-package RetryingClient has 2 built-in retries) → 209 carriers → persist to `logistics_tracking_query` + write cache → return standardized JSON.

**Callback chain (asynchronous)**: carrier webhook → `/api/callback/{carrier}` whitelist route + signature verification → persist to `logistics_tracking_event` + update the query record → write to the webman queue → async consumers push to the merchant callback URL per subscription config (HMAC signature + idempotency key + exponential backoff retry + manual re-push entry).

> In the first release, callback push stays in the PHP queue — event parsing and data are both on the PHP side, and there is no benefit in passing events across languages; if push throughput becomes a bottleneck (above tens of thousands per minute), migrate the consumer to e-cat (ecat-mq + retry middleware) without changing the external contract.

## Security

<img src="diagrams/security.svg" alt="Security" width="100%">

Layered defense in depth, key points:

- **Gateway layer** (tracking-gateway): API-Key authentication, Redis rate limiting (by key / IP), per-carrier circuit breaking, SSRF protection (whitelist resolution of worker endpoints); `/internal` listens only on the internal network + shared secret header; credential isolation — e-cat holds no plaintext credentials;
- **Application layer** (admin): JWT + blacklist (2h access / 14d refresh), RBAC method.path-granularity permissions, full-chain operation audit; `SecurityFilter` blocks XSS / SQL injection / CSRF / command injection / path traversal; sensitive data stored encrypted with `Encryptable` + masked export; login locked for 15 minutes after 5 failures + click captcha;
- **Callback security**: whitelist route + HMAC signature verification, at-least-once delivery + idempotency key to prevent duplicate pushes;
- **Unified error semantics**: rate limit 429, circuit break 503, carrier error `carrier_error` — no internal details leaked to clients.
- **Payment security** (M8/M10): Stripe / PayPal webhook verification (HMAC-SHA256 / verify-webhook-signature), auto order confirmation + manual admin fallback; payment keys stored encrypted via `Encryptable` in `logistics_system_config`;
- **Crypto payment verification** (M9): USDT TRC20 auto-verified via the Tronscan API; BEP20 / ERC20 confirmed manually;
- **Client key security** (M7): X-API-Key is client-set (≥16 chars), stored as sha256 — plaintext returned only once at creation; client JWTs (token_type=client) isolated from admin JWTs;
- **Gateway attack detection** (M11): `ecat-security` SecurityBodyLayer integrated into the gateway (injection / protocol / data serialization / file / sensitive-data leak detectors); attack payloads are blocked at the gateway layer, with the application-layer security package as backstop;
- **CDN security** (M12): Cloudflare free plan full-site HTTPS + dual-layer WAF (edge managed rules + gateway application-layer detection); Tunnel origin keeps the source zero-exposed; callbacks go through DNS-only subdomain direct connection to avoid order loss on CDN outage; rate limiting counts by X-API-Key, unaffected by CDN edge IPs; authenticated endpoints are always no-store to prevent cross-user cache mixing;
- **CDN credential management** (M13): CDN provider access_key / access_secret encrypted with `Encryptable` in the `logistics_cdn_provider` table, configured via `/admin/cdn/provider`;

## Features

<img src="diagrams/description.svg" alt="Platform features" width="100%">

- **Aggregated tracking queries: one tracking number across the globe — 187 number-pattern rules auto-detect the domestic/international channel and carrier, 209 carrier adapters unify output into 7 standard `TrackStatus` states;**
- **Multi-carrier integration: 45 domestic + 164 international adapters, full coverage of DHL / FedEx / UPS / USPS and national posts S10, credentials encrypted at rest, zero hardcoded keys;**
- **Admin RBAC: JWT + blacklist + method.path granular permissions + full audit trail, security filter blocks XSS / SQL injection / CSRF / command injection;**
- **Payment closed loop: Stripe / PayPal plus USDT TRC20 / BEP20 / ERC20, webhook signature verification auto-confirms orders, payment methods take effect via config;**
- **Client portal & plans: register / login / app management / plans / orders API, self-set X-API-Key, client JWT fully isolated from admin;**
- **API gateway protection: API-Key auth, Redis rate limiting (429), per-carrier circuit breaker (503), SSRF protection, attack payloads blocked at the gateway layer;**
- **CDN secure delivery: Cloudflare free plan full-site HTTPS + dual WAF + edge cache, Tunnel origin with zero public exposure;**
- **Multi-language SDKs: five zero-dependency SDKs for Python / PHP / Node.js / Go / Rust, copy and run.**

## One-Click Install

Recommended: one-command Docker Compose deployment — starts 5 services (Nginx / PHP / MySQL / Redis / Elasticsearch) with health checks and data persistence:

```bash
bash install.sh
```

After cloning the repository:

```bash
cd integrated-global-logistics   # enter project root
bash install.sh                  # port 80 by default, override with NGINX_PORT=8080
```

The script checks the Docker environment, starts all services and polls health checks (up to 120 seconds); once ready, visit `http://localhost/install` to complete the installation wizard (database initialization + admin creation). See [admin/README.md](../../admin/README.md) for the detailed Docker Compose deployment.

## Quick Start

**admin management console** (PHP webman):

```bash
cd admin
composer install
php start.php start
```

After startup, open the installation wizard in your browser to complete database initialization and admin creation: `http://localhost:8787/install` (default port 8787, changeable in `config/server.php`).

**infrastructure query gateway** (Rust e-cat):

```bash
cd infrastructure
cargo build
```

**SDK usage** (five zero-dependency clients, ready to copy and use):

```python
from tracking_client import TrackingClient
client = TrackingClient("demo-api-key", "http://127.0.0.1:8080")
result = client.query_tracking("LX123456789CN")
```

```bash
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/tracking/query \
  -H "Content-Type: application/json" -d '{"tracking_no": "LX123456789CN"}'
```

See [sdk/README.md](../../../sdk/README.md) for usage and examples in each language.

For detailed deployment, see [admin/README.md](../../../admin/README.md) (Docker Compose orchestrates 5 services: Nginx / PHP / MySQL / Redis / Elasticsearch) and the implementation plan document.

## Documentation

- [admin/docs/API.md](../../../admin/docs/API.md) — API reference (unified response format, error codes, authentication flow, rate-limit policies, middleware chain)
- [admin/docs/ARCHITECTURE.md](../../../admin/docs/ARCHITECTURE.md) — architecture design
- [admin/docs/DESIGN.md](../../../admin/docs/DESIGN.md) — design document
- [admin/docs/SECURITY.md](../../../admin/docs/SECURITY.md) — security architecture
- [docs/logistics-aggregation-platform-plan.md](../../../docs/logistics-aggregation-platform-plan.md) — platform implementation plan (architecture, data flow, database design, API contracts, milestones)
- [admin/README.md](../../../admin/README.md) — full admin console documentation (tech stack, database conventions, deployment, CI/CD)
- [sdk/README.md](../../../sdk/README.md) — external API client SDKs (Python / PHP / Node.js / Go / Rust, five zero-dependency, copy-and-run)

## Translations (other languages)

- [English](/docs/translations/en/README.md)
- [한국어](/docs/translations/ko/README.md)
- [Русский](/docs/translations/ru/README.md)
- [Deutsch](/docs/translations/de/README.md)
- [Français](/docs/translations/fr/README.md)
- [Español](/docs/translations/es/README.md)
- [Português](/docs/translations/pt/README.md)
- [हिन्दी](/docs/translations/hi/README.md)
- [العربية](/docs/translations/ar/README.md)
- [বাংলা](/docs/translations/bn/README.md)
- [Bahasa Indonesia](/docs/translations/id/README.md)
- [日本語](/docs/translations/ja/README.md)

## Open Source Is Not Easy — Support Welcome

| WeChat | Alipay |
|:---:|:---:|
| <img src="../../weixinpay.png" width="130" height="130" alt="WeChat"> | <img src="../../alipay.png" width="130" height="130" alt="Alipay"> |

### Global Transfer Tip (Cross-border Remittance)

**Payee information**

- Payee name: WANG KEXUN
- Payee account number: 881015918251

**Receiving bank**

- ZA Bank SWIFT Code: AABLHKHHXXX
- Bank name: ZA Bank Limited
- Bank code: 387
- Bank address: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Cross-border remittance agent bank (if required)**

> This is the agent (intermediary) bank for cross-border remittance, not the receiving bank. Please check with your remitting bank whether the agent bank information is required.

- **For HKD, CNY and USD remittances**, the agent bank is Citibank:
  - Bank name: Citibank N.A. Hong Kong
  - SWIFT Code: CITIHKHXXXX
  - Bank code: 006
  - Branch name: Hong Kong Branch
  - Branch code: 391
  - Bank address: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **For other currencies**, the agent bank is BNY Mellon:
  - Bank name: THE BANK OF NEW YORK MELLON
  - SWIFT Code: IRVTUS3NXXX
  - Bank address: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

### Crypto Donation

If this project helps you, scan the QR code to donate, thank you!

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

<!-- TRANSLATE-READY -->
