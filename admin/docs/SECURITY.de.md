> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](SECURITY.md) | [English](SECURITY.en.md) | [한국어](SECURITY.ko.md) | [Русский](SECURITY.ru.md) | [Deutsch](SECURITY.de.md) | [Français](SECURITY.fr.md) | [Español](SECURITY.es.md) | [Português](SECURITY.pt.md) | [हिन्दी](SECURITY.hi.md) | [العربية](SECURITY.ar.md) | [বাংলা](SECURITY.bn.md) | [Bahasa Indonesia](SECURITY.id.md) | [日本語](SECURITY.ja.md)

# Dokument zur Sicherheitsarchitektur

## 1. Panorama der Tiefenverteidigung

Das System nutzt ein 7-stufiges Tiefenverteidigungsmodell, das böswillige Requests von außen nach innen schichtweise filtert und sicherstellt, dass bei Ausfall einer beliebigen Schicht weiterhin nachgelagerte Verteidigungslinien greifen.

Die gesamte Middleware-Kette wird in der folgenden Reihenfolge ausgeführt (siehe `config/middleware.php`):

```
Request → Cors → Locale(Accept-Language) → SecurityMiddleware (erikwang2013/security-php, 31 Detektoren) → RateLimit → [Routengruppen-Middleware: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Ebene | Middleware/Mechanismus | Schutzziele |
|----|--------|---------|
| 1 | SecurityMiddleware (erikwang2013/security-php) | 31 Angriffsdetektoren + HTTP-Methodenprüfung + Request-Body-Größenbegrenzung + Content-Type-Prüfung + CSRF + IP-Angriffs-Eskalations-Blacklist |
| 2 | Cors | Cross-Origin-Sicherheit + Injektion von Sicherheits-Response-Headern |
| 3 | RateLimit | Redis-Gleitfenster-Rate-Limiting, verhindert Brute-Force |
| 4 | AdminAuth | JWT-Authentifizierung + Blacklist-Logout |
| 5 | AdminPermission | RBAC-Berechtigung mit method.path-Granularität |
| 6 | OperationLog | Aktions-Audit + Quellenverfolgung |
| 7 | Datenverschlüsselung | Hashids-ID-Verschleierung + Encryptable-DB-Verschlüsselung + EncryptionService-Übertragungsverschlüsselung |

Das Frontend (Flutter) besitzt drei zusätzliche unabhängige Eingabevalidierungen; das Backend vertraut diesen nicht. Jede Schicht verteidigt unabhängig.

---

## 2. Angriffserkennungs-Engine

## 2. Angriffserkennungs-Engine (erikwang2013/security-php)

Die Angriffserkennung wurde von der selbst entwickelten SecurityMiddleware (erikwang2013/security-php) auf das dedizierte Sicherheitspaket `erikwang2013/security-php` v1.1+ migriert und bietet **31 Detektoren**, die 5 große Angriffskategorien abdecken.

### 2.1 Detektorkategorien

**Injektionsangriffe (11):** XSS, SQL-Injection, Befehlsinjektion, NoSQL-Injection, LDAP-Injection, XPath-Injection, JNDI/Log4Shell, SSI-Server-Side-Includes, GraphQL-Injection, SSTI-Template-Injection

**Protokoll- und Request-Angriffe (9):** SSRF, XXE, HTTP-Response-Header-Injection, Host-Header-Angriff, Request Smuggling, Open Redirect, CORS-Bypass, WebSocket-Hijacking, DNS Rebinding

**HTTP-Protokollprüfung (6):** HTTP-Methodenprüfung (405), Request-Body-Größenbegrenzung (413), Content-Type-Prüfung (415), CSRF-Origin-Check, IP-Angriffs-Eskalations-Blacklist, Erkennung von Datenlecks

**Daten- und Serialisierungsangriffe (5):** PHP-Deserialisierung, CSV-Formel-Injection, E-Mail-Header-Injection, JWT-Angriffe (strukturierte Analyse), JS Prototype Pollution

**Datei- und Pfadangriffe (2):** Pfad-Traversal, bösartige Datei-Uploads

### 2.2 Behandlungsmodi

Jeder Detektor unterstützt unabhängig zwei Modi:
- `block` — bei erkannter Attacke sofort blockieren und den konfigurierten Statuscode zurückgeben
- `log` — nur protokollieren, nicht blockieren (`header_injection`, `ssti`, `nosql_injection` verwenden standardmäßig den log-Modus, um Fehlalarme zu vermeiden)

### 2.3 IP-Angriffs-Eskalations-Blacklist

Löst dieselbe IP innerhalb von 60 Sekunden 5 Angriffserkennungen aus → automatische Sperrung für 15 Minuten. Als Speicher-Backend sind Redis (verteilt), File (einzelne JSON-Datei) oder Cache (unabhängige Datei für hohe Parallelität) wählbar; aktuell ist Redis konfiguriert.

### 2.4 Sicherheitsprotokoll

Dateiposition: `runtime/logs/security.log` (automatische Rotation, 10MB/Datei)

---

## 4. Sicherheits-Response-Header

Alle Header werden in der `Cors`-Middleware injiziert und über `$response->withHeaders()` an jede Antwort angehängt.

| Header | Wert | Zweck |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Beliebige Origin für Cross-Origin erlauben (Intranet-Admin-Szenario) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Erlaubte Methoden |
| Access-Control-Allow-Headers | `Authorization,Content-Type` | Erlaubte benutzerdefinierte Header |
| Access-Control-Max-Age | `86400` | Preflight-Request-Cache 24 Stunden |
| X-Content-Type-Options | `nosniff` | Browser-MIME-Sniffing verbieten |
| X-Frame-Options | `DENY` | Alle iframe-Einbettungen verbieten, verhindert Clickjacking |
| X-XSS-Protection | `1; mode=block` | Integrierten Browser-XSS-Filter aktivieren und Seitenrendering blockieren |
| Referrer-Policy | `strict-origin-when-cross-origin` | Gleich Ursprung: volle URL, cross-origin nur die Domain |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Kamera-/Mikrofon-/Standort-API site-weit deaktivieren |

OPTIONS-Preflight-Requests liefern direkt eine leere 204-Antwort und durchlaufen die nachgelagerte Middleware-Kette nicht.

### 4.2 Content-Security-Policy (CSP)

Wird zusammen mit den anderen Sicherheitsheadern in der Cors-Middleware injiziert und bietet Tiefenverteidigung, indem die Ressourcenquellen begrenzt werden, die der Browser laden und ausführen darf.

| Header | Wert | Zweck |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Begrenzt Ressourcenquellen für Skripte/Styles/Bilder/Verbindungen/Frames/Formulare usw. |
| X-Permitted-Cross-Domain-Policies | `none` | Cross-Domain-Policy-Dateien für Adobe Flash/PDF usw. verbieten |

CSP-Policy-Kernpunkte:
- `default-src 'self'`: Standardmäßig nur gleich Ursprung erlaubt
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: Gleich-Ursprung-Skripte + Inline-Skripte (für Flutter Web erforderlich) + eval (für Flutter-Web-Debug erforderlich)
- `frame-ancestors 'none'`: Einbettung in iframes beliebiger Seiten verbieten, doppelte Absicherung mit X-Frame-Options: DENY
- `base-uri 'self'`: `<base>`-Tag darf nur auf gleich Ursprung zeigen
- `form-action 'self'`: Formulare dürfen nur an gleich Ursprung gesendet werden

---

## 5. Rate-Limiting-Strategie

### Algorithmus

Redis Sorted Set Gleitfenster + atomares Lua-Skript, kritische Operationen:

```lua
-- 1. Alte Datensätze außerhalb des Fensters bereinigen
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. Zähler des aktuellen Fensters prüfen
local count = redis.call('ZCARD', KEYS[1])
-- 3. Bei Überschreitung {0, count} zurückgeben, sonst ZADD und {1, count+1} zurückgeben
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- zufälliges Suffix verhindert Überschreiben in derselben Millisekunde
redis.call('EXPIRE', KEYS[1], window + 10)
```

Das Lua-Skript wird serverseitig in Redis single-threaded ausgeführt und ist **natürlich atomar**, wodurch TOCTOU-Race-Conditions (Time-of-check to Time-of-use) entfallen.

### Rate-Limiting-Konfiguration

| Route | Limit | Fenster | Szenario |
|------|------|------|------|
| Standard (alle Routen) | 60/Minute | 60s | Allgemeine API |
| `/api/v1/auth/login` | 10/Minute | 60s | Login (verhindert Brute-Force) |
| `/api/v1/auth/register` | 5/Minute | 60s | Registrierung (verhindert Massenregistrierung) |

### Response-Header

Bei ausgelöstem Rate-Limit wird HTTP 429 mit JSON-Body zurückgegeben:
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

Alle Antworten (auch normale) tragen die folgenden Header:

| Header | Beschreibung |
|----|------|
| X-RateLimit-Limit | Maximale Request-Anzahl des aktuellen Fensters |
| X-RateLimit-Remaining | Verbleibende Request-Anzahl des aktuellen Fensters |
| X-RateLimit-Reset | Unix-Zeitstempel des Fenster-Resets |
| Retry-After | Nur bei ausgelöstem Rate-Limit enthalten, empfohlene Wartezeit in Sekunden |

### Degradationsstrategie

Bei Redis-Fehlern (Verbindungs-Timeout, nicht verfügbar usw.) gilt **fail-open**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis down, alle Requests durchlassen
}
```

Lieber kurzzeitig den Rate-Limit-Schutz verlieren, als normale Geschäftsrequests zu blockieren.

### 5.4 Kontosperrungs-Mechanismus

Zusätzlich zum Rate-Limiting besitzt der Login-Endpunkt einen **Kontosperrungs**-Mechanismus gegen gezielte Brute-Force-Angriffe auf einzelne Benutzer.

**Sperrablauf**:

```
Login fehlgeschlagen → Redis INCR account_lockout:{userId} TTL=900s
5 aufeinanderfolgende Fehlversuche → Redis SETEX account_locked:{userId} 900 1
            → 429 "账号已被锁定，请15分钟后再试" zurückgeben
            → Zähler löschen DEL account_lockout:{userId}
```

**Verhalten während der Sperrung**:

Während der Sperrung liefern alle Login-Requests direkt 429, ohne Passwortprüfung; Brute-Force-Versuche werden vollständig blockiert.

**Konfigurationskonstanten**:

| Konstante | Wert | Bedeutung |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Maximale aufeinanderfolgende Fehlversuche |
| LOCKOUT_DURATION | 900 | Sperrdauer (Sekunden), also 15 Minuten |

Hinweis: Die Kontosperrung basiert auf der `userId` und nicht auf der IP, daher können Angreifer die Sperrung nicht durch IP-Wechsel umgehen. Zusammen mit dem IP-Rate-Limiting (10/Minute) entsteht ein doppelter Schutz:
- IP-Ebene: 10/Minute-Rate-Limiting verhindert verteilte Brute-Force
- Kontoebene: Sperrung nach 5 Fehlversuchen verhindert gezielte Brute-Force

---

## 6. Authentifizierung und Autorisierung

### 6.1 JWT-Authentifizierung

Implementiert in der AdminAuth-Middleware, gemountet auf den authentifizierungspflichtigen Routengruppen.

**Parameterkonfiguration** (`config/plugin/erikwang2013/jwt/jwt`, aus `.env` injiziert):

| Parameter | Wert | Beschreibung |
|------|-----|------|
| Algorithmus | HS256 | HMAC-SHA256 symmetrische Signatur |
| Schlüssel | `JWT_SECRET` | Per Umgebungsvariable injiziert, in der Produktion auszutauschen |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Issuer | `open-admin` | `JWT_ISSUER` |
| Audience | `open-admin` | `JWT_AUDIENCE` |

**Token-Extraktion**: Aus dem `Authorization: Bearer <token>`-Header extrahieren; das `Bearer `-Präfix wird entfernt, um das rohe JWT zu erhalten.

**Authentifizierungsablauf**:
1. Leeres Token → direkt 401 `{"code": 401, "message": "未登录"}`
2. Redis-Blacklist `jwt_blacklist:{md5(token)}` prüfen → Treffer → 401 `Token已失效，请重新登录`
3. JWT-Dekodierung → fehlgeschlagen (abgelaufen/Signatur falsch) → 401 `Token已过期或无效`
4. Erfolg → `$request->adminId` und `$request->adminUsername` injizieren

**Blacklist-Mechanismus**: Beim Logout wird `md5(token)` mit TTL = verbleibender JWT-Gültigkeitsdauer in Redis geschrieben. Bei Redis-Ausfall wird die Blacklist-Prüfung übersprungen (fail-open); ausgeloggte Tokens bleiben dann kurzzeitig nutzbar, aber die kurze JWT-Gültigkeit (2h) dient als Backstop-Schutz.

### 6.2 Begrenzung paralleler Sitzungen

Um den Missbrauch geleakter Tokens auf mehreren Geräten zu verhindern, begrenzt das System die Anzahl gleichzeitig gültiger Tokens pro Benutzer.

**Limitierungslogik**:

```
Login erfolgreich → neues Token ausstellen
         → Anzahl gültiger Tokens des Benutzers abfragen: Redis SCARD user_tokens:{userId}
         → Wenn Anzahl >= 3 (MAX_CONCURRENT_SESSIONS):
            → Nach Erstellzeit aufsteigend sortieren, ältestes Token entfernen:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → Neues Token zur Menge hinzufügen: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Konfigurationskonstanten**:

| Konstante | Wert | Bedeutung |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Maximale parallele Token-Anzahl pro Benutzer |

**Szenario „abgemeldet"**: Wenn sich der Benutzer auf einem 4. Gerät anmeldet, wird das Token des 1. Geräts zwangsweise geblacklistet; nachfolgende Requests liefern 401 "Token已失效，请重新登录".

Beim Logout wird das aktuelle Token aus der Menge entfernt. Läuft ein Token natürlich ab, verfällt der Redis-Key automatisch und die Menge schrumpft entsprechend.

### 6.3 RBAC-Berechtigungsmodell

Implementiert in der AdminPermission-Middleware.

**Datenmodell**: dreistufige Verknüpfung User -> Role -> Permission

- `logistics_admin_user` (Benutzertabelle)
- `logistics_admin_user_role` (Benutzer-Rolle-Zuordnungstabelle)
- `logistics_admin_role` (Rollentabelle)
- `logistics_admin_role_permission` (Rolle-Berechtigung-Zuordnungstabelle)
- `logistics_admin_permission` (Berechtigungstabelle)

**Berechtigungstypen**:
| type | Bedeutung | Beispiel |
|------|------|------|
| 1 | Menüberechtigung | steuert die Sichtbarkeit der linken Navigation |
| 2 | Buttonberechtigung | steuert Operationsbuttons innerhalb der Seite (Neu/Editieren/Löschen) |
| 3 | API-Berechtigung | steuert Backend-Endpunkt-Aufrufe |

Format der API-Berechtigungs-Kennung: `{method}.{path}`

Zum Beispiel:
- `post.admin/user` — Benutzer erstellen
- `put.admin/user` — Benutzer bearbeiten
- `delete.admin/user` — Benutzer löschen
- `get.admin/user` — Benutzerliste anzeigen

**Autorisierungsablauf**:
1. `$request->adminId` leer → durchlassen (Route hat keine Authentifizierung vorgeschaltet)
2. Benutzer → Rollen (deaktivierte Rollen mit `status=0` überspringen) → Berechtigungsliste
3. Superadministrator (`slug = '*'`) → direkt durchlassen
4. `strtolower(method) . '.' . trim(path, '/')` aufbauen → gegen Berechtigungsliste abgleichen
5. Kein Treffer → 403 `{"code": 403, "message": "无权限访问"}`

**Doppelte Bestätigung**: Die BaseController-Methode `confirmPassword()` verlangt bei sensiblen Operationen (Löschen von Benutzern, Datencxport usw.) auf Controller-Ebene zusätzlich die Eingabe des aktuellen Passworts, um unautorisierte Operationen nach einer Session-Hijacking zu verhindern.

---

## 7. Auditprotokolle

### 7.1 Aktionsprotokoll

Die OperationLog-Middleware zeichnet bei POST / PUT / DELETE-Requests automatisch Aktionsprotokolle auf. GET-Requests werden nicht protokolliert.

**Protokollierte Felder**:

| Feld | Quelle | Beschreibung |
|------|------|------|
| id | SnowflakeService::generate() | Global eindeutige ID |
| user_id | `$request->adminId` | ID des Ausführenden, 0 wenn nicht angemeldet |
| action | `$request->method()` | entspricht method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Request-Pfad |
| ip | `$request->getRealIp()` | Echte Client-IP |
| source | detectSource() | Client-Quellplattform |
| input | Request-Body (maskiertes JSON) | Vom Request übermittelte Daten |
| created_at | `date('Y-m-d H:i:s')` | Aktionszeit |

**Sensibler-Felder-Filter**: Der Request-Body wird rekursiv durchlaufen; die Werte folgender Felder werden durch `***` ersetzt:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Quellenerkennung** (`detectSource()`): nach Priorität:

1. Zuerst den benutzerdefinierten `X-Client-Platform`-Header lesen (explizite Deklaration durch native Clients)
2. Fallback auf die User-Agent-Zeichenketten-Inferenz (Erkennungsreihenfolge der Methode `detectSource()`):

| Plattform | UA-Schlüsselwörter |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Fallback-Standardwert |

**Fehlertoleranz**: Ein Protokollschreibfehler blockiert keine Geschäftsrequests (`catch (\Throwable)` still verschluckt).

### 7.2 Sicherheitsprotokoll

**Dateiposition**: `runtime/logs/security.log`

**Protokollierte Inhalte**:
- Angriffsblock-Protokolle: Angriffskategorie, IP, Pfad, Feld, Quelle, Payload-Ausschnitt (erste 200 Zeichen)
- IP-Sperr-Benachrichtigungen: gesperrte IP, Auslöseanzahl

Die Protokollzugriffsrechte sind `FILE_APPEND | LOCK_EX`, wodurch ein parallel-sicheres Schreiben gewährleistet ist.

---

## 8. Datenschutz

Das System verwendet eine dreistufige Datenschutzstrategie, die den drei Phasen des Datenflusses entspricht.

### 8.1 Übertragungsebene — EncryptionService

`EncryptionService` nutzt das Paket `erikwang2013/encryption` zur Ver-/Entschlüsselung sensibler Felder in API-Requests/-Responses.

**Technische Details**:
- Algorithmus: `aes-256-cbc-hmac` (mit integrierter HMAC-Signatur gegen Manipulation)
- Schlüssel: Umgebungsvariable `ENCRYPTION_KEY`, automatisch auf 32 Byte ausgerichtet
- Verwendung: Übertragung von Feldern wie Handynummer und Personalausweisnummer zwischen Client und API

**Maskierungs-Hilfsmethoden**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (Benutzername länger als 2 Zeichen) oder `a**@example.com`

### 8.2 Speicherebene — Encryptable Cast

Das `AdminUser`-Modell verwendet den Eloquent-Cast `Erikwang2013\Encryptable\Encryptable` für die entsprechenden Felder:

- `email` → als Encryptable gecastet, automatische Ver-/Entschlüsselung
- `phone` → als Encryptable gecastet, automatische Ver-/Entschlüsselung
- `id_card` → als Encryptable gecastet, automatische Ver-/Entschlüsselung

Beim Schreiben in die Datenbank wird automatisch zu Chiffretext verschlüsselt, beim Lesen automatisch zu Klartext entschlüsselt. Der Spaltentyp in der Datenbank ist `VARCHAR(500)`; der Chiffretext wird base64-kodiert gespeichert.

**Schlüsselsystem**: Unabhängig von der Übertragungsverschlüsselung (`ENCRYPTION_KEY`) wird `ENCRYPTABLE_KEY` verwendet — ein geleakter Schlüssel führt nicht zum Ausfall der jeweils anderen Ebene.

Schlüsselrotation: Die Umgebungsvariable `ENCRYPTION_PREVIOUS_KEYS` unterstützt eine Liste historischer Schlüssel (kommagetrennt). Beim Lesen alter Daten wird versucht, mit den historischen Schlüsseln zu entschlüsseln; beim Schreiben wird mit dem aktuellen Schlüssel neu verschlüsselt.

### 8.3 Anzeigeebene — ID-Verschleierung und Maskierung

**Hashids-ID-Verschleierung**: `HashidsService` nutzt das Paket `erikwang2013/hashids`.

- BIGINT-IDs aus der Datenbank werden für externe APIs als hash-Zeichenkette kodiert (z. B. `xK3mN9qR2pL7wV8b`)
- Clients übergeben beim Request die hash-Zeichenkette; das Backend dekodiert sie automatisch zur Original-ID
- Der Salt wird über die Umgebungsvariable `HASHIDS_SALT` injiziert; unterschiedliche Salts ergeben völlig andere Kodier-/Dekodierergebnisse
- Minimale hash-Länge 16 Zeichen, Zeichensatz aus 62 alphanumerischen Zeichen
- BaseController stellt die Komfortmethoden `encodeId()`, `decodeId()`, `encodeIds()` bereit

**Export-Maskierung**: Beim Excel/PDF-Export (ExportController) werden sensible Felder einheitlich maskiert:
- Handynummer: `138****1234`
- E-Mail: `a***@example.com`
- Personalausweisnummer: vollständig abgedeckt als `********`

---

## 9. Schlüsselverwaltung

Alle Schlüssel werden über `.env`-Umgebungsvariablen injiziert; die Konfigurationsdateien lesen sie mit `getenv()` und enthalten eingebaute Fallback-Standardwerte (nur für Entwicklung sicher).

| Umgebungsvariable | Zweck | Paket | Produktionsanforderung |
|----------|------|-----|---------|
| JWT_SECRET | JWT-Signaturschlüssel | erikwang2013/jwt-webman | Zufallszeichenkette mit 64+ Zeichen |
| JWT_ALGORITHM | JWT-Signaturalgorithmus | wie oben | HS256 beibehalten |
| HASHIDS_SALT | Salt für ID-Kodierung | erikwang2013/hashids | Zufallszeichenkette |
| SNOWFLAKE_DATACENTER_ID | Rechenzentrums-ID (0-31) | erikwang2013/snowflake-php | bei einem Rechenzentrum Standard belassen |
| ENCRYPTION_KEY | Verschlüsselungsschlüssel der API-Übertragungsebene | erikwang2013/encryption | 32-Byte-Zufallszeichenkette |
| ENCRYPTABLE_KEY | Verschlüsselungsschlüssel der DB-Speicherebene | erikwang2013/encryptable | 32-Byte-Zufallszeichenkette, verschieden vom Übertragungsschlüssel |

**Sicherheitsanforderungen**:
- `.env` ist in `.gitignore` aufgenommen; das Einchecken in das Versionsverwaltungs-Repository ist strikt verboten
- `.env.example` ist eine öffentliche Vorlagendatei ohne echte Schlüssel
- In der Produktion **müssen** alle Standard-Schlüssel durch Zufallszeichenketten ersetzt werden
- Empfohlen wird die Schlüsselerzeugung mit `openssl rand -base64 32`

### Schlüsselspeicher-Isolation

| Ebene | Konfigurationsschlüssel | Schlüssel-Umgebungsvariable |
|----|--------|-------------|
| Übertragungsverschlüsselung | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Speicherverschlüsselung | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| ID-Verschleierung | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| JWT-Signatur | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

Das System stellt unter `/.well-known/security.txt` einen RFC-9116-konformen Endpunkt für Sicherheitskontaktinformationen bereit, damit Sicherheitsforscher bei entdeckten Schwachstellen schnell einen Meldeweg finden.

**Zugriffsmethode**:

```
GET /.well-known/security.txt
```

**Antwortinhalt**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Feldbeschreibungen**:

| Feld | Beschreibung |
|------|------|
| Contact | Kontaktmöglichkeit für Sicherheitsmeldungen |
| Expires | Ablaufzeit der Datei, muss regelmäßig aktualisiert werden |
| Preferred-Languages | Bevorzugte Kommunikationssprachen |
| Canonical | Kanonische URL dieser Datei |
| Policy | Link zur Sicherheitsrichtlinie/Offenlegungspolitik für Schwachstellen |

Der Endpunkt unterliegt keinen Rate-Limit- oder Authentifizierungs-Middlewares; jeder kann direkt darauf zugreifen.

---

## 11. Nginx-Sicherheitskonfiguration

Das Projekt stellt `docs/nginx-security.conf` als Härtungsreferenz für den Nginx-Reverse-Proxy in Produktionsumgebungen bereit.

**Enthaltene Sicherheitsmaßnahmen**:

| Konfiguration | Zweck |
|--------|------|
| `server_tokens off` | Nginx-Versionsnummer verbergen |
| `client_max_body_size 10m` | Request-Body-Größe begrenzen, wirkt mit der SecurityMiddleware (erikwang2013/security-php) zusammen |
| `limit_req_zone` | Request-Frequenzlimitierung auf Nginx-Ebene |
| `limit_conn_zone` | Begrenzung paralleler Verbindungen |
| `add_header`-Sicherheitsheader | Sicherheitsheader wie X-XSS-Protection auf Nginx-Ebene ergänzen |
| `if ($request_method)` | Nicht standardkonforme HTTP-Methoden auf Nginx-Ebene ablehnen |
| SSL/TLS-Konfiguration | Moderne TLS-1.2/1.3-Konfiguration, schwache Chiffren deaktivieren |
| Backend-Header verbergen | `proxy_hide_header` entfernt sensible Header wie die webman-Version |

**Verwendung**: Konfigurationen aus `docs/nginx-security.conf` in den Nginx-server-Block übernehmen und an die tatsächliche Domain und Zertifikatspfade anpassen.

---

## 12. Bedrohungsmodell

### 12.1 Abgewehrte Bedrohungen

| Bedrohungstyp | Angriffsvektor | Verteidigungsebenen |
|----------|---------|---------|
| HTTP-Methodenmissbrauch | TRACE/TRACK-XST-Angriffe, CONNECT-Tunnel-Proxys, WebDAV-Methoden-Scan | SecurityMiddleware-http_method-Detektor, 405-Methoden-Whitelist |
| Gezielte Brute-Force | Wiederholte Passwortversuche gegen bestimmte Benutzer | Kontosperrung (5 Fehlversuche → 15 Min. Sperrung) + RateLimit (Login 10/min) + Captcha |
| Brute-Force | Verteiltes Ausprobieren von Benutzername/Passwort über viele IPs | RateLimit (Login 10/min) + Captcha |
| XSS-Cross-Site-Scripting | `<script>`, onerror, javascript: | SecurityMiddleware (erikwang2013/security-php) (5 Muster) + X-XSS-Protection-Response-Header + CSP |
| SQL-Injection | UNION SELECT, OR 1=1, Kommentar-Bypass | SecurityMiddleware (erikwang2013/security-php) (6 Muster) + Eloquent-ORM-Parameterabfragen |
| CSRF-Cross-Site-Request-Forgery | Bösartige Websites senden Requests im Namen des Benutzers | SecurityMiddleware (erikwang2013/security-php) Origin/Referer-Prüfung |
| Pfad-Traversal | `../../etc/passwd` | Pfad-Traversal-Muster der SecurityMiddleware (erikwang2013/security-php) + UploadController-Dateiendungs-Whitelist |
| Befehlsinjektion | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityMiddleware (erikwang2013/security-php) (4 Muster) |
| Session-Hijacking | JWT-Token stehlen | Kurze JWT-Gültigkeit (2h) + Blacklist-Logout + doppelte Passwortbestätigung bei sensiblen Operationen |
| ID-Enumeration | Numerische IDs durchprobieren, Datenmenge erraten | Hashids-Verschleierung zu Zufallszeichenketten |
| Datenleck | DB-Dump / Man-in-the-Middle / Protokollleck | Dreistufige Verschlüsselung/Maskierung + OperationLog-Sensibler-Felder-Filter |
| DoS-Angriffe | Überdimensionierte Request-Bodies / Hochfrequenz-Requests | 10MB-Request-Body-Limit + RateLimit 60/min + IP-Blacklist |
| Privilegienerweiterung | Zugriff von Benutzern mit niedrigen Rechten auf Admin-Endpunkte | RBAC-method.path-Granularität |
| Datei-Upload-Angriffe | shell.php.png mit Doppel-Endung | Malware-Dateierkennung der SecurityMiddleware (erikwang2013/security-php) |

### 12.2 Bekannte Einschränkungen

| Einschränkung | Auswirkungsbereich | Gegenmaßnahmen |
|------|---------|---------|
| CSRF-Schutz wirkt nur im Browser | Nicht-Browser-Clients (curl, Postman, Mobile-Apps) können die Origin/Referer-Prüfung umgehen | Nicht-Browser-Clients sind von Natur aus nicht CSRF-anfällig; Abhängigkeit von JWT-Authentifizierung statt Cookies |
| Bei Redis-Ausfall degradieren Rate-Limiting und Blacklist zu fail-open | Angreifer können Rate-Limiting und Hochfrequenz-Blockierung umgehen | Redis-Verfügbarkeit überwachen und alarmieren; IP-Blacklist unterstützt drei Backends (file/redis/cache) als Degradationsoption |
| Kein eigenständiger WAF-Engine | Regex-basierte Erkennung statt dedizierter WAF-Regelengine | In der Produktion Nginx ModSecurity oder Cloudflare WAF vorschalten |
| JWT ist zustandslos, kann nicht aktiv invalidiert werden | Tokens können vor Ablauf nicht serverseitig widerrufen werden (außer Blacklist) | Blacklist + kurze 2h-TTL verkleinern das Risikofenster |
| Admin-Endpunkte ohne spezielles Rate-Limiting | Admin-Endpunkte teilen sich die Standardbegrenzung von 60/min mit normalen Endpunkten | Admin-Operationen sind von Natur aus niedrigfrequent, vorerst keine Unterscheidung nötig |
| PCRE-Rückschritt-Limit | Das Paket hat eine eingebaute Rückschritt-Obergrenze von 1.000.000 mit finally-Wiederherstellung; extrem komplexe Eingaben bergen weiterhin Performance-Risiken | Request-Body-Größenbegrenzung (10MB) als Backstop |
## 13. Client-Portal- und Zahlungssicherheit (M7–M10)
Stripe-/PayPal-Webhook-Signaturprüfung (HMAC-SHA256 / verify-webhook-signature); Zahlungsschlüssel via `Encryptable` verschlüsselt in `logistics_system_config`; USDT TRC20 automatisch über Tronscan verifiziert, BEP20 / ERC20 manuell; X-API-Key vom Client gesetzt (≥16 Zeichen), als sha256 gespeichert. Gateway-Angriffserkennung (M11): `ecat-security` SecurityBodyLayer in das Gateway integriert, Angriffspayloads werden auf der Gateway-Ebene blockiert. **13.5 CDN-Sicherheit (M12)**: kostenloser Cloudflare-Plan — vollständiges HTTPS + zweischichtige WAF (verwaltete Edge-Regeln + Erkennung auf Anwendungsebene im Gateway); Tunnel-Origin ohne öffentliche Exposition; Callbacks laufen über eine reine DNS-Subdomain direkt zum Ursprung, um bei CDN-Ausfällen keine Bestellungen zu verlieren; Rate-Limiting zählt nach X-API-Key und ist von CDN-Edge-IPs unabhängig; authentifizierte Endpunkte sind immer no-store gegen Cross-User-Cache-Vermischung. **13.6 CDN-Anbieter-Schlüsselverwaltung (M13)**: CDN-Anbieterprofile (access_key / access_secret / Domainliste) in der Tabelle `logistics_cdn_provider`, Geheimfelder via `Encryptable` verschlüsselt, Verwaltung über CRUD `/admin/cdn/provider`.
