> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](../README.md) | [English](README.en.md) | [한국어](README.ko.md) | [Русский](README.ru.md) | [Deutsch](README.de.md) | [Français](README.fr.md) | [Español](README.es.md) | [Português](README.pt.md) | [हिन्दी](README.hi.md) | [العربية](README.ar.md) | [বাংলা](README.bn.md) | [Bahasa Indonesia](README.id.md) | [日本語](README.ja.md)

# Offenes Admin-Panel (open-admin)

Ein Full-Stack-Administrations-Backend auf Basis von webman v2 + Flutter.

> [English](README.en.md) | [한국어](README.ko.md) | [Русский](README.ru.md) | [Deutsch](README.de.md) | [Français](README.fr.md) | [Español](README.es.md) | [Português](README.pt.md) | [हिन्दी](README.hi.md) | [العربية](README.ar.md) | [বাংলা](README.bn.md) | [Bahasa Indonesia](README.id.md) | [日本語](README.ja.md) | [Architekturdiagramm](docs/ARCHITECTURE.de.md) | [Design-Dokument](docs/DESIGN.de.md) | [Sicherheitsarchitektur](docs/SECURITY.de.md) | [API-Referenz](docs/API.de.md)

## Funktionsübersicht

<img src="../../../docs/diagrams/description.svg" alt="Funktionsübersicht" width="100%">


| Geschäftsbereich | Funktion | Beschreibung |
|--------|------|------|
| 🔐 Authentifizierung | Login/Token-Erneuerung/Logout | Klick-Captcha + JWT + Blacklist |
| | Kontosperrung | 5 Fehlversuche → 15 Minuten Sperrung |
| | Begrenzung paralleler Sitzungen | Maximal 3 gültige Tokens pro Benutzer |
| 📊 Dashboard | Echtzeit-Statistiken/Trenddiagramm/Verteilungsdiagramm/Letzte Aktionen | Redis-Cache 5 Minuten |
| 👥 Benutzerverwaltung | CRUD + Massenlöschung/Aktivieren-Deaktivieren | Soft Delete + doppelte Passwortbestätigung |
| | Excel-Massenimport | Zeilenweise Validierung + Fehlerbericht |
| 🔒 Rollen & Berechtigungen | Rollen-CRUD + Berechtigungsbaum | RBAC-Berechtigungen mit method.path-Granularität |
| ⚙ Systemkonfiguration | CRUD für Schlüssel-Wert-Paare | Gruppenverwaltung |
| 📋 Aktions-Audit | Protokollabfrage + Quellenerkennung | Automatische Erkennung von 8 Plattformen |
| 📁 Dateiverwaltung | Upload/Excel-Export/PDF-Export | Automatische Maskierung sensibler Daten |
| 🛡 Sicherheitsschutz | 18-stufige Tiefenverteidigung | XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF/Rate-Limiting/CSP... |
| 🏥 Betrieb | Health Check/metrics/API-Dokumentation/security.txt | Prometheus + OpenAPI 3.0 + interaktive hg/apidoc-Dokumentation |
| 🌐 Internationalisierung | Umschaltung Chinesisch/Englisch | Accept-Language-Header / ?lang=-Parameter |

## Technologie-Stack

| Ebene | Technologie | Beschreibung |
|---|------|------|
| Backend-Framework | webman v2 (workerman) | Hochleistungsfähiges PHP-Framework mit persistenten Prozessen |
| PHP-Version | 8.3+ | |
| Datenbank | MySQL 8.0+ | Tabellenpräfix `logistics_`, BIGINT-Primärschlüssel ohne Auto-Increment |
| Suchmaschine | Elasticsearch | Synchronisation und Abfrage über `webman-scout` |
| Admin-Frontend | Flutter 3.x | Web-Version im PC-Admin-Stil (`apps/flutter/`) |
| Mobil | HarmonyOS ArkTS | Natives HarmonyOS-Client (`apps/harmonyos/`), unterstützt Handy/Tablet/2-in-1 |

## Kernabhängigkeiten

| Paket | Zweck |
|---|------|
| `erikwang2013/snowflake-php` | Snowflake-Algorithmus zur Erzeugung global eindeutiger BIGINT-Primärschlüssel |
| `erikwang2013/hashids` | ID-Verschlüsselung/-entschlüsselung auf API-Ebene, verbirgt echte Datenbank-IDs |
| `erikwang2013/jwt-webman` | Ausstellung und Validierung von JWT-Authentifizierungstokens |
| `erikwang2013/encryption` | Ver-/Entschlüsselung sensibler Daten auf Übertragungsebene |
| `erikwang2013/encryptable` | Automatische Ver-/Entschlüsselung sensibler Felder auf Datenbankebene |
| `erikwang2013/webman-scout` | Elasticsearch-Datensynchronisation und Volltextsuche |
| `erikwang2013/season` | Länder-Flaggen-Daten |
| `erikwang2013/poster-php` | Klick-Captcha-Erzeugung und -Validierung + Poster-Erzeugung |
| `phpoffice/phpspreadsheet` | Excel-Export |
| `barryvdh/laravel-dompdf` | PDF-Export (basiert auf Dompdf) |

## Projektstruktur

```
open-admin/
├── app/
│   ├── admin/controller/       # Admin-Controller
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
│   │   └── BaseController.php      # Basis-Controller
│   ├── api/
│   │   └── v1/controller/          # API-v1-Controller (Version per Header API-Version gesteuert)
│   │       ├── CaptchaController.php # Klick-Captcha
│   │       └── AuthController.php    # Login/Token-Erneuerung
│   ├── common/                 # Gemeinsame Werkzeugklassen
│   │   ├── HashidsService.php  # ID-Kodierung/-Dekodierung
│   │   ├── SnowflakeService.php# Snowflake-ID-Erzeugung
│   │   └── EncryptionService.php # Datenver-/entschlüsselung + Maskierung
│   ├── middleware/             # Middleware
│   │   ├── Cors.php            # Cross-Origin
│   │   ├── SecurityFilter.php  # Angriffserkennung (HTTP-Methodenlimitierung/XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF)
│   │   ├── RateLimit.php       # Redis-Rate-Limiting (gleitendes Fenster + Response-Header)
│   │   ├── ApiVersion.php      # API-Versionsprüfung
│   │   ├── AdminAuth.php       # JWT-Authentifizierung + Blacklist
│   │   ├── AdminPermission.php # RBAC-Berechtigungsprüfung
│   │   └── OperationLog.php    # Automatische Aufzeichnung von Aktionsprotokollen (inkl. Quellenerkennung)
│   └── model/                  # Datenmodelle
├── apps/
│   ├── flutter/                # Flutter-Web-Admin-Panel (PC-Stil)
│   │   └── lib/app/
│   │       ├── pages/          # 5 vollständige Seiten (Dashboard/Benutzer/Rollen/Konfiguration/Protokoll/Persönlicher Bereich)
│   │       ├── services/       # ApiService (JWT-Interceptor) + AuthService (Token-Persistierung)
│   │       └── layouts/        # Responsives Admin-Layout (Sidebar+Topbar+Inhaltsbereich)
│   └── harmonyos/              # Natives HarmonyOS-Client (nahtlose Token-Erneuerung)
├── config/                     # Konfigurationsdateien (mit chinesischen Kommentaren)
│   ├── route.php               # Routing + API-Versionsstrategie
│   ├── middleware.php           # Registrierung globaler Middleware
│   └── ...                     # Komponenten-Konfigurationen
├── database/install.sql        # SQL-Installationsskript (inkl. Berechtigungs-Seed-Daten)
├── public/                     # Öffentlicher Einstiegspunkt
├── runtime/                    # Laufzeitdateien
└── vendor/                     # Composer-Abhängigkeiten
```

## Umgebungsanforderungen

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (nur für Frontend-Entwicklung erforderlich)
- Elasticsearch >= 7.x (optional, für Suchfunktionen erforderlich)

## Schnellstart

### 1. Abhängigkeiten installieren

```bash
composer install
```

### 2. Umgebungsvariablen konfigurieren

Umgebungsvariablen kopieren und anpassen (optional; ohne Konfiguration werden die Standardwerte aus `config/*.php` verwendet):

```bash
cp .env.example .env
```

Wichtige Konfigurationsoptionen:

| Umgebungsvariable | Beschreibung | Standardwert |
|---------|------|--------|
| `JWT_SECRET` | JWT-Signaturschlüssel | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids-Salt | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API-Verschlüsselungsschlüssel | 32-Byte-Standardwert |
| `SNOWFLAKE_DATACENTER_ID` | Rechenzentrums-ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | Worker-Knoten-ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES-Adresse | `http://localhost:9200` |

**In der Produktion müssen alle Schlüssel durch zufällige Zeichenketten ersetzt werden.**

### 3. Installation per Assistent

Nach dem Start des Dienstes öffnen Sie den Installationsassistenten im Browser, um die Datenbankinitialisierung und die Erstellung des Administrators abzuschließen:

```bash
php start.php start
```

Standardmäßig lauscht der Dienst auf `http://0.0.0.0:8787` (der Port kann in `config/server.php` geändert werden).

Im Browser **`http://localhost:8787/install`** öffnen und den Assistenten ausfüllen:

| Schritt | Inhalt |
|------|------|
| ① Datenbankkonfiguration | Host-Adresse, Port, Datenbankname, Benutzername, Passwort |
| ② Administrator-Einstellungen | Administrator-Benutzername, Passwort (Standard: admin / admin888) |

Nach dem Klick auf „Installation starten" werden Tabellen erstellt, Berechtigungsdaten eingespielt, das Administrator-Konto angelegt und die Datenbankkonfiguration in `.env` geschrieben.

> Nach der Installation wird die Sperrdatei `runtime/install.lock` erzeugt. Für eine Neuinstallation einfach diese Datei löschen.

### 4. Anmelden

`http://localhost:8787` aufrufen und mit dem bei der Installation festgelegten Administrator-Konto anmelden.

### 5. Frontend starten (optional)

**Flutter-Admin-Panel (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (PC-Admin-Stil)
```

**HarmonyOS-Client (Mobil):**

Öffnen Sie das Verzeichnis `apps/harmonyos/` mit DevEco Studio und führen Sie es auf einem echten Gerät oder Emulator aus.

### 6. One-Click-Deployment mit Docker Compose (für Produktion empfohlen)

Das Projekt enthält ein vollständiges Docker-Orchestrierungskonzept mit 5 Diensten: Nginx, PHP (webman app), MySQL, Redis, Elasticsearch.

```bash
# 1. Docker-Umgebungsvariablen konfigurieren
cp .env.docker .env

# 2. Alle Dienste starten
docker-compose up -d

# 3. Installationsassistenten im Browser öffnen und Initialisierung abschließen
# http://localhost:8787/install  (Datenbank- und Admin-Informationen eintragen)
# oder SQL-Migration manuell ausführen (im app-Container):
# docker-compose exec app mysql -h mysql -u root -p < database/install.sql

# 4. Zugriff
# http://localhost:8787  (webman)
# http://localhost:8080  (Nginx-Reverse-Proxy)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, basierend auf `php:8.3-cli`
- `docker-compose.yml`: Orchestrierung von 5 Diensten, Netzwerk-Isolation, persistente Datenvolumes
- `.env.docker`: Umgebungsvariablen speziell für Docker

## Datenbank-Konventionen

- **Tabellenpräfix**: `logistics_`
- **Primärschlüssel**: Der Primärschlüssel aller Tabellen ist `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT ist verboten**
- **ID-Erzeugung**: Primärschlüssel-IDs werden auf Anwendungsebene von `SnowflakeService::generate()` erzeugt, verteilt eindeutig
- **Pflichtfelder**: Jede Tabelle muss `id`, `created_at`, `updated_at` enthalten
- **Soft Delete**: Tabellen mit Soft Delete fügen `deleted_at DATETIME DEFAULT NULL` hinzu
- **Sensible Felder**: Handynummer, E-Mail, Personalausweisnummer usw. werden automatisch per `encryptable`-Plugin ver-/entschlüsselt; das Datenbankfeld speichert den Chiffretext als `VARCHAR(500)`

## API-Dokumentation

Die vollständige API-Referenz (einheitliches Antwortformat, Fehlercodes, alle Endpunkt-Details, Authentifizierungsablauf, Rate-Limiting-Strategie, Middleware-Kette) finden Sie in **[docs/API.de.md](docs/API.de.md)**. Die wichtigsten Punkte:

- **Einheitliches Antwortformat**: `{ "code": 0, "message": "success", "data": {...} }`, `code=0` bedeutet Erfolg
- **Fehlercodes**: `400` Parameterfehler / `401` nicht angemeldet / `403` keine Berechtigung / `404` nicht vorhanden / `422` Validierungsfehler / `429` Rate-Limit / `500` Serverfehler
- **API-Version**: Steuerung über den Request-Header `API-Version: v1` (Standard v1, wenn fehlend), nicht in der URL sichtbar
- **Authentifizierung**: `Authorization: Bearer <token>`; access_token gültig 2 Stunden, refresh_token 14 Tage
- **ID-Behandlung**: IDs in Requests/Responses sind hashids-verschlüsselte Zeichenketten, echte Datenbank-IDs werden nicht preisgegeben

## Frontend-Hinweise

### Flutter-Admin-Panel (PC-Stil)

- **Layout**: Sidebar (einklappbar 64px/240px) + Topbar + Inhaltsbereich, responsive drei Breakpoints (Handy/Tablet/Desktop)
- **Seiten**: Login, Dashboard, Benutzerverwaltung, Rollen & Berechtigungen, Systemkonfiguration, Aktionsprotokoll, Persönlicher Bereich
- **State-Management**: GetX (`ApiService`-Singleton + `AuthService`-Token-Persistierung)
- **Dashboard**: Statistik-Karten, Trend-Liniendiagramm (fl_chart), Kreisdiagramm, letzte Aktionsprotokolle
- **Export**: Excel/PDF-Export, PDF enthält nicht entfernbaren Copyright-Hinweis
- **Massenoperationen**: Mehrfachauswahl-Massenlöschung, Massenaktivierung/-deaktivierung
- **Theme**: Material 3 mit hellem/dunklem Theme

### HarmonyOS-Mobilclient

- **Seiten**: Login, Dashboard, Benutzerliste/-details, Persönlicher Bereich
- **Authentifizierung**: JWT Bearer + nahtlose automatische Token-Erneuerung bei 401; bei fehlgeschlagener Erneuerung automatische Weiterleitung zur Login-Seite
- **Speicher**: Token wird über AppStorage verwaltet

## Entwicklungsrichtlinien

- Globale Funktionen/Klassen ohne vorangestelltes `\`, einheitlich per `use` importieren
- Alle PHP-Dateien müssen oben den Copyright-Vermerk enthalten
- Alle Konfigurationsdateien müssen chinesische Kommentare enthalten
- Datenbank-Primärschlüssel müssen auf Anwendungsebene per Snowflake erzeugt werden, Auto-Increment ist verboten
- Alle IDs in API-Parametern und -Responses müssen per hashids ver-/entschlüsselt werden
- Die AdminPermission-Middleware cached Benutzerberechtigungen in Redis (TTL=60s), wodurch der N+1-Abfrage-Engpass entfällt

## Deployment

### Docker Compose (empfohlen)

Im Projektstammverzeichnis liegt `docker-compose.yml`, das 5 Dienste orchestriert:

| Dienst | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | Lokal per `Dockerfile` gebaut | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

Das PHP-Image wird über das `Dockerfile` gebaut, Basis-Image `php:8.3-cli`, mit aktiviertem OPcache.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

GitHub Actions Continuous-Integration-Pipeline: `.github/workflows/ci.yml`

- PHP-Syntaxprüfung (`php -l`)
- PHPUnit-Unit-Tests
- Flutter-Statische-Analyse (`flutter analyze`)

### Datenbank-Backup

Verzeichnis `database/backup/`:

- `backup.sh` — mysqldump + gzip-Backup, löscht automatisch Backups älter als 30 Tage
- `restore.sh` — interaktive Wiederherstellung, listet verfügbare Backups zur Auswahl auf

### Nginx-Sicherheitskonfiguration

Für Produktions-Deployments siehe `docs/nginx-security.conf` zur Härtung des Reverse-Proxys.

## Open Source ist nicht selbstverständlich — Ihre Unterstützung ist willkommen

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](./docs/weixinpay.png "WeChat") | ![Alipay](./docs/alipay.png "Alipay") |

### Weltweite Spende per Überweisung (grenzüberschreitende Überweisung)

**Empfängerinformationen**

- Name des Empfängers: WANG KEXUN
- Kontonummer des Empfängers: 881015918251

**Empfängerbank**

- ZA Bank SWIFT-Code: AABLHKHHXXX
- Bankname: ZA Bank Limited
- Bankleitzahl: 387
- Bankadresse: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Korrespondenzbank für grenzüberschreitende Überweisungen (falls erforderlich)**

> Dies sind die Informationen der Korrespondenzbank (Zwischenbank) für grenzüberschreitende Überweisungen, nicht die der Empfängerbank. Fragen Sie Ihre Überweisungsbank, ob die Angabe der Korrespondenzbank erforderlich ist.

- **Für Einzahlungen in Hongkong-Dollar, Renminbi und US-Dollar** ist die Korrespondenzbank Citibank:
  - Bankname: Citibank N.A. Hong Kong
  - SWIFT-Code: CITIHKHXXXX
  - Bankleitzahl: 006
  - Filialname: Hong Kong Branch
  - Filialleitzahl: 391
  - Bankadresse: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **Für Einzahlungen in anderen Währungen** ist die Korrespondenzbank BNY Mellon:
  - Bankname: THE BANK OF NEW YORK MELLON
  - SWIFT-Code: IRVTUS3NXXX
  - Bankadresse: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

---

## Lizenz

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
