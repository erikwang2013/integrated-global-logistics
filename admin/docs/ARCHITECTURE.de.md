> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](ARCHITECTURE.md) | [English](ARCHITECTURE.en.md) | [한국어](ARCHITECTURE.ko.md) | [Русский](ARCHITECTURE.ru.md) | [Deutsch](ARCHITECTURE.de.md) | [Français](ARCHITECTURE.fr.md) | [Español](ARCHITECTURE.es.md) | [Português](ARCHITECTURE.pt.md) | [हिन्दी](ARCHITECTURE.hi.md) | [العربية](ARCHITECTURE.ar.md) | [বাংলা](ARCHITECTURE.bn.md) | [Bahasa Indonesia](ARCHITECTURE.id.md) | [日本語](ARCHITECTURE.ja.md)

# Architekturdiagramme und Geschäftslogikdiagramme

> Die folgenden Mermaid-Diagramme werden in GitHub / GitLab / VS Code automatisch gerendert. In anderen Umgebungen nutzen Sie den [Mermaid Live Editor](https://mermaid.live/).

---

## 1. System-Topologie-Architektur

```mermaid
flowchart TB
    subgraph "Client-Ebene"
        A1["Flutter Web<br/>PC-Admin-Panel<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>Handy-/Tablet-Client"]
    end

    subgraph "Gateway-/Edge-Ebene (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>Reverse-Proxy + HTTPS + Gzip<br/>Statischer Dateiservice"]
    end

    subgraph "Anwendungsebene (webman v2)"
        C0["ApiVersion-Middleware<br/>API-Version-Header-Prüfung"]
        C1["AdminAuth-Middleware<br/>JWT-Validierung"]
        C2["AdminPermission-Middleware<br/>RBAC-Berechtigungsprüfung"]
        C3["Admin-Controller<br/>Dashboard / User / Role / Permission"]
        C4["Öffentliche Controller v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Speicherebene"
        D1[("MySQL 8.0<br/>Hauptspeicher<br/>Tabellenpräfix erik_")]
        D2[("Elasticsearch<br/>Volltextsuche<br/>Indexpräfix erik_")]
        D3[("Redis<br/>Session / Cache<br/>Captcha-Speicher")]
    end

    subgraph "Extern"
        E1["DevEco Studio<br/>HarmonyOS-Build"]
        E2["Flutter SDK<br/>Web-Build"]
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

## 2. Backend-Schichtenarchitektur

```mermaid
flowchart TD
    subgraph "Routing-Ebene Route Layer"
        R1["config/route.php<br/>URL → Controller-Zuordnung"]
    end

    subgraph "Middleware-Ebene Middleware Layer"
        M_RL["RateLimit<br/>Redis-Gleitfenster-Rate-Limiting<br/>X-RateLimit-Response-Header"]
        M_SF["SecurityFilter<br/>Angriffserkennung und -block<br/>XSS/SQL-Injection/Pfad-Traversal/CSRF"]
        M0["ApiVersion<br/>API-Versionsprüfung<br/>apiVersion injizieren"]
        M1["AdminAuth<br/>JWT-Token-Prüfung<br/>adminId injizieren"]
        M2["AdminPermission<br/>RBAC-Autorisierung<br/>method.path-Abgleich<br/>Redis-60s-Berechtigungscache"]
    end

    subgraph "Controller-Ebene Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + Suche + Paginierung"]
        CT3["RoleController<br/>CRUD + Berechtigungssynchronisierung"]
        CT4["PermissionController<br/>CRUD + Baumaufbau"]
        CT5["DashboardController<br/>Statistiken/Trends/Verteilung"]
        CT6["ExportController<br/>Excel/PDF-Export"]
        CT7["CaptchaController<br/>Captcha-Erzeugung/-Validierung"]
        CT8["AuthController<br/>Login/Registrierung/Erneuerung"]
    end

    subgraph "Service-Ebene Service Layer"
        S1["HashidsService<br/>ID-Kodierung/-Dekodierung"]
        S2["SnowflakeService<br/>Global eindeutige ID-Erzeugung"]
        S3["EncryptionService<br/>Ver-/Entschlüsselung + Maskierung"]
    end

    subgraph "Model-Ebene Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Treiber-Ebene Driver Layer"
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

## 3. Request-Lebenszyklus

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

    C->>N: HTTPS-Request<br/>Header: API-Version: v1, Accept-Language: zh_CN
    N->>MW_LOC: Weiterleitung

    MW_LOC->>MW_LOC: locale = zh_CN (Accept-Language / ?lang=)
    MW_LOC->>MW_SF: Durchgelassen

    alt Nicht standardkonforme HTTP-Methode (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Methode gültig (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Methoden-Whitelist-Prüfung bestanden
    end

    alt Angriffserkennung ausgelöst
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Durchgelassen

    alt Rate-Limit ausgelöst
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: Durchgelassen

    alt Nicht unterstützte Version
        MW0-->>C: 400 Nicht unterstützte API-Version
    else Version gültig
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Token fehlt oder ungültig
        MW1-->>C: 401 Unauthorized
    else Token gültig
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt Keine Berechtigung
        MW2-->>C: 403 Forbidden
    else Berechtigung vorhanden
        MW2->>CTL: Controller betreten
    end

    CTL->>CTL: Parametervalidierung (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Sensitive Operation (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Passwort falsch
            CTL-->>C: 422 Passwortvalidierung fehlgeschlagen
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast entschlüsselt automatisch
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: Antwort-JSON aufbauen
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Aktionsprotokoll aufzeichnen (POST/PUT/DELETE)
```

---

## 4. Authentifizierungs- und Captcha-Ablauf

```mermaid
sequenceDiagram
    participant U as Benutzer
    participant CL as Client
    participant SV as Server
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Schritt 1: Captcha abrufen ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: 300×200-Hintergrundbild erzeugen
    CAP->>CAP: N chinesische Ziele zufällig platzieren
    CAP->>CAP: key erzeugen, targets speichern
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Schritt 2: Benutzer klickt ===
    CL->>CL: Captcha-Bild rendern
    CL->>CL: Hinweis "Bitte in Reihenfolge klicken: Baum → Vogel → Blume"
    U->>CL: Der Reihe nach auf die Textpositionen im Bild klicken
    CL->>CL: clicks sammeln: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Schritt 3: Login ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Captcha falsch
        CAP-->>SV: false
        SV-->>CL: 422 Captcha-Fehler
    else Captcha korrekt
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Anmeldedaten falsch
            SV-->>CL: 401 Benutzername oder Passwort falsch
        else Anmeldedaten korrekt
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Folge-Requests ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC-Berechtigungsmodell

```mermaid
flowchart LR
    subgraph "Benutzer User"
        U1["admin<br/>(Superadministrator)"]
        U2["editor<br/>(Redakteur)"]
        U3["viewer<br/>(nur Lesen)"]
    end

    subgraph "Rolle Role"
        R1["super_admin<br/>Berechtigungs-Kennung: *"]
        R2["editor<br/>Berechtigungs-Kennung: get.*, post.*"]
        R3["viewer<br/>Berechtigungs-Kennung: get.*"]
    end

    subgraph "Berechtigung Permission (Baum)"
        P1["dashboard<br/>type=1 Menü"]
        P2["user<br/>type=1 Menü"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 Button"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (alle Berechtigungen)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "Berechtigungstypen"
        T1["type=1 Menü<br/>steuert Sidebar-Anzeige/-Ausblendung"]
        T2["type=2 Button<br/>steuert Seiten-Operationsbuttons"]
        T3["type=3 API<br/>steuert den API-Zugriff"]
    end

    subgraph "Format der Berechtigungs-Kennung"
        F1["{method}.{path}<br/>Bsp.: get.admin/user<br/>Bsp.: post.admin/user<br/>Bsp.: delete.admin/role"]
    end

    subgraph "Entscheidungsablauf"
        J1["Token extrahieren → adminId"]
        J2["Benutzerrollen suchen"]
        J3["Alle Berechtigungs-Slugs sammeln"]
        J4["method.path aufbauen"]
        J5{"Übereinstimmung?"}
        J6["Durchlassen"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"ja / slug=*"| J6
        J5 -->|nein| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. ID-Lebenszyklus

```mermaid
flowchart LR
    subgraph "1. Erzeugung"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>Bsp.: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Speicherung"
        S1["MySQL erik_*-Tabellen<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Sensible Felder<br/>encryptable cast<br/>AES-128-ECB-Verschlüsselung"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Übertragung"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid-Zeichenkette<br/>Bsp.: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Rückwärts-Dekodierung"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Mehrschichtige Datenverschlüsselung

```mermaid
flowchart TB
    subgraph "Übertragungsverschlüsselung (encryption)"
        E1["Client sendet sensible Daten"]
        E2["AES-256-CBC-Verschlüsselung"]
        E3["API überträgt Chiffretext"]
        E4["Server entschlüsselt und verarbeitet"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Speicherverschlüsselung (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Schreiben: automatische Verschlüsselung"]
        D3["MySQL VARCHAR(500)<br/>Chiffretext speichern"]
        D4["Lesen: automatische Entschlüsselung"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Anzeige-Maskierung (mask)"
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

## 8. Datenbank-ER-Beziehungen

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "verschlüsselt"
        VARCHAR phone "verschlüsselt"
        VARCHAR id_card "verschlüsselt"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Soft Delete"
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
        BIGINT parent_id FK "Selbstreferenz"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1Menü 2Button 3API"
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
        VARCHAR source "Quelle"
        TEXT input "maskiert"
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

## 9. Export-Geschäftsablauf

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Dateisystem

    Note over C,FS: === Excel-Export ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Daten
    CTL->>CTL: Sensible Felder entschlüsseln
    CTL->>CTL: Maskierung (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet aufbauen<br/>Tabellenkopf blau-weiß<br/>Datenspalten mit feinen Rändern<br/>Erste Zeile einfrieren<br/>Autofilter
    CTL->>FS: runtime/tmp/export_*.xlsx schreiben
    CTL-->>C: Datei-Download

    Note over C,FS: === PDF-Export ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>Seitenkopf: Titel+Copyright+Zeit<br/>Inhalt: Tabelle oder Karten<br/>Seitenfuß: nicht entfernbares Copyright
    CTL->>CTL: Dompdf rendert A4 Querformat
    CTL->>FS: runtime/tmp/export_*.pdf schreiben
    CTL-->>C: Datei-Download
```

---

## 10. Flutter-Web-Komponentenbaum

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Login-Formular<br/>Benutzername/Passwort/Captcha"]
    LF --> CAPTCHA["Klick-Captcha-Komponente<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Klick-Markierung Circle"]

    DB --> SIDEBAR["Sidebar NavigationDrawer<br/>einklappbar 64px / 240px<br/>Dashboard/Benutzer/Rollen/Konfiguration/Protokoll"]
    DB --> HEADER["Topbar 56px<br/>Einklapp-Button + Benutzermenü<br/>Logout AlertDialog"]
    DB --> CONTENT["Inhaltsbereich"]
    CONTENT --> DASH["DashboardPage<br/>Statistik-Karten GridView<br/>Trend-Liniendiagramm LineChart<br/>Verteilungs-Kreisdiagramm PieChart<br/>Letzte Aktionen ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS-Seitenrouting

```mermaid
flowchart LR
    EA["EntryAbility<br/>Start"]
    EA -->|"Kein Token"| LP["LoginPage<br/>Login-Seite"]
    EA -->|"Token vorhanden"| DP["DashboardPage<br/>Dashboard"]

    LP -->|"Login erfolgreich<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>Benutzerliste"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Persönlicher Bereich"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>Benutzerdetails/Neu/Editieren"]
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

## 12. Panorama der Sicherheits-Tiefenverteidigung

```mermaid
flowchart TB
    subgraph "Schicht 1: Mensch-Maschine-Verifizierung"
        L1["Klick-Captcha<br/>Click Captcha<br/>Pflicht bei Login/Registrierung"]
    end

    subgraph "Schicht 2: Operationsbestätigung"
        L2["Doppelte Passwortbestätigung<br/>confirmPassword()<br/>Pflicht bei DELETE-Operationen"]
    end

    subgraph "Schicht 3: Übertragungssicherheit"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Schicht 4: Identitätsauthentifizierung"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "Schicht 5: Berechtigungsautorisierung"
        L5["RBAC<br/>method.path-Granularität<br/>Superadministrator * "]
    end

    subgraph "Schicht 6: Datenschutz"
        L6["API-IDs: Hashids-Verschlüsselung<br/>Request-Body: Encryption-Verschlüsselung<br/>Speicherebene: Encryptable-Verschlüsselung<br/>Export: Maskierung+Copyright"]
    end

    subgraph "Schicht 7: Audit & Rückverfolgbarkeit"
        L7["OperationLog<br/>zeichnet alle Operationen auf<br/>Benutzer/IP/Zeit/Quelle/Parameter"]
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

## 13. Deployment-Topologie

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Webserver"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["Statische Dateien<br/>Flutter Web build/"]
    end

    subgraph "Anwendungsserver (horizontal skalierbar)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "Datenebene"
        MYSQL["MySQL 8.0<br/>Master-Slave-Replikation<br/>erik_-Präfix"]
        ES["Elasticsearch 8.x<br/>3-Knoten-Cluster<br/>erik_-Präfix"]
        REDIS["Redis 7.x<br/>Sentinel-Modus<br/>poster:captcha:*"]
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
