> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](API.md) | [English](API.en.md) | [한국어](API.ko.md) | [Русский](API.ru.md) | [Deutsch](API.de.md) | [Français](API.fr.md) | [Español](API.es.md) | [Português](API.pt.md) | [हिन्दी](API.hi.md) | [العربية](API.ar.md) | [বাংলা](API.bn.md) | [Bahasa Indonesia](API.id.md) | [日本語](API.ja.md)

# API Reference

## 1. Overview

open-admin is built on webman v2 and provides a RESTful JSON API. All admin endpoints require JWT authentication and RBAC permission checks; public endpoints carry the API version directly in the URL path (`/api/v1/*`), resolved by route groups to versioned controllers.

- **Base URL**: `http://localhost:8787`
- **Language**: switched via the `Accept-Language` header or the `?lang=zh_CN|en` parameter (default zh_CN), auto-detected by the Locale middleware

> **Endpoint overview**: Auth (5) | Dashboard (1) | Users (7) | Roles (4) | Permissions (4) | Config (4) | Logs (1) | Profile (3) | Import/Export (3) | Upload (1) | Ops (4: health/metrics/docs/security.txt) | 37 endpoints in total
- **Authentication**: `Authorization: Bearer <token>` (JWT)
- **Response format**: `{ "code": 0, "message": "success", "data": {...} }`
- **Docs endpoint**: `GET /api/docs` returns the OpenAPI 3.0 JSON specification

### Request Requirements

- Only `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` methods are allowed; other HTTP methods (such as TRACE, CONNECT, PATCH) return 405
- All `POST` / `PUT` requests must set `Content-Type: application/json` (except file uploads), otherwise 415 is returned
- The request body must not exceed 10MB, otherwise 413 is returned
- The security filter scans all request inputs for XSS, SQL injection, path traversal, and command injection; hits return 403
- 5 consecutive login failures trigger account lockout (15 minutes); during lockout, login requests return 429
- A single user can hold at most 3 valid tokens concurrently; when exceeded, the oldest token is automatically blacklisted

## 2. Error Codes

| code | Meaning | Trigger |
|------|---------|---------|
| 0 | Success | |
| 400 | Bad request | Incorrect request format |
| 401 | Unauthenticated | Token missing / expired / blacklisted |
| 403 | No permission / security block | Insufficient RBAC permission / SecurityFilter hit |
| 404 | Resource not found | Target of query/update/delete does not exist |
| 405 | Method not allowed | Only GET/POST/PUT/DELETE/OPTIONS/HEAD allowed; non-standard methods rejected outright |
| 413 | Payload too large | Content-Length exceeds 10MB |
| 415 | Unsupported media type | POST/PUT Content-Type is not JSON and not a file upload |
| 422 | Validation failed | Required fields missing, invalid format, business validation failed |
| 429 | Too many requests | RateLimit triggered / account locked (5 failed logins lock for 15 min) |
| 500 | Internal server error | |

## 3. Public Endpoints

All public endpoints are mounted under the `/api/v1` route group (bound directly via `Route::group('/api/v1', ...)` in `config/route.php`); the version `v1` is the first path segment, resolved by routing to the corresponding versioned controller (e.g. `app\api\v1\controller\AuthController`).

### 3.1 Health Check

```
GET /health
```

- **Authentication**: none
- **Rate limit**: none

**Example response**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

`database`, `redis`, and `elasticsearch` take the values `"ok"` | `"unavailable"`. `elasticsearch` returns `"unavailable"` when ES is unreachable; when the cluster health status is neither green nor yellow, the actual status value is returned (e.g. `"red"`).

### 3.2 API Docs

```
GET /api/docs
```

- **Authentication**: none
- **Rate limit**: global default (60/min)
- **Response**: OpenAPI 3.0.3 JSON specification with all endpoint definitions, parameters, and schemas

### 3.3 Generate Captcha

```
POST /api/v1/captcha/generate
```

- **Authentication**: none
- **Rate limit**: global default (60/min)

**Request body**:
```json
{
  "difficulty": "medium"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| difficulty | string | No | `easy` / `medium` / `hard`, default `medium` |

**Example response** — click type (`type: "click"`):
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "type": "click",
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "extra": {
      "targets": [
        { "order": 1, "text": "A", "x": 120, "y": 85 },
        { "order": 2, "text": "B", "x": 310, "y": 42 }
      ]
    }
  }
}
```

**Example response** — slider type (`type: "slider"`):
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "def456abc789",
    "type": "slider",
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "extra": {
      "x": 120,
      "y": 60,
      "puzzle_w": 50,
      "puzzle_h": 50,
      "puzzle": "data:image/png;base64,iVBORw0KGgo..."
    }
  }
}
```

**Example response** — rotate type (`type: "rotate"`):
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "ghi789abc012",
    "type": "rotate",
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "extra": {
      "angle": 45
    }
  }
}
```

| Field | Type | Description |
|------|------|------|
| key | string | Captcha identifier, echoed back when verifying |
| type | string | Captcha type: `click` / `slider` / `rotate` |
| image | string | base64 data URI image |
| extra | object | Type-specific extra data (see below) |

**`extra` by type**:

| type | extra fields | Type | Description |
|------|-----------|------|------|
| click | targets | array | Click targets with `order` (sequence), `text` (prompt text), `x`, `y` (coordinates) |
| slider | x, y | int | Coordinates of the top-left corner of the gap (based on a 300×200 canvas) |
| slider | puzzle_w, puzzle_h | int | Puzzle piece width and height |
| slider | puzzle | string | Puzzle piece base64 data URI |
| rotate | angle | int | Correct rotation angle (0-359); the image must be rotated by `360-angle` to right itself |

### 3.4 Verify Captcha

```
POST /api/v1/captcha/verify
```

- **Authentication**: none
- **Rate limit**: global default (60/min)

**Request body** — click type (`type: "click"`):
```json
{
  "key": "abc123def456",
  "type": "click",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

**Request body** — slider type (`type: "slider"`):
```json
{
  "key": "def456abc789",
  "type": "slider",
  "clicks": 120
}
```

**Request body** — rotate type (`type: "rotate"`):
```json
{
  "key": "ghi789abc012",
  "type": "rotate",
  "clicks": 315
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| key | string | Yes | Captcha key returned by generate |
| type | string | Yes | Captcha type, must match the `type` returned by generate |
| clicks | variant | Yes | Answer data; format varies by type (see below) |

**`clicks` by type**:

| type | clicks type | Description | Error tolerance |
|------|------------|------|---------|
| click | `[{x:int, y:int}]` | Array of click coordinates, in `order` sequence | 18px radius |
| slider | `int` | Slider X-axis offset | ±4px |
| rotate | `int` | Rotation angle (0-359) | ±5° |

**Example response**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

After a successful verification, the backend writes `captcha_verified:{key}` to Redis (TTL 300s), which the login endpoint checks to allow login.
On failure, `code` is 422, `message` is `"验证失败，请重试"`, and `data.valid` is `false`.

### 3.5 Login

```
POST /api/v1/auth/login
```

- **Authentication**: none
- **Rate limit**: 10/min (per IP + path)

**Request body**:
```json
{
  "username": "admin",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "captcha_key": "abc123def456"
}
```

| Field | Type | Required | Validation | Description |
|------|------|------|---------|------|
| username | string | Yes | min:3, max:50 | Username |
| password | string | Yes | min:6, max:32 (plaintext) | AES-256-CBC-HMAC encrypted then Base64 encoded (plaintext accepted) |
| captcha_key | string | Yes | | Captcha key (must first pass `/api/v1/captcha/verify`) |

### Password Encryption Protocol

Uses **RSA-2048 asymmetric encryption**; the public key is embedded in the frontend code (safe to expose), while the private key is held only by the server.

```
Encryption flow (client):
  RSA public key (PEM) → PKCS1v1.5 encryption → Base64 encode → transmit

Decryption flow (server, stepwise fallback):
  1. RSA private key decrypt → success and valid UTF-8 → use the decrypted result
  2. AES-256-CBC-HMAC decrypt → success → use the decrypted result (legacy client compatibility)
  3. Plaintext fallback → use the raw input directly
```

The public key is built into the frontend application and is never transmitted over the network. The private key is stored only in `RSA_PRIVATE_KEY` in `.env` and must not be leaked.

> AES symmetric encryption is a legacy compatibility scheme and will be removed once all clients migrate to RSA.

**Example response**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| Field | Type | Description |
|------|------|------|
| access_token | string | JWT access token |
| refresh_token | string | JWT refresh token |
| expires_in | int | Access token validity (seconds), default 7200 |
| user.id | string | Hashid-encrypted user ID |
| user.username | string | Username |
| user.real_name | string | Real name |

**Possible errors**:
- 422: Parameter validation failed (missing required fields, invalid format)
- 422: Complete the captcha verification first (captcha_key has not passed `/api/v1/captcha/verify`)
- 401: Incorrect username or password
- 403: Account has been disabled
- 429: Account is locked, try again in 15 minutes (triggered by 5 consecutive login failures)

### 3.6 Register

```
POST /api/v1/auth/register
```

- **Authentication**: none
- **Rate limit**: 5/min (per IP + path)

**Request body**:
```json
{
  "username": "newuser",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "real_name": "新用户",
  "captcha_key": "abc123def456"
}
```

| Field | Type | Required | Validation | Description |
|------|------|------|---------|------|
| username | string | Yes | min:3, max:50 | Username (unique) |
| password | string | Yes | min:6, max:32 (plaintext) | AES-256-CBC-HMAC encrypted then Base64 encoded |
| real_name | string | Yes | max:50 | Real name |
| captcha_key | string | Yes | | Captcha key (must first pass `/api/v1/captcha/verify`) |

**Example response**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

A JWT token is returned immediately after successful registration; the user is enabled by default (status=1).

### 3.7 Refresh Token

```
POST /api/v1/auth/refresh
```

- **Authentication**: none
- **Rate limit**: global default (60/min)

**Request body**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| refresh_token | string | Yes | The refresh_token obtained at login/registration |

**Example response**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

A successful refresh returns new access_token and refresh_token, and the old tokens automatically become invalid. The refresh also updates the user's last login time and IP.

**Possible errors**:
- 422: Refresh token missing
- 401: Refresh token invalid or expired

### 3.8 Prometheus Metrics

```
GET /metrics
```

- **Authentication**: none
- **Rate limit**: none
- **Response format**: Prometheus text format (`text/plain; version=0.0.4`)

Public Prometheus metrics endpoint for scraping by Grafana/Prometheus.

**Example response**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| Metric | Type | Description |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Total cumulative HTTP requests |
| `openadmin_active_users` | gauge | Current active users (logged in within 24 hours) |
| `openadmin_db_connection_status` | gauge | Database connection status, 1=ok, 0=fail |
| `openadmin_redis_connection_status` | gauge | Redis connection status, 1=ok, 0=fail |
| `openadmin_memory_usage_bytes` | gauge | Current memory usage of the PHP process (bytes) |

## 4. Dashboard

All admin endpoints are mounted under the `/admin` group and pass through three middleware: `AdminAuth` (JWT authentication), `AdminPermission` (RBAC permission check), and `OperationLog` (operation logging).

### 4.1 Dashboard Data

```
GET /admin/dashboard
```

- **Authentication**: JWT + RBAC
- **Cache**: Redis 5 minutes

**Example response**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/v1/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| stats field | Type | Description |
|------|------|------|
| label | string | Metric name |
| value | string | Metric value (string type) |
| icon | string | Material icon name |
| color | string | Card color value |
| trend | float? | Day-over-day growth rate (percentage); only present on "total users" |

| trends field | Type | Description |
|------|------|------|
| dates | array{string} | Date series of the last 30 days |
| series | array{object} | Trend line data; each entry contains name, data (value array), color |

## 5. User Management

All `id` values returned by user management endpoints are hashid-encrypted strings. The password field is excluded from responses. Phone and email are masked in list endpoints and returned in plaintext in detail endpoints (encrypted database fields are decrypted automatically by the Encryptable trait).

### 5.1 User List

```
GET /admin/user
```

- **Authentication**: JWT + RBAC

**Query parameters**:

| Parameter | Type | Required | Default | Description |
|------|------|------|------|------|
| page | int | No | 1 | Page number |
| limit | int | No | 15 | Items per page |
| keyword | string | No | | Search keyword, matches username and real name |
| status | int | No | | Status filter, 0=disabled, 1=enabled |

**Example response**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| Field | Type | Description |
|------|------|------|
| id | string | Hashid-encrypted user ID |
| username | string | Username |
| real_name | string | Real name |
| phone | string | Masked phone number (`138****5678` format) |
| email | string | Masked email (`a***@example.com` format) |
| status | int | 1=enabled, 0=disabled |
| last_login_at | string | Last login time (datetime) |
| created_at | string | Creation time (datetime) |

### 5.2 Create User

```
POST /admin/user
```

- **Authentication**: JWT + RBAC

**Request body**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| Field | Type | Required | Validation | Description |
|------|------|------|---------|------|
| username | string | Yes | min:3, max:50 | Username (unique) |
| password | string | Yes | min:6, max:32 | Password (stored as bcrypt hash) |
| real_name | string | Yes | max:50 | Real name |
| phone | string | No | | Phone number (stored encrypted via Encryptable) |
| email | string | No | | Email (stored encrypted via Encryptable) |
| status | int | No | in:0,1 | Status, default 1 (enabled) |

**Example response**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**Possible errors**:
- 422: Username already exists
- 422: Parameter validation failed (required fields missing)

### 5.3 User Detail

```
GET /admin/user/{id}
```

- **Authentication**: JWT + RBAC
- **Path parameter**: `{id}` is the hashid-encrypted user ID

**Example response**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

In the detail endpoint, `phone` and `email` are returned in plaintext (stored encrypted in the database, decrypted automatically by the Encryptable cast), unmasked. `password` and `id_card` are never included in responses.

**Possible errors**:
- 404: User does not exist

### 5.4 Update User

```
PUT /admin/user/{id}
```

- **Authentication**: JWT + RBAC
- **Path parameter**: `{id}` is the hashid-encrypted user ID

**Request body**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| real_name | string | No | Real name; keeps the original value when omitted |
| password | string | No | New password; empty string or omitted means unchanged |
| phone | string | No | Phone number |
| email | string | No | Email |
| status | int | No | 0=disabled, 1=enabled |

**Example response**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**Possible errors**:
- 404: User does not exist

### 5.5 Delete User

```
DELETE /admin/user/{id}
```

- **Authentication**: JWT + RBAC
- **Path parameter**: `{id}` is the hashid-encrypted user ID
- **Sensitive operation**: requires password confirmation

**Request body**:
```json
{
  "password": "admin_password"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| password | string | Yes | Current logged-in user's password (confirmation) |

**Example response**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Soft deletion is performed (Eloquent SoftDeletes): rows are marked with deleted_at rather than physically deleted.

**Possible errors**:
- 404: User does not exist
- 422: Sensitive operations require password confirmation (password empty)
- 422: Password verification failed (password mismatch)

### 5.6 Batch Delete Users

```
POST /admin/user/batch/destroy
```

- **Authentication**: JWT + RBAC
- **Sensitive operation**: requires password confirmation

**Request body**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| ids | array{string} | Yes | Array of hashid-encrypted user IDs |
| password | string | Yes | Current logged-in user's password (confirmation) |

**Example response**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Soft deletion is performed; `data.count` is the number of users actually deleted.

**Possible errors**:
- 422: Select users to delete (ids empty)
- 422: Invalid ID (hashid decode failed)
- 422: Password verification failed

### 5.7 Batch Enable/Disable Users

```
POST /admin/user/batch/status
```

- **Authentication**: JWT + RBAC

**Request body**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| ids | array{string} | Yes | Array of hashid-encrypted user IDs |
| status | int | Yes | 0=disabled, 1=enabled |

**Example response**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message dynamically changes based on status to `"批量启用成功"` (batch enable succeeded) or `"批量禁用成功"` (batch disable succeeded).

**Possible errors**:
- 422: Select users (ids empty)
- 422: Invalid status value (status is not 0 or 1)

## 6. Role Management

### 6.1 Role List

```
GET /admin/role
```

- **Authentication**: JWT + RBAC

**Query parameters**:

| Parameter | Type | Required | Default | Description |
|------|------|------|------|------|
| page | int | No | 1 | Page number |
| limit | int | No | 15 | Items per page |

**Example response**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| Field | Type | Description |
|------|------|------|
| id | string | Hashid-encrypted role ID |
| name | string | Role name |
| slug | string | Role identifier (unique, used for permission checks) |
| description | string | Role description |
| status | int | 1=enabled, 0=disabled |
| users_count | int | Number of users with this role |

### 6.2 Create Role

```
POST /admin/role
```

- **Authentication**: JWT + RBAC

**Request body**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Field | Type | Required | Validation | Description |
|------|------|------|---------|------|
| name | string | Yes | max:50 | Role name |
| slug | string | Yes | max:50 | Role identifier |
| description | string | No | | Role description, default empty string |
| status | int | No | | Status, default 1 |
| permission_ids | array{int} | No | | Array of permission IDs (raw INT IDs, not hashids) |

**Example response**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 Update Role

```
PUT /admin/role/{id}
```

- **Authentication**: JWT + RBAC

**Request body**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| name | string | No | Role name |
| description | string | No | Description |
| status | int | No | 0=disabled, 1=enabled |
| permission_ids | array{int} | No | Array of permission IDs; when provided, role permissions are synced (overwritten) |

**Example response**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 Delete Role

```
DELETE /admin/role/{id}
```

- **Authentication**: JWT + RBAC
- **Sensitive operation**: requires password confirmation

**Request body**:
```json
{
  "password": "admin_password"
}
```

**Example response**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Deletion automatically detaches the role from all its permissions and users, then physically deletes the role record.

## 7. Permission Management

Permissions use a tree structure (self-referencing parent_id) and come in three types. The list endpoint returns the full permission tree.

### 7.1 Permission Tree

```
GET /admin/permission
```

- **Authentication**: JWT + RBAC

**Example response**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| Field | Type | Description |
|------|------|------|
| id | string | Hashid encrypted |
| parent_id | string | Parent permission hashid; "0" denotes the root node |
| name | string | Permission name |
| slug | string | Permission identifier (route/button identifier) |
| type | int | 1=menu, 2=button, 3=API |
| icon | string | Menu icon (Material icon name) |
| path | string | Frontend route path |
| sort | int | Sort value (ascending) |
| children | array? | Child permission list (recursive); omitted when there are no children |

### 7.2 Create Permission

```
POST /admin/permission
```

- **Authentication**: JWT + RBAC

**Request body**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| Field | Type | Required | Validation | Description |
|------|------|------|---------|------|
| parent_id | int | No | | Parent permission ID (raw INT type), default 0 |
| name | string | Yes | max:50 | Permission name |
| slug | string | Yes | max:100 | Permission identifier |
| type | int | Yes | in:1,2,3 | 1=menu, 2=button, 3=API |
| icon | string | No | | Menu icon, default empty |
| path | string | No | | Frontend route path, default empty |
| sort | int | No | | Sort value, default 0 |

**Example response**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 Update Permission

```
PUT /admin/permission/{id}
```

- **Authentication**: JWT + RBAC

**Request body**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| name | string | No | Permission name |
| icon | string | No | Icon |
| path | string | No | Route path |
| sort | int | No | Sort value |

### 7.4 Delete Permission

```
DELETE /admin/permission/{id}
```

- **Authentication**: JWT + RBAC
- **Sensitive operation**: requires password confirmation

**Request body**:
```json
{
  "password": "admin_password"
}
```

**Example response**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Deletion cascades to all child permissions (records where `parent_id` equals the current permission ID) and detaches all role associations.

## 8. System Config

System configs are unique per `group` + `key` combination.

### 8.1 Config List

```
GET /admin/config
```

- **Authentication**: JWT + RBAC

**Query parameters**:

| Parameter | Type | Required | Default | Description |
|------|------|------|------|------|
| page | int | No | 1 | Page number |
| limit | int | No | 15 | Items per page |
| group | string | No | | Filter by config group |

**Example response**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| Field | Type | Description |
|------|------|------|
| id | string | hashid |
| group | string | Config group (e.g. `system`, `email`, `storage`) |
| key | string | Config key |
| value | string | Config value |
| type | string | Value type hint (`string`, `integer`, `boolean`, `json`, etc.) |
| description | string | Config description |

### 8.2 Create Config

```
POST /admin/config
```

- **Authentication**: JWT + RBAC

**Request body**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Field | Type | Required | Validation | Description |
|------|------|------|---------|------|
| group | string | Yes | max:100 | Config group |
| key | string | Yes | max:100 | Config key (unique within a group) |
| value | string | Yes | | Config value |
| type | string | No | | Value type, default `string` |
| description | string | No | | Config description, default empty |

**Example response**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**Possible errors**:
- 422: Config item already exists (same group + key)

### 8.3 Update Config

```
PUT /admin/config/{id}
```

- **Authentication**: JWT + RBAC

**Request body**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| value | string | No | Updated config value |
| type | string | No | Updated value type |
| description | string | No | Updated description text |

### 8.4 Delete Config

```
DELETE /admin/config/{id}
```

- **Authentication**: JWT + RBAC
- **Sensitive operation**: requires password confirmation

**Request body**:
```json
{
  "password": "admin_password"
}
```

Physically deletes the config record.

## 9. Operation Logs

Operation logs are a read-only interface, automatically written by the `OperationLog` middleware on every POST/PUT/DELETE request. Stored fields include `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Operation Log List

```
GET /admin/log
```

- **Authentication**: JWT + RBAC

**Query parameters**:

| Parameter | Type | Required | Default | Description |
|------|------|------|------|------|
| page | int | No | 1 | Page number |
| limit | int | No | 15 | Items per page |
| user_id | int | No | | Exact filter by user ID (raw INT type) |
| action | string | No | | Exact filter by operation action |
| path | string | No | | Fuzzy filter by request path |
| start_date | string | No | | Start date (Y-m-d format) |
| end_date | string | No | | End date (Y-m-d format) |

**Example response**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/v1/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| Field | Type | Description |
|------|------|------|
| id | string | hashid |
| user_name | string | Operating username (via the user relation; "系统" (system) for unauthenticated operations) |
| action | string | Operation description |
| method | string | HTTP method (POST/PUT/DELETE) |
| path | string | Request path |
| ip | string | Client IP |
| source | string | Request source |
| input | string | Request parameter JSON string (files excluded) |
| created_at | string | Operation time (datetime) |

## 10. Profile

Profile endpoints require only JWT authentication (no RBAC check — the `AdminPermission` middleware should whitelist them).

### 10.1 Update Profile

```
PUT /admin/profile
```

- **Authentication**: JWT

**Request body**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| real_name | string | No | Real name |
| phone | string | No | Phone number (stored encrypted via Encryptable) |
| email | string | No | Email (stored encrypted via Encryptable) |

**Example response**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

`phone` and `email` are returned in plaintext; `password` and `id_card` are removed.

### 10.2 Change Password

```
PUT /admin/profile/password
```

- **Authentication**: JWT

**Request body**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Field | Type | Required | Validation | Description |
|------|------|------|---------|------|
| old_password | string | Yes | | Current password |
| new_password | string | Yes | min:6, max:32 | New password |

**Example response**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Possible errors**:
- 422: Please fill in both the old and new password
- 422: Incorrect old password
- 422: New password must be 6-32 characters

### 10.3 Logout

```
POST /admin/profile/logout
```

- **Authentication**: JWT

**Request body**: none (no requestBody; the token is read from the Authorization header)

**Example response**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Logout logic: decode the JWT to get the remaining validity (exp - now), write the token's md5 hash to the Redis blacklist `jwt_blacklist:{md5}` with TTL = remaining validity. Blacklisted tokens are blocked in the `AdminAuth` middleware with a 401 response.

Returns 401 when no token is provided. An expired/invalid token (decode throws an exception) is still treated as a successful logout.

## 11. Import & Export

### 11.1 Export Excel

```
POST /admin/export/excel
```

- **Authentication**: JWT + RBAC
- **Response type**: file download (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Request body**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| Field | Type | Required | Default | Description |
|------|------|------|------|------|
| table | string | No | `admin_user` | Table to export. Supported: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | No | | Field names of columns to export; empty exports all columns of the table |
| conditions | object | No | `{}` | Filter conditions, key-value pairs; non-empty values are used in WHERE |
| title | string | No | `数据导出` | Excel title (shown as the sheet name) |

**Supported tables and columns**:

| table | Available columns |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Sensitive fields `phone`, `email`, and `id_card` are automatically masked on export. Data is capped at 10,000 rows. The first Excel row is frozen and auto-filter is enabled.

### 11.2 Export PDF

```
POST /admin/export/pdf
```

- **Authentication**: JWT + RBAC
- **Response type**: file download (`application/pdf`, A4 landscape)

**Request body**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

Or table mode:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| Field | Type | Required | Default | Description |
|------|------|------|------|------|
| type | string | No | `table` | Export type: `table` / `dashboard` |
| title | string | No | `数据导出` | PDF title |
| data | object | No | `{}` | Export data |

With `type=dashboard`, `data` must contain a `stats` array (rendered as cards); with `type=table`, `data` must contain `columns` and `rows` arrays.

The PDF template includes copyright information and an export timestamp.

### 11.3 Import Users (Excel)

```
POST /admin/import/users
```

- **Authentication**: JWT + RBAC
- **Request type**: `multipart/form-data` (file upload)

**Form fields**:

| Field | Type | Required | Description |
|------|------|------|------|
| file | file | Yes | `.xlsx` or `.xls` format |

**Excel column requirements**:

| Column | Required | Description |
|------|------|------|
| username | Yes | Username (unique) |
| password | Yes | Password (stored as bcrypt hash) |
| real_name | Yes | Real name |
| phone | No | Phone number |
| email | No | Email |
| status | No | Status, default 1 |

Row 1 contains column headers (case-insensitive); data starts at row 2.

**Example response**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| Field | Type | Description |
|------|------|------|
| total | int | Total rows (excluding the header row) |
| success | int | Successfully imported count |
| failed | int | Failed count |
| errors | array | Failure details; each entry contains row (Excel row number) and reason (failure reason) |

## 12. File Upload

```
POST /admin/upload
```

- **Authentication**: JWT + RBAC
- **Request type**: `multipart/form-data`

**Form fields**:

| Field | Type | Required | Description |
|------|------|------|------|
| file | file | Yes | File to upload |

**Allowed file types**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Max file size**: 10MB

**Example response**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Files are stored in date-based directories under `public/upload/{Y-m-d}/`, with filenames of `md5(uniqid) + original extension`. `url` is a relative path from the site root.

**Possible errors**:
- 422: Please select a file (none uploaded)
- 422: Unsupported file type
- 422: File size must not exceed 10MB
- 500: File upload failed (invalid file)

## 13. Response Headers

All endpoints (injected at the global middleware layer) include the following response headers:

| Header | Description |
|----|------|
| `X-RateLimit-Limit` | Rate limit cap (count) |
| `X-RateLimit-Remaining` | Remaining request count |
| `X-RateLimit-Reset` | Rate limit window reset timestamp |
| `Retry-After` | Only returned when rate limited; suggested seconds to wait |
| `X-Content-Type-Options` | `nosniff` (webman default, disables MIME sniffing) |
| `X-Frame-Options` | `DENY` (provided by webman's CORS middleware/base config) |

Rate limit details:
- Default global limit: 60/min per IP + path
- Login endpoint `/api/v1/auth/login`: 10/min
- Register endpoint `/api/v1/auth/register`: 5/min
- Uses the Redis atomic sliding window algorithm (Lua ZSET) to avoid TOCTOU races
- Fails open when Redis is unavailable (requests pass), never blocking traffic

## 14. Authentication Flow

The complete authentication sequence:

```
1. Client requests POST /api/v1/captcha/generate
    ↓
   Server returns: key + type(click|slider|rotate) + base64 image + extra(type-specific data)
   
2. The user completes the captcha interaction (click/drag/rotate), and the client collects the answer
   
3. Client requests POST /api/v1/captcha/verify
   (Request headers: Content-Type: application/json)
   Request body: { key, type, clicks }
   - type=click:  clicks = [{x, y}, ...]        // coordinate array
   - type=slider: clicks = 120                   // X offset
   - type=rotate: clicks = 315                   // rotation angle
    ↓
   Server:
   a. Reads captcha:key data from storage (TTL 300s)
   b. Validates the answer by type (click: Euclidean distance ≤18px / slider: ±4px / rotate: ±5°)
   c. On success → writes Redis `captcha_verified:{key}` = 1 (TTL 300s)
   d. On failure → returns 422, counter +1, key invalidated after 3 failures
    ↓
   Server returns: { valid: true/false }

4. Client requests POST /api/v1/auth/login
   (Request headers: Content-Type: application/json)
   Request body: { username, password(encrypted), captcha_key }
    ↓
   Server:
   a. Parameter validation → 422
   b. Checks captcha_verified:{key} exists → 422
   c. Deletes captcha_verified:{key} (single-use)
   d. Decrypts password: EncryptionService::decrypt(password) → plaintext
   e. Validates user credentials (password_verify) → 401
   f. Checks account status → 403/429
   g. Issues JWT (access + refresh) → 200
   h. Updates last_login_at / last_login_ip
    ↓
   Client stores: access_token, refresh_token, expires_in

5. Subsequent requests carry the JWT
   Request header: Authorization: Bearer <access_token>
    ↓
   AdminAuth middleware:
   a. Extracts the Bearer token
   b. Checks the blacklist (Redis jwt_blacklist:{md5}) → 401
   c. Decodes the JWT, validates expiry → 401
   d. Sets $request->adminId = sub field
    ↓
   AdminPermission middleware:
   a. Resolves the permission identifier for resource routes
   b. Queries user roles → role permissions, performs matching
   c. No permission → 403
    ↓
   Controller processes the request
    ↓
   Response + X-RateLimit-* headers

6. Refresh before the access token expires
   Client requests POST /api/v1/auth/refresh
   Request body: { refresh_token: "..." }
    ↓
   Server decodes refresh_token → issues new access + refresh
    ↓
   Client updates local tokens

7. Logout
   Client requests POST /admin/profile/logout
   Request header: Authorization: Bearer <access_token>
    ↓
   Server:
   a. Decodes the JWT to get the remaining TTL
   b. Writes to the Redis blacklist: jwt_blacklist:{md5(token)} = 1, TTL = remaining validity
   c. Returns success
```

### JWT Structure

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, default TTL 7200 seconds (controlled by JWT config `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, default TTL 1209600 seconds (controlled by JWT config `refresh_expire`, i.e. 14 days)

### Security Management

- Passwords are stored as `PASSWORD_BCRYPT` hashes
- Password transport uses AES-256-CBC-HMAC encryption (client encrypts → server decrypts), with plaintext fallback
- Sensitive fields (phone, email, id_card) are transparently encrypted/decrypted at the database layer with `erikwang2013/encryptable`
- API-layer IDs are encrypted in transit with `erikwang2013/hashids` to avoid exposing the raw snowflake ID sequence
- SecurityFilter globally scans for XSS, SQL injection, path traversal, and command injection; 5 hits per IP in 60 seconds trigger a 15-minute temporary blacklist
- Sensitive operations (deleting users, roles, permissions, configs) require password confirmation from the currently logged-in user
- Concurrent session limit: at most 3 valid tokens per user; when a 4th device logs in, the oldest token is forcibly blacklisted
- Account lockout: 5 consecutive login failures trigger a 15-minute lock; 429 is returned while locked

### Middleware Architecture

Global middleware applies to all requests, executed in order:

```
Cors（跨域预处理 + 响应头）
  → Locale（Accept-Language 语言检测 / ?lang=zh_CN|en）
  → SecurityFilter（HTTP方法限制/请求体大小/Content-Type校验/XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截）
  → RateLimit（Redis 滑动窗口限流 + 账号锁定：5次登录失败锁定15分钟）
  → ClientAuth（客户端 JWT 认证，token_type=client，/api/v1 客户端门户路由组）
  → AdminAuth（JWT 认证 + 黑名单，/admin 路由组）
  → AdminPermission（RBAC 鉴权 / Redis 60s 缓存，/admin 路由组）
  → OperationLog（POST/PUT/DELETE 自动记录，含来源端检测，/admin 路由组）
```

`/health` and `/api/docs` are public endpoints, passing only `Cors → SecurityFilter → RateLimit`.

Security enhancements:
- **Account lockout**: 5 consecutive login failures lock the account for 15 minutes; logins return 429 during the lock
- **Concurrent session limit**: at most 3 valid tokens per user; when exceeded, the oldest token is automatically blacklisted
- **security.txt**: `GET /.well-known/security.txt` serves RFC 9116 standard security contact information
- **Nginx security config**: see `docs/nginx-security.conf` for a complete reverse-proxy security hardening example

### Operation Source Detection

The OperationLog middleware auto-detects the client platform and writes it to the `source` field of the operation log:

| Platform | Detection method |
|------|---------|
| `ipados` | UA contains iPad |
| `macos` | UA contains Macintosh/Mac OS |
| `windows` | UA contains Windows |
| `linux` | UA contains Linux (not Android) |
| `ios` | UA contains iPhone / iOS / CFNetwork |
| `android` | UA contains Android |
| `harmonyos` | UA contains HarmonyOS / OpenHarmony, or explicitly declared via the `X-Client-Platform` header |
| `web` | Default (none of the above matched) |

> Two-level detection: `X-Client-Platform` request header (declared by native apps) → User-Agent auto-inference (fallback). The `source` field in the operation log query `GET /admin/log` is the client platform.

## 15. Deployment & Ops

### Docker Compose

The project root provides `docker-compose.yml` orchestrating 5 services (Nginx, webman app, MySQL, Redis, Elasticsearch). PHP is built via `Dockerfile` (based on `php:8.3-cli` with OPcache enabled).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` defines the GitHub Actions CI pipeline:
- `php -l` syntax check
- PHPUnit unit tests
- `flutter analyze` static analysis

### Database Backup

The `database/backup/` directory provides backup and restore scripts:
- `backup.sh` — mysqldump + gzip compressed backup, auto-clears backups older than 30 days
- `restore.sh` — interactive restore, lists existing backups for selection

### Nginx Security Config

For production deployments, refer to `docs/nginx-security.conf` for reverse-proxy security hardening.

## 16. Internal gRPC Interface (e-cat → PHP worker)

For use by the e-cat query gateway only. On the PHP side it is a workerman h2c service (`tcp://0.0.0.0:8792`, process `internal_grpc`). The contract is defined in `infrastructure/tracking-gateway/proto/internal.proto` (package `internal.v1`), messages are protobuf-encoded, and metadata must carry `x-internal-token` (value from `config/logistics.php` `internal_token`, overridden by the `INTERNAL_TOKEN` environment variable in production). A missing or invalid token returns `code=401 UNAUTHORIZED`.

### 16.1 RPC Overview

| RPC | Request | Response | Description |
|---|---|---|---|
| `Detect` | `{tracking_no}` | `{code, carrier_code, channel, confidence}` | Carrier detection; returns `404 CARRIER_NOT_DETECTED` when unrecognized |
| `Query` | `{carrier_code, tracking_no, credential_id?}` | `{code, query_no, carrier_code, tracking_no, status, delivered_at, estimated_delivery_at, latest_description, raw_status, events[]}` | Tracking query; `credential_id` optional (specified for multi-credential scenarios, must match `carrier_code`, otherwise `400 INVALID_PARAMS`). Every query is persisted to `logistics_tracking_query` |
| `Carriers` | `{}` | `{code, carriers[]}` | All carriers in the registry `{carrier_code, channel}` |

### 16.2 Error Contract (response `code` field, gRPC status always 0)

| `code` | `error_code` | Scenario |
|---|---|---|
| 400 | `INVALID_PARAMS` | Missing parameters / credential_id mismatch |
| 401 | `UNAUTHORIZED` | Invalid internal token |
| 404 | `CARRIER_NOT_FOUND` / `TRACKING_NOT_FOUND` / `CARRIER_NOT_DETECTED` | Carrier not registered / tracking not found / cannot detect |
| 5001 | `CARRIER_AUTH_ERROR` / `CARRIER_NETWORK_ERROR` / `CARRIER_ERROR` | Upstream credential error / network error / carrier business error (includes structured upstream payload, e.g. `[SF-INTERNATIONAL A1001] required parameters must not be empty`) |
| 500 | `INTERNAL_ERROR` | Internal exception |


## 17. e-cat Query Gateway External API

Rust high-frequency gateway (`infrastructure/tracking-gateway`), listens on `0.0.0.0:8080` (default), requests carry the `X-API-Key` header (config `api_keys`). The gateway handles API-Key authentication, Redis cache hits (prefix `logistics:`), rate limiting, per-carrier circuit breaking and RoundRobin worker forwarding; credentials are never sent down to the gateway.

| 端点 | 说明 |
|---|---|
| `GET /v1/health` | Health check (public endpoint, no key required) |
| `GET /v1/openapi.json` | OpenAPI 3.0 documentation (public endpoint, no key required) |
| `GET /v1/carriers` | Carrier list (forwards PHP gRPC `InternalService.Carriers`) |
| `POST /v1/tracking/query` | Tracking query (`{"carrier_code": "...", "tracking_no": "..."}`); when `carrier_code` is omitted the gateway auto-detects (detect is done inside query) |
| `GET /v1/tracking/{query_no}` | Fetch the last tracking result by query number |
| `POST /v1/subscriptions` | Register a callback subscription (`{"carrier_code", "callback_url", "event_type"?}`, `event_type` defaults to `tracking.update`) |


Gateway-side additional semantics: exceeding `rate_limit` (default 100 per 60s) returns `429`; consecutive carrier failures trigger the circuit breaker (default OPEN 60s after 5x 5xx/transport errors) returning `503`; worker unreachable returns `502`. Gateway-to-worker traffic is internal gRPC (h2c, `0.0.0.0:8792`) authenticated with the shared secret `x-internal-token`, see Section 16.

### 17.1 Response Envelope & Errors

All endpoints return the unified envelope `{"code": 0, "message": "ok", "data": ...}`; `code != 0` is an error and `data` is omitted. Business error bodies may carry `error_code` / `error_message` (upstream carrier diagnostics, passed through by the gateway); `code` matches the HTTP status:

| HTTP | Meaning |
|---|---|
| 400 | Parameter error (`tracking_no` missing / invalid subscription parameters) |
| 401 | Missing or invalid key |
| 404 | Query number not found |
| 429 | Rate limited (default 100 per 60s per key), back off and retry |
| 502 | Worker unreachable or upstream failure |
| 503 | Carrier circuit-broken, back off and retry |

### 17.2 Call Examples (curl)

Demo key `demo-api-key`, gateway default `http://127.0.0.1:8080`:

```bash
# Health check (public endpoint, no key required)
curl http://127.0.0.1:8080/v1/health

# OpenAPI docs (public endpoint, no key required)
curl http://127.0.0.1:8080/v1/openapi.json

# Carrier list
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/carriers

# Tracking query (carrier optional)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "tracking_no": "LX123456789CN"}' \
  http://127.0.0.1:8080/v1/tracking/query

# Last result by query number
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/tracking/{query_no}

# Register callback subscription (callback URL must be public http/https; gateway rejects loopback/private networks)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "callback_url": "https://example.com/cb", "event_type": "tracking.update"}' \
  http://127.0.0.1:8080/v1/subscriptions
```

### 17.3 Call Examples (SDK)

Five zero-dependency SDKs are in the repo `sdk/` directory (Python / PHP / Node.js / Go / Rust, copy-and-run):

```python
from tracking_client import TrackingClient
client = TrackingClient("demo-api-key", "http://127.0.0.1:8080")
result = client.query_tracking("LX123456789CN")
print(result["status"], result["events"][0]["description"])
```

```php
require "TrackingClient.php";
$client = new TrackingClient("demo-api-key", "http://127.0.0.1:8080");
$carriers = $client->list_carriers();
$sub = $client->subscribe("china-post", "https://example.com/cb");
```

```js
const { TrackingClient } = require("./tracking-client.js");
const client = new TrackingClient("demo-api-key", "http://127.0.0.1:8080");
const detail = await client.getTracking("query_no");
console.log(detail.status);
```

```go
import "trackingclient"
client := trackingclient.NewClient("demo-api-key", "http://127.0.0.1:8080")
raw, err := client.QueryTracking("LX123456789CN", "china-post")
if err != nil { panic(err) }
fmt.Println(string(raw))
```

```rust
use trackingclient::tracking_client::TrackingClient;
let client = TrackingClient::new("demo-api-key", "http://127.0.0.1:8080")?;
let detail = client.get_tracking("query_no")?;
println!("{}", detail);
```

The SDKs throw exceptions (`TrackingApiError`) when the envelope has `code != 0` or on network failure, carrying `code` / `message` / `error_code` / `error_message` / `http_status`; back off and retry on rate-limit and circuit-breaker errors, do not retry carrier-side errors. Full usage in `sdk/README.md`.
