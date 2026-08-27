> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](API.md) | [English](API.en.md) | [한국어](API.ko.md) | [Русский](API.ru.md) | [Deutsch](API.de.md) | [Français](API.fr.md) | [Español](API.es.md) | [Português](API.pt.md) | [हिन्दी](API.hi.md) | [العربية](API.ar.md) | [বাংলা](API.bn.md) | [Bahasa Indonesia](API.id.md) | [日本語](API.ja.md)

# Dokumentasi Referensi API

## 1. Ringkasan

Panel Admin Terbuka (open-admin) dibangun di atas webman v2 dan menyediakan RESTful JSON API. Semua antarmuka sisi admin memerlukan autentikasi JWT dan validasi hak akses RBAC; antarmuka publik dirutekan ke kontroler ber-versi melalui header versi API.

- **URL dasar**: `http://localhost:8787`
- **Versi API**: Dikontrol melalui header `API-Version: v1` (default v1 jika tidak ada)
- **Bahasa**: Berpindah melalui header `Accept-Language` atau parameter `?lang=zh_CN|en` (default zh_CN), dideteksi otomatis oleh middleware Locale

> **Ringkasan endpoint**: Autentikasi(5) | Dasbor(1) | Pengguna(7) | Peran(4) | Hak Akses(4) | Konfigurasi(4) | Log(1) | Pusat Akun(3) | Impor Ekspor(3) | Upload(1) | Operasional(4: health/metrics/docs/security.txt) | Total 37 endpoint
- **Autentikasi**: `Authorization: Bearer <token>` (JWT)
- **Format respons**: `{ "code": 0, "message": "success", "data": {...} }`
- **Endpoint dokumentasi**: `GET /api/docs` mengembalikan spesifikasi JSON OpenAPI 3.0

### Persyaratan Permintaan

- Hanya metode `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` yang diizinkan; penggunaan metode HTTP lain (seperti TRACE, CONNECT, PATCH) mengembalikan 405
- Semua permintaan `POST` / `PUT` harus mengatur `Content-Type: application/json` (kecuali upload file), jika tidak mengembalikan 415
- Ukuran body permintaan tidak boleh melebihi 10MB, jika tidak mengembalikan 413
- Filter keamanan memindai semua input permintaan untuk XSS, injeksi SQL, path traversal, injeksi perintah; jika terdeteksi mengembalikan 403
- 5 kali kegagalan login berturut-turut memicu penguncian akun (15 menit), permintaan login selama terkunci mengembalikan 429
- Satu pengguna maksimal memegang 3 Token valid secara bersamaan; jika lebih, Token paling lama otomatis masuk blacklist

## 2. Kode Kesalahan

| code | Arti | Skenario pemicu |
|------|------|---------|
| 0 | Sukses | |
| 400 | Kesalahan parameter permintaan | Format permintaan tidak benar |
| 401 | Belum terautentikasi | Token hilang / kedaluwarsa / sudah masuk blacklist |
| 403 | Tanpa izin / diblokir keamanan | Hak akses RBAC tidak cukup / SecurityFilter terdeteksi |
| 404 | Sumber daya tidak ada | Target kueri/perbarui/hapus tidak ada |
| 405 | Metode permintaan tidak diizinkan | Hanya GET/POST/PUT/DELETE/OPTIONS/HEAD yang diizinkan, metode non-standar langsung ditolak |
| 413 | Body permintaan terlalu besar | Content-Length melebihi 10MB |
| 415 | Tipe media tidak didukung | Content-Type permintaan POST/PUT bukan JSON dan bukan upload file |
| 422 | Gagal validasi parameter | Bidang wajib kosong, format tidak sesuai, validasi bisnis tidak lolos |
| 429 | Terlalu banyak permintaan | RateLimit terpicu / akun terkunci (5 kali kegagalan login berturut-turut terkunci 15 menit) |
| 500 | Kesalahan internal server | |

## 3. Endpoint Publik

Semua endpoint publik terpasang di grup `/api`, didistribusikan oleh middleware `ApiVersion` ke kontroler ber-versi sesuai header `API-Version` (misalnya `app\api\v1\controller\AuthController`).

### 3.1 Pemeriksaan Kesehatan

```
GET /health
```

- **Autentikasi**: Tidak diperlukan
- **Rate limit**: Tidak ada

**Contoh respons**:
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

Nilai `database`, `redis`, `elasticsearch`: `"ok"` | `"unavailable"`. `elasticsearch` mengembalikan `"unavailable"` ketika ES tidak dapat dijangkau; jika status kesehatan cluster bukan green/yellow, mengembalikan nilai status aktual (misalnya `"red"`).

### 3.2 Dokumentasi API

```
GET /api/docs
```

- **Autentikasi**: Tidak diperlukan
- **Rate limit**: Default global (60 kali/menit)
- **Respons**: Spesifikasi JSON OpenAPI 3.0.3, berisi definisi semua endpoint, parameter, dan Schema

### 3.3 Membuat Captcha

```
POST /api/captcha/generate
```

- **Autentikasi**: Tidak diperlukan
- **Header permintaan**: `API-Version: v1` (wajib)
- **Rate limit**: Default global (60 kali/menit)

**Body permintaan**:
```json
{
  "difficulty": "medium"
}
```

| Bidang | Tipe | Wajib | Keterangan |
|------|------|------|------|
| difficulty | string | Tidak | `easy` / `medium` / `hard`, default `medium` |

**Contoh respons** — tipe klik (`type: "click"`):
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

**Contoh respons** — tipe slider (`type: "slider"`):
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

**Contoh respons** — tipe rotasi (`type: "rotate"`):
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

| Bidang | Tipe | Keterangan |
|------|------|------|
| key | string | Identitas captcha, dikirim kembali saat verifikasi |
| type | string | Jenis captcha: `click` / `slider` / `rotate` |
| image | string | Gambar base64 data URI |
| extra | object | Data tambahan terkait jenis (lihat di bawah) |

**Keterangan `extra` per jenis**:

| type | Bidang extra | Tipe | Keterangan |
|------|-----------|------|------|
| click | targets | array | Target klik, berisi `order`(urutan) `text`(teks petunjuk) `x` `y`(koordinat) |
| slider | x, y | int | Koordinat sudut kiri atas celah (berdasarkan kanvas 300×200) |
| slider | puzzle_w, puzzle_h | int | Lebar dan tinggi gambar puzzle |
| slider | puzzle | string | Gambar puzzle base64 data URI |
| rotate | angle | int | Sudut rotasi yang benar (0-359), perlu diputar `360-angle` agar gambar kembali tegak |

### 3.4 Verifikasi Captcha

```
POST /api/captcha/verify
```

- **Autentikasi**: Tidak diperlukan
- **Header permintaan**: `API-Version: v1` (wajib)
- **Rate limit**: Default global (60 kali/menit)

**Body permintaan** — tipe klik (`type: "click"`):
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

**Body permintaan** — tipe slider (`type: "slider"`):
```json
{
  "key": "def456abc789",
  "type": "slider",
  "clicks": 120
}
```

**Body permintaan** — tipe rotasi (`type: "rotate"`):
```json
{
  "key": "ghi789abc012",
  "type": "rotate",
  "clicks": 315
}
```

| Bidang | Tipe | Wajib | Keterangan |
|------|------|------|------|
| key | string | Ya | Key captcha, dikembalikan oleh generate |
| type | string | Ya | Jenis captcha, harus sama dengan `type` yang dikembalikan generate |
| clicks | varian | Ya | Data jawaban, format mengikuti type (lihat di bawah) |

**Keterangan `clicks` per jenis**:

| type | Tipe clicks | Keterangan | Toleransi kesalahan |
|------|------------|------|---------|
| click | `[{x:int, y:int}]` | Array koordinat klik, sesuai urutan order | Radius 18px |
| slider | `int` | Offset sumbu X slider | ±4px |
| rotate | `int` | Sudut rotasi (0-359) | ±5° |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

Setelah verifikasi berhasil, backend menulis `captcha_verified:{key}` ke Redis (TTL 300s), dan antarmuka login memvalidasi berdasarkan ini.
Saat verifikasi gagal, `code` adalah 422, `message` adalah `"验证失败，请重试"`, `data.valid` adalah `false`.

### 3.5 Login

```
POST /api/auth/login
```

- **Autentikasi**: Tidak diperlukan
- **Header permintaan**: `API-Version: v1` (wajib)
- **Rate limit**: 10 kali/menit (per IP + path)

**Body permintaan**:
```json
{
  "username": "admin",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "captcha_key": "abc123def456"
}
```

| Bidang | Tipe | Wajib | Aturan validasi | Keterangan |
|------|------|------|---------|------|
| username | string | Ya | min:3, max:50 | Nama pengguna |
| password | string | Ya | min:6, max:32 (teks polos) | Dienkripsi AES-256-CBC-HMAC lalu di-encode Base64 (kompatibel teks polos) |
| captcha_key | string | Ya | | Key captcha (harus lolos verifikasi `/api/captcha/verify` terlebih dahulu) |

### Protokol Enkripsi Kata Sandi

Menggunakan **enkripsi asimetris RSA-2048**, kunci publik disimpan di kode frontend (aman diekspos), kunci privat hanya dipegang server.

```
Alur enkripsi (klien):
  Kunci publik RSA (PEM) → enkripsi PKCS1v1.5 → encode Base64 → transmisi

Alur dekripsi (server, fallback bertahap):
  1. Dekripsi kunci privat RSA → sukses dan UTF-8 valid → gunakan hasil dekripsi
  2. Dekripsi AES-256-CBC-HMAC → sukses → gunakan hasil dekripsi (kompatibilitas klien lama)
  3. Fallback teks polos → langsung gunakan input asli
```

Kunci publik tertanam di aplikasi frontend, tidak perlu dikirim melalui jaringan. Kunci privat hanya disimpan di `RSA_PRIVATE_KEY` pada `.env`, tidak boleh bocor.

> Enkripsi simetris AES adalah solusi kompatibilitas versi lama; akan dihapus setelah semua klien bermigrasi ke RSA.

**Contoh respons**:
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

| Bidang | Tipe | Keterangan |
|------|------|------|
| access_token | string | Token akses JWT |
| refresh_token | string | Token refresh JWT |
| expires_in | int | Masa berlaku token akses (detik), default 7200 |
| user.id | string | ID pengguna terenkripsi hashid |
| user.username | string | Nama pengguna |
| user.real_name | string | Nama asli |

**Kesalahan yang mungkin**:
- 422: Gagal validasi parameter (bidang wajib kosong, format tidak sesuai)
- 422: Selesaikan verifikasi captcha terlebih dahulu (captcha_key tidak lolos `/api/captcha/verify`)
- 401: Nama pengguna atau kata sandi salah
- 403: Akun telah dinonaktifkan
- 429: Akun telah terkunci, coba lagi setelah 15 menit (dipicu 5 kali kegagalan login berturut-turut)

### 3.6 Registrasi

```
POST /api/auth/register
```

- **Autentikasi**: Tidak diperlukan
- **Header permintaan**: `API-Version: v1` (wajib)
- **Rate limit**: 5 kali/menit (per IP + path)

**Body permintaan**:
```json
{
  "username": "newuser",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "real_name": "新用户",
  "captcha_key": "abc123def456"
}
```

| Bidang | Tipe | Wajib | Aturan validasi | Keterangan |
|------|------|------|---------|------|
| username | string | Ya | min:3, max:50 | Nama pengguna (unik) |
| password | string | Ya | min:6, max:32 (teks polos) | Dienkripsi AES-256-CBC-HMAC lalu di-encode Base64 |
| real_name | string | Ya | max:50 | Nama asli |
| captcha_key | string | Ya | | Key captcha (harus lolos verifikasi `/api/captcha/verify` terlebih dahulu) |

**Contoh respons**:
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

Setelah registrasi berhasil, token JWT langsung dikembalikan, status pengguna default aktif (status=1).

### 3.7 Refresh Token

```
POST /api/auth/refresh
```

- **Autentikasi**: Tidak diperlukan
- **Header permintaan**: `API-Version: v1` (wajib)
- **Rate limit**: Default global (60 kali/menit)

**Body permintaan**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Bidang | Tipe | Wajib | Keterangan |
|------|------|------|------|
| refresh_token | string | Ya | refresh_token yang didapat saat login/registrasi |

**Contoh respons**:
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

Refresh berhasil mengembalikan access_token dan refresh_token baru secara bersamaan, token lama otomatis tidak berlaku. Saat refresh, waktu login terakhir dan IP pengguna diperbarui.

**Kesalahan yang mungkin**:
- 422: Token refresh tidak ada
- 401: Token refresh tidak valid atau sudah kedaluwarsa

### 3.8 Metrik Pemantauan Prometheus

```
GET /metrics
```

- **Autentikasi**: Tidak diperlukan
- **Rate limit**: Tidak ada
- **Format respons**: Prometheus text format (`text/plain; version=0.0.4`)

Endpoint metrik pemantauan Prometheus publik, untuk diambil oleh Grafana/Prometheus.

**Contoh respons**:
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

| Nama metrik | Tipe | Keterangan |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Total kumulatif permintaan HTTP |
| `openadmin_active_users` | gauge | Jumlah pengguna aktif saat ini (login dalam 24 jam) |
| `openadmin_db_connection_status` | gauge | Status koneksi basis data, 1=normal, 0=abnormal |
| `openadmin_redis_connection_status` | gauge | Status koneksi Redis, 1=normal, 0=abnormal |
| `openadmin_memory_usage_bytes` | gauge | Penggunaan memori saat ini proses PHP (bytes) |

## 4. Dasbor

Semua antarmuka sisi admin terpasang di grup `/admin`, melalui tiga middleware `AdminAuth` (autentikasi JWT), `AdminPermission` (validasi hak akses RBAC), `OperationLog` (pencatatan operasi).

### 4.1 Data Dasbor

```
GET /admin/dashboard
```

- **Autentikasi**: JWT + RBAC
- **Cache**: Redis 5 menit

**Contoh respons**:
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
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| Bidang stats | Tipe | Keterangan |
|------|------|------|
| label | string | Nama indikator |
| value | string | Nilai indikator (tipe string) |
| icon | string | Nama ikon Material |
| color | string | Nilai warna kartu |
| trend | float? | Tingkat pertumbuhan harian (persen), hanya "用户总数" yang memiliki bidang ini |

| Bidang trends | Tipe | Keterangan |
|------|------|------|
| dates | array{string} | Seri tanggal 30 hari terakhir |
| series | array{object} | Data garis tren, setiap item berisi name (nama), data (array nilai), color (warna) |

## 5. Manajemen Pengguna

Semua `id` yang dikembalikan antarmuka manajemen pengguna adalah string terenkripsi hashid. Bidang kata sandi telah dikecualikan dari respons. Nomor ponsel dan email disamarkan di antarmuka daftar, dan dikembalikan sebagai teks polos di antarmuka detail (bidang terenkripsi basis data didekripsi otomatis oleh trait Encryptable).

### 5.1 Daftar Pengguna

```
GET /admin/user
```

- **Autentikasi**: JWT + RBAC

**Parameter kueri**:

| Parameter | Tipe | Wajib | Nilai default | Keterangan |
|------|------|------|------|------|
| page | int | Tidak | 1 | Nomor halaman |
| limit | int | Tidak | 15 | Jumlah per halaman |
| keyword | string | Tidak | | Kata kunci pencarian, mencocokkan nama pengguna dan nama asli |
| status | int | Tidak | | Filter status, 0=nonaktif, 1=aktif |

**Contoh respons**:
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

| Bidang | Tipe | Keterangan |
|------|------|------|
| id | string | ID pengguna terenkripsi hashid |
| username | string | Nama pengguna |
| real_name | string | Nama asli |
| phone | string | Nomor ponsel tersamarkan (format `138****5678`) |
| email | string | Email tersamarkan (format `a***@example.com`) |
| status | int | 1=aktif, 0=nonaktif |
| last_login_at | string | Waktu login terakhir (datetime) |
| created_at | string | Waktu pembuatan (datetime) |

### 5.2 Membuat Pengguna

```
POST /admin/user
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
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

| Bidang | Tipe | Wajib | Aturan validasi | Keterangan |
|------|------|------|---------|------|
| username | string | Ya | min:3, max:50 | Nama pengguna (unik) |
| password | string | Ya | min:6, max:32 | Kata sandi (disimpan bcrypt) |
| real_name | string | Ya | max:50 | Nama asli |
| phone | string | Tidak | | Nomor ponsel (disimpan terenkripsi Encryptable) |
| email | string | Tidak | | Email (disimpan terenkripsi Encryptable) |
| status | int | Tidak | in:0,1 | Status, default 1 (aktif) |

**Contoh respons**:
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

**Kesalahan yang mungkin**:
- 422: Nama pengguna sudah ada
- 422: Gagal validasi parameter (bidang wajib kosong)

### 5.3 Detail Pengguna

```
GET /admin/user/{id}
```

- **Autentikasi**: JWT + RBAC
- **Parameter path**: `{id}` adalah ID pengguna terenkripsi hashid

**Contoh respons**:
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

Pada antarmuka detail, `phone` dan `email` dikembalikan sebagai teks polos (tersimpan terenkripsi di basis data, cast Encryptable mendekripsi otomatis), tanpa penyamaran. `password` dan `id_card` tidak pernah ada dalam respons.

**Kesalahan yang mungkin**:
- 404: Pengguna tidak ada

### 5.4 Memperbarui Pengguna

```
PUT /admin/user/{id}
```

- **Autentikasi**: JWT + RBAC
- **Parameter path**: `{id}` adalah ID pengguna terenkripsi hashid

**Body permintaan**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Bidang | Tipe | Wajib | Keterangan |
|------|------|------|------|
| real_name | string | Tidak | Nama asli, jika tidak dikirim mempertahankan nilai lama |
| password | string | Tidak | Kata sandi baru, string kosong atau tidak dikirim berarti tidak diubah |
| phone | string | Tidak | Nomor ponsel |
| email | string | Tidak | Email |
| status | int | Tidak | 0=nonaktif, 1=aktif |

**Contoh respons**:
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

**Kesalahan yang mungkin**:
- 404: Pengguna tidak ada

### 5.5 Menghapus Pengguna

```
DELETE /admin/user/{id}
```

- **Autentikasi**: JWT + RBAC
- **Parameter path**: `{id}` adalah ID pengguna terenkripsi hashid
- **Operasi sensitif**: Memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "password": "admin_password"
}
```

| Bidang | Tipe | Wajib | Keterangan |
|------|------|------|------|
| password | string | Ya | Kata sandi pengguna yang sedang login (konfirmasi ulang) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Menjalankan soft delete (Eloquent SoftDeletes), data ditandai deleted_at tanpa penghapusan fisik.

**Kesalahan yang mungkin**:
- 404: Pengguna tidak ada
- 422: Operasi sensitif memerlukan konfirmasi kata sandi (password kosong)
- 422: Gagal verifikasi kata sandi (kata sandi tidak cocok)

### 5.6 Menghapus Pengguna Secara Massal

```
POST /admin/user/batch/destroy
```

- **Autentikasi**: JWT + RBAC
- **Operasi sensitif**: Memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Bidang | Tipe | Wajib | Keterangan |
|------|------|------|------|
| ids | array{string} | Ya | Array ID pengguna terenkripsi hashid |
| password | string | Ya | Kata sandi pengguna yang sedang login (konfirmasi ulang) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Menjalankan soft delete, `data.count` adalah jumlah yang benar-benar dihapus.

**Kesalahan yang mungkin**:
- 422: Pilih pengguna yang akan dihapus (ids kosong)
- 422: ID tidak valid (gagal dekode hashid)
- 422: Gagal verifikasi kata sandi

### 5.7 Mengaktifkan/Menonaktifkan Pengguna Secara Massal

```
POST /admin/user/batch/status
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Bidang | Tipe | Wajib | Keterangan |
|------|------|------|------|
| ids | array{string} | Ya | Array ID pengguna terenkripsi hashid |
| status | int | Ya | 0=nonaktif, 1=aktif |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message berubah dinamis sesuai nilai status menjadi `"批量启用成功"` atau `"批量禁用成功"`.

**Kesalahan yang mungkin**:
- 422: Pilih pengguna (ids kosong)
- 422: Nilai status tidak valid (status bukan 0 atau 1)

## 6. Manajemen Peran

### 6.1 Daftar Peran

```
GET /admin/role
```

- **Autentikasi**: JWT + RBAC

**Parameter kueri**:

| Parameter | Tipe | Wajib | Nilai default | Keterangan |
|------|------|------|------|------|
| page | int | Tidak | 1 | Nomor halaman |
| limit | int | Tidak | 15 | Jumlah per halaman |

**Contoh respons**:
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

| Bidang | Tipe | Keterangan |
|------|------|------|
| id | string | ID peran terenkripsi hashid |
| name | string | Nama peran |
| slug | string | Identitas peran (unik, digunakan untuk penilaian hak akses) |
| description | string | Deskripsi peran |
| status | int | 1=aktif, 0=nonaktif |
| users_count | int | Jumlah pengguna yang memiliki peran ini |

### 6.2 Membuat Peran

```
POST /admin/role
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Bidang | Tipe | Wajib | Aturan validasi | Keterangan |
|------|------|------|---------|------|
| name | string | Ya | max:50 | Nama peran |
| slug | string | Ya | max:50 | Identitas peran |
| description | string | Tidak | | Deskripsi peran, default string kosong |
| status | int | Tidak | | Status, default 1 |
| permission_ids | array{int} | Tidak | | Array ID hak akses (ID INT asli, bukan hashid) |

**Contoh respons**:
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

### 6.3 Memperbarui Peran

```
PUT /admin/role/{id}
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Bidang | Tipe | Wajib | Keterangan |
|------|------|------|------|
| name | string | Tidak | Nama peran |
| description | string | Tidak | Deskripsi |
| status | int | Tidak | 0=nonaktif, 1=aktif |
| permission_ids | array{int} | Tidak | Array ID hak akses; jika dikirim maka hak akses peran disinkronkan (ditimpa) |

**Contoh respons**:
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

### 6.4 Menghapus Peran

```
DELETE /admin/role/{id}
```

- **Autentikasi**: JWT + RBAC
- **Operasi sensitif**: Memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "password": "admin_password"
}
```

**Contoh respons**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Saat menghapus, relasi antara peran dengan semua hak akses dan pengguna otomatis dibebaskan, lalu catatan peran dihapus secara fisik.

## 7. Manajemen Hak Akses

Hak akses menggunakan struktur pohon (parent_id self-referencing), dibagi menjadi tiga jenis. Antarmuka daftar mengembalikan pohon hak akses lengkap.

### 7.1 Pohon Hak Akses

```
GET /admin/permission
```

- **Autentikasi**: JWT + RBAC

**Contoh respons**:
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

| Bidang | Tipe | Keterangan |
|------|------|------|
| id | string | Terenkripsi hashid |
| parent_id | string | Hashid hak akses induk, `"0"` berarti node akar |
| name | string | Nama hak akses |
| slug | string | Identitas hak akses (identitas rute/tombol) |
| type | int | 1=menu, 2=tombol, 3=API |
| icon | string | Ikon menu (nama ikon Material) |
| path | string | Path rute frontend |
| sort | int | Nilai urutan (ascending) |
| children | array? | Daftar hak akses anak (rekursif), tidak ada bidang ini jika tanpa node anak |

### 7.2 Membuat Hak Akses

```
POST /admin/permission
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
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

| Bidang | Tipe | Wajib | Aturan validasi | Keterangan |
|------|------|------|---------|------|
| parent_id | int | Tidak | | ID hak akses induk (tipe INT asli), default 0 |
| name | string | Ya | max:50 | Nama hak akses |
| slug | string | Ya | max:100 | Identitas hak akses |
| type | int | Ya | in:1,2,3 | 1=menu, 2=tombol, 3=API |
| icon | string | Tidak | | Ikon menu, default kosong |
| path | string | Tidak | | Path rute frontend, default kosong |
| sort | int | Tidak | | Nilai urutan, default 0 |

**Contoh respons**:
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

### 7.3 Memperbarui Hak Akses

```
PUT /admin/permission/{id}
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Bidang | Tipe | Wajib | Keterangan |
|------|------|------|------|
| name | string | Tidak | Nama hak akses |
| icon | string | Tidak | Ikon |
| path | string | Tidak | Path rute |
| sort | int | Tidak | Nilai urutan |

### 7.4 Menghapus Hak Akses

```
DELETE /admin/permission/{id}
```

- **Autentikasi**: JWT + RBAC
- **Operasi sensitif**: Memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "password": "admin_password"
}
```

**Contoh respons**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Saat menghapus, semua hak akses anak dihapus berjenjang (`parent_id` = catatan ID hak akses saat ini), sekaligus membebaskan relasi dengan semua peran.

## 8. Konfigurasi Sistem

Konfigurasi sistem unik berdasarkan kombinasi `group` + `key`.

### 8.1 Daftar Konfigurasi

```
GET /admin/config
```

- **Autentikasi**: JWT + RBAC

**Parameter kueri**:

| Parameter | Tipe | Wajib | Nilai default | Keterangan |
|------|------|------|------|------|
| page | int | Tidak | 1 | Nomor halaman |
| limit | int | Tidak | 15 | Jumlah per halaman |
| group | string | Tidak | | Filter berdasarkan grup konfigurasi |

**Contoh respons**:
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

| Bidang | Tipe | Keterangan |
|------|------|------|
| id | string | hashid |
| group | string | Grup konfigurasi (misalnya `system`, `email`, `storage`) |
| key | string | Kunci konfigurasi |
| value | string | Nilai konfigurasi |
| type | string | Petunjuk tipe nilai (`string`, `integer`, `boolean`, `json`, dll.) |
| description | string | Keterangan konfigurasi |

### 8.2 Membuat Konfigurasi

```
POST /admin/config
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Bidang | Tipe | Wajib | Aturan validasi | Keterangan |
|------|------|------|---------|------|
| group | string | Ya | max:100 | Grup konfigurasi |
| key | string | Ya | max:100 | Kunci konfigurasi (unik dalam grup yang sama) |
| value | string | Ya | | Nilai konfigurasi |
| type | string | Tidak | | Tipe nilai, default `string` |
| description | string | Tidak | | Keterangan konfigurasi, default kosong |

**Contoh respons**:
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

**Kesalahan yang mungkin**:
- 422: Item konfigurasi sudah ada (grup + key yang sama)

### 8.3 Memperbarui Konfigurasi

```
PUT /admin/config/{id}
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Bidang | Tipe | Wajib | Keterangan |
|------|------|------|------|
| value | string | Tidak | Perbarui nilai konfigurasi |
| type | string | Tidak | Perbarui tipe nilai |
| description | string | Tidak | Perbarui teks keterangan |

### 8.4 Menghapus Konfigurasi

```
DELETE /admin/config/{id}
```

- **Autentikasi**: JWT + RBAC
- **Operasi sensitif**: Memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "password": "admin_password"
}
```

Menghapus catatan konfigurasi secara fisik.

## 9. Log Operasi

Log operasi adalah antarmuka read-only, ditulis otomatis oleh middleware `OperationLog` pada setiap permintaan POST/PUT/DELETE, bidang yang disimpan meliputi `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Daftar Log Operasi

```
GET /admin/log
```

- **Autentikasi**: JWT + RBAC

**Parameter kueri**:

| Parameter | Tipe | Wajib | Nilai default | Keterangan |
|------|------|------|------|------|
| page | int | Tidak | 1 | Nomor halaman |
| limit | int | Tidak | 15 | Jumlah per halaman |
| user_id | int | Tidak | | Filter presisi berdasarkan ID pengguna (tipe INT asli) |
| action | string | Tidak | | Filter presisi berdasarkan aksi operasi |
| path | string | Tidak | | Filter fuzzy berdasarkan path permintaan |
| start_date | string | Tidak | | Tanggal mulai (format Y-m-d) |
| end_date | string | Tidak | | Tanggal selesai (format Y-m-d) |

**Contoh respons**:
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
        "path": "/api/auth/login",
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

| Bidang | Tipe | Keterangan |
|------|------|------|
| id | string | hashid |
| user_name | string | Nama pengguna operasi (didapat melalui relasi user, operasi tanpa login ditampilkan「系统」) |
| action | string | Deskripsi aksi operasi |
| method | string | Metode HTTP (POST/PUT/DELETE) |
| path | string | Path permintaan |
| ip | string | IP klien |
| source | string | Sumber permintaan |
| input | string | String JSON parameter permintaan (tidak termasuk file) |
| created_at | string | Waktu operasi (datetime) |

## 10. Pusat Akun Pribadi

Antarmuka pusat akun hanya memerlukan autentikasi JWT (tidak perlu validasi hak akses RBAC — middleware `AdminPermission` harus menambahkannya ke whitelist).

### 10.1 Memperbarui Informasi Pribadi

```
PUT /admin/profile
```

- **Autentikasi**: JWT

**Body permintaan**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Bidang | Tipe | Wajib | Keterangan |
|------|------|------|------|
| real_name | string | Tidak | Nama asli |
| phone | string | Tidak | Nomor ponsel (disimpan terenkripsi Encryptable) |
| email | string | Tidak | Email (disimpan terenkripsi Encryptable) |

**Contoh respons**:
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

Dalam respons, `phone` dan `email` dikembalikan sebagai teks polos, `password` dan `id_card` telah dihapus.

### 10.2 Mengubah Kata Sandi

```
PUT /admin/profile/password
```

- **Autentikasi**: JWT

**Body permintaan**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Bidang | Tipe | Wajib | Aturan validasi | Keterangan |
|------|------|------|---------|------|
| old_password | string | Ya | | Kata sandi saat ini |
| new_password | string | Ya | min:6, max:32 | Kata sandi baru |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Kesalahan yang mungkin**:
- 422: Mohon isi kata sandi lama dan baru
- 422: Kata sandi lama salah
- 422: Panjang kata sandi baru 6-32 karakter

### 10.3 Logout

```
POST /admin/profile/logout
```

- **Autentikasi**: JWT

**Body permintaan**: Tidak ada (tanpa requestBody, token dibaca dari header Authorization)

**Contoh respons**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Logika logout: dekode JWT untuk mendapatkan sisa masa berlaku (exp - now), tulis hash md5 token tersebut ke blacklist Redis `jwt_blacklist:{md5}`, TTL = sisa masa berlaku. Token dalam blacklist dicegat di middleware `AdminAuth`, mengembalikan 401.

Tanpa token mengembalikan 401. Token kedaluwarsa/tidak valid (pengecualian saat dekode) tetap dianggap logout sukses.

## 11. Impor & Ekspor

### 11.1 Ekspor Excel

```
POST /admin/export/excel
```

- **Autentikasi**: JWT + RBAC
- **Tipe respons**: Unduhan file (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Body permintaan**:
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

| Bidang | Tipe | Wajib | Nilai default | Keterangan |
|------|------|------|------|------|
| table | string | Tidak | `admin_user` | Nama tabel yang diekspor. Didukung: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | Tidak | | Array nama bidang kolom yang diekspor; kosong berarti mengekspor semua kolom tabel |
| conditions | object | Tidak | `{}` | Kondisi filter, pasangan key-value, nilai tidak kosong digunakan untuk WHERE |
| title | string | Tidak | `数据导出` | Judul Excel (ditampilkan sebagai nama Sheet) |

**Tabel dan kolom yang didukung**:

| table | Kolom yang tersedia |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Bidang sensitif `phone`, `email`, `id_card` otomatis disamarkan saat ekspor. Batas data 10000 baris. Baris pertama Excel dibekukan, filter otomatis diaktifkan.

### 11.2 Ekspor PDF

```
POST /admin/export/pdf
```

- **Autentikasi**: JWT + RBAC
- **Tipe respons**: Unduhan file (`application/pdf`, A4 landscape)

**Body permintaan**:
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

Atau mode tabel:
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

| Bidang | Tipe | Wajib | Nilai default | Keterangan |
|------|------|------|------|------|
| type | string | Tidak | `table` | Tipe ekspor: `table` / `dashboard` |
| title | string | Tidak | `数据导出` | Judul PDF |
| data | object | Tidak | `{}` | Data ekspor |

Saat `type=dashboard`, `data` harus berisi array `stats` (dirender sebagai kartu); saat `type=table`, `data` harus berisi array `columns` dan `rows`.

Template PDF menyertakan informasi hak cipta dan stempel waktu ekspor.

### 11.3 Impor Pengguna (Excel)

```
POST /admin/import/users
```

- **Autentikasi**: JWT + RBAC
- **Tipe permintaan**: `multipart/form-data` (upload file)

**Bidang formulir**:

| Bidang | Tipe | Wajib | Keterangan |
|------|------|------|------|
| file | file | Ya | Format `.xlsx` atau `.xls` |

**Persyaratan kolom Excel**:

| Nama kolom | Wajib | Keterangan |
|------|------|------|
| username | Ya | Nama pengguna (unik) |
| password | Ya | Kata sandi (disimpan hash bcrypt) |
| real_name | Ya | Nama asli |
| phone | Tidak | Nomor ponsel |
| email | Tidak | Email |
| status | Tidak | Status, default 1 |

Baris ke-1 adalah judul kolom (tidak peka huruf besar/kecil), baris ke-2 dan seterusnya adalah data.

**Contoh respons**:
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

| Bidang | Tipe | Keterangan |
|------|------|------|
| total | int | Total baris (tidak termasuk baris judul) |
| success | int | Jumlah impor sukses |
| failed | int | Jumlah gagal |
| errors | array | Detail kegagalan, setiap item berisi row (nomor baris Excel) dan reason (alasan kegagalan) |

## 12. Upload File

```
POST /admin/upload
```

- **Autentikasi**: JWT + RBAC
- **Tipe permintaan**: `multipart/form-data`

**Bidang formulir**:

| Bidang | Tipe | Wajib | Keterangan |
|------|------|------|------|
| file | file | Ya | File yang diunggah |

**Tipe file yang diizinkan**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Ukuran file maksimum**: 10MB

**Contoh respons**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

File disimpan dalam direktori berdasarkan tanggal di `public/upload/{Y-m-d}/`, nama file adalah `md5(uniqid) + ekstensi asli`. `url` adalah path relatif dari akar situs.

**Kesalahan yang mungkin**:
- 422: Pilih file (belum diunggah)
- 422: Tipe file tidak didukung
- 422: Ukuran file tidak boleh melebihi 10MB
- 500: Gagal upload file (file tidak valid)

## 13. Header Respons

Semua antarmuka (disuntikkan pada lapisan middleware global) menyertakan header respons berikut:

| Header | Keterangan |
|----|------|
| `X-RateLimit-Limit` | Batas atas rate limit (jumlah) |
| `X-RateLimit-Remaining` | Sisa jumlah permintaan |
| `X-RateLimit-Reset` | Stempel waktu reset jendela rate limit |
| `Retry-After` | Hanya dikembalikan saat rate limit terpicu, jumlah detik yang disarankan untuk menunggu |
| `X-Content-Type-Options` | `nosniff` (default dari webman, melarang MIME sniffing) |
| `X-Frame-Options` | `DENY` (disediakan oleh middleware CORS/konfigurasi dasar webman) |

Detail rate limit:
- Batas global default: 60 kali/menit / IP+path
- Endpoint login `/api/auth/login`: 10 kali/menit
- Endpoint registrasi `/api/auth/register`: 5 kali/menit
- Menggunakan algoritma sliding window atomik Redis (Lua ZSET), menghindari race condition TOCTOU
- Saat Redis tidak tersedia, fail open (membiarkan lewat), tidak memblokir permintaan

## 14. Alur Autentikasi

Urutan autentikasi lengkap:

```
1. 客户端请求 POST /api/captcha/generate
   (请求头: API-Version: v1)
    ↓
   服务端返回: key + type(click|slider|rotate) + base64 图片 + extra(类型相关数据)
   
2. 用户交互完成验证码操作（点击/拖拽/旋转），客户端收集答案
   
3. 客户端请求 POST /api/captcha/verify
   (请求头: API-Version: v1, Content-Type: application/json)
   请求体: { key, type, clicks }
   - type=click:  clicks = [{x, y}, ...]        // 坐标数组
   - type=slider: clicks = 120                   // X 偏移量
   - type=rotate: clicks = 315                   // 旋转角度
    ↓
   服务端:
   a. 从存储读取 captcha:key 数据（TTL 300s）
   b. 按 type 校验答案（click: 欧氏距离 ≤18px / slider: ±4px / rotate: ±5°）
   c. 校验通过 → 写入 Redis `captcha_verified:{key}` = 1 (TTL 300s)
   d. 校验失败 → 返回 422，计数 +1，超过 3 次 key 作废
    ↓
   服务端返回: { valid: true/false }

4. 客户端请求 POST /api/auth/login
   (请求头: API-Version: v1, Content-Type: application/json)
   请求体: { username, password(加密), captcha_key }
    ↓
   服务端:
   a. 参数校验 → 422
   b. 检查 captcha_verified:{key} 是否存在 → 422
   c. 删除 captcha_verified:{key}（一次性使用）
   d. 解密密码: EncryptionService::decrypt(password) → 明文
   e. 校验用户凭证 (password_verify) → 401
   f. 检查账号状态 → 403/429
   g. 签发 JWT (access + refresh) → 200
   h. 更新 last_login_at / last_login_ip
    ↓
   客户端保存: access_token, refresh_token, expires_in

5. 后续请求携带 JWT
   请求头: Authorization: Bearer <access_token>
    ↓
   AdminAuth 中间件:
   a. 提取 Bearer token
   b. 检查黑名单 (Redis jwt_blacklist:{md5}) → 401
   c. 解码 JWT，校验过期 → 401
   d. 设置 $request->adminId = sub 字段
    ↓
   AdminPermission 中间件:
   a. 对资源路由解析权限标识
   b. 查询用户角色 → 角色权限，进行匹配
   c. 无权限 → 403
    ↓
   Controller 处理请求
    ↓
   Response + X-RateLimit-* 头

6. Access Token 过期前刷新
   客户端请求 POST /api/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

7. 登出
   客户端请求 POST /admin/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### Struktur JWT

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, TTL default 7200 detik (dikontrol oleh konfigurasi JWT `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, TTL default 1209600 detik (dikontrol oleh konfigurasi JWT `refresh_expire`, yaitu 14 hari)

### Manajemen Keamanan

- Kata sandi disimpan dengan hash `PASSWORD_BCRYPT`
- Lapisan transmisi kata sandi menggunakan enkripsi AES-256-CBC-HMAC (enkripsi klien → dekripsi server), kompatibel fallback teks polos
- Bidang sensitif (phone, email, id_card) menggunakan `erikwang2013/encryptable` untuk enkripsi/dekripsi transparan di lapisan basis data
- ID lapisan API menggunakan `erikwang2013/hashids` untuk transmisi terenkripsi, menghindari paparan urutan ID snowflake asli
- SecurityFilter memindai XSS, injeksi SQL, path traversal, injeksi perintah secara global; IP yang sama 5 kali/60 detik masuk blacklist sementara 15 menit
- Operasi sensitif (menghapus pengguna, peran, hak akses, konfigurasi) memerlukan konfirmasi ulang kata sandi pengguna yang sedang login
- Batasan sesi bersamaan: satu pengguna maksimal 3 Token valid; saat perangkat ke-4 login, Token paling lama dipaksa masuk blacklist
- Penguncian akun: 5 kali kegagalan login berturut-turut memicu penguncian akun 15 menit, selama terkunci mengembalikan 429

### Arsitektur Middleware

Middleware global berlaku untuk semua permintaan, dieksekusi berurutan:

```
Cors（跨域预处理 + 响应头）
  → Locale（Accept-Language 语言检测 / ?lang=zh_CN|en）
  → SecurityFilter（HTTP方法限制/请求体大小/Content-Type校验/XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截）
  → RateLimit（Redis 滑动窗口限流 + 账号锁定：5次登录失败锁定15分钟）
  → ApiVersion（API 版本校验，/api 路由组）
  → AdminAuth（JWT 认证 + 黑名单，/admin 路由组）
  → AdminPermission（RBAC 鉴权 / Redis 60s 缓存，/admin 路由组）
  → OperationLog（POST/PUT/DELETE 自动记录，含来源端检测，/admin 路由组）
```

`/health` dan `/api/docs` adalah endpoint publik, hanya melalui `Cors → SecurityFilter → RateLimit`.

Penguatan keamanan:
- **Penguncian akun**: 5 kali kegagalan login berturut-turut, akun otomatis terkunci 15 menit, login selama periode tersebut mengembalikan 429
- **Batasan sesi bersamaan**: satu pengguna maksimal 3 Token valid; jika lebih, Token paling lama otomatis masuk blacklist
- **security.txt**: `GET /.well-known/security.txt` menyediakan informasi kontak keamanan standar RFC 9116
- **Konfigurasi keamanan Nginx**: lihat `docs/nginx-security.conf` untuk contoh penguatan keamanan reverse proxy yang lengkap

### Deteksi Sumber Operasi

Middleware OperationLog otomatis mengenali platform klien dan menulis ke bidang `source` log operasi:

| Platform | Cara deteksi |
|------|---------|
| `ipados` | UA mengandung iPad |
| `macos` | UA mengandung Macintosh/Mac OS |
| `windows` | UA mengandung Windows |
| `linux` | UA mengandung Linux (bukan Android) |
| `ios` | UA mengandung iPhone / iOS / CFNetwork |
| `android` | UA mengandung Android |
| `harmonyos` | UA mengandung HarmonyOS / OpenHarmony atau dideklarasikan eksplisit melalui header `X-Client-Platform` |
| `web` | Default (tidak cocok dengan semua platform di atas) |

> Deteksi dua tingkat: header `X-Client-Platform` (dideklarasikan oleh App native) → inferensi otomatis User-Agent (fallback). Bidang `source` pada kueri log operasi `GET /admin/log` adalah sumbernya.

## 15. Deployment & Operasional

### Docker Compose

Direktori root proyek menyediakan `docker-compose.yml`, mengorkestrasi 5 layanan (Nginx, app webman, MySQL, Redis, Elasticsearch). PHP dibangun melalui `Dockerfile` (berbasis `php:8.3-cli`, dengan OPcache diaktifkan).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` mendefinisikan pipeline integrasi berkelanjutan GitHub Actions:
- Pemeriksaan sintaks `php -l`
- Unit test PHPUnit
- Analisis statis `flutter analyze`

### Backup Basis Data

Direktori `database/backup/` menyediakan skrip backup dan pemulihan:
- `backup.sh` — backup kompresi mysqldump + gzip, otomatis membersihkan file backup lama lebih dari 30 hari
- `restore.sh` — pemulihan interaktif, menampilkan backup yang ada untuk dipilih pengguna

### Konfigurasi Keamanan Nginx

Untuk deployment produksi, lihat `docs/nginx-security.conf` untuk konfigurasi penguatan keamanan reverse proxy.
