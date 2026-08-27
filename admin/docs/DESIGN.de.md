> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](DESIGN.md) | [English](DESIGN.en.md) | [한국어](DESIGN.ko.md) | [Русский](DESIGN.ru.md) | [Deutsch](DESIGN.de.md) | [Français](DESIGN.fr.md) | [Español](DESIGN.es.md) | [Português](DESIGN.pt.md) | [हिन्दी](DESIGN.hi.md) | [العربية](DESIGN.ar.md) | [বাংলা](DESIGN.bn.md) | [Bahasa Indonesia](DESIGN.id.md) | [日本語](DESIGN.ja.md)

# Offenes Admin-Panel — Design-Dokument

> Detaillierte Mermaid-Architekturdiagramme finden Sie in [ARCHITECTURE.de.md](ARCHITECTURE.de.md) (wird in GitHub/GitLab/VS Code automatisch gerendert).

## 1. Systemarchitektur

> **Funktionsübersicht**: Authentifizierung (login/register/refresh/logout + Kontosperrung + Sitzungsbegrenzung) | Dashboard (Redis-Cache) | Benutzer-CRUD + Massenoperationen + Import | Rollen & Berechtigungen (RBAC) | Systemkonfiguration | Aktions-Audit (8 Plattform-Quellen) | Dateien (Upload + Export + Maskierung) | Sicherheit (18-stufige Verteidigung) | Betrieb (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        Client-Ebene                          │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  Admin-Panel         │  │  Client (Handy/Tablet/2-in-1)│  │
│  │  (Desktop-Stil)      │  │                              │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API-Gateway-Ebene                   │    │
│  │  AdminAuth(Auth) → AdminPermission(Authz) → Controller│    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │        Geschäftslogik-Ebene (Controller/Service)      │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │           │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼      │    │
│  │                   Model-Ebene                         │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (PK-Erzeugung)  (DB-Feld-Verschl.) (API-Übertragung)│    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │                  Datenspeicher-Ebene                   │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (Hauptspeicher)│ (Volltextsuche) │ (Cache)  │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Backend-Architektur

### 2.1 Schichten-Design

| Ebene | Verzeichnis | Verantwortung |
|---|------|------|
| Routing | `config/route.php` | URL-zu-Controller-Zuordnung, Middleware-Bindung, versionierte Routen |
| Middleware | `app/middleware/` | Angriffsblock (SecurityFilter), Rate-Limiting (RateLimit), Authentifizierung (JWT), Autorisierung (RBAC), API-Version (ApiVersion) |
| Controller | 14: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs (Admin) + Captcha/Auth (API v1) | Request-Parametervalidierung, Geschäftslogik-Aufruf, Antwortformatierung |
| Geschäftsservices | `app/service/` | Wiederverwendbare Geschäftslogik (vorgesehen) |
| Datenmodelle | `app/model/` | ORM-Zuordnung, Beziehungen, Feldver-/entschlüsselung |
| Gemeinsame Werkzeuge | `app/common/` | Hashids-, Snowflake-, Encryption-Services |

### 2.2 Request-Lebenszyklus

```
Client-Request
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route-Matching
  │
  ▼
Middleware-Kette:
  Locale ──────────────► Accept-Language / ?lang= Spracherkennung
  │
  ▼
  SecurityFilter ──────► HTTP-Methodenprüfung → 405 (nur GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF-Angriffsblock (403)
  ▼
  RateLimit ───────────► Redis-Gleitfenster-Rate-Limiting
  │ (Fehler → 429 + Retry-After-Header)
  ▼
  ApiVersion ─────────► API-Version-Header-Prüfung, injiziert $request->apiVersion
  │ (Fehler → 400)
  ▼
  AdminAuth ──────────► JWT-Validierung, injiziert $request->adminId
  │ (Fehler → 401)
  ▼
  AdminPermission ────► RBAC-Berechtigungsprüfung (Redis-60s-Cache)
  │ (Fehler → 403)
  ▼
  OperationLog ───────► Aktionsprotokoll-Aufzeichnung (POST/PUT/DELETE), automatische Quellenerkennung
  │
  ▼
Controller::method()
  │
  ├─► Parametervalidierung (validator)
  ├─► Bestätigung sensibler Operationen (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model-Operationen (automatische encryptable-Ver-/entschlüsselung)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 ID-Lebenszyklus

```
Erzeugung (Snowflake) → Speicherung (MySQL BIGINT) → Übertragung (Hashids-Kodierung) → Extern (hash-Zeichenkette)
                                                                             │
                          HashidsService::decode() ←──────────────────────────┘
```

### 2.4 Datenverschlüsselungs-System

```
Übertragungsebene (encryption)  — AES-256-CBC, unabhängiger Schlüssel
Speicherebene (encryptable)     — AES-128-ECB, unabhängiger Schlüssel, automatisch über Model $casts
Anzeigeebene (mask)             — Handynummer: 138****1234, E-Mail: a***@example.com
```

## 3. Datenbank-Design

### 3.1 ER-Beziehungen

```
logistics_admin_user ──┬── logistics_admin_user_role ──┬── logistics_admin_role
  (Benutzer)       │    (Benutzer-Rolle-Zuordnung) │     (Rolle)
                   │                          │
                   │                    logistics_admin_role_permission
                   │                     (Rolle-Berechtigung-Zuordnung)
                   │                          │
                   │                          ▼
                   │                    logistics_admin_permission
                   │                      (Berechtigung/Menü)
                   │
                   ▼
            logistics_operation_log
              (Aktionsprotokoll)

logistics_system_config (Systemkonfiguration) — eigenständige Tabelle
```

### 3.2 Kern-Tabellenstrukturen

| Tabellenname | Feldanzahl | Beschreibung |
|------|-------|------|
| `logistics_admin_user` | 14 | Admin-Benutzer, phone/email/id_card verschlüsselt gespeichert, Soft Delete unterstützt |
| `logistics_admin_role` | 7 | Rollen, slug eindeutig |
| `logistics_admin_permission` | 10 | Berechtigungsbaum (parent_id-Selbstreferenz), type: 1=Menü 2=Button 3=API |
| `logistics_admin_user_role` | 2 | Viele-zu-viele-Zwischentabelle Benutzer-Rolle |
| `logistics_admin_role_permission` | 2 | Viele-zu-viele-Zwischentabelle Rolle-Berechtigung |
| `logistics_system_config` | 8 | Schlüssel-Wert-Konfiguration, group+key gemeinsam eindeutig |
| `logistics_operation_log` | 9 | Aktions-Auditprotokoll (inkl. source-Quelle) |

### 3.3 Primärschlüssel-Konventionen

- Typ: `BIGINT UNSIGNED NOT NULL`
- Eigenschaft: **kein Auto-Increment**, Erzeugung auf Anwendungsebene per Snowflake-Algorithmus
- Vorteile: global eindeutig, verteilungsfreundlich, trendmäßig inkrementell (indexfreundlich), gibt kein Geschäftsvolumen preis
- Konfiguration: datacenter_id(0-31) + worker_id(0-31), unterstützt 1024 Knoten parallel

## 4. API-Design

### 4.1 URL-Konventionen

```
Öffentliche Endpunkte:  /api/captcha/{generate|verify}
                        /api/auth/{login|register|refresh}

Admin-Endpunkte:       /admin/{resource}[/{hashid}]
                       /admin/export/{excel|pdf}

Ressourcen-Routen:
  GET    /admin/user          → Liste
  POST   /admin/user          → Erstellen
  GET    /admin/user/{hashid} → Details
  PUT    /admin/user/{hashid} → Aktualisieren
  DELETE /admin/user/{hashid} → Löschen (Passwortbestätigung erforderlich)

Systemkonfiguration:  /admin/config[/{hashid}]
Aktionsprotokoll:     /admin/log
Persönlicher Bereich: /admin/profile[/password|/logout]
Import:               /admin/import/users
Upload:               /admin/upload
Massenoperationen:    /admin/user/batch/{destroy|status}
Dokumentation:        /api/docs     (OpenAPI 3.0)
Health:               /health
```

### 4.2 API-Versionsstrategie

Die API-Version wird über den Request-Header gesteuert und **erscheint nicht im URL-Pfad**:

```http
API-Version: v1
```

| Mechanismus | Beschreibung |
|------|------|
| Standardversion | Ohne `API-Version`-Header standardmäßig `v1` |
| Validierung | `ApiVersion`-Middleware validiert; nicht unterstützte Versionen liefern 400 |
| Routing | Die Hilfsfunktion `v()` löst die Controller-Klasse dynamisch anhand der Version auf |
| Verzeichnis | Controller werden nach Version organisiert: `app/api/{version}/controller/` |

Erweiterungsbeispiel — neue v2-API:
1. `app/api/v2/controller/AuthController.php` erstellen
2. In der `ApiVersion`-Middleware die Konstante `SUPPORTED` um `'v2'` erweitern
3. Routendefinitionen müssen nicht geändert werden

```bash
# v1 verwenden
curl -H "API-Version: v1" /api/auth/login

# v2 verwenden
curl -H "API-Version: v2" /api/auth/login

# Nicht übergeben, Standard v1
curl /api/auth/login
```

### 4.3 Rate-Limiting-Strategie

Basiert auf dem Redis-Sorted-Set-Gleitfenster-Algorithmus, ausgeführt als atomares Lua-Skript:

| Endpunkt | Limit |
|------|------|
| Standard | 60/Minute/IP/Route |
| POST /api/auth/login | 10/Minute |
| POST /api/auth/register | 5/Minute |

Bei Überschreitung wird 429 zurückgegeben; die Response-Header enthalten X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 Einheitliche Antwort

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Bedeutung | Auslöseszenario |
|------|------|---------|
| 0 | Erfolg | Normale Antwort |
| 400 | Parameterfehler | Request-Format nicht korrekt |
| 401 | Nicht authentifiziert | Token fehlt/abgelaufen/ungültig |
| 403 | Keine Berechtigung | Die Benutzerrolle enthält die benötigte Berechtigung nicht |
| 404 | Nicht vorhanden | Ressource nicht gefunden |
| 422 | Validierungsfehler | Formularparameter entsprechen nicht den Regeln / Passwortbestätigung fehlgeschlagen |
| 500 | Serverfehler | Unerwartete Exception |

### 4.5 Authentifizierungsablauf (inkl. Klick-Captcha)

```
Client                                  Server
  │                                      │
  │  ① POST /api/captcha/generate       │ captcha_create('click')
  │◄── {key, image(base64), targets}    │
  │                                      │
  │  ② Benutzer klickt die Textstellen   │
  │     im Bild an                       │
  │                                      │
  │  ③ POST /api/auth/login             │
  │     {username, password,             │
  │      captcha_key, clicks}            │
  │──────────────────────────────────►  │
  │                                      │ ① captcha_verify()
  │                                      │ ② password_verify()
  │                                      │ ③ jwt()->create()
  │◄── {access_token, refresh_token}    │
  │                                      │
  │  ④ GET /admin/dashboard             │
  │     Authorization: Bearer xxx       │
  │──────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}             │
```

### 4.6 Berechtigungsmodell (RBAC)

```
  Benutzer ──┬── Rolle ──┬── Berechtigung
  User         Role        Permission
                 │
                 ├── type=1: Menü (steuert die Sidebar-Sichtbarkeit)
                 ├── type=2: Button (steuert Operationen innerhalb der Seite)
                 └── type=3: API  (steuert den Endpunkt-Zugriff)

  Format der Berechtigungs-Kennung: {method}.{path}
  Bsp.: get.admin/user  post.admin/user  delete.admin/user
  Superadministrator-Kennung: * (überspringt alle Berechtigungsprüfungen)
```

### 4.7 Doppelte Bestätigung sensibler Operationen

Sensitive Operationen wie das Löschen von Benutzern, Rollen oder Berechtigungen erfordern die Übergabe des aktuellen Benutzerpassworts im Request-Body zur Identitätsüberprüfung:

```
Client                              Server
  │                                  │
  │  DELETE /admin/user/{hashid}    │
  │  { password: "******" }         │
  │──────────────────────────────►  │
  │                                  │ confirmPassword(adminId, password)
  │                                  │ → Passwort falsch: 422
  │                                  │ → Passwort korrekt: weiter ausführen
  │◄── 200 { code: 0 }              │
```

Das Frontend zeigt vor dem Auslösen einer Löschoperation einen Bestätigungsdialog an, sammelt das Benutzerpasswort und sendet anschließend den Request.

## 5. Frontend-Design

### 5.1 Flutter-Web-Admin-Panel

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ Menü-Button       🔔 Nachricht  👤 Admin  ▼ │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Inhaltsbereich                     │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 Dashboard│  Statistik-Karten×4│ │ Trenddiagramm│     │
│ 👥 Benutzer│  └──────────────┘ └──────────┘     │
│ 🔒 Rollen │  ┌──────┐ ┌────────────────┐       │
│ ⚙ Konfig. │  │Kreisdiagramm│ │ Letzte Aktionen │       │
│ 📋 Protokoll│  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

Eigenschaften: einklappbare Sidebar, Material 3 mit zwei Themes, dichte Datentabellen, Dialog-Popups, Hover-Interaktionen

### 5.2 HarmonyOS-Mobilclient

Seitenrouting:

| Seite | Route | Beschreibung |
|------|------|------|
| LoginPage | `pages/LoginPage` | Benutzername/Passwort + Klick-Captcha-Login |
| DashboardPage | `pages/DashboardPage` | Statistik-Karten + letzte Aktionen |
| UserListPage | `pages/UserListPage` | Benutzerliste, Suche + Pull-to-Refresh + Scroll-Laden |
| UserDetailPage | `pages/UserDetailPage` | Neu/Editieren/Anzeigen/Löschen (AlertDialog-Bestätigung) |
| ProfilePage | `pages/ProfilePage` | Persönlicher Bereich, Logout (AlertDialog-Bestätigung) |

Datenfluss: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Sicherheitsdesign

### 6.1 Tiefenverteidigung

| Ebene | Maßnahme |
|------|------|
| Methodenlimit | SecurityFilter-HTTP-Methoden-Whitelist, nur GET/POST/PUT/DELETE/OPTIONS/HEAD erlaubt, nicht standardkonforme Methoden → 405 |
| Angriffsblock | SecurityFilter-Middleware, XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF-Erkennung und -Block |
| Mensch-Maschine-Verifizierung | Klick-Captcha (Click Captcha), Pflicht bei Login/Registrierung |
| Kontosperrung | 5 aufeinanderfolgende Fehlversuche sperren das Konto für 15 Minuten; während der Sperrung 429 |
| Sitzungsbegrenzung | Maximal 3 gleichzeitige Tokens pro Benutzer; bei Überschreitung wird das älteste Token automatisch geblacklistet |
| Rate-Limiting | RateLimit-Middleware, Redis-Gleitfenster, atomar per Lua |
| CSP | Content-Security-Policy-Header begrenzt Ressourcenquellen, verhindert XSS und Dateninjektion |
| Operationsbestätigung | Löschen und andere sensitive Operationen erfordern die doppelte Passwortbestätigung des aktuellen Benutzers |
| Übertragung | HTTPS + JWT Bearer Token |
| API-IDs | Hashids-Verschlüsselung, echte IDs können von außen nicht zurückgerechnet werden |
| Request-Body | AES-256-CBC-Verschlüsselung sensibler Felder |
| Datenbank | BIGINT-Primärschlüssel (gibt kein Auto-Increment preis) |
| Datenbank | AES-128-ECB-Verschlüsselung sensibler Felder |
| Authentifizierung | JWT HS256, 2h Ablauf + refresh token |
| Autorisierung | RBAC, method.path-Granularität |
| Audit | OperationLog zeichnet alle Operationen auf (inkl. automatischer Quellenerkennung `source`) |

### 6.2 Schlüsselverwaltung

```
JWT_SECRET          → per Umgebungsvariable injiziert, 64-stellige Zufallszeichenkette
HASHIDS_SALT        → eindeutiger Salt; bei Leckage globaler Austausch erforderlich
ENCRYPTION_KEY      → Verschlüsselungsschlüssel für API-Übertragung, 32 Byte
ENCRYPTABLE_KEY     → Verschlüsselungsschlüssel für DB-Speicherung, unabhängig vom Übertragungsschlüssel
SCOUT_HOSTS         → ES-Adresse, Intranet-Bereitstellung
```

### 6.3 Schutz sensibler Daten

| Szenario | Felder | Maßnahme |
|------|------|------|
| Listendarstellung | phone | Maskierung: 138****1234 |
| Listendarstellung | email | Maskierung: a***@example.com |
| Detailansicht | phone/email | Entschlüsselter Endpunkt |
| Excel-Export | phone/email | Maskiert exportieren |
| PDF-Export | alle Felder | Maskierung + nicht entfernbares Copyright-Wasserzeichen |
| Speicherung | phone/email/id_card | encryptable-Verschlüsselung zu Chiffretext |

## 7. Export-Design

### 7.1 Excel-Export

```
Request: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() Daten abfragen (limit 10000)
  → Sensible Felder maskieren
  → PhpSpreadsheet aufbauen (blau-weißer Tabellenkopf + erste Zeile einfrieren + Autofilter)
  → In runtime/tmp/ schreiben → download-Antwort
```

### 7.2 PDF-Export

```
Request: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + Inline-CSS + Seitenkopf-Copyright + nicht entfernbares Seitenfuß-Copyright
  → Dompdf rendert A4 Querformat
  → In runtime/tmp/ schreiben → download-Antwort
```

## 8. Deployment-Architektur

### 8.1 Empfohlene Topologie

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                     Statische Dateien: Flutter Web build/
```

### 8.2 Docker Compose (für Produktion empfohlen)

Das `docker-compose.yml` im Projektstammverzeichnis orchestriert alle Dienste der obigen Topologie:

| Dienst | Image/Build | Port | Beschreibung |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Reverse-Proxy + statische Dateien + Gzip |
| `app` | Lokal per `Dockerfile` gebaut | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Hauptdatenbank, persistente Datenvolumes |
| `redis` | redis:7-alpine | 6379 | Cache / Rate-Limiting / Captcha |
| `elasticsearch` | elasticsearch:8.x | 9200 | Volltextsuche |

Vor dem Start müssen die Schlüssel `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY` usw. in `docker-compose.yml` durch zufällige Zeichenketten ersetzt werden.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

Die GitHub-Actions-Continuous-Integration ist in `.github/workflows/ci.yml` definiert:
- PHP-Syntaxprüfung (`php -l`)
- PHPUnit-Unit-Tests
- Flutter-Statische-Analyse (`flutter analyze`)

### 8.4 Datenbank-Backup

`database/backup/backup.sh` — mysqldump + gzip-Backup, löscht automatisch Backups älter als 30 Tage.
`database/backup/restore.sh` — interaktive Auswahl und Wiederherstellung des Backups.

### 8.5 Monitoring

Der Endpunkt `GET /metrics` (`MetricsController`) stellt im Prometheus text format 5 gauge-Metriken bereit: Gesamtzahl der HTTP-Requests, aktive Benutzer, Datenbank-/Redis-Verbindungsstatus, Speichernutzung.

### 8.6 Umgebungsanforderungen

| Komponente | Mindestversion | Empfohlene Konfiguration |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ mit OPcache aktiviert |
| MySQL | 8.0+ | 8.0+ Master-Slave-Replikation |
| Elasticsearch | 7.x | 8.x mit 3-Knoten-Cluster |
| Redis | 6.x | 7.x im Sentinel-Modus |
| Nginx | 1.20+ | Reverse-Proxy + gzip + SSL |
| Flutter SDK | 3.41+ | Neueste stabile Version |
| HarmonyOS | API 12 | DevEco Studio 5.x |
