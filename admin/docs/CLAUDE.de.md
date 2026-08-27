> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](CLAUDE.md) | [English](CLAUDE.en.md) | [한국어](CLAUDE.ko.md) | [Русский](CLAUDE.ru.md) | [Deutsch](CLAUDE.de.md) | [Français](CLAUDE.fr.md) | [Español](CLAUDE.es.md) | [Português](CLAUDE.pt.md) | [हिन्दी](CLAUDE.hi.md) | [العربية](CLAUDE.ar.md) | [বাংলা](CLAUDE.bn.md) | [Bahasa Indonesia](CLAUDE.id.md) | [日本語](CLAUDE.ja.md)

# Offenes Admin-Panel (open-admin)

Ein Full-Stack-Administrations-Backend auf Basis von webman v2 + Flutter.

## Copyright-Hinweis

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **Nicht änderbar, nicht entfernbar, nicht umkehrbar.** Alle neuen Dateien müssen den obigen Copyright-Hinweis als Datei-Header-Kommentar enthalten.

## Funktionsübersicht

| Bereich | Funktion |
|----|------|
| Authentifizierung | Login/Registrierung/Erneuerung/Logout + Captcha + Kontosperrung + Sitzungsbegrenzung |
| Dashboard | Echtzeit-Statistiken/Trends/Verteilung/Protokoll (Redis-5m-Cache) |
| Benutzer | CRUD + Massenlöschung/Aktivieren-Deaktivieren + Excel-Import |
| Rollen & Berechtigungen | CRUD + Berechtigungsbaum + RBAC-method.path-Autorisierung |
| Systemkonfiguration | Schlüssel-Wert-CRUD |
| Aktions-Audit | Protokollabfrage + automatische Quellenerkennung von 8 Plattformen |
| Dateien | Upload + Excel/PDF-Export (Maskierung sensibler Daten) |
| Sicherheit | 18-stufige Tiefenverteidigung (XSS/SQL-Injection/CSRF/Rate-Limiting/CSP...) |
| Betrieb | Health Check/Prometheus-Metriken/API-Dokumentation/security.txt + Docker + CI/CD |

## Technologie-Stack

### Backend
- PHP 8.3+, webman v2 (workerman/webman)
- Datenbank: MySQL 8.0+, Tabellenpräfix `logistics_`
- Primärschlüssel: BIGINT ohne Auto-Increment, erzeugt von `erikwang2013/snowflake-php`
- API-Ebenen-ID-Ver-/entschlüsselung: `erikwang2013/hashids`
- JWT-Authentifizierung: `erikwang2013/jwt-webman`
- Ver-/Entschlüsselung sensibler API-Daten: `erikwang2013/encryption`
- Ver-/Entschlüsselung sensibler Datenbankfelder: `erikwang2013/encryptable`
- ES-Synchronisation und -Abfrage: `erikwang2013/webman-scout`
- Länder-Flaggen: `erikwang2013/season`

### Frontend
- Flutter 3.x, Quellverzeichnis `apps/flutter/`
- Web-Version im PC-Admin-Stil gestaltet (nicht Mobile-App-Stil)
- Unterstützt Client und Admin-Endgerät
- HarmonyOS ArkTS, Quellverzeichnis `apps/harmonyos/`

## Projektstruktur

```
open-admin/
├── app/
│   ├── admin/controller/       # Admin-Controller (14)
│   │   ├── BaseController.php      # Basis-Controller
│   │   ├── DashboardController.php # Dashboard (Redis-Cache)
│   │   ├── UserController.php      # Benutzer-CRUD + Massenoperationen
│   │   ├── RoleController.php      # Rollen-CRUD
│   │   ├── PermissionController.php# Berechtigungs-CRUD
│   │   ├── ConfigController.php    # Systemkonfigurations-CRUD
│   │   ├── LogController.php       # Aktionsprotokoll-Abfrage
│   │   ├── ProfileController.php   # Persönlicher Bereich + Logout
│   │   ├── ExportController.php    # Excel/PDF-Export
│   │   ├── ImportController.php    # Excel-Benutzerimport
│   │   ├── UploadController.php    # Datei-Upload
│   │   ├── HealthController.php    # Health Check
│   │   ├── DocsController.php      # OpenAPI-Dokumentation
│   │   └── MetricsController.php   # Prometheus-Monitoring-Metriken
│   ├── api/v1/controller/      # API-v1-Controller (Versionsheader-Steuerung)
│   │   ├── CaptchaController.php
│   │   └── AuthController.php
│   ├── common/                 # Gemeinsame Werkzeugklassen
│   │   ├── HashidsService.php
│   │   ├── SnowflakeService.php
│   │   └── EncryptionService.php
│   ├── common/                 # Gemeinsame Definitionen (inkl. Apidoc Definitions)
│   ├── middleware/             # Middleware (8)
│   │   ├── Cors.php            # Cross-Origin (global)
│   │   └── (migriert in das Paket erikwang2013/security-php)  # 31 Angriffserkennungen
│   │   ├── RateLimit.php       # Redis-Rate-Limiting (global, atomar per Lua)
│   │   ├── ApiVersion.php      # API-Versionsprüfung
│   │   ├── AdminAuth.php       # JWT-Authentifizierung + Blacklist
│   │   ├── AdminPermission.php # RBAC-Berechtigungsprüfung (Redis-60s-Cache)
│   │   └── OperationLog.php    # Automatische Aktionsprotokoll-Aufzeichnung (inkl. Quellenerkennung)
│   ├── model/                  # Datenmodelle
│   ├── queue/                  # Queue-Tasks
│   └── process/                # Prozesse (Http, Monitor)
├── apps/
│   ├── flutter/                # Flutter-Web-Admin-Panel
│   │   └── lib/app/
│   │       ├── pages/          # 6 vollständige Seiten
│   │       │   ├── dashboard/  # Dashboard
│   │       │   ├── login/      # Login
│   │       │   ├── user/       # Benutzerverwaltung
│   │       │   ├── role/       # Rollen & Berechtigungen
│   │       │   ├── config/     # Systemkonfiguration
│   │       │   ├── log/        # Aktionsprotokoll
│   │       │   └── profile/    # Persönlicher Bereich
│   │       ├── services/       # ApiService + AuthService
│   │       ├── layouts/        # Responsives Layout
│   │       └── theme/          # Material-3-Theme
│   └── harmonyos/              # HarmonyOS-Client
├── config/                     # Konfigurationsdateien
│   ├── route.php               # Routing + API-Versionsstrategie
│   └── middleware.php           # Registrierung globaler Middleware
├── database/
│   ├── install.sql             # Vollständiges Installationsskript (kombinierte SQLs)
│   └── backup/                 # Datenbank-Backupskripte
│       ├── backup.sh           # mysqldump+gzip, 30 Tage Aufbewahrung
│       └── restore.sh          # Interaktive Wiederherstellung
├── docs/                       # Dokumentation
│   ├── ARCHITECTURE.md         # Mermaid-Architekturdiagramme
│   ├── DESIGN.md               # Design-Dokument
│   ├── SECURITY.md             # Sicherheitsarchitektur-Design
│   ├── API.md                  # API-Referenzdokumentation
│   ├── nginx-security.conf     # Nginx-Sicherheitsreferenz
│   ├── diagrams/               # Zerlegte Architekturdiagramme
│   └── superpowers/            # Konventionen & Pläne
│       ├── specs/              # Design-Spezifikationen
│       └── plans/              # Implementierungspläne
├── public/                     # Öffentlicher Einstiegspunkt
├── runtime/                    # Laufzeitdateien
├── tests/                      # Tests
├── vendor/                     # Composer-Abhängigkeiten
├── CLAUDE.md                   # Diese Datei
├── README.md                   # Chinesische Anleitung
├── docs/translations/README.en.md                # Englische Anleitung
├── docs/translations/README.ko.md ... README.ja.md  # Mehrsprachige Anleitungen (Kor/Russ/Deutsch/Franz/Span/Portug/Hindi/Arabisch/Bengali/Indonesisch/Japanisch)
├── .env                        # Umgebungsvariablen (nicht versioniert)
├── .env.example                # Umgebungsvariablen-Vorlage
├── .env.docker                 # Docker-Umgebungsvariablen
├── composer.json               # PHP-Abhängigkeiten
├── Dockerfile                  # Docker-Build
├── docker-compose.yml          # Docker-Orchestrierung
└── .github/
    └── workflows/
        └── ci.yml              # CI/CD-Pipeline (PHP-Syntax+PHPUnit+Flutter analyze)
```

## Middleware-Ausführungskette

```
Global:  Cors → Locale(Accept-Language) → SecurityFilter(Methodenprüfung→405) → RateLimit → {Routen-Middleware}
/admin: Cors → Locale(Accept-Language) → SecurityFilter(Methodenprüfung→405) → RateLimit → AdminAuth → AdminPermission → OperationLog → Controller
/api:   Cors → Locale(Accept-Language) → SecurityFilter(Methodenprüfung→405) → RateLimit → ApiVersion → Controller
/health: Cors → Locale(Accept-Language) → SecurityFilter(Methodenprüfung→405) → RateLimit → Controller
```

## Sicherheitsverbesserungen

- **HTTP-Methodenlimitierung**: Der SecurityFilter erlaubt nur GET/POST/PUT/DELETE/OPTIONS/HEAD, nicht standardkonforme Methoden liefern 405
- **CSP-Header**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies werden allen Antworten injiziert
- **Kontosperrung**: 5 aufeinanderfolgende fehlgeschlagene Logins → Konto für 15 Minuten gesperrt
- **Begrenzung paralleler Sitzungen**: Maximal 3 gültige Tokens pro Benutzer; bei Überschreitung wird das älteste Token geblacklistet
- **security.txt**: `/.well-known/security.txt`-Endpunkt nach RFC 9116
- **Nginx-Sicherheitskonfiguration**: `docs/nginx-security.conf` als Härtungsreferenz für den Reverse-Proxy

## API-Versionsstrategie

Die Version wird über den Request-Header `API-Version` gesteuert (Standard `v1`) und erscheint nicht in der URL:

```bash
curl -H "API-Version: v1" http://localhost:8787/api/auth/login
```

Eine neue Version erfordert lediglich die Erstellung des Verzeichnisses `app/api/{version}/controller/` und die Registrierung in der `ApiVersion`-Middleware.

## Rate-Limiting-Strategie

Redis-Gleitfenster (atomar per Lua), Standard 60/Minute/IP/Route:
- Login: 10/Minute
- Registrierung: 5/Minute
- Response-Header: `X-RateLimit-Limit/Remaining/Reset`, bei Überschreitung zusätzlich `Retry-After`

## Code-Konventionen

### PHP
- Globale Funktionen/Klassen ohne vorangestelltes `\`, per `use` importieren
- Konfigurationsdateien müssen chinesische Kommentare mit der Bedeutung jedes Konfigurationspunkts enthalten
- Alle neuen `.php`-Dateien müssen oben den Copyright-Hinweis enthalten

### Datenbank
- Tabellenpräfix: `logistics_`
- Primärschlüssel `id`: Typ BIGINT, ohne Auto-Increment, von Snowflake erzeugt
- Sensible Felder werden über das `erikwang2013/encryptable`-Trait automatisch ver-/entschlüsselt
- Migrationsdateien im SQL-Format

### Flutter
- Web-Layout im PC-Admin-Stil (Sidebar + Topbar + Inhaltsbereich)
- GetX-State-Management, `ApiService`-Singleton (Dio + JWT-Interceptor)
- Token-Persistierung über `shared_preferences`
- Responsive Breakpoints: Mobil (< 768px) und Desktop (>= 768px)

### HarmonyOS
- Natives HTTP-Client `@ohos.net.http`
- Nahtlose Token-Erneuerung: bei 401 automatisch `/api/auth/refresh` aufrufen
- Bei fehlgeschlagener Erneuerung automatische Weiterleitung zur Login-Seite

## Deployment

### Docker Compose (für Produktion empfohlen)

Das `docker-compose.yml` im Projektstammverzeichnis orchestriert 5 Dienste:

| Dienst | Beschreibung |
|------|------|
| `nginx` | Nginx-Reverse-Proxy (80/443), statischer Dateiservice |
| `app` | webman-PHP-8.3-App, über `Dockerfile` gebaut (mit OPcache) |
| `mysql` | MySQL 8.0, persistente Datenvolumes |
| `redis` | Redis 7 Alpine, Cache/Rate-Limiting/Session |
| `elasticsearch` | Elasticsearch 8.x, Volltextsuche |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` definiert die GitHub-Actions-Pipeline:

- PHP-Syntaxprüfung (`php -l`)
- PHPUnit-Unit-Tests
- Flutter-Statische-Analyse (`flutter analyze`)

### Datenbank-Backup

`database/backup/backup.sh` — mysqldump + gzip, löscht automatisch Backups älter als 30 Tage.
`database/backup/restore.sh` — interaktive Wiederherstellung, listet verfügbare Backups zur Auswahl auf.

### Monitoring

Der Endpunkt `GET /metrics` (`MetricsController`) liefert Prometheus text format mit 5 gauge-Metriken:
- `openadmin_http_requests_total` — Gesamtzahl der Requests
- `openadmin_active_users` — Anzahl aktiver Benutzer
- `openadmin_db_connection_status` — Datenbank-Verbindungsstatus (0/1)
- `openadmin_redis_connection_status` — Redis-Verbindungsstatus (0/1)
- `openadmin_memory_usage_bytes` — Speichernutzung
