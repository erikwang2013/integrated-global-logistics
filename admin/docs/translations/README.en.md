# Open Admin (open-admin)

A full-stack admin dashboard built with webman v2 + Layui.

> [中文](../../README.md) | [English](README.en.md)

## Features

![Features](../../../docs/diagrams/description.svg)


| Domain | Feature | Notes |
|--------|---------|-------|
| 🔐 Auth | Login/Refresh/Logout | Click captcha + JWT + blacklist |
| | Account lockout | 5 failures → 15 min lock |
| | Concurrent session limit | Max 3 active tokens per user |
| 📊 Dashboard | Real-time stats/trends/distribution/logs | Redis cached 5 min |
| 👥 Users | CRUD + batch delete/toggle status | Soft delete + password confirmation |
| | Excel batch import | Row-level validation + error report |
| 🔒 Roles & Perms | Role CRUD + permission tree | RBAC method.path granularity |
| ⚙ Config | Key-value CRUD | Grouped management |
| 📋 Audit | Log query + source detection | 8 platforms auto-detected |
| 📁 Files | Upload/Excel export/PDF export | Sensitive data auto-masked |
| 🛡 Security | 18-layer defense-in-depth | XSS/SQLi/path traversal/cmd injection/CSRF/rate limit/CSP... |
| 🏥 Ops | Health check/metrics/API docs/security.txt | Prometheus + OpenAPI 3.0 + hg/apidoc interactive docs |
| 🌐 i18n | Chinese/English | Accept-Language header / ?lang= param |

## Copyright

```
Copyright (c) 2026 erik erik@erik.xyz — https://erik.xyz
```

This copyright notice is permanent, must not be modified, removed, or reversed. All project files are protected under this copyright.

## Tech Stack

| Layer | Technology | Notes |
|---|------|------|
| Backend | webman v2 (workerman) | High-performance PHP daemon framework |
| PHP | 8.3+ | |
| Database | MySQL 8.0+ | Table prefix `logistics_`, BIGINT non-auto-increment PKs |
| Admin UI | Layui | Static assets under `public/`, rendered by PHP controllers |

## Core Packages

| Package | Purpose |
|---|------|
| `erikwang2013/snowflake-php` | Globally unique BIGINT primary key generation |
| `erikwang2013/hashids` | API-layer ID encryption to hide real database IDs |
| `erikwang2013/jwt-webman` | JWT token issuance and verification |
| `erikwang2013/encryption` | Transport-layer sensitive data encryption |
| `erikwang2013/encryptable` | Database-layer sensitive field auto encryption |
| `erikwang2013/season` | Country flag data |
| `erikwang2013/poster-php` | Click captcha generation/verification + poster generation |
| `phpoffice/phpspreadsheet` | Excel export |
| `barryvdh/laravel-dompdf` | PDF export (Dompdf-based) |

## Project Structure

```
open-admin/
├── app/
│   ├── admin/controller/       # Admin controllers
│   │   ├── DashboardController.php # Dashboard (Redis cached)
│   │   ├── UserController.php      # User CRUD + batch ops
│   │   ├── RoleController.php      # Role CRUD
│   │   ├── PermissionController.php# Permission CRUD
│   │   ├── ConfigController.php    # System config CRUD
│   │   ├── LogController.php       # Operation log viewer
│   │   ├── ProfileController.php   # Profile + logout
│   │   ├── ExportController.php    # Excel/PDF export
│   │   ├── ImportController.php    # Excel import users
│   │   ├── UploadController.php    # File upload
│   │   ├── HealthController.php    # Health check
│   │   └── DocsController.php      # OpenAPI docs
│   ├── api/
│   │   └── v1/controller/          # API v1 (version in URL path)
│   │       ├── CaptchaController.php
│   │       └── AuthController.php    # Login/Refresh
│   ├── middleware/             # Middleware
│   │   ├── Cors.php            # CORS
│   │   ├── SecurityFilter.php  # Attack detection (HTTP method restriction/XSS/SQLi/path traversal/cmd injection/CSRF)
│   │   ├── RateLimit.php       # Redis rate limiting
│   │   ├── AdminAuth.php       # JWT auth + blacklist
│   │   ├── AdminPermission.php # RBAC authorization
│   │   └── OperationLog.php    # Auto operation logging (with source detection)
│   └── model/                  # Eloquent models
├── config/                     # Config files
├── database/install.sql        # SQL install script (incl. permission seeds)
└── vendor/                     # Composer dependencies
```

## Requirements

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0

## Quick Start

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

```bash
cp .env.example .env
```

Key environment variables:

| Variable | Description | Default |
|---------|-------------|---------|
| `JWT_SECRET` | JWT signing key | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids salt | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API encryption key | 32-byte default |
| `SNOWFLAKE_DATACENTER_ID` | Datacenter ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | Worker ID (0-31) | `1` |

**Always change all keys to random strings in production.**

### 3. One-Click Install

Start the server, then open the install wizard in your browser to set up the database and create an admin account:

```bash
php start.php start
```

Default: `http://0.0.0.0:8787` (change port in `config/server.php`).

Open **`http://localhost:8787/install`** and follow the wizard:

| Step | Description |
|------|-------------|
| ① Database | Host, port, database name, username, password |
| ② Admin Account | Admin username and password (default: admin / admin888) |

Click "Start Install" — tables are created, permissions seeded, admin account created, and `.env` is updated automatically.

> After installation, `runtime/install.lock` is created to prevent re-installation. Delete this file to re-install.

### 4. Login

Visit `http://localhost:8787` and log in with the admin credentials set during installation.

### 6. Docker Compose (Recommended for Production)

Full Docker orchestration with 4 services: Nginx, PHP (webman app), MySQL, Redis.

```bash
# 1. Configure Docker environment variables
cp .env.docker .env

# 2. Start all services
docker-compose up -d

# 3. Open the install wizard in your browser
# http://localhost:8787/install  (fill in DB and admin info)
# Or run SQL migration manually (inside the app container):
# docker-compose exec app mysql -h mysql -u root -p < database/install.sql

# 4. Access
# http://localhost:8787  (webman)
# http://localhost:8080  (Nginx reverse proxy)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, based on `php:8.3-cli`
- `docker-compose.yml`: 4 services, isolated network, persistent volumes
- `.env.docker`: Docker-specific environment variables

## Database Conventions

- **Prefix**: `logistics_`
- **Primary Key**: `id BIGINT UNSIGNED NOT NULL`, **NO AUTO_INCREMENT**
- **ID Generation**: PKs are generated at the application layer via `SnowflakeService::generate()`
- **Required Columns**: Every table must have `id`, `created_at`, `updated_at`
- **Soft Delete**: Add `deleted_at DATETIME DEFAULT NULL` where needed
- **Sensitive Fields**: Phone, email, ID card — stored as ciphertext via the `encryptable` plugin, database column type `VARCHAR(500)`

## API Documentation

The complete API reference (response format, error codes, all endpoint details, auth flow, rate limiting, middleware chain) lives in **[docs/API.en.md](../API.en.md)**. Highlights:

- **Response format**: `{ "code": 0, "message": "success", "data": {...} }`, `code=0` means success
- **Error codes**: `400` bad request / `401` unauthenticated / `403` forbidden / `404` not found / `422` validation failed / `429` rate limited / `500` server error
- **Authentication**: `Authorization: Bearer <token>`; access_token TTL 2h, refresh_token TTL 14d
- **ID handling**: IDs in requests/responses are hashid-encrypted strings, real database IDs are never exposed

## Frontend Notes

### Admin UI (Layui)

- Admin pages and static assets live under `public/`, using bundled Layui components.
- Login, dashboard, users, roles, config, logs, and profile pages are rendered directly by PHP controllers.

## Development Rules

- No leading `\` on global function/class references — use `use` imports
- All PHP files must include the copyright header
- All config files must include inline comments
- Primary keys must be generated at the application layer via snowflake — no auto-increment
- All IDs in API parameters and responses must be encoded/decoded via hashids
- AdminPermission middleware uses Redis cache for user permissions (TTL=60s), eliminating N+1 query bottlenecks

## Deployment

### Docker Compose (Recommended)

`docker-compose.yml` in the project root orchestrates 4 services:

| Service | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | Local `Dockerfile` build | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |

The PHP image is built from `Dockerfile`, based on `php:8.3-cli` with OPcache enabled.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

GitHub Actions CI pipeline: `.github/workflows/ci.yml`

- PHP syntax check (`php -l`)
- PHPUnit tests

### Database Backup

`database/backup/` directory:

- `backup.sh` — mysqldump + gzip backup, auto-clears backups older than 30 days
- `restore.sh` — interactive restore, lists available backups for selection

### Nginx Security

See `docs/nginx-security.conf` for production reverse-proxy security hardening.

## Open Source Needs Your Support

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](../weixinpay.png "WeChat") | ![Alipay](../alipay.png "Alipay") |

## 项目授权 / Project License

Copyright (c) 2026 erik erik@erik.xyz — https://erik.xyz

- 本项目仅供个人学习、研究使用。
- 未经作者书面授权，禁止任何形式的商业使用（包括但不限于商业部署、付费服务、二次销售、SaaS 运营及以本项目为基础提供商业服务）。
- 商业授权请联系 **erik@erik.xyz**。
- 未经授权的商业使用，作者将在全球范围内依法追究法律责任，并保留终身追责的权利。
- 第三方组件仍遵循其各自原有的许可证；视觉资产适用仓库内的 ASSETS-LICENSE.md（如适用）。

**English:** This project is provided for personal study and research only. Commercial use of any kind requires prior written authorization from the author (erik@erik.xyz). Unauthorized commercial use will be pursued for legal liability worldwide, and the author reserves the right to pursue such liability for life. Third-party components remain under their original licenses; visual assets are governed by ASSETS-LICENSE.md where applicable.
