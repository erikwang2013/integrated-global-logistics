> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](ARCHITECTURE.md) | [English](ARCHITECTURE.en.md) | [한국어](ARCHITECTURE.ko.md) | [Русский](ARCHITECTURE.ru.md) | [Deutsch](ARCHITECTURE.de.md) | [Français](ARCHITECTURE.fr.md) | [Español](ARCHITECTURE.es.md) | [Português](ARCHITECTURE.pt.md) | [हिन्दी](ARCHITECTURE.hi.md) | [العربية](ARCHITECTURE.ar.md) | [বাংলা](ARCHITECTURE.bn.md) | [Bahasa Indonesia](ARCHITECTURE.id.md) | [日本語](ARCHITECTURE.ja.md)

# Architecture and Business Logic Diagrams

> The Mermaid diagrams below render automatically on GitHub / GitLab / VS Code. In other environments, view them with the [Mermaid Live Editor](https://mermaid.live/).

---

## 1. System Topology

```mermaid
flowchart TB
    subgraph "Client Layer"
        A1["Flutter Web<br/>PC Admin Panel<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>Phone/Tablet Client"]
    end

    subgraph "Gateway/Edge Layer (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>Reverse Proxy + HTTPS + Gzip<br/>Static File Serving"]
    end

    subgraph "Application Layer (webman v2)"
        C0["ApiVersion Middleware<br/>API-Version Header Validation"]
        C1["AdminAuth Middleware<br/>JWT Verification"]
        C2["AdminPermission Middleware<br/>RBAC Permission Check"]
        C3["Admin Controllers<br/>Dashboard / User / Role / Permission"]
        C4["Public Controllers v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Storage Layer"
        D1[("MySQL 8.0<br/>Primary Storage<br/>Table Prefix erik_")]
        D2[("Elasticsearch<br/>Full-Text Search<br/>Index Prefix erik_")]
        D3[("Redis<br/>Session / Cache<br/>Captcha Storage")]
    end

    subgraph "External"
        E1["DevEco Studio<br/>HarmonyOS Build"]
        E2["Flutter SDK<br/>Web Build"]
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

## 2. Backend Layered Architecture

```mermaid
flowchart TD
    subgraph "Route Layer"
        R1["config/route.php<br/>URL → Controller Mapping"]
    end

    subgraph "Middleware Layer"
        M_RL["RateLimit<br/>Redis Sliding Window Rate Limiting<br/>X-RateLimit Response Headers"]
        M_SF["SecurityFilter<br/>Attack Detection & Blocking<br/>XSS/SQLi/Path Traversal/CSRF"]
        M0["ApiVersion<br/>API Version Validation<br/>Injects apiVersion"]
        M1["AdminAuth<br/>JWT Token Verification<br/>Injects adminId"]
        M2["AdminPermission<br/>RBAC Authorization<br/>method.path Matching<br/>Redis 60s Permission Cache"]
    end

    subgraph "Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + Search + Pagination"]
        CT3["RoleController<br/>CRUD + Permission Sync"]
        CT4["PermissionController<br/>CRUD + Tree Building"]
        CT5["DashboardController<br/>Stats / Trends / Distribution"]
        CT6["ExportController<br/>Excel/PDF Export"]
        CT7["CaptchaController<br/>Captcha Generate/Verify"]
        CT8["AuthController<br/>Login / Register / Refresh"]
    end

    subgraph "Service Layer"
        S1["HashidsService<br/>ID Encode/Decode"]
        S2["SnowflakeService<br/>Globally Unique ID Generation"]
        S3["EncryptionService<br/>Encryption + Masking"]
    end

    subgraph "Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Driver Layer"
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

## 3. Request Lifecycle

```mermaid
sequenceDiagram
    participant C as Client
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

    C->>N: HTTPS request<br/>Header: API-Version: v1, Accept-Language: zh_CN
    N->>MW_LOC: Forward

    MW_LOC->>MW_LOC: locale = zh_CN (Accept-Language / ?lang=)
    MW_LOC->>MW_SF: Pass

    alt Non-standard HTTP method (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Method valid (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Method whitelist check passed
    end

    alt Attack detection triggered
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Pass

    alt Rate limit triggered
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: Pass

    alt Unsupported version
        MW0-->>C: 400 Unsupported API version
    else Version valid
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Token missing or invalid
        MW1-->>C: 401 Unauthorized
    else Token valid
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt No permission
        MW2-->>C: 403 Forbidden
    else Authorized
        MW2->>CTL: Enter controller
    end

    CTL->>CTL: Parameter validation (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Sensitive operation (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Wrong password
            CTL-->>C: 422 Password verification failed
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast auto-decrypt
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: Build response JSON
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Record operation log (POST/PUT/DELETE)
```

---

## 4. Authentication and Captcha Flow

```mermaid
sequenceDiagram
    participant U as User
    participant CL as Client
    participant SV as Server
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Step 1: Get Captcha ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Generate 300×200 background image
    CAP->>CAP: Randomly place N Chinese targets
    CAP->>CAP: Generate key, store targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Step 2: User Clicks ===
    CL->>CL: Render captcha image
    CL->>CL: Prompt "Click in order: tree → bird → flower"
    U->>CL: Click text positions in the image in order
    CL->>CL: Collect clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Step 3: Login ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Incorrect captcha
        CAP-->>SV: false
        SV-->>CL: 422 Captcha error
    else Captcha correct
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Invalid credentials
            SV-->>CL: 401 Incorrect username or password
        else Credentials valid
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Subsequent Requests ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC Permission Model

```mermaid
flowchart LR
    subgraph "Users"
        U1["admin<br/>(Super Admin)"]
        U2["editor<br/>(Editor)"]
        U3["viewer<br/>(Read-Only)"]
    end

    subgraph "Roles"
        R1["super_admin<br/>Permission ID: *"]
        R2["editor<br/>Permission ID: get.*, post.*"]
        R3["viewer<br/>Permission ID: get.*"]
    end

    subgraph "Permissions (Tree)"
        P1["dashboard<br/>type=1 Menu"]
        P2["user<br/>type=1 Menu"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 Button"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (All Permissions)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "Permission Types"
        T1["type=1 Menu<br/>Controls Sidebar Visibility"]
        T2["type=2 Button<br/>Controls Page Action Buttons"]
        T3["type=3 API<br/>Controls Endpoint Access"]
    end

    subgraph "Permission ID Format"
        F1["{method}.{path}<br/>e.g. get.admin/user<br/>e.g. post.admin/user<br/>e.g. delete.admin/role"]
    end

    subgraph "Decision Flow"
        J1["Extract Token → adminId"]
        J2["Look Up User Roles"]
        J3["Collect All Permission Slugs"]
        J4["Build method.path"]
        J5{"Match?"}
        J6["Allow"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"Yes / slug=*"| J6
        J5 -->|"No"| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Full ID Lifecycle

```mermaid
flowchart LR
    subgraph "1. Generation"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>e.g. 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Storage"
        S1["MySQL erik_* Tables<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Sensitive Fields<br/>encryptable cast<br/>AES-128-ECB Encryption"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Transport"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["Hashid String<br/>e.g. aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Reverse Decode"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Data Encryption Layers

```mermaid
flowchart TB
    subgraph "Transport Layer Encryption (encryption)"
        E1["Client Sends Sensitive Data"]
        E2["AES-256-CBC Encryption"]
        E3["API Transport Ciphertext"]
        E4["Server Decrypts"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Storage Layer Encryption (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Write: Auto-Encrypt"]
        D3["MySQL VARCHAR(500)<br/>Stores Ciphertext"]
        D4["Read: Auto-Decrypt"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Display Layer Masking (mask)"
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

## 8. Database ER Relationships

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "encrypted"
        VARCHAR phone "encrypted"
        VARCHAR id_card "encrypted"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "soft delete"
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
        BIGINT parent_id FK "self-referencing"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1 menu 2 button 3 API"
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
        VARCHAR source "client source"
        TEXT input "masked"
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

## 9. Export Business Flow

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as File System

    Note over C,FS: === Excel Export ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Data
    CTL->>CTL: Decrypt sensitive fields
    CTL->>CTL: Masking (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet build<br/>Blue header with white text<br/>Thin borders on data rows<br/>Frozen first row<br/>Auto-filter
    CTL->>FS: Write runtime/tmp/export_*.xlsx
    CTL-->>C: File download

    Note over C,FS: === PDF Export ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>Header: title + copyright + time<br/>Content: table or cards<br/>Footer: non-removable copyright
    CTL->>CTL: Dompdf renders A4 landscape
    CTL->>FS: Write runtime/tmp/export_*.pdf
    CTL-->>C: File download
```

---

## 10. Flutter Web Widget Tree

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Login Form<br/>Username/Password/Captcha"]
    LF --> CAPTCHA["Click Captcha Widget<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Click Marker Circle"]

    DB --> SIDEBAR["Sidebar NavigationDrawer<br/>Collapsible 64px / 240px<br/>Dashboard/Users/Roles/Config/Logs"]
    DB --> HEADER["Header 56px<br/>Collapse Button + User Menu<br/>Logout AlertDialog"]
    DB --> CONTENT["Content Area"]
    CONTENT --> DASH["DashboardPage<br/>Stats Cards GridView<br/>Trend LineChart<br/>Distribution PieChart<br/>Recent Activity ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS Page Routing

```mermaid
flowchart LR
    EA["EntryAbility<br/>Launch"]
    EA -->|"No Token"| LP["LoginPage<br/>Login Page"]
    EA -->|"Has Token"| DP["DashboardPage<br/>Dashboard"]

    LP -->|"Login Success<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>User List"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Profile"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>User Detail/Add/Edit"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"Logout<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Defense-in-Depth Overview

```mermaid
flowchart TB
    subgraph "Layer 1: Human Verification"
        L1["Click Captcha<br/>Mandatory for Login/Register"]
    end

    subgraph "Layer 2: Operation Confirmation"
        L2["Password Re-Confirmation<br/>confirmPassword()<br/>Required for DELETE"]
    end

    subgraph "Layer 3: Transport Security"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Layer 4: Authentication"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "Layer 5: Authorization"
        L5["RBAC<br/>method.path Granularity<br/>Super Admin * "]
    end

    subgraph "Layer 6: Data Protection"
        L6["API ID: Hashids Encrypted<br/>Request Body: Encryption Encrypted<br/>Storage: Encryptable Encrypted<br/>Export: Masked + Copyright"]
    end

    subgraph "Layer 7: Audit Trail"
        L7["OperationLog<br/>Records All Operations<br/>User/IP/Time/Source/Params"]
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

## 13. Deployment Topology

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web Server"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["Static Files<br/>Flutter Web build/"]
    end

    subgraph "Application Servers (Horizontally Scalable)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "Data Layer"
        MYSQL["MySQL 8.0<br/>Master-Slave Replication<br/>erik_ Prefix"]
        ES["Elasticsearch 8.x<br/>3-Node Cluster<br/>erik_ Prefix"]
        REDIS["Redis 7.x<br/>Sentinel Mode<br/>poster:captcha:*"]
    end

    subgraph "Monitoring"
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
