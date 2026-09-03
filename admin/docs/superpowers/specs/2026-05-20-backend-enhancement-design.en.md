> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-20-backend-enhancement-design.md) | [English](2026-05-20-backend-enhancement-design.en.md) | [한국어](2026-05-20-backend-enhancement-design.ko.md) | [Русский](2026-05-20-backend-enhancement-design.ru.md) | [Deutsch](2026-05-20-backend-enhancement-design.de.md) | [Français](2026-05-20-backend-enhancement-design.fr.md) | [Español](2026-05-20-backend-enhancement-design.es.md) | [Português](2026-05-20-backend-enhancement-design.pt.md) | [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) | [العربية](2026-05-20-backend-enhancement-design.ar.md) | [বাংলা](2026-05-20-backend-enhancement-design.bn.md) | [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) | [日本語](2026-05-20-backend-enhancement-design.ja.md)

# Sub-project A: Backend Enhancement — Design Specification

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Scope

This backend enhancement covers 15 feature points, involving 9 new files + 4 modified files.

---

## List of New/Modified Files

```
app/middleware/
├── OperationLog.php          # Added: automatic operation log recording
├── Cors.php                  # Added: cross-origin
└── RateLimit.php             # Added: Redis rate limiting
app/admin/controller/
├── ConfigController.php      # Added: system config CRUD
├── LogController.php         # Added: operation log query
├── ProfileController.php     # Added: profile center (incl. logout)
├── UploadController.php      # Added: file upload
├── ImportController.php      # Added: Excel user import
└── HealthController.php      # Added: health check
app/model/
├── AdminUser.php             # Modified: add SoftDeletes + Searchable trait
└── OperationLog.php          # Modified: add public $timestamps = false
app/middleware/
└── AdminAuth.php             # Modified: JWT blacklist check
app/admin/controller/
├── DashboardController.php   # Modified: switch to real-time DB statistics
└── UserController.php        # Modified: add batch actions
config/
└── route.php                 # Modified: add routes + middleware
```

---

## 1. Middleware

### 1.1 CORS Middleware

**File**: `app/middleware/Cors.php`

- OPTIONS preflight requests return 204 directly
- Non-preflight requests append `Access-Control-Allow-Origin: *` to response headers
- Allowed headers: `Authorization, Content-Type`
- Max cache: 86400 seconds

Mounted: global middleware (`config/middleware.php`)

### 1.2 Rate Limit Middleware

**File**: `app/middleware/RateLimit.php`

- Storage: Redis Sorted Set sliding window
- Default: 60 requests/minute/IP/route
- Sensitive endpoints:
  - `/api/v1/auth/login`: 10 requests/minute
  - `/api/v1/auth/register`: 5 requests/minute
- Returns `429 Too Many Requests` when exceeded

Mounted: global middleware (`config/middleware.php`), after Cors and before ApiVersion

### 1.3 Operation Log Middleware

**File**: `app/middleware/OperationLog.php`

- Records only POST/PUT/DELETE
- Recorded fields: user_id, action, method, path, ip, input(JSON)
- Written asynchronously after the response is returned (non-blocking)

Mounted: `/admin` route group, after AdminPermission

### 1.4 Global Middleware Execution Chain

```
All requests:
  Cors → RateLimit → ApiVersion → {Route middleware} → Controller

/admin/* requests:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Logout (JWT Blacklist)

**File**: `app/middleware/AdminAuth.php` (modified)

**Principle**: JWT is stateless by itself; on logout the token is added to a Redis blacklist, and AdminAuth checks the blacklist first during validation.

**AdminAuth changes**:
- Add at the start of `process()`: check whether the current token is in the Redis `jwt_blacklist` set
- Return 401 if it is blacklisted

**Logout route** (under profile center):

| Method | Route | Notes |
|------|------|------|
| `POST` | `/admin/profile/logout` | Add the current Bearer token to the Redis blacklist, TTL=token remaining validity |

**Logout logic**:
```php
// Parse the token's remaining validity
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// Add to the blacklist
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. New Controllers and Existing Changes

### 2.1 System Config CRUD (`ConfigController`)

Extends `BaseController`.

| Method | Route | Notes |
|------|------|------|
| `index()` | GET `/admin/config` | Paginated list, filterable by `group`, paginated with `page`/`limit` |
| `store()` | POST `/admin/config` | Create a config item, required: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Update config item value/type/description |
| `destroy()` | DELETE `/admin/config/{id}` | Delete a config item, requires `confirmPassword()` |

### 2.2 Operation Log Query (`LogController`)

Extends `BaseController`.

| Method | Route | Notes |
|------|------|------|
| `index()` | GET `/admin/log` | Paginated list, supports filters: user_id, action, path, created_at (range) |

No create/update/delete is provided; logs are recorded automatically by the middleware.

### 2.3 Profile Center (`ProfileController`)

Extends `BaseController`. Operates on the currently logged-in user (`$request->adminId`).

| Method | Route | Notes |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Update real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Change password, requires old_password, new_password, new_password_confirmation |

### 2.4 File Upload (`UploadController`)

Extends `BaseController`.

| Method | Route | Notes |
|------|------|------|
| `upload()` | POST `/admin/upload` | Accept a file, supports image/jpeg/png/gif/pdf/xlsx/docx |

- Max 10MB
- Storage path: `public/upload/{date}/{hash}.{ext}`
- Returns: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Dashboard Real Data

**File**: `app/admin/controller/DashboardController.php` (modified)

Replace the current hardcoded fake data with real-time database statistics:

| Metric | Source | Notes |
|------|------|------|
| Total users | `AdminUser::count()` | Excludes soft-deleted |
| New today | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Total roles | `AdminRole::count()` | |
| Total permissions | `AdminPermission::count()` | |
| Trend data | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Daily new users for the last 7 days |
| Distribution data | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Distribution by status |
| Recent operations | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | Latest 10 operation logs |

### 2.6 User Batch Operations

**File**: `app/admin/controller/UserController.php` (modified, new methods)

| Method | Route | Notes |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Batch delete, request body `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Batch enable/disable, request body `{ ids: [hashid, ...], status: 1|0 }` |

- Each id is converted to BIGINT with `decodeId()` first
- `batchDestroy()` must pass `confirmPassword()` validation

### 2.7 Data Import

**File**: `app/admin/controller/ImportController.php` (added)

| Method | Route | Notes |
|------|------|------|
| `users()` | POST `/admin/import/users` | Upload an Excel file and batch-create users |

Flow:
1. Receive the `.xlsx` file
2. Parse with PhpSpreadsheet, expected columns: `username, password, real_name, phone, email, status`
3. Validate and create row by row (snowflake-generated IDs, bcrypt passwords, encryption for phone/email)
4. Return the result: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Health Check

**File**: `app/admin/controller/HealthController.php` (added)

`GET /health` (no authentication required, not counted in operation logs):

Returns the connection status of each component:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- When a component check fails, the corresponding field value is the error description string
- The route does not carry the `/admin` prefix; it is registered globally on its own

---

## 3. Model Fixes

### 3.1 OperationLog Timestamps

**File**: `app/model/OperationLog.php` (modified)

The `logistics_operation_log` table only has a `created_at` column (no `updated_at`). Eloquent's default `save()` tries to write `updated_at`, which causes SQL errors.

Fix: `public $timestamps = false;` + manually set `created_at` on write.

### 3.2 AdminUser Model Changes

- Add the `Searchable` trait
- Implement `toSearchableArray()`: returns username, real_name
- `UserController::index()` uses `AdminUser::search($kw)->get()` instead of MySQL LIKE when a keyword is detected

The ES index must be created first, via Scout commands:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Route Changes

New routes in `config/route.php`:

```php
// New routes inside the /admin route group:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// Health check (global route, not inside the /admin group)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// Middleware:
Append app\middleware\OperationLog::class to the /admin group middleware
```

Register global middleware in `config/middleware.php`:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Additional Error Codes

| code | Meaning | Trigger Scenario |
|------|------|---------|
| 429 | Too many requests | RateLimit triggered |

---

## 6. Out of Scope for This Round

- Notification system (requires message queue + frontend push infrastructure)
- Flutter frontend pages (sub-project B)
- HarmonyOS token refresh (sub-project C)
