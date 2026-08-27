> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](../README.md) | [English](README.en.md) | [한국어](README.ko.md) | [Русский](README.ru.md) | [Deutsch](README.de.md) | [Français](README.fr.md) | [Español](README.es.md) | [Português](README.pt.md) | [हिन्दी](README.hi.md) | [العربية](README.ar.md) | [বাংলা](README.bn.md) | [Bahasa Indonesia](README.id.md) | [日本語](README.ja.md)

# Panel Admin Terbuka (open-admin)

Sistem panel admin full-stack berbasis webman v2 + Flutter.

> [Diagram Arsitektur](docs/ARCHITECTURE.id.md) | [Dokumen Desain](docs/DESIGN.id.md) | [Arsitektur Keamanan](docs/SECURITY.id.md) | [Referensi API](docs/API.id.md)

## Daftar Fitur

| Domain | Fitur | Keterangan |
|--------|------|------|
| 🔐 Autentikasi | Login/Refresh Token/Logout | Captcha klik + JWT + blacklist |
| | Penguncian akun | 5 kali gagal → terkunci 15 menit |
| | Batasan sesi bersamaan | Maksimal 3 Token valid per pengguna |
| 📊 Dasbor | Statistik real-time/grafik tren/grafik distribusi/aktivitas terbaru | Cache Redis 5 menit |
| 👥 Manajemen Pengguna | CRUD + hapus massal/aktif-nonaktifkan | Soft delete + konfirmasi ulang kata sandi |
| | Impor massal Excel | Validasi per baris + laporan kesalahan |
| 🔒 Peran & Hak Akses | CRUD peran + pohon hak akses | Otorisasi RBAC dengan granularitas method.path |
| ⚙ Konfigurasi Sistem | CRUD pasangan kunci-nilai | Manajemen per grup |
| 📋 Audit Operasi | Kueri log + deteksi sumber | Auto-deteksi 8 platform |
| 📁 Manajemen File | Upload/ekspor Excel/ekspor PDF | Data sensitif otomatis disamarkan |
| 🛡 Perlindungan Keamanan | 18 lapis pertahanan berlapis | XSS/Injeksi SQL/Path traversal/Injeksi perintah/CSRF/rate limit/CSP... |
| 🏥 Operasional | Health check/metrics/dokumen API/security.txt | Prometheus + OpenAPI 3.0 + dokumentasi interaktif hg/apidoc |
| 🌐 Internasionalisasi | Alih bahasa Cina-Inggris | Header Accept-Language / parameter ?lang= |

## Tumpukan Teknologi

| Lapisan | Teknologi | Keterangan |
|---|------|------|
| Kerangka kerja backend | webman v2 (workerman) | Kerangka kerja proses-tinggal (long-running) PHP berperforma sangat tinggi |
| Versi PHP | 8.3+ | |
| Basis data | MySQL 8.0+ | Prefiks tabel `erik_`, primary key BIGINT non-auto-increment |
| Mesin pencari | Elasticsearch | Sinkronisasi & kueri melalui `webman-scout` |
| Frontend admin | Flutter 3.x | Web versi PC bergaya panel admin (di `apps/flutter/`) |
| Perangkat seluler | HarmonyOS ArkTS | Klien native HarmonyOS (di `apps/harmonyos/`), mendukung ponsel/tablet/2in1 |

## Dependensi Inti

| Paket | Kegunaan |
|---|------|
| `erikwang2013/snowflake-php` | Algoritma Snowflake untuk menghasilkan primary key BIGINT unik global |
| `erikwang2013/hashids` | Enkripsi/dekripsi ID di lapisan API, menyembunyikan ID basis data asli |
| `erikwang2013/jwt-webman` | Penerbitan & validasi token autentikasi JWT |
| `erikwang2013/encryption` | Enkripsi/dekripsi data sensitif di lapisan transportasi antarmuka |
| `erikwang2013/encryptable` | Enkripsi/dekripsi otomatis bidang sensitif di lapisan penyimpanan basis data |
| `erikwang2013/webman-scout` | Sinkronisasi data Elasticsearch & pencarian teks lengkap |
| `erikwang2013/season` | Data bendera negara |
| `erikwang2013/poster-php` | Pembuatan & validasi captcha klik + pembuatan poster |
| `phpoffice/phpspreadsheet` | Ekspor Excel |
| `barryvdh/laravel-dompdf` | Ekspor PDF (berbasis Dompdf) |

## Struktur Proyek

```
open-admin/
├── app/
│   ├── admin/controller/       # Kontroler sisi admin
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
│   │   └── BaseController.php      # Kontroler dasar
│   ├── api/
│   │   └── v1/controller/          # Kontroler API v1 (versi dikontrol oleh header API-Version)
│   │       ├── CaptchaController.php # Captcha klik
│   │       └── AuthController.php    # Login/refresh token
│   ├── common/                 # Kelas utilitas publik
│   │   ├── HashidsService.php  # Enkode/dekode ID
│   │   ├── SnowflakeService.php# Pembuatan ID Snowflake
│   │   └── EncryptionService.php # Enkripsi/dekripsi data + penyamaran
│   ├── middleware/             # Middleware
│   │   ├── Cors.php            # CORS lintas domain
│   │   ├── SecurityFilter.php  # Interception deteksi serangan (pembatasan metode HTTP/XSS/Injeksi SQL/Path traversal/Injeksi perintah/CSRF)
│   │   ├── RateLimit.php       # Rate limit Redis (sliding window + header respons)
│   │   ├── ApiVersion.php      # Validasi versi API
│   │   ├── AdminAuth.php       # Autentikasi JWT + blacklist
│   │   ├── AdminPermission.php # Validasi hak akses RBAC
│   │   └── OperationLog.php    # Pencatatan log operasi otomatis (termasuk deteksi sumber)
│   └── model/                  # Model data
├── apps/
│   ├── flutter/                # Panel admin Web Flutter (gaya PC)
│   │   └── lib/app/
│   │       ├── pages/          # 5 halaman lengkap (dasbor/pengguna/peran/konfigurasi/log/pusat akun)
│   │       ├── services/       # ApiService (interceptor JWT) + AuthService (persistensi Token)
│   │       └── layouts/        # Tata letak panel admin responsif (sidebar+header+area konten)
│   └── harmonyos/              # Klien native HarmonyOS (refresh token tanpa terasa)
├── config/                     # File konfigurasi (dengan komentar bahasa Cina)
│   ├── route.php               # Routing + kebijakan versi API
│   ├── middleware.php           # Registrasi middleware global
│   └── ...                     # Konfigurasi berbagai komponen
├── database/install.sql        # Skrip instalasi SQL (termasuk data seed hak akses)
├── public/                     # Titik masuk publik
├── runtime/                    # File runtime
└── vendor/                     # Dependensi Composer
```

## Persyaratan Lingkungan

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (hanya diperlukan untuk pengembangan frontend)
- Elasticsearch >= 7.x (opsional, diperlukan untuk fitur pencarian)

## Memulai Cepat

### 1. Instal Dependensi

```bash
composer install
```

### 2. Konfigurasi Variabel Lingkungan

Salin dan ubah variabel lingkungan (opsional; jika tidak dikonfigurasi, nilai default di `config/*.php` yang digunakan):

```bash
cp .env.example .env
```

Item konfigurasi penting:

| Variabel lingkungan | Keterangan | Nilai default |
|---------|------|--------|
| `JWT_SECRET` | Kunci penandatanganan JWT | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Nilai salt Hashids | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | Kunci enkripsi API | Nilai default 32 byte |
| `SNOWFLAKE_DATACENTER_ID` | ID pusat data (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID node pekerja (0-31) | `1` |
| `SCOUT_HOSTS` | Alamat ES | `http://localhost:9200` |

**Di lingkungan produksi, semua kunci wajib diganti dengan string acak.**

### 3. Instalasi Sekali Klik

Setelah layanan berjalan, buka panduan instalasi di browser untuk menyelesaikan inisialisasi basis data dan pembuatan admin:

```bash
php start.php start
```

Secara default mendengarkan `http://0.0.0.0:8787` (port dapat diubah di `config/server.php`).

Buka **`http://localhost:8787/install`** di browser, lalu isi sesuai panduan:

| Langkah | Konten |
|------|------|
| ① Konfigurasi basis data | Alamat host, port, nama basis data, nama pengguna, kata sandi |
| ② Pengaturan admin | Nama pengguna dan kata sandi admin (default admin / admin888) |

Klik「Mulai Instalasi」untuk otomatis membuat tabel, menanam data hak akses, membuat akun admin, dan menulis konfigurasi basis data ke `.env`.

> Setelah instalasi selesai, file kunci `runtime/install.lock` akan dibuat. Hapus file ini jika ingin menginstal ulang.

### 4. Login

Akses `http://localhost:8787`, lalu login menggunakan nama pengguna dan kata sandi admin yang diatur saat instalasi.

### 5. Menjalankan Frontend (Opsional)

**Panel admin Flutter (versi Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Versi Web (gaya panel admin PC)
```

**Klien HarmonyOS (ponsel):**

Gunakan DevEco Studio untuk membuka direktori `apps/harmonyos/`, lalu jalankan di perangkat nyata atau emulator.

### 6. Deploy Satu Klik dengan Docker Compose (Direkomendasikan untuk Produksi)

Proyek menyediakan solusi orkestrasi Docker lengkap dengan 5 layanan: Nginx, PHP (app webman), MySQL, Redis, Elasticsearch.

```bash
# 1. Konfigurasi variabel lingkungan Docker
cp .env.docker .env

# 2. Jalankan semua layanan
docker-compose up -d

# 3. Buka panduan instalasi di browser untuk menyelesaikan inisialisasi
# http://localhost:8787/install  (isi informasi basis data dan admin)
# atau jalankan migrasi SQL secara manual (masuk ke container app):
# docker-compose exec app mysql -h mysql -u root -p < database/install.sql

# 4. Akses
# http://localhost:8787  (webman)
# http://localhost:8080  (reverse proxy Nginx)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, berbasis `php:8.3-cli`
- `docker-compose.yml`: Orkestrasi 5 layanan, isolasi jaringan, persistensi data volume
- `.env.docker`: Variabel lingkungan khusus untuk lingkungan Docker


## Standar Basis Data

- **Prefiks tabel**: `erik_`
- **Primary key**: Semua primary key tabel berupa `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT dilarang**
- **Pembuatan ID**: ID primary key dibuat oleh `SnowflakeService::generate()` di lapisan aplikasi, unik secara terdistribusi
- **Bidang wajib**: Setiap tabel harus memiliki `id`, `created_at`, `updated_at`
- **Soft delete**: Tabel yang membutuhkan soft delete menambahkan `deleted_at DATETIME DEFAULT NULL`
- **Bidang sensitif**: Nomor ponsel, email, nomor KTP, dll. menggunakan plugin `encryptable` untuk enkripsi/dekripsi otomatis, bidang basis data disimpan sebagai ciphertext dengan `VARCHAR(500)`

## Dokumentasi API

Referensi API lengkap (format respons terpadu, kode kesalahan, detail semua endpoint, alur autentikasi, kebijakan rate limit, rantai middleware) lihat **[docs/API.id.md](docs/API.id.md)**, poin-poin penting:

- **Format respons terpadu**: `{ "code": 0, "message": "success", "data": {...} }`, `code=0` berarti sukses
- **Kode kesalahan**: `400` kesalahan parameter / `401` belum login / `403` tanpa izin / `404` tidak ditemukan / `422` gagal validasi / `429` rate limit / `500` kesalahan server
- **Versi API**: Dikontrol melalui header `API-Version: v1` (default v1 jika tidak ada), tidak tampil di URL
- **Autentikasi**: `Authorization: Bearer <token>`; masa berlaku access_token 2 jam, refresh_token 14 hari
- **Penanganan ID**: ID pada permintaan/respons adalah string terenkripsi hashids, tidak mengekspos ID basis data asli

## Catatan Frontend

### Panel admin Flutter (gaya PC)

- **Tata letak**: Sidebar (dapat dilipat 64px/240px) + header + area konten, tiga titik putus responsif (ponsel/tablet/desktop)
- **Halaman**: Login, dasbor, manajemen pengguna, peran & hak akses, konfigurasi sistem, log operasi, pusat akun pribadi
- **Manajemen status**: GetX (singleton `ApiService` + persistensi Token `AuthService`)
- **Dasbor**: Kartu statistik, grafik garis tren (fl_chart), pie chart, log operasi terbaru
- **Ekspor**: Ekspor Excel/PDF, PDF menyertakan informasi hak cipta yang tidak dapat dihapus
- **Operasi massal**: Hapus massal multi-pilih, aktifkan/nonaktifkan massal
- **Tema**: Material 3 tema terang/gelap

### Seluler HarmonyOS

- **Halaman**: Login, dasbor, daftar/detail pengguna, pusat akun pribadi
- **Autentikasi**: JWT Bearer + refresh token otomatis tanpa terasa saat 401, gagal refresh otomatis dialihkan ke halaman login
- **Penyimpanan**: Token dikelola melalui AppStorage

## Standar Pengembangan

- Referensi fungsi/kelas global tanpa awalan `\`, gunakan `use` secara seragam
- Semua file PHP wajib menyertakan deklarasi hak cipta di bagian atas
- Semua file konfigurasi wajib menyertakan komentar bahasa Cina yang menjelaskan arti setiap item
- Primary key basis data wajib dibuat oleh snowflake di lapisan aplikasi, auto-increment dilarang
- Semua ID pada parameter dan respons di lapisan API wajib melalui enkripsi/dekripsi hashids
- Middleware AdminPermission menggunakan cache Redis untuk hak akses pengguna (TTL=60s), menghilangkan bottleneck kueri N+1

## Deployment

### Docker Compose (Direkomendasikan)

Direktori root proyek menyediakan `docker-compose.yml`, mengorkestrasi 5 layanan:

| Layanan | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | dibangun dari `Dockerfile` lokal | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

Image PHP dibangun melalui `Dockerfile`, base image `php:8.3-cli`, dengan OPcache diaktifkan.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

Pipeline integrasi berkelanjutan GitHub Actions: `.github/workflows/ci.yml`

- Pemeriksaan sintaks PHP (`php -l`)
- Unit test PHPUnit
- Analisis statis Flutter (`flutter analyze`)

### Backup Basis Data

Direktori `database/backup/`:

- `backup.sh` — backup mysqldump + gzip, otomatis membersihkan backup lama lebih dari 30 hari
- `restore.sh` — pemulihan interaktif, menampilkan backup yang tersedia untuk dipilih

### Konfigurasi Keamanan Nginx

Untuk deployment produksi, lihat `docs/nginx-security.conf` untuk konfigurasi penguatan keamanan reverse proxy.

## Mendukung Proyek Open Source

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](./docs/weixinpay.png "WeChat") | ![Alipay](./docs/alipay.png "Alipay") |

### Donasi Transfer Global (Remitansi Lintas Negara)

**Informasi Penerima**

- Nama penerima: WANG KEXUN
- Nomor rekening penerima: 881015918251

**Bank Penerima**

- Kode SWIFT ZA Bank: AABLHKHHXXX
- Nama bank: ZA Bank Limited
- Kode bank: 387
- Alamat bank: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Bank Perantara Remitansi Lintas Negara (jika diperlukan)**

> Ini adalah informasi bank perantara (bank koresponden) untuk remitansi lintas negara, bukan informasi bank penerima. Silakan tanyakan kepada bank pengirim apakah informasi bank perantara diperlukan.

- **Untuk kiriman HKD, CNY, dan USD**, bank perantaranya adalah Citibank:
  - Nama bank: Citibank N.A. Hong Kong
  - Kode SWIFT: CITIHKHXXXX
  - Kode bank: 006
  - Nama cabang: Hong Kong Branch
  - Kode cabang: 391
  - Alamat bank: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **Untuk mata uang lainnya**, bank perantaranya adalah BNY Mellon:
  - Nama bank: THE BANK OF NEW YORK MELLON
  - Kode SWIFT: IRVTUS3NXXX
  - Alamat bank: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

---

## Lisensi

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
