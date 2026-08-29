> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](API.md) | [English](API.en.md) | [한국어](API.ko.md) | [Русский](API.ru.md) | [Deutsch](API.de.md) | [Français](API.fr.md) | [Español](API.es.md) | [Português](API.pt.md) | [हिन्दी](API.hi.md) | [العربية](API.ar.md) | [বাংলা](API.bn.md) | [Bahasa Indonesia](API.id.md) | [日本語](API.ja.md)

# API-Referenzdokumentation

## 1. Überblick

Das offene Admin-Panel (open-admin) basiert auf webman v2 und bietet eine RESTful-JSON-API. Alle Admin-Endpunkte erfordern JWT-Authentifizierung und RBAC-Berechtigungsprüfung; öffentliche Endpunkte werden über den API-Versionsheader an versionierte Controller geroutet.

- **Basis-URL**: `http://localhost:8787`
- **API-Version**: Steuerung über den Request-Header `API-Version: v1` (Standard v1, wenn fehlend)
- **Sprache**: Umschaltung über den `Accept-Language`-Header oder den Parameter `?lang=zh_CN|en` (Standard zh_CN); die Locale-Middleware erkennt dies automatisch

> **Endpunktübersicht**: Authentifizierung(5) | Dashboard(1) | Benutzer(7) | Rollen(4) | Berechtigungen(4) | Konfiguration(4) | Protokoll(1) | Persönlicher Bereich(3) | Import/Export(3) | Upload(1) | Betrieb(4: health/metrics/docs/security.txt) | Insgesamt 37 Endpunkte
- **Authentifizierung**: `Authorization: Bearer <token>` (JWT)
- **Antwortformat**: `{ "code": 0, "message": "success", "data": {...} }`
- **Dokumentations-Endpunkt**: `GET /api/docs` liefert die OpenAPI-3.0-JSON-Spezifikation

### Anforderungen an Requests

- Nur die Methoden `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` sind erlaubt; andere HTTP-Methoden (z. B. TRACE, CONNECT, PATCH) liefern 405
- Alle `POST` / `PUT`-Requests müssen `Content-Type: application/json` setzen (außer Datei-Upload), sonst 415
- Die Request-Body-Größe darf 10MB nicht überschreiten, sonst 413
- Der Sicherheitsfilter scannt alle Request-Inputs auf XSS, SQL-Injection, Pfad-Traversal und Befehlsinjektion; bei Treffer 403
- 5 aufeinanderfolgende fehlgeschlagene Logins lösen eine Kontosperrung aus (15 Minuten); während der Sperrung liefern Login-Requests 429
- Derselbe Benutzer darf maximal 3 gültige Tokens gleichzeitig besitzen; bei Überschreitung wird das älteste Token automatisch auf die Blacklist gesetzt

## 2. Fehlercodes

| code | Bedeutung | Auslöseszenario |
|------|------|---------|
| 0 | Erfolg | |
| 400 | Fehlerhafte Request-Parameter | Request-Format nicht korrekt |
| 401 | Nicht authentifiziert | Token fehlt / abgelaufen / auf der Blacklist |
| 403 | Keine Berechtigung / Sicherheitsblock | Unzureichende RBAC-Berechtigung / SecurityFilter getroffen |
| 404 | Ressource nicht vorhanden | Das Ziel von Abfrage/Aktualisierung/Löschung existiert nicht |
| 405 | Request-Methode nicht erlaubt | Nur GET/POST/PUT/DELETE/OPTIONS/HEAD erlaubt, nicht standardkonforme Methoden werden direkt abgelehnt |
| 413 | Request-Body zu groß | Content-Length über 10MB |
| 415 | Nicht unterstützter Medientyp | POST/PUT-Request mit Content-Type ungleich JSON und kein Datei-Upload |
| 422 | Parametervalidierung fehlgeschlagen | Pflichtfelder fehlen, Format nicht korrekt, Geschäftsregel-Prüfung nicht bestanden |
| 429 | Zu viele Requests | RateLimit ausgelöst / Kontosperrung (5 Fehlversuche → 15 Min. Sperrung) |
| 500 | Interner Serverfehler | |

## 3. Öffentliche Endpunkte

Alle öffentlichen Endpunkte sind unter der Gruppe `/api` gemountet und werden über die `ApiVersion`-Middleware anhand des `API-Version`-Headers an die entsprechenden versionierten Controller verteilt (z. B. `app\api\v1\controller\AuthController`).

### 3.1 Health Check

```
GET /health
```

- **Authentifizierung**: nicht erforderlich
- **Rate-Limiting**: keines

**Antwortbeispiel**:
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

Werte von `database`, `redis`, `elasticsearch`: `"ok"` | `"unavailable"`. `elasticsearch` liefert `"unavailable"`, wenn ES nicht erreichbar ist; ist der Cluster-Health-Status nicht green/yellow, wird der tatsächliche Status-Wert zurückgegeben (z. B. `"red"`).

### 3.2 API-Dokumentation

```
GET /api/docs
```

- **Authentifizierung**: nicht erforderlich
- **Rate-Limiting**: globaler Standard (60/Minute)
- **Antwort**: OpenAPI-3.0.3-JSON-Spezifikation mit allen Endpunkt-Definitionen, Parametern und Schemas

### 3.3 Captcha erzeugen

```
POST /api/captcha/generate
```

- **Authentifizierung**: nicht erforderlich
- **Request-Header**: `API-Version: v1` (erforderlich)
- **Rate-Limiting**: globaler Standard (60/Minute)

**Request-Body**:
```json
{
  "difficulty": "medium"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| difficulty | string | nein | `easy` / `medium` / `hard`, Standard `medium` |

**Antwortbeispiel** — Klick-Typ (`type: "click"`):
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

**Antwortbeispiel** — Slider-Typ (`type: "slider"`):
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

**Antwortbeispiel** — Rotations-Typ (`type: "rotate"`):
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

| Feld | Typ | Beschreibung |
|------|------|------|
| key | string | Captcha-Kennung, wird bei der Validierung zurückgesendet |
| type | string | Captcha-Typ: `click` / `slider` / `rotate` |
| image | string | base64-data-URI-Bild |
| extra | object | Typbezogene Zusatzdaten (siehe unten) |

**`extra` nach Typ**:

| type | extra-Felder | Typ | Beschreibung |
|------|-----------|------|------|
| click | targets | array | Klickziele mit `order`(Reihenfolge) `text`(Hinweistext) `x` `y`(Koordinaten) |
| slider | x, y | int | Koordinaten der oberen linken Ecke der Lücke (basierend auf 300×200-Leinwand) |
| slider | puzzle_w, puzzle_h | int | Breite/Höhe des Puzzles |
| slider | puzzle | string | base64-data-URI des Puzzle-Bildes |
| rotate | angle | int | Korrekter Rotationswinkel (0-359); um `360-angle` drehen, damit das Bild wieder gerade ist |

### 3.4 Captcha validieren

```
POST /api/captcha/verify
```

- **Authentifizierung**: nicht erforderlich
- **Request-Header**: `API-Version: v1` (erforderlich)
- **Rate-Limiting**: globaler Standard (60/Minute)

**Request-Body** — Klick-Typ (`type: "click"`):
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

**Request-Body** — Slider-Typ (`type: "slider"`):
```json
{
  "key": "def456abc789",
  "type": "slider",
  "clicks": 120
}
```

**Request-Body** — Rotations-Typ (`type: "rotate"`):
```json
{
  "key": "ghi789abc012",
  "type": "rotate",
  "clicks": 315
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| key | string | ja | Captcha-Key, von generate zurückgegeben |
| type | string | ja | Captcha-Typ, muss mit dem von generate zurückgegebenen `type` übereinstimmen |
| clicks | variiert | ja | Antwortdaten, Format variiert je nach type (siehe unten) |

**`clicks` nach Typ**:

| type | clicks-Typ | Beschreibung | Fehlertoleranz |
|------|------------|------|---------|
| click | `[{x:int, y:int}]` | Array von Klick-Koordinaten, in order-Reihenfolge | Radius 18px |
| slider | `int` | X-Achsen-Versatz des Sliders | ±4px |
| rotate | `int` | Rotationswinkel (0-359) | ±5° |

**Antwortbeispiel**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

Nach erfolgreicher Validierung schreibt das Backend `captcha_verified:{key}` in Redis (TTL 300s); der Login-Endpunkt lässt den Request daraufhin durch.
Bei fehlgeschlagener Validierung ist `code` 422, `message` lautet `"验证失败，请重试"` und `data.valid` ist `false`.

### 3.5 Login

```
POST /api/auth/login
```

- **Authentifizierung**: nicht erforderlich
- **Request-Header**: `API-Version: v1` (erforderlich)
- **Rate-Limiting**: 10/Minute (pro IP + Pfad)

**Request-Body**:
```json
{
  "username": "admin",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "captcha_key": "abc123def456"
}
```

| Feld | Typ | Pflicht | Validierungsregel | Beschreibung |
|------|------|------|---------|------|
| username | string | ja | min:3, max:50 | Benutzername |
| password | string | ja | min:6, max:32 (Klartext) | AES-256-CBC-HMAC-verschlüsselt und Base64-kodiert (Klartext kompatibel) |
| captcha_key | string | ja | | Captcha-Key (muss zuvor über `/api/captcha/verify` validiert worden sein) |

### Passwort-Verschlüsselungsprotokoll

Es wird **RSA-2048-asymmetrische Verschlüsselung** verwendet; der öffentliche Schlüssel liegt im Frontend-Code (kann sicher offengelegt werden), der private Schlüssel wird nur serverseitig gehalten.

```
Verschlüsselungsablauf (Client):
  RSA-Public-Key (PEM) → PKCS1v1.5-Verschlüsselung → Base64-Kodierung → Übertragung

Entschlüsselungsablauf (Server, stufenweises Fallback):
  1. RSA-Private-Key entschlüsseln → erfolgreich und gültiges UTF-8 → entschlüsseltes Ergebnis verwenden
  2. AES-256-CBC-HMAC entschlüsseln → erfolgreich → entschlüsseltes Ergebnis verwenden (Kompatibilität mit alten Clients)
  3. Klartext-Fallback → Originaleingabe direkt verwenden
```

Der öffentliche Schlüssel ist im Frontend eingebaut und muss nicht über das Netzwerk übertragen werden. Der private Schlüssel wird nur in `RSA_PRIVATE_KEY` der `.env` gespeichert und darf nicht preisgegeben werden.

> Die AES-symmetrische Verschlüsselung ist ein Kompatibilitätsansatz für alte Versionen und wird entfernt, sobald alle Clients auf RSA migriert sind.

**Antwortbeispiel**:
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

| Feld | Typ | Beschreibung |
|------|------|------|
| access_token | string | JWT-Zugriffstoken |
| refresh_token | string | JWT-Refresh-Token |
| expires_in | int | Gültigkeitsdauer des Zugriffstokens (Sekunden), Standard 7200 |
| user.id | string | hashid-verschlüsselte Benutzer-ID |
| user.username | string | Benutzername |
| user.real_name | string | Echter Name |

**Mögliche Fehler**:
- 422: Parametervalidierung fehlgeschlagen (Pflichtfelder fehlen, Format nicht korrekt)
- 422: Bitte zuerst die Captcha-Validierung abschließen (captcha_key nicht über `/api/captcha/verify` validiert)
- 401: Benutzername oder Passwort falsch
- 403: Konto wurde deaktiviert
- 429: Konto wurde gesperrt, bitte in 15 Minuten erneut versuchen (ausgelöst durch 5 aufeinanderfolgende Fehlversuche)

### 3.6 Registrierung

```
POST /api/auth/register
```

- **Authentifizierung**: nicht erforderlich
- **Request-Header**: `API-Version: v1` (erforderlich)
- **Rate-Limiting**: 5/Minute (pro IP + Pfad)

**Request-Body**:
```json
{
  "username": "newuser",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "real_name": "新用户",
  "captcha_key": "abc123def456"
}
```

| Feld | Typ | Pflicht | Validierungsregel | Beschreibung |
|------|------|------|---------|------|
| username | string | ja | min:3, max:50 | Benutzername (eindeutig) |
| password | string | ja | min:6, max:32 (Klartext) | AES-256-CBC-HMAC-verschlüsselt und Base64-kodiert |
| real_name | string | ja | max:50 | Echter Name |
| captcha_key | string | ja | | Captcha-Key (muss zuvor über `/api/captcha/verify` validiert worden sein) |

**Antwortbeispiel**:
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

Nach erfolgreicher Registrierung werden direkt die JWT-Tokens zurückgegeben; der Benutzerstatus ist standardmäßig aktiviert (status=1).

### 3.7 Token erneuern

```
POST /api/auth/refresh
```

- **Authentifizierung**: nicht erforderlich
- **Request-Header**: `API-Version: v1` (erforderlich)
- **Rate-Limiting**: globaler Standard (60/Minute)

**Request-Body**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| refresh_token | string | ja | Der bei Login/Registrierung erhaltene refresh_token |

**Antwortbeispiel**:
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

Bei erfolgreicher Erneuerung werden ein neuer access_token und refresh_token zurückgegeben; die alten Tokens verlieren automatisch ihre Gültigkeit. Bei der Erneuerung werden die letzte Login-Zeit und IP des Benutzers aktualisiert.

**Mögliche Fehler**:
- 422: Refresh-Token fehlt
- 401: Refresh-Token ungültig oder abgelaufen

### 3.8 Prometheus-Monitoring-Metriken

```
GET /metrics
```

- **Authentifizierung**: nicht erforderlich
- **Rate-Limiting**: keines
- **Antwortformat**: Prometheus text format (`text/plain; version=0.0.4`)

Öffentlicher Prometheus-Monitoring-Endpunkt zum Scraping durch Grafana/Prometheus.

**Antwortbeispiel**:
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

| Metrikname | Typ | Beschreibung |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Kumulierte Gesamtzahl der HTTP-Requests |
| `openadmin_active_users` | gauge | Aktuell aktive Benutzer (Login innerhalb von 24 Stunden) |
| `openadmin_db_connection_status` | gauge | Datenbank-Verbindungsstatus, 1=normal, 0=Fehler |
| `openadmin_redis_connection_status` | gauge | Redis-Verbindungsstatus, 1=normal, 0=Fehler |
| `openadmin_memory_usage_bytes` | gauge | Aktuelle Speichernutzung des PHP-Prozesses (bytes) |

## 4. Dashboard

Alle Admin-Endpunkte sind unter der Gruppe `/admin` gemountet und durchlaufen drei Middleware: `AdminAuth` (JWT-Authentifizierung), `AdminPermission` (RBAC-Berechtigungsprüfung) und `OperationLog` (Aktionsaufzeichnung).

### 4.1 Dashboard-Daten

```
GET /admin/dashboard
```

- **Authentifizierung**: JWT + RBAC
- **Cache**: Redis 5 Minuten

**Antwortbeispiel**:
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

| stats-Feld | Typ | Beschreibung |
|------|------|------|
| label | string | Metrikname |
| value | string | Metrikwert (String-Typ) |
| icon | string | Material-Icon-Name |
| color | string | Karten-Farbwert |
| trend | float? | Tagesvergleichs-Wachstumsrate (Prozent); nur das Feld „用户总数" enthält dieses Feld |

| trends-Feld | Typ | Beschreibung |
|------|------|------|
| dates | array{string} | Datumssequenz der letzten 30 Tage |
| series | array{object} | Trendliniendaten; jede Linie enthält name (Name), data (Wertearray), color (Farbe) |

## 5. Benutzerverwaltung

Alle `id`-Felder der Benutzerverwaltungs-Endpunkte sind hashid-verschlüsselte Zeichenketten. Passwortfelder werden aus den Antworten ausgeschlossen. Handynummer und E-Mail werden in Listen-Endpunkten maskiert dargestellt und in Detail-Endpunkten im Klartext zurückgegeben (verschlüsselte Datenbankfelder werden vom Encryptable-Trait automatisch entschlüsselt).

### 5.1 Benutzerliste

```
GET /admin/user
```

- **Authentifizierung**: JWT + RBAC

**Abfrageparameter**:

| Parameter | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| page | int | nein | 1 | Seitennummer |
| limit | int | nein | 15 | Anzahl pro Seite |
| keyword | string | nein | | Suchbegriff, matcht Benutzernamen und echten Namen |
| status | int | nein | | Statusfilter, 0=deaktiviert, 1=aktiviert |

**Antwortbeispiel**:
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

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | hashid-verschlüsselte Benutzer-ID |
| username | string | Benutzername |
| real_name | string | Echter Name |
| phone | string | Maskierte Handynummer (`138****5678`-Format) |
| email | string | Maskierte E-Mail (`a***@example.com`-Format) |
| status | int | 1=aktiviert, 0=deaktiviert |
| last_login_at | string | Letzte Login-Zeit (datetime) |
| created_at | string | Erstellzeit (datetime) |

### 5.2 Benutzer erstellen

```
POST /admin/user
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
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

| Feld | Typ | Pflicht | Validierungsregel | Beschreibung |
|------|------|------|---------|------|
| username | string | ja | min:3, max:50 | Benutzername (eindeutig) |
| password | string | ja | min:6, max:32 | Passwort (bcrypt-Speicherung) |
| real_name | string | ja | max:50 | Echter Name |
| phone | string | nein | | Handynummer (verschlüsselt per Encryptable gespeichert) |
| email | string | nein | | E-Mail (verschlüsselt per Encryptable gespeichert) |
| status | int | nein | in:0,1 | Status, Standard 1 (aktiviert) |

**Antwortbeispiel**:
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

**Mögliche Fehler**:
- 422: Benutzername existiert bereits
- 422: Parametervalidierung fehlgeschlagen (Pflichtfelder fehlen)

### 5.3 Benutzerdetails

```
GET /admin/user/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Pfadparameter**: `{id}` ist die hashid-verschlüsselte Benutzer-ID

**Antwortbeispiel**:
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

Im Detail-Endpunkt werden `phone` und `email` im Klartext zurückgegeben (in der Datenbank verschlüsselt gespeichert, automatisch entschlüsselt über den Encryptable-Cast), ohne Maskierung. `password` und `id_card` erscheinen nie in Antworten.

**Mögliche Fehler**:
- 404: Benutzer existiert nicht

### 5.4 Benutzer aktualisieren

```
PUT /admin/user/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Pfadparameter**: `{id}` ist die hashid-verschlüsselte Benutzer-ID

**Request-Body**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| real_name | string | nein | Echter Name; wenn nicht übergeben, bleibt der bisherige Wert |
| password | string | nein | Neues Passwort; leere Zeichenkette oder weggelassen bedeutet keine Änderung |
| phone | string | nein | Handynummer |
| email | string | nein | E-Mail |
| status | int | nein | 0=deaktiviert, 1=aktiviert |

**Antwortbeispiel**:
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

**Mögliche Fehler**:
- 404: Benutzer existiert nicht

### 5.5 Benutzer löschen

```
DELETE /admin/user/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Pfadparameter**: `{id}` ist die hashid-verschlüsselte Benutzer-ID
- **Sensitive Operation**: erfordert doppelte Passwortbestätigung

**Request-Body**:
```json
{
  "password": "admin_password"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| password | string | ja | Passwort des aktuell angemeldeten Benutzers (doppelte Bestätigung) |

**Antwortbeispiel**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Es wird ein Soft Delete ausgeführt (Eloquent SoftDeletes); die Daten werden mit deleted_at markiert, aber nicht physisch gelöscht.

**Mögliche Fehler**:
- 404: Benutzer existiert nicht
- 422: Sensitive Operation erfordert Passworteingabe zur Bestätigung (password leer)
- 422: Passwortvalidierung fehlgeschlagen (Passwort stimmt nicht überein)

### 5.6 Benutzer massenhaft löschen

```
POST /admin/user/batch/destroy
```

- **Authentifizierung**: JWT + RBAC
- **Sensitive Operation**: erfordert doppelte Passwortbestätigung

**Request-Body**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| ids | array{string} | ja | Array der hashid-verschlüsselten Benutzer-IDs |
| password | string | ja | Passwort des aktuell angemeldeten Benutzers (doppelte Bestätigung) |

**Antwortbeispiel**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Es wird ein Soft Delete ausgeführt; `data.count` ist die tatsächlich gelöschte Anzahl.

**Mögliche Fehler**:
- 422: Bitte wählen Sie zu löschende Benutzer aus (ids leer)
- 422: Ungültige ID (hashid-Dekodierung fehlgeschlagen)
- 422: Passwortvalidierung fehlgeschlagen

### 5.7 Benutzer massenhaft aktivieren/deaktivieren

```
POST /admin/user/batch/status
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| ids | array{string} | ja | Array der hashid-verschlüsselten Benutzer-IDs |
| status | int | ja | 0=deaktiviert, 1=aktiviert |

**Antwortbeispiel**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

Die message ändert sich dynamisch je nach status-Wert zu `"批量启用成功"` oder `"批量禁用成功"`.

**Mögliche Fehler**:
- 422: Bitte wählen Sie Benutzer aus (ids leer)
- 422: Ungültiger Statuswert (status ist nicht 0 oder 1)

## 6. Rollenverwaltung

### 6.1 Rollenliste

```
GET /admin/role
```

- **Authentifizierung**: JWT + RBAC

**Abfrageparameter**:

| Parameter | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| page | int | nein | 1 | Seitennummer |
| limit | int | nein | 15 | Anzahl pro Seite |

**Antwortbeispiel**:
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

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | hashid-verschlüsselte Rollen-ID |
| name | string | Rollenname |
| slug | string | Rollen-Kennung (eindeutig, für die Berechtigungsprüfung) |
| description | string | Rollenbeschreibung |
| status | int | 1=aktiviert, 0=deaktiviert |
| users_count | int | Anzahl der Benutzer mit dieser Rolle |

### 6.2 Rolle erstellen

```
POST /admin/role
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Feld | Typ | Pflicht | Validierungsregel | Beschreibung |
|------|------|------|---------|------|
| name | string | ja | max:50 | Rollenname |
| slug | string | ja | max:50 | Rollen-Kennung |
| description | string | nein | | Rollenbeschreibung, Standard leere Zeichenkette |
| status | int | nein | | Status, Standard 1 |
| permission_ids | array{int} | nein | | Array der Berechtigungs-IDs (Original-INT-IDs, keine hashids) |

**Antwortbeispiel**:
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

### 6.3 Rolle aktualisieren

```
PUT /admin/role/{id}
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| name | string | nein | Rollenname |
| description | string | nein | Beschreibung |
| status | int | nein | 0=deaktiviert, 1=aktiviert |
| permission_ids | array{int} | nein | Array der Berechtigungs-IDs; bei Übergabe werden die Rollenberechtigungen synchronisiert (überschrieben) |

**Antwortbeispiel**:
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

### 6.4 Rolle löschen

```
DELETE /admin/role/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Sensitive Operation**: erfordert doppelte Passwortbestätigung

**Request-Body**:
```json
{
  "password": "admin_password"
}
```

**Antwortbeispiel**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Beim Löschen werden automatisch alle Zuordnungen der Rolle zu Berechtigungen und Benutzern gelöst und der Rollendatensatz anschließend physisch gelöscht.

## 7. Berechtigungsverwaltung

Berechtigungen sind als Baumstruktur organisiert (parent_id-Selbstreferenz) und in drei Typen unterteilt. Der Listen-Endpunkt liefert den vollständigen Berechtigungsbaum.

### 7.1 Berechtigungsbaum

```
GET /admin/permission
```

- **Authentifizierung**: JWT + RBAC

**Antwortbeispiel**:
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

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | hashid-verschlüsselt |
| parent_id | string | hashid der übergeordneten Berechtigung, "0" bedeutet Wurzelknoten |
| name | string | Berechtigungsname |
| slug | string | Berechtigungs-Kennung (Route/Button-Kennung) |
| type | int | 1=Menü, 2=Button, 3=API |
| icon | string | Menü-Icon (Material-Icon-Name) |
| path | string | Frontend-Routing-Pfad |
| sort | int | Sortierwert (aufsteigend) |
| children | array? | Liste der Unterberechtigungen (rekursiv); ohne Unterknoten wird dieses Feld nicht enthalten |

### 7.2 Berechtigung erstellen

```
POST /admin/permission
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
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

| Feld | Typ | Pflicht | Validierungsregel | Beschreibung |
|------|------|------|---------|------|
| parent_id | int | nein | | ID der übergeordneten Berechtigung (Original-INT-Typ), Standard 0 |
| name | string | ja | max:50 | Berechtigungsname |
| slug | string | ja | max:100 | Berechtigungs-Kennung |
| type | int | ja | in:1,2,3 | 1=Menü, 2=Button, 3=API |
| icon | string | nein | | Menü-Icon, Standard leer |
| path | string | nein | | Frontend-Routing-Pfad, Standard leer |
| sort | int | nein | | Sortierwert, Standard 0 |

**Antwortbeispiel**:
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

### 7.3 Berechtigung aktualisieren

```
PUT /admin/permission/{id}
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| name | string | nein | Berechtigungsname |
| icon | string | nein | Icon |
| path | string | nein | Routing-Pfad |
| sort | int | nein | Sortierwert |

### 7.4 Berechtigung löschen

```
DELETE /admin/permission/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Sensitive Operation**: erfordert doppelte Passwortbestätigung

**Request-Body**:
```json
{
  "password": "admin_password"
}
```

**Antwortbeispiel**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Beim Löschen werden kaskadierend alle Unterberechtigungen gelöscht (Datensätze mit `parent_id` = ID der aktuellen Berechtigung) und gleichzeitig alle Zuordnungen zu Rollen gelöst.

## 8. Systemkonfiguration

Systemkonfigurationen sind durch die Kombination `group` + `key` eindeutig.

### 8.1 Konfigurationsliste

```
GET /admin/config
```

- **Authentifizierung**: JWT + RBAC

**Abfrageparameter**:

| Parameter | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| page | int | nein | 1 | Seitennummer |
| limit | int | nein | 15 | Anzahl pro Seite |
| group | string | nein | | Filter nach Konfigurationsgruppe |

**Antwortbeispiel**:
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

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | hashid |
| group | string | Konfigurationsgruppe (z. B. `system`, `email`, `storage`) |
| key | string | Konfigurationsschlüssel |
| value | string | Konfigurationswert |
| type | string | Werttyp-Hinweis (`string`, `integer`, `boolean`, `json` usw.) |
| description | string | Konfigurationsbeschreibung |

### 8.2 Konfiguration erstellen

```
POST /admin/config
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Feld | Typ | Pflicht | Validierungsregel | Beschreibung |
|------|------|------|---------|------|
| group | string | ja | max:100 | Konfigurationsgruppe |
| key | string | ja | max:100 | Konfigurationsschlüssel (eindeutig innerhalb der Gruppe) |
| value | string | ja | | Konfigurationswert |
| type | string | nein | | Werttyp, Standard `string` |
| description | string | nein | | Konfigurationsbeschreibung, Standard leer |

**Antwortbeispiel**:
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

**Mögliche Fehler**:
- 422: Konfiguration existiert bereits (gleiche group + key)

### 8.3 Konfiguration aktualisieren

```
PUT /admin/config/{id}
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| value | string | nein | Aktualisierter Konfigurationswert |
| type | string | nein | Aktualisierter Werttyp |
| description | string | nein | Aktualisierter Beschreibungstext |

### 8.4 Konfiguration löschen

```
DELETE /admin/config/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Sensitive Operation**: erfordert doppelte Passwortbestätigung

**Request-Body**:
```json
{
  "password": "admin_password"
}
```

Der Konfigurationsdatensatz wird physisch gelöscht.

## 9. Aktionsprotokoll

Das Aktionsprotokoll ist ein reiner Lese-Endpunkt; es wird von der `OperationLog`-Middleware bei jedem POST/PUT/DELETE-Request automatisch geschrieben. Die gespeicherten Felder umfassen `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Aktionsprotokoll-Liste

```
GET /admin/log
```

- **Authentifizierung**: JWT + RBAC

**Abfrageparameter**:

| Parameter | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| page | int | nein | 1 | Seitennummer |
| limit | int | nein | 15 | Anzahl pro Seite |
| user_id | int | nein | | Exakter Filter nach Benutzer-ID (Original-INT-Typ) |
| action | string | nein | | Exakter Filter nach Aktionsbezeichnung |
| path | string | nein | | Unscharfer Filter nach Request-Pfad |
| start_date | string | nein | | Startdatum (Y-m-d-Format) |
| end_date | string | nein | | Enddatum (Y-m-d-Format) |

**Antwortbeispiel**:
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

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | hashid |
| user_name | string | Benutzername des Ausführenden (über die user-Verknüpfung bezogen; bei nicht angemeldeten Aktionen wird „System" angezeigt) |
| action | string | Beschreibung der Aktionsoperation |
| method | string | HTTP-Methode (POST/PUT/DELETE) |
| path | string | Request-Pfad |
| ip | string | Client-IP |
| source | string | Request-Quelle |
| input | string | Request-Parameter als JSON-Zeichenkette (ohne Dateien) |
| created_at | string | Aktionszeit (datetime) |

## 10. Persönlicher Bereich

Die Endpunkte des persönlichen Bereichs benötigen nur JWT-Authentifizierung (keine RBAC-Berechtigungsprüfung — die `AdminPermission`-Middleware sollte sie in ihre Whitelist aufnehmen).

### 10.1 Persönliche Informationen aktualisieren

```
PUT /admin/profile
```

- **Authentifizierung**: JWT

**Request-Body**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| real_name | string | nein | Echter Name |
| phone | string | nein | Handynummer (verschlüsselt per Encryptable gespeichert) |
| email | string | nein | E-Mail (verschlüsselt per Encryptable gespeichert) |

**Antwortbeispiel**:
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

In der Antwort werden `phone` und `email` im Klartext zurückgegeben; `password` und `id_card` wurden entfernt.

### 10.2 Passwort ändern

```
PUT /admin/profile/password
```

- **Authentifizierung**: JWT

**Request-Body**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Feld | Typ | Pflicht | Validierungsregel | Beschreibung |
|------|------|------|---------|------|
| old_password | string | ja | | Aktuelles Passwort |
| new_password | string | ja | min:6, max:32 | Neues Passwort |

**Antwortbeispiel**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Mögliche Fehler**:
- 422: Bitte altes und neues Passwort angeben
- 422: Altes Passwort falsch
- 422: Neues Passwort muss 6-32 Zeichen lang sein

### 10.3 Abmelden

```
POST /admin/profile/logout
```

- **Authentifizierung**: JWT

**Request-Body**: keiner (kein requestBody, das Token wird aus dem Authorization-Header gelesen)

**Antwortbeispiel**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Logout-Logik: JWT dekodieren, die verbleibende Gültigkeitsdauer ermitteln (exp - now) und den md5-Hash dieses Tokens in die Redis-Blacklist `jwt_blacklist:{md5}` schreiben, TTL = verbleibende Gültigkeitsdauer. Tokens auf der Blacklist werden in der `AdminAuth`-Middleware abgefangen und liefern 401.

Ohne Token wird 401 zurückgegeben. Ein abgelaufenes/ungültiges Token (Dekodierung wirft eine Exception) wird dennoch als erfolgreicher Logout behandelt.

## 11. Import/Export

### 11.1 Excel exportieren

```
POST /admin/export/excel
```

- **Authentifizierung**: JWT + RBAC
- **Antworttyp**: Datei-Download (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Request-Body**:
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

| Feld | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| table | string | nein | `admin_user` | Zu exportierende Tabelle. Unterstützt: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | nein | | Array der zu exportierenden Spaltennamen; leer bedeutet alle Spalten der Tabelle |
| conditions | object | nein | `{}` | Filterbedingungen, key-value-Paare; nicht-leere Werte fließen in das WHERE ein |
| title | string | nein | `数据导出` | Excel-Titel (wird als Sheet-Name angezeigt) |

**Unterstützte Tabellen und Spalten**:

| table | Verfügbare Spalten |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Sensible Felder (`phone`, `email`, `id_card`) werden beim Export automatisch maskiert. Obergrenze: 10000 Zeilen. Erste Excel-Zeile eingefroren, Autofilter aktiviert.

### 11.2 PDF exportieren

```
POST /admin/export/pdf
```

- **Authentifizierung**: JWT + RBAC
- **Antworttyp**: Datei-Download (`application/pdf`, A4-Querformat)

**Request-Body**:
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

Oder im Tabellenmodus:
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

| Feld | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| type | string | nein | `table` | Exporttyp: `table` / `dashboard` |
| title | string | nein | `数据导出` | PDF-Titel |
| data | object | nein | `{}` | Exportdaten |

Bei `type=dashboard` muss `data` ein `stats`-Array enthalten (als Karten gerendert); bei `type=table` muss `data` die Arrays `columns` und `rows` enthalten.

Die PDF-Vorlage enthält Copyright-Informationen und einen Export-Zeitstempel.

### 11.3 Benutzer importieren (Excel)

```
POST /admin/import/users
```

- **Authentifizierung**: JWT + RBAC
- **Request-Typ**: `multipart/form-data` (Datei-Upload)

**Formularfelder**:

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| file | file | ja | Format `.xlsx` oder `.xls` |

**Excel-Spaltenanforderungen**:

| Spaltenname | Pflicht | Beschreibung |
|------|------|------|
| username | ja | Benutzername (eindeutig) |
| password | ja | Passwort (bcrypt-Hash gespeichert) |
| real_name | ja | Echter Name |
| phone | nein | Handynummer |
| email | nein | E-Mail |
| status | nein | Status, Standard 1 |

Zeile 1 ist der Spaltentitel (Groß-/Kleinschreibung egal), ab Zeile 2 folgen die Daten.

**Antwortbeispiel**:
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

| Feld | Typ | Beschreibung |
|------|------|------|
| total | int | Gesamtzahl der Zeilen (ohne Titelzeile) |
| success | int | Anzahl erfolgreich importierter Datensätze |
| failed | int | Anzahl fehlgeschlagener Datensätze |
| errors | array | Fehlerdetails; jeder Eintrag enthält row (Excel-Zeilennummer) und reason (Fehlergrund) |

## 12. Datei-Upload

```
POST /admin/upload
```

- **Authentifizierung**: JWT + RBAC
- **Request-Typ**: `multipart/form-data`

**Formularfelder**:

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| file | file | ja | Hochzuladende Datei |

**Erlaubte Dateitypen**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Maximale Dateigröße**: 10MB

**Antwortbeispiel**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Dateien werden nach Datum sortiert in `public/upload/{Y-m-d}/` gespeichert; der Dateiname ist `md5(uniqid) + ursprüngliche Dateiendung`. `url` ist ein relativer Pfad zur Site-Wurzel.

**Mögliche Fehler**:
- 422: Bitte eine Datei auswählen (keine hochgeladen)
- 422: Nicht unterstützter Dateityp
- 422: Dateigröße darf 10MB nicht überschreiten
- 500: Datei-Upload fehlgeschlagen (Datei ungültig)

## 13. Response-Header

Alle Endpunkte (über die globale Middleware-Ebene injiziert) enthalten die folgenden Response-Header:

| Header | Beschreibung |
|----|------|
| `X-RateLimit-Limit` | Rate-Limit-Obergrenze (Anzahl) |
| `X-RateLimit-Remaining` | Verbleibende Request-Anzahl |
| `X-RateLimit-Reset` | Zeitstempel des Rate-Limit-Fenster-Resets |
| `Retry-After` | Nur bei ausgelöstem Rate-Limit zurückgegeben, empfohlene Wartezeit in Sekunden |
| `X-Content-Type-Options` | `nosniff` (Standard von webman, verhindert MIME-Sniffing) |
| `X-Frame-Options` | `DENY` (bereitgestellt durch die CORS-Middleware/Basiskonfiguration von webman) |

Rate-Limiting-Details:
- Standard-Globallimit: 60/Minute / IP+Pfad
- Login-Endpunkt `/api/auth/login`: 10/Minute
- Registrierungs-Endpunkt `/api/auth/register`: 5/Minute
- Nutzung des atomaren Redis-Gleitfenster-Algorithmus (Lua ZSET), vermeidet TOCTOU-Race-Conditions
- Bei Redis-Ausfall fail-open (durchlassen), blockiert keine Requests

## 14. Authentifizierungsablauf

Vollständige Authentifizierungs-Sequenz:

```
1. Client ruft POST /api/captcha/generate auf
   (Request-Header: API-Version: v1)
    ↓
   Server liefert: key + type(click|slider|rotate) + base64-Bild + extra(typspezifische Daten)

2. Der Benutzer führt die Captcha-Operation aus (Klick/Ziehen/Drehen), der Client sammelt die Antwort

3. Client ruft POST /api/captcha/verify auf
   (Request-Header: API-Version: v1, Content-Type: application/json)
   Request-Body: { key, type, clicks }
   - type=click:  clicks = [{x, y}, ...]        // Koordinatenarray
   - type=slider: clicks = 120                   // X-Versatz
   - type=rotate: clicks = 315                   // Rotationswinkel
    ↓
   Server:
   a. captcha:key-Daten aus dem Speicher lesen (TTL 300s)
   b. Antwort je nach type validieren (click: euklidischer Abstand ≤18px / slider: ±4px / rotate: ±5°)
   c. Validierung bestanden → Redis `captcha_verified:{key}` = 1 schreiben (TTL 300s)
   d. Validierung fehlgeschlagen → 422 zurückgeben, Zähler +1, nach 3 Versuchen wird der Key ungültig
    ↓
   Server liefert: { valid: true/false }

4. Client ruft POST /api/auth/login auf
   (Request-Header: API-Version: v1, Content-Type: application/json)
   Request-Body: { username, password(verschlüsselt), captcha_key }
    ↓
   Server:
   a. Parametervalidierung → 422
   b. Prüfen, ob captcha_verified:{key} existiert → 422
   c. captcha_verified:{key} löschen (Einmalverwendung)
   d. Passwort entschlüsseln: EncryptionService::decrypt(password) → Klartext
   e. Benutzeranmeldedaten prüfen (password_verify) → 401
   f. Kontostatus prüfen → 403/429
   g. JWT ausstellen (access + refresh) → 200
   h. last_login_at / last_login_ip aktualisieren
    ↓
   Client speichert: access_token, refresh_token, expires_in

5. Nachfolgende Requests tragen das JWT
   Request-Header: Authorization: Bearer <access_token>
    ↓
   AdminAuth-Middleware:
   a. Bearer-Token extrahieren
   b. Blacklist prüfen (Redis jwt_blacklist:{md5}) → 401
   c. JWT dekodieren, Ablauf prüfen → 401
   d. $request->adminId = sub-Feld setzen
    ↓
   AdminPermission-Middleware:
   a. Berechtigungs-Kennung für die Ressourcenroute auflösen
   b. Benutzerrollen → Rollenberechtigungen abfragen und abgleichen
   c. Keine Berechtigung → 403
    ↓
   Controller verarbeitet den Request
    ↓
   Response + X-RateLimit-*-Header

6. Access-Token vor Ablauf erneuern
   Client ruft POST /api/auth/refresh auf
   Request-Body: { refresh_token: "..." }
    ↓
   Server dekodiert refresh_token → stellt neue access + refresh aus
    ↓
   Client aktualisiert lokale Tokens

7. Abmelden
   Client ruft POST /admin/profile/logout auf
   Request-Header: Authorization: Bearer <access_token>
    ↓
   Server:
   a. JWT dekodieren, verbleibende TTL ermitteln
   b. In Redis-Blacklist schreiben: jwt_blacklist:{md5(token)} = 1, TTL = verbleibende Gültigkeitsdauer
   c. Erfolg zurückgeben
```

### JWT-Struktur

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, Standard-TTL 7200 Sekunden (gesteuert durch die JWT-Konfiguration `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, Standard-TTL 1209600 Sekunden (gesteuert durch die JWT-Konfiguration `refresh_expire`, also 14 Tage)

### Sicherheitsverwaltung

- Passwörter werden als `PASSWORD_BCRYPT`-Hash gespeichert
- Die Passwortübertragung verwendet AES-256-CBC-HMAC-Verschlüsselung (Client verschlüsselt → Server entschlüsselt), kompatibel mit Klartext-Fallback
- Sensible Felder (phone, email, id_card) werden über `erikwang2013/encryptable` auf Datenbankebene transparent ver-/entschlüsselt
- IDs auf API-Ebene werden über `erikwang2013/hashids` verschlüsselt übertragen, wodurch die ursprüngliche Snowflake-ID-Sequenz nicht preisgegeben wird
- Der SecurityFilter scannt global auf XSS, SQL-Injection, Pfad-Traversal und Befehlsinjektion; bei 5 Treffern desselben IPs in 60 Sekunden temporäre Blacklist für 15 Minuten
- Sensitive Operationen (Löschen von Benutzern, Rollen, Berechtigungen, Konfigurationen) erfordern die doppelte Passwortbestätigung des aktuell angemeldeten Benutzers
- Begrenzung paralleler Sitzungen: maximal 3 gültige Tokens pro Benutzer; beim Login vom 4. Gerät wird das älteste Token zwangsweise auf die Blacklist gesetzt
- Kontosperrung: 5 aufeinanderfolgende Fehlversuche lösen eine 15-minütige Kontosperrung aus; während der Sperrung wird 429 zurückgegeben

### Middleware-Architektur

Globale Middleware wirkt auf alle Requests und wird in dieser Reihenfolge ausgeführt:

```
Cors (CORS-Vorverarbeitung + Response-Header)
  → Locale (Accept-Language-Spracherkennung / ?lang=zh_CN|en)
  → SecurityFilter (HTTP-Methodenlimitierung/Body-Größe/Content-Type-Prüfung/XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF-Angriffsblock)
  → RateLimit (Redis-Gleitfenster-Rate-Limiting + Kontosperrung: 5 Fehlversuche → 15 Min. Sperrung)
  → ApiVersion (API-Versionsprüfung, /api-Routengruppe)
  → AdminAuth (JWT-Authentifizierung + Blacklist, /admin-Routengruppe)
  → AdminPermission (RBAC-Autorisierung / Redis-60s-Cache, /admin-Routengruppe)
  → OperationLog (automatische Aufzeichnung von POST/PUT/DELETE, inkl. Quellenerkennung, /admin-Routengruppe)
```

`/health` und `/api/docs` sind öffentliche Endpunkte und durchlaufen nur `Cors → SecurityFilter → RateLimit`.

Sicherheitsverbesserungen:
- **Kontosperrung**: Nach 5 aufeinanderfolgenden fehlgeschlagenen Logins wird das Konto automatisch für 15 Minuten gesperrt; während der Sperrung liefert das Login 429
- **Begrenzung paralleler Sitzungen**: Maximal 3 gültige Tokens pro Benutzer; bei Überschreitung wird das älteste Token automatisch auf die Blacklist gesetzt
- **security.txt**: `GET /.well-known/security.txt` stellt die standardkonformen Sicherheitskontaktinformationen nach RFC 9116 bereit
- **Nginx-Sicherheitskonfiguration**: Siehe `docs/nginx-security.conf` für ein vollständiges Härtungsbeispiel des Reverse-Proxys

### Erkennung der Operationsquelle

Die OperationLog-Middleware erkennt automatisch die Client-Plattform und schreibt sie in das `source`-Feld des Aktionsprotokolls:

| Plattform | Erkennungsmethode |
|------|---------|
| `ipados` | UA enthält iPad |
| `macos` | UA enthält Macintosh/Mac OS |
| `windows` | UA enthält Windows |
| `linux` | UA enthält Linux (nicht Android) |
| `ios` | UA enthält iPhone / iOS / CFNetwork |
| `android` | UA enthält Android |
| `harmonyos` | UA enthält HarmonyOS / OpenHarmony oder explizit per `X-Client-Platform`-Header deklariert |
| `web` | Standard (keine der obigen Plattformen getroffen) |

> Zweistufige Erkennung: `X-Client-Platform`-Request-Header (Deklaration durch native Apps) → automatische User-Agent-Inferenz (Fallback). Das `source`-Feld der Aktionsprotokoll-Abfrage `GET /admin/log` ist die Quelle.

## 15. Deployment & Betrieb

### Docker Compose

Im Projektstammverzeichnis liegt `docker-compose.yml`, das 5 Dienste orchestriert (Nginx, webman app, MySQL, Redis, Elasticsearch). PHP wird über das `Dockerfile` gebaut (basiert auf `php:8.3-cli`, mit OPcache).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` definiert die GitHub-Actions-Continuous-Integration-Pipeline:
- `php -l`-Syntaxprüfung
- PHPUnit-Unit-Tests
- `flutter analyze`-Statische-Analyse

### Datenbank-Backup

Das Verzeichnis `database/backup/` stellt Backup- und Wiederherstellungsskripte bereit:
- `backup.sh` — mysqldump + gzip-komprimiertes Backup, löscht automatisch Backups älter als 30 Tage
- `restore.sh` — interaktive Wiederherstellung, listet vorhandene Backups zur Auswahl auf

### Nginx-Sicherheitskonfiguration

Für Produktions-Deployments siehe `docs/nginx-security.conf` zur Härtung des Reverse-Proxys.

## 16. Internes gRPC-Interface (e-cat → PHP worker)

Nur für den e-cat Query-Gateway. Auf PHP-Seite ist es ein workerman-h2c-Dienst (`tcp://0.0.0.0:8792`, Prozess `internal_grpc`). Der Vertrag ist in `infrastructure/tracking-gateway/proto/internal.proto` (package `internal.v1`) definiert, Nachrichten sind protobuf-kodiert und in den Metadata muss `x-internal-token` enthalten sein (Wert aus `config/logistics.php` — `internal_token`, in Produktion durch die Umgebungsvariable `INTERNAL_TOKEN` überschrieben). Fehlendes/ungültiges Token liefert `code=401 UNAUTHORIZED`.

### 16.1 RPC-Übersicht

| RPC | Anfrage | Antwort | Beschreibung |
|---|---|---|---|
| `Detect` | `{tracking_no}` | `{code, carrier_code, channel, confidence}` | Carrier-Erkennung; bei Nichterkennung `404 CARRIER_NOT_DETECTED` |
| `Query` | `{carrier_code, tracking_no, credential_id?}` | `{code, query_no, carrier_code, tracking_no, status, delivered_at, estimated_delivery_at, latest_description, raw_status, events[]}` | Tracking-Abfrage; `credential_id` optional (bei mehreren Zugangsdaten anzugeben, muss zu `carrier_code` passen, sonst `400 INVALID_PARAMS`). Jede Abfrage wird in `logistics_tracking_query` gespeichert |
| `Carriers` | `{}` | `{code, carriers[]}` | Alle Carrier des Registers `{carrier_code, channel}` |

### 16.2 Fehlervertrag (Feld `code` in der Antwort, gRPC-Status immer 0)

| `code` | `error_code` | Szenario |
|---|---|---|
| 400 | `INVALID_PARAMS` | Parameter fehlen / credential_id passt nicht |
| 401 | `UNAUTHORIZED` | Internes Token falsch |
| 404 | `CARRIER_NOT_FOUND` / `TRACKING_NOT_FOUND` / `CARRIER_NOT_DETECTED` | Carrier nicht registriert / Tracking nicht gefunden / nicht erkennbar |
| 5001 | `CARRIER_AUTH_ERROR` / `CARRIER_NETWORK_ERROR` / `CARRIER_ERROR` | Fehler bei den Zugangsdaten des Carriers / Netzwerkfehler / Geschäftsfehler des Carriers (inkl. strukturierter Upstream-Meldung, z. B. `[SF-INTERNATIONAL A1001] Pflichtparameter darf nicht leer sein`) |
| 500 | `INTERNAL_ERROR` | Interner Fehler |


## 17. Externe API des e-cat Query-Gateways

Hochfrequenz-Gateway in Rust (`infrastructure/tracking-gateway`), lauscht auf `0.0.0.0:8080` (Standard), Anfragen tragen den Header `X-API-Key` (Konfiguration `api_keys`). Das Gateway übernimmt API-Key-Authentifizierung, Redis-Cache-Treffer (Präfix `logistics:`), Rate Limiting, Circuit Breaker je Carrier und RoundRobin-Weiterleitung an die Worker; Zugangsdaten werden nie an das Gateway durchgereicht.

| 端点 | 说明 |
|---|---|
| `GET /v1/health` | Healthcheck (öffentlicher Endpunkt, kein Schlüssel nötig) |
| `GET /v1/openapi.json` | OpenAPI-3.0-Dokumentation (öffentlicher Endpunkt, kein Schlüssel nötig) |
| `GET /v1/carriers` | Carrier-Liste (weitergeleitet an PHP gRPC `InternalService.Carriers`) |
| `POST /v1/tracking/query` | Tracking-Abfrage (`{"carrier_code": "...", "tracking_no": "..."}`); ohne `carrier_code` erkennt das Gateway automatisch (detect läuft innerhalb von query) |
| `GET /v1/tracking/{query_no}` | Letztes Tracking-Ergebnis per Abfragenummer |
| `POST /v1/subscriptions` | Callback-Abo registrieren (`{"carrier_code", "callback_url", "event_type"?}`, `event_type` Standard `tracking.update`) |


Zusätzliche Gateway-Semantik: Überschreitung von `rate_limit` (Standard 100/60s) liefert `429`; wiederholte Carrier-Fehler lösen den Circuit Breaker aus (Standard OPEN 60s nach 5x 5xx/Transportfehlern) → `503`; Worker nicht erreichbar → `502`. Gateway↔Worker ist internes gRPC (h2c, `0.0.0.0:8792`), authentifiziert mit dem gemeinsamen Geheimnis `x-internal-token`, siehe Abschnitt 16.

### 17.1 Antwort-Envelope und Fehler

Alle Endpunkte liefern den einheitlichen Envelope `{"code": 0, "message": "ok", "data": ...}`; `code != 0` gilt als Fehler, `data` entfällt. Business-Fehler können `error_code` / `error_message` tragen (Diagnose des Carriers, vom Gateway durchgereicht); `code` entspricht dem HTTP-Status:

| HTTP | Bedeutung |
|---|---|
| 400 | Parameterfehler (`tracking_no` fehlt / ungültige Abo-Parameter) |
| 401 | Schlüssel fehlt oder ungültig |
| 404 | Abfragenummer nicht vorhanden |
| 429 | Rate limit (Standard 100/60s je Schlüssel), Backoff und erneut versuchen |
| 502 | Worker nicht erreichbar oder Upstream-Fehler |
| 503 | Carrier Circuit Broken, Backoff und erneut versuchen |

### 17.2 Aufrufbeispiele (curl)

Demo-Schlüssel `demo-api-key`, Gateway-Standard `http://127.0.0.1:8080`:

```bash
# Healthcheck (öffentlicher Endpunkt, kein Schlüssel nötig)
curl http://127.0.0.1:8080/v1/health

# OpenAPI-Dokumentation (öffentlicher Endpunkt, kein Schlüssel nötig)
curl http://127.0.0.1:8080/v1/openapi.json

# Carrier-Liste
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/carriers

# Tracking-Abfrage (Carrier optional angeben)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "tracking_no": "LX123456789CN"}' \
  http://127.0.0.1:8080/v1/tracking/query

# Letztes Ergebnis per Abfragenummer
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/tracking/{query_no}

# Callback-Abo registrieren (Callback-Adresse muss öffentliches http/https sein; das Gateway lehnt Loopback/private Netze ab)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "callback_url": "https://example.com/cb", "event_type": "tracking.update"}' \
  http://127.0.0.1:8080/v1/subscriptions
```

### 17.3 Aufrufbeispiele (SDK)

Fünf dependenciesfreie SDKs liegen im Verzeichnis `sdk/` des Repos (Python / PHP / Node.js / Go / Rust, kopieren und loslegen):

```python
from tracking_client import TrackingClient
client = TrackingClient("demo-api-key", "http://127.0.0.1:8080")
result = client.query_tracking("LX123456789CN")
print(result["status"], result["events"][0]["description"])
```

```php
require "TrackingClient.php";
$client = new TrackingClient("demo-api-key", "http://127.0.0.1:8080");
$carriers = $client->list_carriers();
$sub = $client->subscribe("china-post", "https://example.com/cb");
```

```js
const { TrackingClient } = require("./tracking-client.js");
const client = new TrackingClient("demo-api-key", "http://127.0.0.1:8080");
const detail = await client.getTracking("query_no");
console.log(detail.status);
```

```go
import "trackingclient"
client := trackingclient.NewClient("demo-api-key", "http://127.0.0.1:8080")
raw, err := client.QueryTracking("LX123456789CN", "china-post")
if err != nil { panic(err) }
fmt.Println(string(raw))
```

```rust
use trackingclient::tracking_client::TrackingClient;
let client = TrackingClient::new("demo-api-key", "http://127.0.0.1:8080")?;
let detail = client.get_tracking("query_no")?;
println!("{}", detail);
```

Die SDKs werfen bei `code != 0` im Envelope oder Netzwerkfehlern eine Exception (`TrackingApiError`) mit `code` / `message` / `error_code` / `error_message` / `http_status`; bei Rate-Limit- und Circuit-Breaker-Fehlern mit Backoff erneut versuchen, Carrier-seitige Fehler nicht wiederholen. Vollständige Nutzung siehe `sdk/README.md`.
