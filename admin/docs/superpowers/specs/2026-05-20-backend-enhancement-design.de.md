> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-20-backend-enhancement-design.md) | [English](2026-05-20-backend-enhancement-design.en.md) | [한국어](2026-05-20-backend-enhancement-design.ko.md) | [Русский](2026-05-20-backend-enhancement-design.ru.md) | [Deutsch](2026-05-20-backend-enhancement-design.de.md) | [Français](2026-05-20-backend-enhancement-design.fr.md) | [Español](2026-05-20-backend-enhancement-design.es.md) | [Português](2026-05-20-backend-enhancement-design.pt.md) | [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) | [العربية](2026-05-20-backend-enhancement-design.ar.md) | [বাংলা](2026-05-20-backend-enhancement-design.bn.md) | [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) | [日本語](2026-05-20-backend-enhancement-design.ja.md)

# Teilprojekt A: Backend-Erweiterung – Designvorgaben

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Umfang

Dies ist eine Backend-Erweiterung mit insgesamt 15 Funktionspunkten: 9 neue Dateien + 4 geänderte Dateien.

---

## Liste neuer/geänderter Dateien

```
app/middleware/
├── OperationLog.php          # Neu: automatische Aufzeichnung von Operationsprotokollen
├── Cors.php                  # Neu: CORS
└── RateLimit.php             # Neu: Redis-Ratenbegrenzung
app/admin/controller/
├── ConfigController.php      # Neu: Systemkonfigurations-CRUD
├── LogController.php         # Neu: Abfrage der Operationsprotokolle
├── ProfileController.php     # Neu: Persönlicher Bereich (inkl. Logout)
├── UploadController.php      # Neu: Datei-Upload
├── ImportController.php      # Neu: Excel-Import von Benutzern
└── HealthController.php      # Neu: Health Check
app/model/
├── AdminUser.php             # Geändert: SoftDeletes- + Searchable-Trait hinzugefügt
└── OperationLog.php          # Geändert: public $timestamps = false hinzugefügt
app/middleware/
└── AdminAuth.php             # Geändert: JWT-Blacklist-Prüfung
app/admin/controller/
├── DashboardController.php   # Geändert: auf Echtzeitstatistik aus der Datenbank umgestellt
└── UserController.php        # Geändert: Stapelverarbeitungsaktionen hinzugefügt
config/
└── route.php                 # Geändert: neue Routen + Middleware
```

---

## 1. Middleware

### 1.1 CORS-Middleware

**Datei**: `app/middleware/Cors.php`

- OPTIONS-Preflight-Anfragen geben direkt 204 zurück
- Bei Nicht-Preflight-Anfragen wird `Access-Control-Allow-Origin: *` an die Response-Header angehängt
- Erlaubte Header: `Authorization, Content-Type, API-Version`
- Maximale Cache-Dauer: 86400 Sekunden

Montage: globale Middleware (`config/middleware.php`)

### 1.2 Ratenbegrenzungs-Middleware

**Datei**: `app/middleware/RateLimit.php`

- Speicherung: Redis Sorted Set (gleitendes Fenster)
- Standard: 60 Anfragen/Minute/IP/Route
- Sensible Schnittstellen:
  - `/api/auth/login`: 10 Anfragen/Minute
  - `/api/auth/register`: 5 Anfragen/Minute
- Bei Überschreitung wird `429 Too Many Requests` zurückgegeben

Montage: globale Middleware (`config/middleware.php`), nach Cors und vor ApiVersion

### 1.3 Operationsprotokoll-Middleware

**Datei**: `app/middleware/OperationLog.php`

- Protokolliert nur POST/PUT/DELETE
- Protokollierte Felder: user_id, action, method, path, ip, input (JSON)
- Asynchrones Schreiben nach der Antwort (nicht blockierend)

Montage: Routengruppe `/admin`, nach AdminPermission

### 1.4 Globale Middleware-Kette

```
Alle Anfragen:
  Cors → RateLimit → ApiVersion → {Route-Middleware} → Controller

/admin/*-Anfragen:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Logout (JWT-Blacklist)

**Datei**: `app/middleware/AdminAuth.php` (geändert)

**Prinzip**: JWT ist zustandslos; beim Logout wird das Token zur Redis-Blacklist hinzugefügt, und AdminAuth prüft bei der Validierung zuerst die Blacklist.

**AdminAuth-Umbau**:
- Neu am Anfang von `process()`: prüfen, ob das aktuelle Token in der Redis-Sammlung `jwt_blacklist` enthalten ist
- Bei Treffer in der Blacklist wird 401 zurückgegeben

**Logout-Route** (im persönlichen Bereich):

| Methode | Route | Beschreibung |
|------|------|------|
| `POST` | `/admin/profile/logout` | Fügt das aktuelle Bearer-Token zur Redis-Blacklist hinzu, TTL = verbleibende Token-Gültigkeit |

**Logout-Logik**:
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Neue Controller und Umbauten bestehender

### 2.1 Systemkonfigurations-CRUD (`ConfigController`)

Erbt von `BaseController`.

| Methode | Route | Beschreibung |
|------|------|------|
| `index()` | GET `/admin/config` | Seitierte Liste, filterbar nach `group`, paginiert mit `page`/`limit` |
| `store()` | POST `/admin/config` | Konfigurationspunkt anlegen; Pflichtfelder: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Aktualisiert value/type/description des Konfigurationspunkts |
| `destroy()` | DELETE `/admin/config/{id}` | Löscht den Konfigurationspunkt; erfordert `confirmPassword()` |

### 2.2 Abfrage der Operationsprotokolle (`LogController`)

Erbt von `BaseController`.

| Methode | Route | Beschreibung |
|------|------|------|
| `index()` | GET `/admin/log` | Seitierte Liste, filterbar nach: user_id, action, path, created_at (Zeitraum) |

Keine Erstell-/Lösch-/Änderungsfunktionen; die Protokolle werden automatisch von der Middleware aufgezeichnet.

### 2.3 Persönlicher Bereich (`ProfileController`)

Erbt von `BaseController`. Operiert auf dem aktuell angemeldeten Benutzer (`$request->adminId`).

| Methode | Route | Beschreibung |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Aktualisiert real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Passwort ändern; erforderlich: old_password, new_password, new_password_confirmation |

### 2.4 Datei-Upload (`UploadController`)

Erbt von `BaseController`.

| Methode | Route | Beschreibung |
|------|------|------|
| `upload()` | POST `/admin/upload` | Nimmt Dateien entgegen; unterstützt image/jpeg/png/gif/pdf/xlsx/docx |

- Maximal 10 MB
- Speicherpfad: `public/upload/{date}/{hash}.{ext}`
- Rückgabe: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Dashboard mit echten Daten

**Datei**: `app/admin/controller/DashboardController.php` (geändert)

Die derzeit hartkodierten Beispieldaten durch Echtzeitstatistiken aus der Datenbank ersetzen:

| Metrik | Quelle | Beschreibung |
|------|------|------|
| Benutzer gesamt | `AdminUser::count()` | ohne Soft-Deleted |
| Heute neu | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Rollen gesamt | `AdminRole::count()` | |
| Berechtigungen gesamt | `AdminPermission::count()` | |
| Trenddaten | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Neuzugänge der letzten 7 Tage, tagesweise |
| Verteilungsdaten | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Verteilung nach Status |
| Letzte Aktionen | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | Die 10 neuesten Operationsprotokolle |

### 2.6 Benutzer-Stapeloperationen

**Datei**: `app/admin/controller/UserController.php` (geändert, neue Methoden)

| Methode | Route | Beschreibung |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Stapelweises Löschen; Request-Body `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Stapelweises Aktivieren/Deaktivieren; Request-Body `{ ids: [hashid, ...], status: 1|0 }` |

- Jede id wird zuerst per `decodeId()` in BIGINT umgewandelt
- `batchDestroy()` muss über `confirmPassword()` validiert werden

### 2.7 Datenimport

**Datei**: `app/admin/controller/ImportController.php` (neu)

| Methode | Route | Beschreibung |
|------|------|------|
| `users()` | POST `/admin/import/users` | Excel-Datei hochladen und Benutzer stapelweise anlegen |

Ablauf:
1. `.xlsx`-Datei entgegennehmen
2. Mit PhpSpreadsheet parsen; erwartete Spalten: `username, password, real_name, phone, email, status`
3. Zeilenweise validieren und anlegen (snowflake erzeugt IDs, bcrypt für Passwörter, encryption verschlüsselt phone/email)
4. Ergebnis zurückgeben: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Health Check

**Datei**: `app/admin/controller/HealthController.php` (neu)

`GET /health` (ohne Authentifizierung, wird nicht in den Operationsprotokollen erfasst):

Gibt den Verbindungsstatus aller Komponenten zurück:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- Bei fehlgeschlagener Komponentenprüfung enthält das jeweilige Feld eine Fehlerbeschreibung
- Die Route hat kein `/admin`-Präfix und ist global separat registriert

---

## 3. Modellkorrekturen

### 3.1 OperationLog-Zeitstempel

**Datei**: `app/model/OperationLog.php` (geändert)

Die Tabelle `erik_operation_log` hat nur die Spalte `created_at` (kein `updated_at`). Eloquent versucht bei `save()` standardmäßig, `updated_at` zu schreiben, was einen SQL-Fehler verursacht.

Fix: `public $timestamps = false;` + `created_at` beim Schreiben manuell setzen.

### 3.2 AdminUser-Modellumbau

- `Searchable`-Trait hinzufügen
- `toSearchableArray()` implementieren: gibt username, real_name zurück
- `UserController::index()` verwendet bei erkanntem Schlüsselwort `AdminUser::search($kw)->get()` statt MySQL LIKE

Für ES muss zuerst ein Index angelegt werden, z. B. mit den Scout-Befehlen:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Routenänderungen

Neue Routen in `config/route.php`:

```php
// /admin 路由组内新增:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// 健康检查（全局路由，非 /admin 组内）
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// 中间件:
/admin 组中间件追加 app\middleware\OperationLog::class
```

Globale Middleware in `config/middleware.php` registrieren:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Ergänzte Fehlercodes

| Code | Bedeutung | Auslöser |
|------|------|---------|
| 429 | Zu viele Anfragen | von RateLimit ausgelöst |

---

## 6. Außerhalb des Umfangs dieser Ausbaustufe

- Benachrichtigungssystem (erfordert Message Queue + Push-Infrastruktur im Frontend)
- Flutter-Frontend-Seiten (Teilprojekt B)
- HarmonyOS-Token-Refresh (Teilprojekt C)
