> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](CLAUDE.md) | [English](CLAUDE.en.md) | [한국어](CLAUDE.ko.md) | [Русский](CLAUDE.ru.md) | [Deutsch](CLAUDE.de.md) | [Français](CLAUDE.fr.md) | [Español](CLAUDE.es.md) | [Português](CLAUDE.pt.md) | [हिन्दी](CLAUDE.hi.md) | [العربية](CLAUDE.ar.md) | [বাংলা](CLAUDE.bn.md) | [Bahasa Indonesia](CLAUDE.id.md) | [日本語](CLAUDE.ja.md)

# Panel Admin Terbuka (open-admin)

Sistem panel admin full-stack berbasis webman v2 + Flutter.

## Deklarasi Hak Cipta

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **Tidak dapat dimodifikasi, tidak dapat dihapus, tidak dapat dibatalkan.** Semua file baru wajib menyertakan deklarasi hak cipta di atas sebagai komentar header file.

## Daftar Fitur

| Domain | Fitur |
|----|------|
| Autentikasi | Login/Registrasi/Refresh/Logout + captcha + penguncian akun + batasan sesi |
| Dasbor | Statistik real-time/tren/distribusi/log (cache Redis 5m) |
| Pengguna | CRUD + hapus massal/aktif-nonaktifkan + impor Excel |
| Peran & Hak Akses | CRUD + pohon hak akses + otorisasi RBAC method.path |
| Konfigurasi Sistem | CRUD pasangan kunci-nilai |
| Audit Operasi | Kueri log + deteksi otomatis sumber 8 platform |
| File | Upload + ekspor Excel/PDF (penyamaran data sensitif) |
| Keamanan | 18 lapis pertahanan berlapis (XSS/Injeksi SQL/CSRF/rate limit/CSP...) |
| Operasional | Health check/metrik Prometheus/dokumen API/security.txt + Docker + CI/CD |

## Tumpukan Teknologi

### Backend
- PHP 8.3+, webman v2 (workerman/webman)
- Basis data: MySQL 8.0+, prefiks tabel `logistics_`
- Primary key: BIGINT non-auto-increment, dibuat oleh `erikwang2013/snowflake-php`
- Enkripsi/dekripsi ID lapisan API: `erikwang2013/hashids`
- Autentikasi JWT: `erikwang2013/jwt-webman`
- Enkripsi/dekripsi data sensitif API: `erikwang2013/encryption`
- Enkripsi/dekripsi bidang sensitif basis data: `erikwang2013/encryptable`
- Sinkronisasi & kueri ES: `erikwang2013/webman-scout`
- Bendera negara: `erikwang2013/season`

### Frontend
- Flutter 3.x, direktori sumber `apps/flutter/`
- Versi Web didesain bergaya panel admin PC (bukan gaya App seluler)
- Mendukung klien dan sisi admin
- HarmonyOS ArkTS, direktori sumber `apps/harmonyos/`

## Struktur Proyek

```
open-admin/
├── app/
│   ├── admin/controller/       # Kontroler sisi admin (14)
│   │   ├── BaseController.php      # Kontroler dasar
│   │   ├── DashboardController.php # Dasbor (cache Redis)
│   │   ├── UserController.php      # CRUD pengguna + operasi massal
│   │   ├── RoleController.php      # CRUD peran
│   │   ├── PermissionController.php# CRUD hak akses
│   │   ├── ConfigController.php    # CRUD konfigurasi sistem
│   │   ├── LogController.php       # Kueri log operasi
│   │   ├── ProfileController.php   # Pusat akun pribadi + logout
│   │   ├── ExportController.php    # Ekspor Excel/PDF
│   │   ├── ImportController.php    # Impor pengguna via Excel
│   │   ├── UploadController.php    # Upload file
│   │   ├── HealthController.php    # Pemeriksaan kesehatan
│   │   ├── DocsController.php      # Dokumen OpenAPI
│   │   └── MetricsController.php   # Metrik pemantauan Prometheus
│   ├── api/v1/controller/      # Kontroler API v1 (dikontrol header versi)
│   │   ├── CaptchaController.php
│   │   └── AuthController.php
│   ├── common/                 # Kelas utilitas publik
│   │   ├── HashidsService.php
│   │   ├── SnowflakeService.php
│   │   └── EncryptionService.php
│   ├── common/                 # Definisi publik (termasuk Apidoc Definitions)
│   ├── middleware/             # Middleware (8)
│   │   ├── Cors.php            # Lintas domain (global)
│   │   ├── SecurityFilter.php  # Interception serangan (global: XSS/Injeksi SQL/Path traversal/Injeksi perintah/CSRF)
│   │   ├── RateLimit.php       # Rate limit Redis (global, atomik Lua)
│   │   ├── ApiVersion.php      # Validasi versi API
│   │   ├── AdminAuth.php       # Autentikasi JWT + blacklist
│   │   ├── AdminPermission.php # Validasi hak akses RBAC (cache Redis 60s)
│   │   └── OperationLog.php    # Pencatatan log operasi otomatis (termasuk deteksi sumber)
│   ├── model/                  # Model data
│   ├── queue/                  # Tugas antrian
│   └── process/                # Proses (Http, Monitor)
├── apps/
│   ├── flutter/                # Panel admin Web Flutter
│   │   └── lib/app/
│   │       ├── pages/          # 6 halaman lengkap
│   │       │   ├── dashboard/  # Dasbor
│   │       │   ├── login/      # Login
│   │       │   ├── user/       # Manajemen pengguna
│   │       │   ├── role/       # Peran & hak akses
│   │       │   ├── config/     # Konfigurasi sistem
│   │       │   ├── log/        # Log operasi
│   │       │   └── profile/    # Pusat akun pribadi
│   │       ├── services/       # ApiService + AuthService
│   │       ├── layouts/        # Tata letak responsif
│   │       └── theme/          # Tema Material 3
│   └── harmonyos/              # Klien HarmonyOS
├── config/                     # File konfigurasi
│   ├── route.php               # Routing + kebijakan versi API
│   └── middleware.php           # Registrasi middleware global
├── database/
│   ├── install.sql             # Skrip instalasi lengkap (menggabungkan semua SQL)
│   └── backup/                 # Skrip backup basis data
│       ├── backup.sh           # mysqldump+gzip, retensi 30 hari
│       └── restore.sh          # Pemulihan interaktif
├── docs/                       # Dokumentasi
│   ├── ARCHITECTURE.md         # Diagram arsitektur Mermaid
│   ├── DESIGN.md               # Dokumen desain
│   ├── SECURITY.md             # Desain arsitektur keamanan
│   ├── API.md                  # Dokumen referensi API
│   ├── nginx-security.conf     # Konfigurasi referensi keamanan Nginx
│   ├── diagrams/               # Diagram arsitektur terurai
│   └── superpowers/            # Spesifikasi & rencana
│       ├── specs/              # Spesifikasi desain
│       └── plans/              # Rencana implementasi
├── public/                     # Titik masuk publik
├── runtime/                    # File runtime
├── tests/                      # Pengujian
├── vendor/                     # Dependensi Composer
├── CLAUDE.md                   # File ini
├── README.md                   # Dokumentasi bahasa Cina
├── docs/translations/README.en.md                # Dokumentasi bahasa Inggris
├── docs/translations/README.ko.md ... README.ja.md  # Dokumentasi multi-bahasa (Korea/Rusia/Jerman/Prancis/Spanyol/Portugis/Hindi/Arab/Bengali/Indonesia/Jepang)
├── .env                        # Variabel lingkungan (tidak masuk versi kontrol)
├── .env.example                # Template variabel lingkungan
├── .env.docker                 # Variabel lingkungan Docker
├── composer.json               # Dependensi PHP
├── Dockerfile                  # Build Docker
├── docker-compose.yml          # Orkestrasi Docker
└── .github/
    └── workflows/
        └── ci.yml              # Pipeline CI/CD (sintaks PHP+PHPUnit+Flutter analyze)
```

## Rantai Eksekusi Middleware

```
全局:  Cors → Locale(Accept-Language) → SecurityFilter(方法检查→405) → RateLimit → {路由中间件}
/admin: Cors → Locale(Accept-Language) → SecurityFilter(方法检查→405) → RateLimit → AdminAuth → AdminPermission → OperationLog → Controller
/api:   Cors → Locale(Accept-Language) → SecurityFilter(方法检查→405) → RateLimit → ApiVersion → Controller
/health: Cors → Locale(Accept-Language) → SecurityFilter(方法检查→405) → RateLimit → Controller
```

## Penguatan Keamanan

- **Pembatasan metode HTTP**: SecurityFilter hanya mengizinkan GET/POST/PUT/DELETE/OPTIONS/HEAD, metode non-standar mengembalikan 405
- **Header CSP**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies disuntikkan ke semua respons
- **Penguncian akun**: 5 kali kegagalan login berturut-turut, akun terkunci 15 menit
- **Batasan sesi bersamaan**: satu pengguna maksimal 3 Token valid, lebih dari itu Token paling lama masuk blacklist
- **security.txt**: endpoint `/.well-known/security.txt` RFC 9116
- **Konfigurasi keamanan Nginx**: `docs/nginx-security.conf` referensi penguatan reverse proxy

## Kebijakan Versi API

Versi dikontrol melalui header `API-Version` (default `v1`), tidak tampil di URL:

```bash
curl -H "API-Version: v1" http://localhost:8787/api/auth/login
```

Menambah versi baru hanya perlu membuat direktori `app/api/{version}/controller/` dan mendaftarkannya ke middleware `ApiVersion`.

## Kebijakan Rate Limit

Sliding window Redis (atomik Lua), default 60 kali/menit/IP/rute:
- Login: 10 kali/menit
- Registrasi: 5 kali/menit
- Header respons: `X-RateLimit-Limit/Remaining/Reset`, saat melebihi batas ditambahkan `Retry-After`

## Standar Kode

### PHP
- Referensi fungsi/kelas global tanpa awalan `\`, gunakan `use` untuk impor
- File konfigurasi wajib menyertakan komentar bahasa Cina yang menjelaskan arti setiap item konfigurasi
- Semua file `.php` baru wajib menyertakan deklarasi hak cipta di bagian atas

### Basis data
- Prefiks tabel: `logistics_`
- Primary key `id`: tipe BIGINT, non-auto-increment, dibuat oleh snowflake
- Bidang sensitif menggunakan trait `erikwang2013/encryptable` untuk enkripsi/dekripsi otomatis
- File migrasi menggunakan format SQL

### Flutter
- Tata letak versi Web menggunakan gaya panel admin PC (sidebar + header + area konten)
- Menggunakan manajemen status GetX, singleton `ApiService` (Dio + interceptor JWT)
- Persistensi Token menggunakan `shared_preferences`
- Titik putus responsif: seluler (< 768px) dan desktop (>= 768px)

### HarmonyOS
- Menggunakan klien HTTP native `@ohos.net.http`
- Refresh token tanpa terasa: saat 401 otomatis memanggil `/api/auth/refresh`
- Gagal refresh otomatis dialihkan ke halaman login

## Deployment

### Docker Compose (direkomendasikan untuk produksi)

`docker-compose.yml` di direktori root proyek mengorkestrasi 5 layanan:

| Layanan | Keterangan |
|------|------|
| `nginx` | Reverse proxy Nginx (80/443), layanan file statis |
| `app` | Aplikasi webman PHP 8.3, dibangun `Dockerfile` (termasuk OPcache) |
| `mysql` | MySQL 8.0, persistensi volume data |
| `redis` | Redis 7 Alpine, cache/rate limit/Session |
| `elasticsearch` | Elasticsearch 8.x, pencarian teks lengkap |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` mendefinisikan pipeline GitHub Actions:

- Pemeriksaan sintaks PHP (`php -l`)
- Unit test PHPUnit
- Analisis statis Flutter (`flutter analyze`)

### Backup Basis Data

`database/backup/backup.sh` — mysqldump + gzip, otomatis membersihkan backup lama lebih dari 30 hari.
`database/backup/restore.sh` — pemulihan interaktif, menampilkan backup yang tersedia untuk dipilih.

### Pemantauan

Endpoint `GET /metrics` (`MetricsController`) mengeluarkan format teks Prometheus, berisi 5 metrik gauge:
- `openadmin_http_requests_total` — total permintaan
- `openadmin_active_users` — jumlah pengguna aktif
- `openadmin_db_connection_status` — status koneksi basis data (0/1)
- `openadmin_redis_connection_status` — status koneksi Redis (0/1)
- `openadmin_memory_usage_bytes` — penggunaan memori
