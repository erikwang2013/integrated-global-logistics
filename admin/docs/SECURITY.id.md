> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](SECURITY.md) | [English](SECURITY.en.md) | [한국어](SECURITY.ko.md) | [Русский](SECURITY.ru.md) | [Deutsch](SECURITY.de.md) | [Français](SECURITY.fr.md) | [Español](SECURITY.es.md) | [Português](SECURITY.pt.md) | [हिन्दी](SECURITY.hi.md) | [العربية](SECURITY.ar.md) | [বাংলা](SECURITY.bn.md) | [Bahasa Indonesia](SECURITY.id.md) | [日本語](SECURITY.ja.md)

# Dokumen Desain Arsitektur Keamanan

## 1. Panorama Pertahanan Berlapis

Sistem mengadopsi model pertahanan berlapis 7 lapis, menyaring permintaan berbahaya dari luar ke dalam lapis demi lapis, memastikan bahwa ketika satu lapis gagal, masih ada garis pertahanan berikutnya sebagai cadangan.

Seluruh rantai middleware dieksekusi dalam urutan berikut (lihat `config/middleware.php`):

```
请求 → Cors → Locale(Accept-Language) → SecurityMiddleware (erikwang2013/security-php, 31种检测器) → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Lapis | Middleware/Mekanisme | Target perlindungan |
|----|--------|---------|
| 1 | SecurityMiddleware (erikwang2013/security-php) | 31 jenis deteksi serangan + validasi metode HTTP + pembatasan ukuran body + validasi Content-Type + CSRF + blacklist eskalasi serangan IP |
| 2 | Cors | Keamanan lintas domain + injeksi header keamanan respons |
| 3 | RateLimit | Rate limit sliding window Redis, mencegah brute force |
| 4 | AdminAuth | Autentikasi JWT + logout blacklist |
| 5 | AdminPermission | Otorisasi granularitas method.path RBAC |
| 6 | OperationLog | Audit operasi + pelacakan sumber |
| 7 | Enkripsi data | Obfuskasi ID Hashids + Enkripsi DB Encryptable + Enkripsi transmisi EncryptionService |

Tiga lapis frontend (Flutter) memiliki validasi input independen tambahan; backend tidak memercayainya. Setiap lapis bertahan secara independen.

---

## 2. Mesin Deteksi Serangan

## 2. Mesin Deteksi Serangan (erikwang2013/security-php)

Deteksi serangan telah dimigrasikan dari SecurityMiddleware buatan sendiri ke paket keamanan khusus `erikwang2013/security-php` v1.1+, menyediakan **31 detektor**, mencakup 5 kategori serangan besar.

### 2.1 Klasifikasi Detektor

**Serangan injeksi (11 jenis):** XSS, injeksi SQL, injeksi perintah, injeksi NoSQL, injeksi LDAP, injeksi XPath, JNDI/Log4Shell, SSI server-side include, injeksi GraphQL, injeksi SSTI template

**Serangan protokol & permintaan (9 jenis):** SSRF, XXE, injeksi header respons HTTP, serangan Host header, Request Smuggling, Open Redirect, bypass CORS, pembajakan WebSocket, DNS Rebinding

**Validasi lapisan protokol HTTP (6 jenis):** validasi metode HTTP(405), pembatasan ukuran body(413), validasi Content-Type(415), pemeriksaan Origin CSRF, blacklist eskalasi serangan IP, deteksi kebocoran data sensitif

**Serangan data & serialisasi (5 jenis):** deserialisasi PHP, injeksi formula CSV, injeksi header email, serangan JWT (analisis terstruktur), JS Prototype Pollution

**Serangan file & path (2 jenis):** path traversal, upload file berbahaya

### 2.2 Mode Penanganan

Setiap detektor secara independen mendukung dua mode:
- `block` — terdeteksi serangan langsung dicegat, mengembalikan kode status yang dikonfigurasi
- `log` — hanya mencatat log tanpa mencegat (`header_injection`, `ssti`, `nosql_injection` default mode log untuk mencegah false positive)

### 2.3 Blacklist Eskalasi Serangan IP

IP yang sama memicu 5 kali deteksi serangan dalam 60 detik → otomatis diblokir 15 menit. Backend penyimpanan dapat memilih Redis (terdistribusi), File (JSON tunggal) atau Cache (file independen konkurensi tinggi), saat ini dikonfigurasi penyimpanan Redis.

### 2.4 Log Keamanan

Lokasi file: `runtime/logs/security.log` (rotasi otomatis, 10MB/file)

---

## 4. Header Keamanan Respons

Semua header disuntikkan di middleware `Cors`, ditambahkan ke setiap respons melalui `$response->withHeaders()`.

| Header | Nilai | Fungsi |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Mengizinkan lintas domain dari semua origin (skenario panel admin intranet) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Kumpulan metode yang diizinkan |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | Header khusus yang diizinkan |
| Access-Control-Max-Age | `86400` | Cache permintaan preflight 24 jam |
| X-Content-Type-Options | `nosniff` | Melarang MIME sniffing browser |
| X-Frame-Options | `DENY` | Melarang semua embedding iframe, mencegah clickjacking |
| X-XSS-Protection | `1; mode=block` | Mengaktifkan filter XSS bawaan browser dan memblokir render halaman |
| Referrer-Policy | `strict-origin-when-cross-origin` | Asal yang sama mengirim URL lengkap, lintas domain hanya mengirim domain |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Menonaktifkan API kamera/mikrofon/geolokasi di seluruh situs |

Permintaan preflight OPTIONS langsung mengembalikan respons kosong 204, tidak masuk ke rantai middleware berikutnya.

### 4.2 Content-Security-Policy (CSP)

Disuntikkan bersama header keamanan lainnya di middleware Cors, menyediakan pertahanan berlapis, membatasi sumber daya yang dapat dimuat dan dieksekusi browser.

| Header | Nilai | Fungsi |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Membatasi sumber skrip/gaya/gambar/koneksi/frame/formulir, dll. |
| X-Permitted-Cross-Domain-Policies | `none` | Melarang pemuatan file kebijakan lintas domain Adobe Flash/PDF, dll. |

Poin-poin kebijakan CSP:
- `default-src 'self'`: default hanya mengizinkan sumber daya asal yang sama
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: mengizinkan skrip asal yang sama + skrip inline (wajib untuk Flutter Web) + eval (wajib untuk debugging Flutter Web)
- `frame-ancestors 'none'`: melarang embedding iframe oleh halaman mana pun, pengaman ganda dengan X-Frame-Options: DENY
- `base-uri 'self'`: membatasi tag `<base>` hanya menunjuk ke asal yang sama
- `form-action 'self'`: membatasi formulir hanya mengirim ke asal yang sama

---

## 5. Kebijakan Rate Limit

### Algoritma

Sliding window Redis Sorted Set + skrip Lua atomik, operasi kunci:

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

Skrip Lua dieksekusi single-thread di sisi server Redis, **atomik secara alami**, menghilangkan race condition TOCTOU (Time-of-check to Time-of-use).

### Konfigurasi Rate Limit

| Rute | Batasan | Jendela | Skenario |
|------|------|------|------|
| Default (semua rute) | 60 kali/menit | 60s | API umum |
| `/api/auth/login` | 10 kali/menit | 60s | Login (mencegah brute force) |
| `/api/auth/register` | 5 kali/menit | 60s | Registrasi (mencegah registrasi massal) |

### Header Respons

Saat rate limit terpicu, mengembalikan HTTP 429 beserta body JSON:
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

Semua respons (termasuk respons normal) membawa header berikut:

| Header | Keterangan |
|----|------|
| X-RateLimit-Limit | Jumlah maksimum permintaan yang diizinkan pada jendela saat ini |
| X-RateLimit-Remaining | Sisa permintaan yang tersedia pada jendela saat ini |
| X-RateLimit-Reset | Stempel waktu Unix reset jendela |
| Retry-After | Hanya dibawa saat rate limit, jumlah detik yang disarankan untuk menunggu |

### Strategi Degradasi

Saat Redis bermasalah (timeout koneksi, tidak tersedia, dll.) berlaku **fail-open**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis down, 放行所有请求
}
```

Lebih baik kehilangan perlindungan rate limit sesaat daripada memblokir permintaan bisnis normal.

### 5.4 Mekanisme Penguncian Akun

Antarmuka login, di atas batas kecepatan, menambahkan mekanisme **penguncian akun** untuk mencegah brute force terarah terhadap pengguna tertentu.

**Alur penguncian**:

```
登录失败 → Redis INCR account_lockout:{userId} TTL=900s
连续 5 次失败 → Redis SETEX account_locked:{userId} 900 1
            → 返回 429 "账号已被锁定，请15分钟后再试"
            → 清除计数器 DEL account_lockout:{userId}
```

**Perilaku selama terkunci**:

Selama terkunci, semua permintaan login langsung mengembalikan 429, tanpa verifikasi kata sandi, sepenuhnya memblokir upaya brute force.

**Konstanta konfigurasi**:

| Konstanta | Nilai | Arti |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Jumlah maksimum kegagalan berturut-turut |
| LOCKOUT_DURATION | 900 | Durasi penguncian (detik), yaitu 15 menit |

Catatan: penguncian akun berbasis `userId` bukan IP, sehingga penyerang yang mengganti IP tidak dapat melewati penguncian. Bertumpuk dengan rate limit IP (10 kali/menit) membentuk perlindungan ganda:
- Tingkat IP: rate limit 10 kali/menit mencegah brute force terdistribusi
- Tingkat akun: penguncian 5 kali gagal mencegah brute force terarah

---

## 6. Autentikasi & Otorisasi

### 6.1 Autentikasi JWT

Diimplementasikan oleh middleware AdminAuth, terpasang pada grup rute yang memerlukan autentikasi.

**Konfigurasi parameter** (`config/plugin/erikwang2013/jwt/jwt`, diinjeksi dari `.env`):

| Parameter | Nilai | Keterangan |
|------|-----|------|
| Algoritma | HS256 | Penandatanganan simetris HMAC-SHA256 |
| Kunci | `JWT_SECRET` | Diinjeksi dari variabel lingkungan, perlu diganti di produksi |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Issuer | `open-admin` | `JWT_ISSUER` |
| Audience | `open-admin` | `JWT_AUDIENCE` |

**Ekstraksi Token**: diekstrak dari header `Authorization: Bearer <token>`, strip prefiks `Bearer ` untuk mendapatkan JWT asli.

**Alur autentikasi**:
1. Token kosong → langsung 401 `{"code": 401, "message": "未登录"}`
2. Periksa blacklist Redis `jwt_blacklist:{md5(token)}` → terdeteksi → 401 `Token已失效，请重新登录`
3. Dekode JWT → gagal (kedaluwarsa/tanda tangan tidak cocok) → 401 `Token已过期或无效`
4. Sukses → injeksi `$request->adminId` dan `$request->adminUsername`

**Mekanisme blacklist**: saat pengguna logout, `md5(token)` ditulis ke Redis, TTL diatur ke sisa masa berlaku JWT. Saat Redis bermasalah, pemeriksaan blacklist dilewati (fail-open); saat itu token yang sudah logout masih dapat digunakan sementara, tetapi masa berlaku pendek JWT itu sendiri (2 jam) menjadi perlindungan cadangan.

### 6.2 Batasan Sesi Bersamaan

Untuk mencegah penyalahgunaan Token di banyak perangkat setelah bocor, sistem membatasi jumlah Token valid yang dipegang pengguna yang sama secara bersamaan.

**Logika pembatasan**:

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

**Konstanta konfigurasi**:

| Konstanta | Nilai | Arti |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Jumlah maksimum Token konkuren per pengguna |

**Skenario terlempar**: saat pengguna login di perangkat ke-4, Token perangkat ke-1 dipaksa masuk blacklist, permintaan berikutnya mengembalikan 401 "Token已失效，请重新登录".

Saat logout, Token saat ini dihapus dari kumpulan. Saat Token kedaluwarsa secara alami, key Redis otomatis tidak berlaku, anggota kumpulan berkurang seiringnya.

### 6.3 Model Hak Akses RBAC

Diimplementasikan oleh middleware AdminPermission.

**Model data**: relasi tiga lapis User -> Role -> Permission

- `logistics_admin_user` (tabel pengguna)
- `logistics_admin_user_role` (tabel relasi pengguna-peran)
- `logistics_admin_role` (tabel peran)
- `logistics_admin_role_permission` (tabel relasi peran-hak akses)
- `logistics_admin_permission` (tabel hak akses)

**Jenis hak akses**:
| type | Arti | Contoh |
|------|------|------|
| 1 | Hak akses menu | Mengontrol visibilitas navigasi kiri |
| 2 | Hak akses tombol | Mengontrol tombol operasi dalam halaman (tambah/edit/hapus) |
| 3 | Hak akses API | Mengontrol pemanggilan antarmuka backend |

Format identitas hak akses API: `{method}.{path}`

Misalnya:
- `post.admin/user` — membuat pengguna
- `put.admin/user` — mengedit pengguna
- `delete.admin/user` — menghapus pengguna
- `get.admin/user` — melihat daftar pengguna

**Alur otorisasi**:
1. `$request->adminId` kosong → izinkan (rute tidak mengonfigurasi pre-autentikasi)
2. Ambil pengguna → peran (lewati peran nonaktif `status=0`) → daftar hak akses
3. Super admin (`slug = '*'`) → langsung izinkan
4. Konstruksi `strtolower(method) . '.' . trim(path, '/')` → bandingkan dengan daftar hak akses
5. Tidak cocok → 403 `{"code": 403, "message": "无权限访问"}`

**Konfirmasi ulang**: BaseController menyediakan metode `confirmPassword()`, operasi sensitif (menghapus pengguna, ekspor data, dll.) di lapisan Controller mengharuskan input kata sandi saat ini, mencegah operasi tidak sah setelah pembajakan sesi.

---

## 7. Log Audit

### 7.1 Log Operasi

Middleware OperationLog mencatat log operasi otomatis untuk permintaan POST / PUT / DELETE. Permintaan GET tidak dicatat.

**Bidang yang dicatat**:

| Bidang | Sumber | Keterangan |
|------|------|------|
| id | SnowflakeService::generate() | ID unik global |
| user_id | `$request->adminId` | ID pelaku, 0 jika belum login |
| action | `$request->method()` | Setara dengan method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Path permintaan |
| ip | `$request->getRealIp()` | IP asli klien |
| source | detectSource() | Platform sumber klien |
| input | body permintaan (JSON setelah penyamaran) | Data yang dikirim operasi |
| created_at | `date('Y-m-d H:i:s')` | Waktu operasi |

**Filter bidang sensitif**: traversal rekursif body permintaan, nilai bidang berikut diganti `***`:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Deteksi sumber** (`detectSource()`): sesuai prioritas:

1. Prioritaskan membaca header khusus `X-Client-Platform` (deklarasi eksplisit klien native)
2. Degradasi ke inferensi string User-Agent (urutan deteksi metode `detectSource()`):

| Platform | Kata kunci UA |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Nilai default fallback |

**Toleransi kesalahan**: pengecualian penulisan log tidak memblokir permintaan bisnis (`catch (\Throwable)` ditelan diam-diam).

### 7.2 Log Keamanan

**Lokasi file**: `runtime/logs/security.log`

**Konten yang dicatat**:
- Log interception serangan: kategori serangan, IP, path, bidang, sumber, potongan payload (200 karakter pertama)
- Notifikasi blokir IP: IP yang diblokir, jumlah pemicuan

Izin log adalah `FILE_APPEND | LOCK_EX`, memastikan penulisan konkuren yang aman.

---

## 8. Perlindungan Data

Sistem mengadopsi strategi perlindungan data tiga lapis, sesuai tiga tahap aliran data.

### 8.1 Lapisan Transmisi — EncryptionService

`EncryptionService` menggunakan paket `erikwang2013/encryption`, melakukan enkripsi/dekripsi bidang sensitif pada permintaan/respons API.

**Detail teknis**:
- Algoritma: `aes-256-cbc-hmac` (dengan tanda tangan HMAC anti-tamper)
- Kunci: variabel lingkungan `ENCRYPTION_KEY`, otomatis diselaraskan ke 32 byte
- Digunakan untuk: transmisi nomor ponsel, nomor KTP, dll. antara klien dan API

**Metode utilitas penyamaran**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (nama pengguna lebih dari 2 karakter) atau `a**@example.com`

### 8.2 Lapisan Penyimpanan — Encryptable Cast

Model `AdminUser` menggunakan Eloquent cast `Erikwang2013\Encryptable\Encryptable`, bidang terkait:

- `email` → cast sebagai Encryptable, enkripsi/dekripsi otomatis
- `phone` → cast sebagai Encryptable, enkripsi/dekripsi otomatis
- `id_card` → cast sebagai Encryptable, enkripsi/dekripsi otomatis

Saat menulis ke basis data otomatis dienkripsi menjadi ciphertext, saat membaca otomatis didekripsi menjadi teks polos. Tipe kolom penyimpanan basis data adalah `VARCHAR(500)`, ciphertext disimpan dalam bentuk base64.

**Sistem kunci**: independen dari enkripsi lapisan transmisi (`ENCRYPTION_KEY`), menggunakan `ENCRYPTABLE_KEY`; kebocoran satu kunci tidak menyebabkan lapisan lain gagal.

Rotasi kunci: variabel lingkungan `ENCRYPTION_PREVIOUS_KEYS` mendukung daftar kunci historis (dipisahkan koma), saat membaca data lama mencoba dekripsi dengan kunci historis, saat menulis kembali mengenkripsi ulang dengan kunci saat ini.

### 8.3 Lapisan Tampilan — Obfuskasi ID & Penyamaran

**Obfuskasi ID Hashids**: `HashidsService` menggunakan paket `erikwang2013/hashids`.

- ID BIGINT basis data yang dikembalikan API publik dienkode menjadi string hash (misalnya `xK3mN9qR2pL7wV8b`)
- Saat klien meminta, string hash dikirim, backend otomatis mendekode menjadi ID asli
- Nilai salt `HASHIDS_SALT` diinjeksi dari variabel lingkungan, salt berbeda maka hasil enkode/dekode sepenuhnya berbeda
- Panjang minimum hash 16 karakter, menggunakan kumpulan 62 karakter alfanumerik
- BaseController menyediakan metode praktis `encodeId()`, `decodeId()`, `encodeIds()`

**Penyamaran ekspor**: saat ekspor Excel/PDF (ExportController), bidang sensitif disamarkan secara seragam:
- Nomor ponsel: `138****1234`
- Email: `a***@example.com`
- KTP: sepenuhnya ditutupi menjadi `********`

---

## 9. Manajemen Kunci

Semua kunci diinjeksi melalui variabel lingkungan `.env`, file konfigurasi membaca dengan `getenv()` dan memiliki nilai default cadangan bawaan (hanya aman untuk lingkungan pengembangan).

| Variabel lingkungan | Kegunaan | Paket | Persyaratan produksi |
|----------|------|-----|---------|
| JWT_SECRET | Kunci penandatanganan JWT | erikwang2013/jwt-webman | String acak 64+ karakter |
| JWT_ALGORITHM | Algoritma penandatanganan JWT | sama seperti di atas | Pertahankan HS256 |
| HASHIDS_SALT | Salt enkode ID | erikwang2013/hashids | String acak |
| SNOWFLAKE_DATACENTER_ID | ID pusat data (0-31) | erikwang2013/snowflake-php | Pusat data tunggal tetap default |
| ENCRYPTION_KEY | Kunci enkripsi lapisan transmisi API | erikwang2013/encryption | String acak 32 byte |
| ENCRYPTABLE_KEY | Kunci enkripsi lapisan penyimpanan DB | erikwang2013/encryptable | String acak 32 byte, berbeda dari kunci transmisi |

**Persyaratan keamanan**:
- File `.env` sudah ditambahkan ke `.gitignore`, dilarang keras di-commit ke repositori
- `.env.example` adalah file template publik, tidak berisi kunci asli
- Di produksi **wajib** mengganti semua kunci default dengan string acak
- Disarankan menggunakan `openssl rand -base64 32` untuk membuat kunci

### Isolasi Penyimpanan Kunci

| Lapisan | Kunci konfigurasi | Variabel lingkungan kunci |
|----|--------|-------------|
| Enkripsi transmisi | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Enkripsi penyimpanan | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| Obfuskasi ID | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| Penandatanganan JWT | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

Sistem menyediakan endpoint informasi kontak keamanan sesuai standar RFC 9116 di `/.well-known/security.txt`, memudahkan peneliti keamanan menemukan saluran pelaporan dengan cepat saat menemukan kerentanan.

**Cara akses**:

```
GET /.well-known/security.txt
```

**Isi respons**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Keterangan bidang**:

| Bidang | Keterangan |
|------|------|
| Contact | Kontak pelaporan kerentanan keamanan |
| Expires | Waktu kedaluwarsa file, perlu diperbarui berkala |
| Preferred-Languages | Bahasa komunikasi pilihan |
| Canonical | URL kanonik file ini |
| Policy | Tautan kebijakan keamanan/kebijakan pengungkapan kerentanan |

Endpoint ini tidak dibatasi oleh middleware seperti rate limit, autentikasi; siapa pun dapat mengakses langsung.

---

## 11. Konfigurasi Keamanan Nginx

Proyek menyediakan `docs/nginx-security.conf` sebagai konfigurasi referensi penguatan keamanan reverse proxy Nginx untuk produksi.

**Langkah keamanan yang disertakan**:

| Item konfigurasi | Fungsi |
|--------|------|
| `server_tokens off` | Menyembunyikan nomor versi Nginx |
| `client_max_body_size 10m` | Membatasi ukuran body permintaan, bekerja sama dengan SecurityMiddleware (erikwang2013/security-php) |
| `limit_req_zone` | Pembatasan frekuensi permintaan pada tingkat Nginx |
| `limit_conn_zone` | Pembatasan jumlah koneksi bersamaan |
| `add_header` header keamanan | Menambahkan header keamanan seperti X-XSS-Protection pada tingkat Nginx |
| `if ($request_method)` | Menolak metode HTTP non-standar pada tingkat Nginx |
| Konfigurasi SSL/TLS | Konfigurasi TLS 1.2/1.3 modern, menonaktifkan cipher suite lemah |
| Sembunyikan header backend | `proxy_hide_header` menghapus header sensitif seperti versi webman |

**Cara penggunaan**: gabungkan konfigurasi di `docs/nginx-security.conf` ke blok server Nginx Anda, sesuaikan sesuai nama domain dan jalur sertifikat aktual.

---

## 12. Model Ancaman

### 12.1 Ancaman yang Dilindungi

| Jenis ancaman | Vektor serangan | Lapisan pertahanan |
|----------|---------|---------|
| Penyalahgunaan metode HTTP | Serangan XST TRACE/TRACK, proxy tunnel CONNECT, deteksi metode WebDAV | whitelist metode 405 detektor http_method SecurityMiddleware |
| Brute force terarah | Percobaan berulang kata sandi terhadap pengguna tertentu | Penguncian akun (5 kali gagal terkunci 15 menit) + RateLimit (login 10/menit) + Captcha |
| Brute force | IP terdistribusi mencoba nama pengguna/kata sandi berulang | RateLimit (login 10/menit) + Captcha |
| XSS skrip lintas situs | `<script>`, onerror, javascript: | SecurityMiddleware (erikwang2013/security-php) (5 mode) + header respons X-XSS-Protection + CSP |
| Injeksi SQL | UNION SELECT, OR 1=1, bypass komentar | SecurityMiddleware (erikwang2013/security-php) (6 mode) + kueri terparameterisasi Eloquent ORM |
| CSRF pemalsuan permintaan lintas situs | Situs jahat mengirim permintaan atas nama | Validasi Origin/Referer SecurityMiddleware (erikwang2013/security-php) |
| Path traversal | `../../etc/passwd` | Mode path traversal SecurityMiddleware (erikwang2013/security-php) + whitelist ekstensi UploadController |
| Injeksi perintah | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityMiddleware (erikwang2013/security-php) (4 mode) |
| Pembajakan sesi | Mencuri Token JWT | Masa berlaku pendek JWT (2 jam) + logout blacklist + konfirmasi ulang kata sandi operasi sensitif |
| Enumerasi ID | Traversal ID numerik untuk menerka volume data | Hashids diobfuskasi menjadi string acak |
| Kebocoran data | Pencurian DB / man-in-the-middle / kebocoran log | Enkripsi/penyamaran tiga lapis + filter bidang sensitif OperationLog |
| Serangan DoS | Body permintaan sangat besar / permintaan frekuensi tinggi | Batas body 10MB + RateLimit 60/menit + blacklist IP |
| Eskalasi hak akses | Pengguna hak rendah mengakses antarmuka admin | Otorisasi granularitas method.path RBAC |
| Serangan upload file | shell.php.png ekstensi ganda | Deteksi file berbahaya SecurityMiddleware (erikwang2013/security-php) |

### 12.2 Keterbatasan yang Diketahui

| Keterbatasan | Cakupan dampak | Langkah mitigasi |
|------|---------|---------|
| Perlindungan CSRF hanya efektif untuk browser | Klien non-browser (curl, Postman, App seluler) dapat melewati pemeriksaan Origin/Referer | Klien non-browser secara alami tidak rentan CSRF; mengandalkan autentikasi JWT menggantikan Cookie |
| Saat Redis tidak tersedia, rate limit dan blacklist terdegradasi menjadi fail-open | Penyerang dapat melewati rate limit dan interception frekuensi tinggi | Pantau ketersediaan Redis dengan peringatan; blacklist IP mendukung tiga backend file/redis/cache yang dapat terdegradasi |
| Tanpa mesin WAF independen | Deteksi berbasis regex, bukan mesin aturan WAF khusus | Produksi disarankan memasang Nginx ModSecurity atau Cloudflare WAF di depan |
| JWT tanpa status tidak dapat dinonaktifkan secara proaktif | Token tidak dapat dicabut dari sisi server sebelum kedaluwarsa (selain blacklist) | Blacklist + TTL pendek 2 jam mengurangi jendela risiko |
| Endpoint admin tanpa rate limit khusus | Antarmuka admin berbagi batas default 60/menit dengan antarmuka biasa | Frekuensi operasi admin secara alami rendah, sementara tidak perlu dibedakan |
| Batas backtracking PCRE | Paket memiliki batas backtracking 1.000.000 bawaan + pemulihan finally, input ekstrem kompleks masih memiliki risiko performa | Batas ukuran body (10MB) sebagai cadangan |
## 13. Keamanan Portal Klien & Pembayaran (M7–M10)
Verifikasi signature webhook Stripe / PayPal (HMAC-SHA256 / verify-webhook-signature); kunci pembayaran dienkripsi `Encryptable` di `logistics_system_config`; USDT TRC20 diverifikasi otomatis via Tronscan, BEP20 / ERC20 manual; X-API-Key ditetapkan klien (≥16 karakter) disimpan sebagai sha256. Deteksi serangan gateway (M11): `ecat-security` SecurityBodyLayer terintegrasi ke gateway, muatan serangan diblokir di lapisan gateway. **13.5 Keamanan CDN (M12)**: paket gratis Cloudflare HTTPS penuh + WAF dua lapis (aturan terkelola di edge + deteksi lapisan aplikasi gateway); origin Tunnel tanpa eksposur sumber; callback via subdomain DNS langsung agar tidak kehilangan pesanan saat CDN down; rate limit dihitung per X-API-Key, tidak terpengaruh IP edge CDN; endpoint terautentikasi selalu no-store mencegah campur aduk cache antar pengguna. **13.6 Manajemen kunci penyedia CDN (M13)**: profil penyedia CDN (access_key / access_secret / daftar domain) di tabel `logistics_cdn_provider`, kolom rahasia dienkripsi `Encryptable`, pemeliharaan via CRUD `/admin/cdn/provider`.
