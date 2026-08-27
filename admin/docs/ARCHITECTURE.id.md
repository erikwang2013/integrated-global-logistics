> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](ARCHITECTURE.md) | [English](ARCHITECTURE.en.md) | [한국어](ARCHITECTURE.ko.md) | [Русский](ARCHITECTURE.ru.md) | [Deutsch](ARCHITECTURE.de.md) | [Français](ARCHITECTURE.fr.md) | [Español](ARCHITECTURE.es.md) | [Português](ARCHITECTURE.pt.md) | [हिन्दी](ARCHITECTURE.hi.md) | [العربية](ARCHITECTURE.ar.md) | [বাংলা](ARCHITECTURE.bn.md) | [Bahasa Indonesia](ARCHITECTURE.id.md) | [日本語](ARCHITECTURE.ja.md)

# Diagram Arsitektur & Diagram Alur Bisnis

> Diagram Mermaid berikut dapat dirender otomatis di GitHub / GitLab / VS Code. Untuk lingkungan lain gunakan [Mermaid Live Editor](https://mermaid.live/).

---

## 1. Topologi Arsitektur Sistem

```mermaid
flowchart TB
    subgraph "Lapisan Klien"
        A1["Flutter Web<br/>Panel Admin PC<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>Klien Ponsel/Tablet"]
    end

    subgraph "Lapisan Gateway/Edge (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>Reverse Proxy + HTTPS + Gzip<br/>Layanan File Statis"]
    end

    subgraph "Lapisan Aplikasi (webman v2)"
        C0["Middleware ApiVersion<br/>Validasi Header API-Version"]
        C1["Middleware AdminAuth<br/>Verifikasi JWT"]
        C2["Middleware AdminPermission<br/>Validasi Hak Akses RBAC"]
        C3["Controller Sisi Admin<br/>Dashboard / User / Role / Permission"]
        C4["Controller Publik v1<br/>Captcha / Auth"]
        C5["Layanan Umum<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Lapisan Penyimpanan"
        D1[("MySQL 8.0<br/>Penyimpanan Utama<br/>Prefiks Tabel erik_")]
        D2[("Elasticsearch<br/>Pencarian Teks Lengkap<br/>Prefiks Indeks erik_")]
        D3[("Redis<br/>Session / Cache<br/>Penyimpanan Captcha")]
    end

    subgraph "Eksternal"
        E1["DevEco Studio<br/>Build HarmonyOS"]
        E2["Flutter SDK<br/>Build Web"]
    end

    A1 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    A2 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    B1 --> C0
    C0 --> C1
    C1 --> C2
    C2 --> C3
    C0 --> C4
    C3 --> C5
    C4 --> C5
    C3 --> D1
    C4 --> D1
    C3 --> D2
    C4 --> D2
    C1 --> D3

    style A1 fill:#1677FF,color:#fff
    style A2 fill:#1677FF,color:#fff
    style B1 fill:#722ED1,color:#fff
    style C0 fill:#EB2F96,color:#fff
    style C1 fill:#FA8C16,color:#fff
    style C2 fill:#FA8C16,color:#fff
    style C3 fill:#52C41A,color:#fff
    style C4 fill:#52C41A,color:#fff
    style C5 fill:#52C41A,color:#fff
    style D1 fill:#1890FF,color:#fff
    style D2 fill:#1890FF,color:#fff
    style D3 fill:#1890FF,color:#fff
```

---

## 2. Arsitektur Berlapis Backend

```mermaid
flowchart TD
    subgraph "Lapisan Routing Route Layer"
        R1["config/route.php<br/>Pemetaan URL → Controller"]
    end

    subgraph "Lapisan Middleware Middleware Layer"
        M_RL["RateLimit<br/>Rate limit sliding window Redis<br/>Header respons X-RateLimit"]
        M_SF["SecurityFilter<br/>Interception deteksi serangan<br/>XSS/Injeksi SQL/Path Traversal/CSRF"]
        M0["ApiVersion<br/>Validasi versi API<br/>injeksi apiVersion"]
        M1["AdminAuth<br/>Validasi Token JWT<br/>injeksi adminId"]
        M2["AdminPermission<br/>Otorisasi RBAC<br/>pencocokan method.path<br/>cache hak akses Redis 60s"]
    end

    subgraph "Lapisan Controller Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + Pencarian + Paginasi"]
        CT3["RoleController<br/>CRUD + Sinkronisasi Hak Akses"]
        CT4["PermissionController<br/>CRUD + Pembangunan Pohon"]
        CT5["DashboardController<br/>Statistik/Tren/Distribusi"]
        CT6["ExportController<br/>Ekspor Excel/PDF"]
        CT7["CaptchaController<br/>Pembuatan/Verifikasi Captcha"]
        CT8["AuthController<br/>Login/Registrasi/Refresh"]
    end

    subgraph "Lapisan Layanan Service Layer"
        S1["HashidsService<br/>Enkode/Dekode ID"]
        S2["SnowflakeService<br/>Pembuatan ID Unik Global"]
        S3["EncryptionService<br/>Enkripsi/Dekripsi + Penyamaran"]
    end

    subgraph "Lapisan Model Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Lapisan Driver Driver Layer"
        D1["MySQL PDO"]
        D2["Elasticsearch HTTP"]
        D3["Redis"]
    end

    R1 --> M_SF --> M_RL --> M0
    M0 --> M1
    M1 --> M2
    M2 --> CT2 & CT3 & CT4 & CT5 & CT6
    M0 --> CT7 & CT8
    CT1 -.->|extends| CT2 & CT3 & CT4 & CT5 & CT6
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> S1 & S2 & S3
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> MD1 & MD2 & MD3 & MD4 & MD5
    MD1 & MD2 & MD3 & MD4 & MD5 --> D1
    MD1 --> D2
    CT7 --> D3

    style R1 fill:#722ED1,color:#fff
    style M_SF fill:#FF4D4F,color:#fff
    style M_RL fill:#EB2F96,color:#fff
    style M0 fill:#EB2F96,color:#fff
    style M1 fill:#FA8C16,color:#fff
    style M2 fill:#FA8C16,color:#fff
    style CT1 fill:#1677FF,color:#fff
```

---

## 3. Siklus Hidup Permintaan

```mermaid
sequenceDiagram
    participant C as Klien
    participant N as Nginx
    participant MW_LOC as Locale
    participant MW_SF as SecurityFilter
    participant MW_RL as RateLimit
    participant MW0 as ApiVersion
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL
    participant OPLOG as OperationLog

    C->>N: Permintaan HTTPS<br/>Header: API-Version: v1, Accept-Language: zh_CN
    N->>MW_LOC: Teruskan

    MW_LOC->>MW_LOC: locale = zh_CN (Accept-Language / ?lang=)
    MW_LOC->>MW_SF: Lolos

    alt Metode HTTP non-standar (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Metode valid (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Pemeriksaan whitelist metode lolos
    end

    alt Deteksi serangan terpicu
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Lolos

    alt Rate limit terpicu
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: Lolos

    alt Versi tidak didukung
        MW0-->>C: 400 Versi API tidak didukung
    else Versi valid
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Token hilang atau tidak valid
        MW1-->>C: 401 Unauthorized
    else Token valid
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt Tanpa hak akses
        MW2-->>C: 403 Forbidden
    else Memiliki hak akses
        MW2->>CTL: Masuk ke controller
    end

    CTL->>CTL: Validasi parameter (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Operasi sensitif (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Kata sandi salah
            CTL-->>C: 422 Gagal verifikasi kata sandi
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: cast encryptable mendekripsi otomatis
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: Membangun respons JSON
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Mencatat log operasi (POST/PUT/DELETE)
```

---

## 4. Alur Autentikasi & Captcha

```mermaid
sequenceDiagram
    participant U as Pengguna
    participant CL as Klien
    participant SV as Server
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Langkah 1: Mendapatkan Captcha ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Membuat gambar latar 300×200
    CAP->>CAP: Menempatkan N target karakter Cina secara acak
    CAP->>CAP: Membuat key, menyimpan targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Langkah 2: Klik Pengguna ===
    CL->>CL: Merender gambar captcha
    CL->>CL: Petunjuk "Silakan klik sesuai urutan: pohon → burung → bunga"
    U->>CL: Klik posisi teks dalam gambar secara berurutan
    CL->>CL: Mengumpulkan clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Langkah 3: Login ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Captcha salah
        CAP-->>SV: false
        SV-->>CL: 422 Captcha salah
    else Captcha benar
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Kredensial salah
            SV-->>CL: 401 Nama pengguna atau kata sandi salah
        else Kredensial benar
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Permintaan Berikutnya ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { data dasbor }
```

---

## 5. Model Hak Akses RBAC

```mermaid
flowchart LR
    subgraph "Pengguna User"
        U1["admin<br/>(Super Admin)"]
        U2["editor<br/>(Editor)"]
        U3["viewer<br/>(Hanya Baca)"]
    end

    subgraph "Peran Role"
        R1["super_admin<br/>Identitas hak akses: *"]
        R2["editor<br/>Identitas hak akses: get.*, post.*"]
        R3["viewer<br/>Identitas hak akses: get.*"]
    end

    subgraph "Hak Akses Permission (Pohon)"
        P1["dashboard<br/>type=1 Menu"]
        P2["user<br/>type=1 Menu"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 Tombol"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (Semua Hak Akses)"| P1 & P2 & P3 & P4 & P5 & P6
    R2 --> P1 & P2 & P3 & P4
    R3 --> P1 & P3

    P2 --> P3 & P4 & P5
    P1 --> P6

    style U1 fill:#1677FF,color:#fff
    style R1 fill:#FA8C16,color:#fff
    style P1 fill:#52C41A,color:#fff
```

```mermaid
flowchart TD
    subgraph "Jenis Hak Akses"
        T1["type=1 Menu<br/>Mengontrol tampil/sembunyi sidebar"]
        T2["type=2 Tombol<br/>Mengontrol tombol operasi halaman"]
        T3["type=3 API<br/>Mengontrol akses antarmuka"]
    end

    subgraph "Format Identitas Hak Akses"
        F1["{method}.{path}<br/>Contoh: get.admin/user<br/>Contoh: post.admin/user<br/>Contoh: delete.admin/role"]
    end

    subgraph "Alur Penilaian"
        J1["Ekstrak Token → adminId"]
        J2["Cari peran pengguna"]
        J3["Kumpulkan semua slug hak akses"]
        J4["Konstruksi method.path"]
        J5{"Cocok?"}
        J6["Izinkan"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"Ya / slug=*"| J6
        J5 -->|Tidak| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Siklus Hidup Lengkap ID

```mermaid
flowchart LR
    subgraph "1. Pembuatan"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>Contoh: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Penyimpanan"
        S1["Tabel MySQL erik_*<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Bidang sensitif<br/>cast encryptable<br/>Enkripsi AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Transmisi"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["String hashid<br/>Contoh: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Dekode Terbalik"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Lapisan Enkripsi Data

```mermaid
flowchart TB
    subgraph "Enkripsi Lapisan Transmisi (encryption)"
        E1["Klien mengirim data sensitif"]
        E2["Enkripsi AES-256-CBC"]
        E3["Ciphertext transmisi API"]
        E4["Server mendekripsi dan memproses"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Enkripsi Lapisan Penyimpanan (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Menulis: enkripsi otomatis"]
        D3["MySQL VARCHAR(500)<br/>menyimpan ciphertext"]
        D4["Membaca: dekripsi otomatis"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Penyamaran Lapisan Tampilan (mask)"
        M1["phone: 138****1234"]
        M2["email: a***@example.com"]
        M3["id_card: ********"]
        D4 --> M1 & M2 & M3
    end

    E4 --> D1

    style E2 fill:#1677FF,color:#fff
    style D2 fill:#FA8C16,color:#fff
    style M1 fill:#52C41A,color:#fff
```

---

## 8. Relasi ER Basis Data

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "Terenkripsi"
        VARCHAR phone "Terenkripsi"
        VARCHAR id_card "Terenkripsi"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Soft delete"
    }

    erik_admin_role {
        BIGINT id PK "Snowflake"
        VARCHAR name
        VARCHAR slug UK
        VARCHAR description
        TINYINT status
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Snowflake"
        BIGINT parent_id FK "Self-referencing"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1Menu2Tombol3API"
        VARCHAR icon
        VARCHAR path
        INT sort
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK
        BIGINT role_id PK_FK
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK
        BIGINT permission_id PK_FK
    }

    erik_operation_log {
        BIGINT id PK "Snowflake"
        BIGINT user_id FK
        VARCHAR action
        VARCHAR method
        VARCHAR path
        VARCHAR ip
        VARCHAR source "Sumber"
        TEXT input "Tersamarkan"
        DATETIME created_at
    }

    erik_system_config {
        BIGINT id PK "Snowflake"
        VARCHAR group
        VARCHAR key
        TEXT value
        VARCHAR type
        VARCHAR description
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user ||--o{ erik_admin_user_role : "user_id"
    erik_admin_role ||--o{ erik_admin_user_role : "role_id"
    erik_admin_role ||--o{ erik_admin_role_permission : "role_id"
    erik_admin_permission ||--o{ erik_admin_role_permission : "permission_id"
    erik_admin_user ||--o{ erik_operation_log : "user_id"
    erik_admin_permission ||--o{ erik_admin_permission : "parent_id"
```

---

## 9. Alur Bisnis Ekspor

```mermaid
sequenceDiagram
    participant C as Klien
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Sistem File

    Note over C,FS: === Ekspor Excel ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Data
    CTL->>CTL: Mendekripsi bidang sensitif
    CTL->>CTL: Pemrosesan penyamaran (maskPhone/maskEmail)
    CTL->>CTL: Pembangunan PhpSpreadsheet<br/>judul kolom dasar biru teks putih<br/>baris data border tipis<br/>membekukan baris pertama<br/>filter otomatis
    CTL->>FS: Menulis runtime/tmp/export_*.xlsx
    CTL-->>C: Unduhan file

    Note over C,FS: === Ekspor PDF ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>Header: judul+hak cipta+waktu<br/>Isi: tabel atau kartu<br/>Footer: hak cipta tidak dapat dihapus
    CTL->>CTL: Render Dompdf A4 landscape
    CTL->>FS: Menulis runtime/tmp/export_*.pdf
    CTL-->>C: Unduhan file
```

---

## 10. Pohon Komponen Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Formulir Login<br/>Nama pengguna/Kata sandi/Captcha"]
    LF --> CAPTCHA["Komponen captcha klik<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>tanda klik Circle"]

    DB --> SIDEBAR["Sidebar NavigationDrawer<br/>dapat dilipat 64px / 240px<br/>Dasbor/Pengguna/Peran/Konfigurasi/Log"]
    DB --> HEADER["Header 56px<br/>tombol lipat + menu pengguna<br/>AlertDialog keluar"]
    DB --> CONTENT["Area Konten"]
    CONTENT --> DASH["DashboardPage<br/>kartu statistik GridView<br/>grafik garis tren LineChart<br/>pie chart distribusi PieChart<br/>aktivitas terbaru ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. Routing Halaman HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>Memulai"]
    EA -->|"Tanpa Token"| LP["LoginPage<br/>Halaman Login"]
    EA -->|"Memiliki Token"| DP["DashboardPage<br/>Dasbor"]

    LP -->|"Login sukses<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>Daftar Pengguna"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Pusat Akun Pribadi"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>Detail/Tambah/Edit Pengguna"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"Keluar<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Panorama Pertahanan Berlapis Keamanan

```mermaid
flowchart TB
    subgraph "Lapisan 1: Verifikasi Manusia"
        L1["Captcha Klik<br/>Click Captcha<br/>Wajib untuk login/registrasi"]
    end

    subgraph "Lapisan 2: Konfirmasi Operasi"
        L2["Konfirmasi ulang kata sandi<br/>confirmPassword()<br/>Wajib untuk operasi DELETE"]
    end

    subgraph "Lapisan 3: Keamanan Transmisi"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Lapisan 4: Autentikasi Identitas"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "Lapisan 5: Otorisasi Hak Akses"
        L5["RBAC<br/>granularitas method.path<br/>Super Admin * "]
    end

    subgraph "Lapisan 6: Perlindungan Data"
        L6["ID Antarmuka: Enkripsi Hashids<br/>Body Permintaan: Enkripsi Encryption<br/>Lapisan Penyimpanan: Enkripsi Encryptable<br/>Ekspor: Penyamaran + Hak Cipta"]
    end

    subgraph "Lapisan 7: Jejak Audit"
        L7["OperationLog<br/>Mencatat semua operasi<br/>Pengguna/IP/Waktu/Sumber/Parameter"]
    end

    L1 --> L2 --> L3 --> L4 --> L5 --> L6 --> L7

    style L1 fill:#1677FF,color:#fff
    style L2 fill:#1677FF,color:#fff
    style L3 fill:#FA8C16,color:#fff
    style L4 fill:#FA8C16,color:#fff
    style L5 fill:#52C41A,color:#fff
    style L6 fill:#722ED1,color:#fff
    style L7 fill:#FF4D4F,color:#fff
```

---

## 13. Topologi Deployment

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Server Web"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["File Statis<br/>Flutter Web build/"]
    end

    subgraph "Server Aplikasi (dapat diskalakan horizontal)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "Lapisan Data"
        MYSQL["MySQL 8.0<br/>replikasi master-slave<br/>prefiks erik_"]
        ES["Elasticsearch 8.x<br/>cluster 3 node<br/>prefiks erik_"]
        REDIS["Redis 7.x<br/>mode sentinel<br/>poster:captcha:*"]
    end

    subgraph "Pemantauan"
        MON["Grafana<br/>+ Prometheus"]
    end

    DNS --> NGX
    NGX --> STA
    NGX --> WM1 & WM2 & WM3
    WM1 & WM2 & WM3 --> MYSQL
    WM1 & WM2 & WM3 --> ES
    WM1 & WM2 & WM3 --> REDIS
    WM1 & WM2 & WM3 --> MON

    style NGX fill:#722ED1,color:#fff
    style WM1 fill:#1677FF,color:#fff
    style WM2 fill:#1677FF,color:#fff
    style WM3 fill:#1677FF,color:#fff
    style MYSQL fill:#1890FF,color:#fff
    style ES fill:#1890FF,color:#fff
    style REDIS fill:#1890FF,color:#fff
```
