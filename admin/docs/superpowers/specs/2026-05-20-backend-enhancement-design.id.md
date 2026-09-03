> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-20-backend-enhancement-design.md) | [English](2026-05-20-backend-enhancement-design.en.md) | [한국어](2026-05-20-backend-enhancement-design.ko.md) | [Русский](2026-05-20-backend-enhancement-design.ru.md) | [Deutsch](2026-05-20-backend-enhancement-design.de.md) | [Français](2026-05-20-backend-enhancement-design.fr.md) | [Español](2026-05-20-backend-enhancement-design.es.md) | [Português](2026-05-20-backend-enhancement-design.pt.md) | [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) | [العربية](2026-05-20-backend-enhancement-design.ar.md) | [বাংলা](2026-05-20-backend-enhancement-design.bn.md) | [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) | [日本語](2026-05-20-backend-enhancement-design.ja.md)

# Subproyek A: Peningkatan Backend — Spesifikasi Desain

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Ruang Lingkup

Peningkatan backend kali ini mencakup 15 poin fungsional, melibatkan 9 file baru + 4 file yang dimodifikasi.

---

## Daftar File Baru/Modifikasi

```
app/middleware/
├── OperationLog.php          # Baru: pencatatan log operasi otomatis
├── Cors.php                  # Baru: lintas domain (CORS)
└── RateLimit.php             # Baru: pembatasan laju Redis
app/admin/controller/
├── ConfigController.php      # Baru: CRUD konfigurasi sistem
├── LogController.php         # Baru: kueri log operasi
├── ProfileController.php     # Baru: pusat profil (termasuk logout)
├── UploadController.php      # Baru: unggah file
├── ImportController.php      # Baru: impor pengguna dari Excel
└── HealthController.php      # Baru: pemeriksaan kesehatan
app/model/
├── AdminUser.php             # Modifikasi: menambah trait SoftDeletes + Searchable
└── OperationLog.php          # Modifikasi: menambah public $timestamps = false
app/middleware/
└── AdminAuth.php             # Modifikasi: validasi daftar hitam JWT
app/admin/controller/
├── DashboardController.php   # Modifikasi: menjadi statistik real-time dari database
└── UserController.php        # Modifikasi: menambah aksi batch baru
config/
└── route.php                 # Modifikasi: menambah rute + middleware
```

---

## 1. Middleware

### 1.1 Middleware CORS

**File**: `app/middleware/Cors.php`

- Permintaan preflight OPTIONS langsung mengembalikan 204
- Untuk permintaan non-preflight, tambahkan `Access-Control-Allow-Origin: *` pada header respons
- Header yang diizinkan: `Authorization, Content-Type`
- Cache maksimum: 86400 detik

Dipasang: middleware global (`config/middleware.php`)

### 1.2 Middleware Pembatasan Laju

**File**: `app/middleware/RateLimit.php`

- Penyimpanan: jendela geser Redis Sorted Set
- Default: 60 kali/menit/IP/rute
- Antarmuka sensitif:
  - `/api/v1/auth/login`: 10 kali/menit
  - `/api/v1/auth/register`: 5 kali/menit
- Jika terlampaui, kembalikan `429 Too Many Requests`

Dipasang: middleware global (`config/middleware.php`), setelah Cors, sebelum ApiVersion

### 1.3 Middleware Log Operasi

**File**: `app/middleware/OperationLog.php`

- Hanya mencatat POST/PUT/DELETE
- Kolom yang dicatat: user_id, action, method, path, ip, input (JSON)
- Ditulis secara asinkron setelah respons dikembalikan (tidak memblokir)

Dipasang: grup rute `/admin`, setelah AdminPermission

### 1.4 Rantai Eksekusi Middleware Global

```
Semua permintaan:
  Cors → RateLimit → ApiVersion → {Middleware Rute} → Controller

Permintaan /admin/*:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Logout (Daftar Hitam JWT)

**File**: `app/middleware/AdminAuth.php` (dimodifikasi)

**Prinsip**: JWT sendiri bersifat tanpa status; saat logout, token dimasukkan ke daftar hitam Redis, dan AdminAuth memeriksa daftar hitam terlebih dahulu saat validasi.

**Perubahan AdminAuth**:
- Di awal `process()`: periksa dari set Redis `jwt_blacklist` apakah token saat ini ada dalam daftar hitam
- Jika ada dalam daftar hitam, kembalikan 401

**Rute logout** (di bawah pusat profil):

| Metode | Rute | Keterangan |
|------|------|------|
| `POST` | `/admin/profile/logout` | Tambahkan Bearer token saat ini ke daftar hitam Redis, TTL=masa berlaku sisa token |

**Logika Logout**:
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Controller Baru dan Modifikasi yang Ada

### 2.1 CRUD Konfigurasi Sistem (`ConfigController`)

Mewarisi `BaseController`.

| Metode | Rute | Keterangan |
|------|------|------|
| `index()` | GET `/admin/config` | Daftar berhalaman, dapat difilter berdasarkan `group`, paginasi `page`/`limit` |
| `store()` | POST `/admin/config` | Membuat item konfigurasi, wajib diisi: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Memperbarui item konfigurasi value/type/description |
| `destroy()` | DELETE `/admin/config/{id}` | Menghapus item konfigurasi, memerlukan `confirmPassword()` |

### 2.2 Kueri Log Operasi (`LogController`)

Mewarisi `BaseController`.

| Metode | Rute | Keterangan |
|------|------|------|
| `index()` | GET `/admin/log` | Daftar berhalaman, mendukung filter: user_id, action, path, created_at (rentang) |

Tidak menyediakan tambah/hapus/ubah; log dicatat otomatis oleh middleware.

### 2.3 Pusat Profil (`ProfileController`)

Mewarisi `BaseController`. Beroperasi pada pengguna yang sedang login (`$request->adminId`).

| Metode | Rute | Keterangan |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Memperbarui real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Mengubah kata sandi, memerlukan old_password, new_password, new_password_confirmation |

### 2.4 Unggah File (`UploadController`)

Mewarisi `BaseController`.

| Metode | Rute | Keterangan |
|------|------|------|
| `upload()` | POST `/admin/upload` | Menerima file, mendukung image/jpeg/png/gif/pdf/xlsx/docx |

- Maksimum 10MB
- Jalur penyimpanan: `public/upload/{date}/{hash}.{ext}`
- Mengembalikan: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Data Nyata Dashboard

**File**: `app/admin/controller/DashboardController.php` (dimodifikasi)

Mengubah data palsu yang dikodekan keras saat ini menjadi statistik real-time dari database:

| Metrik | Sumber | Keterangan |
|------|------|------|
| Jumlah pengguna | `AdminUser::count()` | Tidak termasuk penghapusan lunak |
| Baru hari ini | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Jumlah role | `AdminRole::count()` | |
| Jumlah izin | `AdminPermission::count()` | |
| Data tren | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Statistik harian pengguna baru 7 hari terakhir |
| Data distribusi | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Distribusi berdasarkan status |
| Operasi terbaru | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | 10 log operasi terakhir |

### 2.6 Operasi Batch Pengguna

**File**: `app/admin/controller/UserController.php` (dimodifikasi, metode baru)

| Metode | Rute | Keterangan |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Menghapus massal, body permintaan `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Mengaktifkan/menonaktifkan massal, body permintaan `{ ids: [hashid, ...], status: 1|0 }` |

- Setiap id terlebih dahulu diubah menjadi BIGINT melalui `decodeId()`
- `batchDestroy()` harus melewati validasi `confirmPassword()`

### 2.7 Impor Data

**File**: `app/admin/controller/ImportController.php` (baru)

| Metode | Rute | Keterangan |
|------|------|------|
| `users()` | POST `/admin/import/users` | Mengunggah file Excel, membuat pengguna secara massal |

Alur:
1. Menerima file `.xlsx`
2. Parse dengan PhpSpreadsheet, kolom yang diharapkan: `username, password, real_name, phone, email, status`
3. Validasi + buat baris per baris (ID dihasilkan snowflake, kata sandi bcrypt, phone/email dienkripsi dengan encryption)
4. Mengembalikan hasil: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Pemeriksaan Kesehatan

**File**: `app/admin/controller/HealthController.php` (baru)

`GET /health` (tanpa autentikasi, tidak dihitung dalam log operasi):

Mengembalikan status koneksi setiap komponen:
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

- Jika pemeriksaan komponen gagal, nilai kolom terkait adalah string deskripsi kesalahan
- Rute tidak memakai prefiks `/admin`, didaftarkan terpisah secara global

---

## 3. Perbaikan Model

### 3.1 Stempel Waktu OperationLog

**File**: `app/model/OperationLog.php` (dimodifikasi)

Tabel `logistics_operation_log` hanya memiliki kolom `created_at` (tanpa `updated_at`). Secara default, `save()` Eloquent akan mencoba menulis `updated_at`, menyebabkan kesalahan SQL.

Perbaikan: `public $timestamps = false;` + tentukan `created_at` secara manual saat menulis.

### 3.2 Modifikasi Model AdminUser

- Menambahkan trait `Searchable`
- Mengimplementasikan `toSearchableArray()`: mengembalikan username, real_name
- Ketika `UserController::index()` mendeteksi kata kunci, gunakan `AdminUser::search($kw)->get()` alih-alih MySQL LIKE

ES perlu membuat indeks terlebih dahulu, dapat melalui perintah Scout:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Perubahan Rute

`config/route.php` menambahkan rute:

```php
// /admin 路由组内新增:
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

// 健康检查（全局路由，非 /admin 组内）
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// 中间件:
/admin 组中间件追加 app\middleware\OperationLog::class
```

`config/middleware.php` mendaftarkan middleware global:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Tambahan Kode Kesalahan

| Kode | Arti | Skenario Pemicu |
|------|------|---------|
| 429 | Terlalu banyak permintaan | Dipicu oleh RateLimit |

---

## 6. Di Luar Ruang Lingkup Ini

- Sistem notifikasi (membutuhkan antrian pesan + infrastruktur push frontend)
- Halaman frontend Flutter (subproyek B)
- Penyegaran Token HarmonyOS (subproyek C)
