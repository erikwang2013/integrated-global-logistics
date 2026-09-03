# Documento de diseño de la arquitectura de seguridad

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](SECURITY.md) | [English](SECURITY.en.md) | [한국어](SECURITY.ko.md) | [Русский](SECURITY.ru.md) | [Deutsch](SECURITY.de.md) | [Français](SECURITY.fr.md) | [Español](SECURITY.es.md) | [Português](SECURITY.pt.md) | [हिन्दी](SECURITY.hi.md) | [العربية](SECURITY.ar.md) | [বাংলা](SECURITY.bn.md) | [Bahasa Indonesia](SECURITY.id.md) | [日本語](SECURITY.ja.md)

## 1. Panorama de la defensa en profundidad

El sistema adopta un modelo de defensa en profundidad de 7 capas que filtra las peticiones maliciosas capa por capa, de fuera hacia dentro, garantizando que, si falla cualquier capa individual, las líneas de defensa posteriores actúen como respaldo.

Toda la cadena de middleware se ejecuta en el siguiente orden (ver `config/middleware.php`):

```
Petición → Cors → Locale(Accept-Language) → SecurityMiddleware (erikwang2013/security-php, 31 detectores) → RateLimit → [middleware del grupo de rutas: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Capa | Middleware/mecanismo | Objetivo de protección |
|----|--------|---------|
| 1 | SecurityMiddleware (erikwang2013/security-php) | 31 tipos de detección de ataques + validación de métodos HTTP + límite del tamaño del cuerpo de la petición + validación de Content-Type + CSRF + lista negra por escalado de ataques de IP |
| 2 | Cors | Seguridad CORS + inyección de cabeceras de seguridad en las respuestas |
| 3 | RateLimit | Límite de peticiones con ventana deslizante Redis, previene la fuerza bruta |
| 4 | AdminAuth | Autenticación JWT + lista negra de cierre de sesión |
| 5 | AdminPermission | Autorización RBAC con granularidad method.path |
| 6 | OperationLog | Auditoría de operaciones + seguimiento del origen |
| 7 | Cifrado de datos | Ofuscación de IDs con Hashids + cifrado en DB con Encryptable + cifrado de transmisión con EncryptionService |

Las tres capas del frontend (Flutter) tienen además su propia validación de entrada; el backend no confía en ella, cada capa se defiende de forma independiente.

---

## 2. Motor de detección de ataques

## 2. Motor de detección de ataques (erikwang2013/security-php)

La detección de ataques se ha migrado del SecurityMiddleware propio a `erikwang2013/security-php` v1.1+, un paquete de seguridad dedicado que ofrece **31 detectores** que cubren 5 grandes categorías de ataques.

### 2.1 Clasificación de los detectores

**Ataques de inyección (11 tipos):** XSS, inyección SQL, inyección de comandos, inyección NoSQL, inyección LDAP, inyección XPath, JNDI/Log4Shell, inclusión de servidor SSI, inyección GraphQL, inyección de plantillas SSTI

**Ataques de protocolo y de petición (9 tipos):** SSRF, XXE, inyección en cabeceras de respuesta HTTP, ataque de cabecera Host, Request Smuggling, Open Redirect, evasión CORS, secuestro de WebSocket, DNS Rebinding

**Validación de la capa de protocolo HTTP (6 tipos):** validación de métodos HTTP(405), límite del tamaño del cuerpo de la petición(413), validación de Content-Type(415), comprobación del origen CSRF, lista negra por escalado de ataques de IP, detección de fugas de datos sensibles

**Ataques de datos y serialización (5 tipos):** deserialización de PHP, inyección de fórmulas CSV, inyección en cabeceras de correo, ataques JWT (análisis estructural), polución de prototipos JS

**Ataques de archivos y rutas (2 tipos):** traversal de rutas, subida de archivos maliciosos

### 2.2 Modos de tratamiento

Cada detector soporta de forma independiente dos modos:
- `block` — ante un ataque detectado, intercepta y devuelve el código de estado configurado
- `log` — solo registra en el log sin interceptar (`header_injection`, `ssti`, `nosql_injection` usan el modo log por defecto para evitar falsos positivos)

### 2.3 Lista negra por escalado de ataques de IP

Si la misma IP activa 5 detecciones de ataque en 60 segundos → bloqueo automático de 15 minutos. El backend de almacenamiento puede ser Redis (distribuido), File (JSON en un solo nodo) o Cache (archivo independiente de alta concurrencia); actualmente está configurado con almacenamiento Redis.

### 2.4 Registros de seguridad

Ubicación del archivo: `runtime/logs/security.log` (rotación automática, 10MB por archivo)

---

## 4. Cabeceras de seguridad de las respuestas

Todas las cabeceras se inyectan en el middleware `Cors` y se añaden a cada respuesta mediante `$response->withHeaders()`.

| Cabecera | Valor | Función |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Permite CORS desde cualquier origen (escenario de panel de administración en intranet) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Conjunto de métodos permitidos |
| Access-Control-Allow-Headers | `Authorization,Content-Type` | Cabeceras personalizadas permitidas |
| Access-Control-Max-Age | `86400` | Caché de la petición de preflight durante 24 horas |
| X-Content-Type-Options | `nosniff` | Prohíbe la detección de MIME por parte del navegador |
| X-Frame-Options | `DENY` | Prohíbe la incrustación en iframes, previene el clickjacking |
| X-XSS-Protection | `1; mode=block` | Activa el filtro XSS integrado del navegador e intercepta el renderizado de la página |
| Referrer-Policy | `strict-origin-when-cross-origin` | Mismo origen: URL completa; entre orígenes: solo el dominio |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Desactiva las API de cámara/micrófono/geolocalización en todo el sitio |

Las peticiones de preflight OPTIONS devuelven directamente una respuesta 204 vacía y no entran en el resto de la cadena de middleware.

### 4.2 Content-Security-Policy (CSP)

Se inyecta en el middleware Cors junto con las demás cabeceras de seguridad para proporcionar defensa en profundidad, limitando los orígenes de recursos que el navegador puede cargar y ejecutar.

| Cabecera | Valor | Función |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Limita los orígenes de scripts/estilos/imágenes/conexiones/frames/formularios y otros recursos |
| X-Permitted-Cross-Domain-Policies | `none` | Prohíbe la carga de archivos de políticas entre dominios de Adobe Flash/PDF |

Puntos clave de la política CSP:
- `default-src 'self'`: por defecto solo se permiten recursos del mismo origen
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: permite scripts del mismo origen + scripts en línea (necesarios para Flutter Web) + eval (necesario para la depuración de Flutter Web)
- `frame-ancestors 'none'`: prohíbe la incrustación en iframes de cualquier página, doble garantía junto con X-Frame-Options: DENY
- `base-uri 'self'`: limita la etiqueta `<base>` a apuntar solo al mismo origen
- `form-action 'self'`: limita los formularios a enviarse solo al mismo origen

---

## 5. Estrategia de límite de peticiones

### Algoritmo

Ventana deslizante con Redis Sorted Set + script Lua atómico, operaciones clave:

```lua
-- 1. Limpiar los registros antiguos fuera de la ventana
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. Comprobar el contador de la ventana actual
local count = redis.call('ZCARD', KEYS[1])
-- 3. Si supera el límite devolver {0, count}; si no, hacer ZADD y devolver {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- sufijo aleatorio para evitar sobrescrituras en el mismo milisegundo
redis.call('EXPIRE', KEYS[1], window + 10)
```

El script Lua se ejecuta en un solo hilo en el servidor Redis, por lo que es **naturalmente atómico** y elimina las condiciones de carrera TOCTOU (Time-of-check to Time-of-use).

### Configuración del límite de peticiones

| Ruta | Límite | Ventana | Escenario |
|------|------|------|------|
| Por defecto (todas las rutas) | 60 peticiones/minuto | 60s | API general |
| `/api/v1/auth/login` | 10 peticiones/minuto | 60s | Inicio de sesión (previene la fuerza bruta) |
| `/api/v1/auth/register` | 5 peticiones/minuto | 60s | Registro (previene el registro masivo) |

### Cabeceras de respuesta

Al activarse el límite se devuelve HTTP 429 con el body JSON:
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

Todas las respuestas (incluidas las normales) llevan las siguientes cabeceras:

| Cabecera | Descripción |
|----|------|
| X-RateLimit-Limit | Número máximo de peticiones permitidas en la ventana actual |
| X-RateLimit-Remaining | Peticiones disponibles restantes en la ventana actual |
| X-RateLimit-Reset | Marca de tiempo Unix del reinicio de la ventana |
| Retry-After | Solo se incluye al activarse el límite; segundos recomendados de espera |

### Estrategia de degradación

Ante una anomalía de Redis (timeout de conexión, no disponible, etc.), **fail-open**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis caído, dejar pasar todas las peticiones
}
```

Mejor perder temporalmente la protección del límite que bloquear las peticiones de negocio normales.

### 5.4 Mecanismo de bloqueo de cuenta

Además del límite de velocidad, el endpoint de inicio de sesión incorpora un mecanismo de **bloqueo de cuenta** para prevenir la fuerza bruta dirigida contra usuarios concretos.

**Flujo de bloqueo**:

```
Inicio de sesión fallido → Redis INCR account_lockout:{userId} TTL=900s
5 fallos consecutivos → Redis SETEX account_locked:{userId} 900 1
                      → devuelve 429 "账号已被锁定，请15分钟后再试"
                      → limpia el contador DEL account_lockout:{userId}
```

**Comportamiento durante el bloqueo**:

Durante el bloqueo, todas las peticiones de inicio de sesión devuelven directamente 429 sin comprobar la contraseña, bloqueando por completo los intentos de fuerza bruta.

**Constantes de configuración**:

| Constante | Valor | Significado |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Número máximo de fallos consecutivos |
| LOCKOUT_DURATION | 900 | Duración del bloqueo en segundos, es decir, 15 minutos |

Nota: el bloqueo de cuenta se basa en `userId`, no en la IP, por lo que un atacante no puede evitar el bloqueo cambiando de IP. Se combina con el límite por IP (10 peticiones/minuto) formando una doble protección:
- Nivel de IP: el límite de 10 peticiones/minuto impide la fuerza bruta distribuida
- Nivel de cuenta: el bloqueo tras 5 fallos impide la fuerza bruta dirigida

---

## 6. Autenticación y autorización

### 6.1 Autenticación JWT

Implementada por el middleware AdminAuth, montado en los grupos de rutas que requieren autenticación.

**Configuración de parámetros** (`config/plugin/erikwang2013/jwt/jwt`, inyectada desde `.env`):

| Parámetro | Valor | Descripción |
|------|-----|------|
| Algoritmo | HS256 | Firma simétrica HMAC-SHA256 |
| Clave | `JWT_SECRET` | Inyectada por variable de entorno; hay que cambiarla en producción |
| TTL de access_token | 7200s (2h) | `JWT_TTL` |
| TTL de refresh_token | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Emisor | `open-admin` | `JWT_ISSUER` |
| Audiencia | `open-admin` | `JWT_AUDIENCE` |

**Extracción del token**: se extrae de la cabecera `Authorization: Bearer <token>`, eliminando el prefijo `Bearer ` para obtener el JWT original.

**Flujo de autenticación**:
1. Token vacío → 401 directo `{"code": 401, "message": "未登录"}`
2. Comprobar la lista negra de Redis `jwt_blacklist:{md5(token)}` → si coincide → 401 `Token已失效，请重新登录`
3. Decodificar JWT → si falla (caducado/firma incorrecta) → 401 `Token已过期或无效`
4. Si es correcto → inyectar `$request->adminId` y `$request->adminUsername`

**Mecanismo de lista negra**: al cerrar sesión, `md5(token)` se escribe en Redis con TTL igual al tiempo de validez restante del JWT. Si Redis falla, la comprobación de la lista negra se omite (fail-open) y el token cerrado podría seguir usándose durante un tiempo breve, pero la validez corta del propio JWT (2h) actúa como protección de respaldo.

### 6.2 Límite de sesiones concurrentes

Para evitar el uso indebido de un token filtrado en varios dispositivos, el sistema limita el número de tokens válidos que puede mantener simultáneamente un mismo usuario.

**Lógica de la limitación**:

```
Inicio de sesión correcto → emitir nuevo Token
                          → consultar el número de tokens válidos del usuario actual: Redis SCARD user_tokens:{userId}
                          → si el número >= 3 (MAX_CONCURRENT_SESSIONS):
                             → ordenar por tiempo de creación ascendente y eliminar el token más antiguo:
                               Redis SREM user_tokens:{userId} <oldest_token_id>
                               Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
                          → añadir el nuevo Token al conjunto: Redis SADD user_tokens:{userId} <new_token_id>
                            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Constantes de configuración**:

| Constante | Valor | Significado |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Número máximo de tokens concurrentes por usuario |

**Escenario de expulsión**: cuando el usuario inicia sesión en el 4.º dispositivo, el token del 1.er dispositivo se añade a la fuerza a la lista negra y las peticiones posteriores devuelven 401 "Token已失效，请重新登录".

Al cerrar sesión, el token actual se elimina del conjunto. Cuando un token caduca de forma natural, la clave Redis expira automáticamente y los miembros del conjunto disminuyen en consecuencia.

### 6.3 Modelo de permisos RBAC

Implementado por el middleware AdminPermission.

**Modelo de datos**: asociación en tres niveles User -> Role -> Permission

- `logistics_admin_user` (tabla de usuarios)
- `logistics_admin_user_role` (tabla de relación usuario-rol)
- `logistics_admin_role` (tabla de roles)
- `logistics_admin_role_permission` (tabla de relación rol-permiso)
- `logistics_admin_permission` (tabla de permisos)

**Tipos de permiso**:
| type | Significado | Ejemplo |
|------|------|------|
| 1 | Permiso de menú | Controla la visibilidad de la navegación lateral |
| 2 | Permiso de botón | Controla los botones de acción de la página (crear/editar/eliminar) |
| 3 | Permiso de API | Controla las llamadas a las interfaces del backend |

Formato del identificador de permiso de API: `{method}.{path}`

Por ejemplo:
- `post.admin/user` — crear usuario
- `put.admin/user` — editar usuario
- `delete.admin/user` — eliminar usuario
- `get.admin/user` — ver la lista de usuarios

**Flujo de autorización**:
1. Si `$request->adminId` está vacío → dejar pasar (la ruta no tiene autenticación previa configurada)
2. Obtener usuario → roles (omitiendo los roles deshabilitados con `status=0`) → lista de permisos
3. Superadministrador (`slug = '*'`) → dejar pasar directamente
4. Construir `strtolower(method) . '.' . trim(path, '/')` → comparar con la lista de permisos
5. Sin coincidencia → 403 `{"code": 403, "message": "无权限访问"}`

**Segunda confirmación**: BaseController ofrece el método `confirmPassword()`; las operaciones sensibles (eliminar usuarios, exportar datos, etc.) exigen además introducir la contraseña actual en la capa de Controller para prevenir operaciones no autorizadas tras un secuestro de sesión.

---

## 7. Registros de auditoría

### 7.1 Registros de operaciones

El middleware OperationLog registra automáticamente las operaciones de las peticiones POST / PUT / DELETE. Las peticiones GET no se registran.

**Campos registrados**:

| Campo | Origen | Descripción |
|------|------|------|
| id | SnowflakeService::generate() | ID único global |
| user_id | `$request->adminId` | ID del operador; 0 si no hay inicio de sesión |
| action | `$request->method()` | Equivalente al método |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Ruta de la petición |
| ip | `$request->getRealIp()` | IP real del cliente |
| source | detectSource() | Plataforma de origen del cliente |
| input | Body de la petición (JSON enmascarado) | Datos enviados en la operación |
| created_at | `date('Y-m-d H:i:s')` | Hora de la operación |

**Filtrado de campos sensibles**: se recorre recursivamente el body de la petición y los valores de los siguientes campos se sustituyen por `***`:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Detección del origen** (`detectSource()`): por prioridad:

1. Se lee primero la cabecera personalizada `X-Client-Platform` (declarada explícitamente por los clientes nativos)
2. Si no, se deduce de la cadena User-Agent (orden de detección del método `detectSource()`):

| Plataforma | Palabra clave en UA |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Valor por defecto de respaldo |

**Tolerancia a fallos**: una anomalía al escribir el registro no bloquea la petición de negocio (`catch (\Throwable)` se traga silenciosamente).

### 7.2 Registros de seguridad

**Ubicación del archivo**: `runtime/logs/security.log`

**Contenido registrado**:
- Registros de bloqueo de ataques: categoría del ataque, IP, ruta, campo, origen, fragmento del payload (primeros 200 caracteres)
- Avisos de bloqueo de IP: IP bloqueada, número de activaciones

Los registros usan los permisos `FILE_APPEND | LOCK_EX` para garantizar escrituras seguras en concurrencia.

---

## 8. Protección de datos

El sistema adopta una estrategia de protección de datos en tres capas, correspondientes a las tres fases del flujo de datos.

### 8.1 Capa de transmisión — EncryptionService

`EncryptionService` usa el paquete `erikwang2013/encryption` para cifrar y descifrar los campos sensibles de las peticiones/respuestas de la API.

**Detalles técnicos**:
- Algoritmo: `aes-256-cbc-hmac` (incluye firma HMAC contra manipulaciones)
- Clave: variable de entorno `ENCRYPTION_KEY`, alineada automáticamente a 32 bytes
- Uso: transmisión de campos como el teléfono o el número de documento de identidad entre el cliente y la API

**Métodos de utilidad de enmascarado**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (si el nombre de usuario supera los 2 caracteres) o `a**@example.com`

### 8.2 Capa de almacenamiento — Cast Encryptable

El modelo `AdminUser` usa el cast de Eloquent `Erikwang2013\Encryptable\Encryptable`; los campos correspondientes:

- `email` → cast a Encryptable, cifrado/descifrado automático
- `phone` → cast a Encryptable, cifrado/descifrado automático
- `id_card` → cast a Encryptable, cifrado/descifrado automático

Al escribir en la base de datos se cifra automáticamente a texto cifrado; al leer se descifra automáticamente a texto plano. El tipo de columna de almacenamiento es `VARCHAR(500)` y el texto cifrado se almacena en base64.

**Sistema de claves**: usa `ENCRYPTABLE_KEY`, independiente del cifrado de la capa de transmisión (`ENCRYPTION_KEY`); la fuga de una clave no inutiliza la otra capa.

Rotación de claves: la variable de entorno `ENCRYPTION_PREVIOUS_KEYS` admite una lista de claves históricas (separadas por comas); al leer datos antiguos se intenta descifrar con las claves históricas y al escribir se vuelve a cifrar con la clave actual.

### 8.3 Capa de presentación — Ofuscación de IDs y enmascarado

**Ofuscación de IDs con Hashids**: `HashidsService` usa el paquete `erikwang2013/hashids`.

- Los IDs BIGINT de la base de datos que devuelve la API pública se codifican como cadenas hash (por ejemplo, `xK3mN9qR2pL7wV8b`)
- El cliente envía la cadena hash en las peticiones y el backend la decodifica automáticamente al ID original
- El valor salt `HASHIDS_SALT` se inyecta por variable de entorno; con distintos salts los resultados de codificación/decodificación son completamente diferentes
- Longitud mínima del hash: 16 caracteres, con un conjunto de 62 caracteres alfanuméricos
- BaseController ofrece los métodos de conveniencia `encodeId()`, `decodeId()`, `encodeIds()`

**Enmascarado en la exportación**: al exportar Excel/PDF (ExportController), los campos sensibles se enmascaran de forma unificada:
- Teléfono: `138****1234`
- Correo: `a***@example.com`
- Documento de identidad: completamente cubierto como `********`

---

## 9. Gestión de claves

Todas las claves se inyectan mediante variables de entorno del `.env`; los archivos de configuración las leen con `getenv()` e incluyen valores por defecto de respaldo (solo seguros para desarrollo).

| Variable de entorno | Uso | Paquete | Requisito de producción |
|----------|------|-----|---------|
| JWT_SECRET | Clave de firma JWT | erikwang2013/jwt-webman | Cadena aleatoria de 64+ caracteres |
| JWT_ALGORITHM | Algoritmo de firma JWT | Ídem | Mantener HS256 |
| HASHIDS_SALT | Valor salt de codificación de IDs | erikwang2013/hashids | Cadena aleatoria |
| SNOWFLAKE_DATACENTER_ID | ID del centro de datos (0-31) | erikwang2013/snowflake-php | Mantener el predeterminado en un solo centro de datos |
| ENCRYPTION_KEY | Clave de cifrado de la capa de transmisión de la API | erikwang2013/encryption | Cadena aleatoria de 32 bytes |
| ENCRYPTABLE_KEY | Clave de cifrado de la capa de almacenamiento en DB | erikwang2013/encryptable | Cadena aleatoria de 32 bytes, distinta de la clave de transmisión |

**Requisitos de seguridad**:
- El archivo `.env` está incluido en `.gitignore`; está estrictamente prohibido enviarlo al repositorio
- `.env.example` es una plantilla pública que no contiene claves reales
- En producción es **obligatorio** sustituir todas las claves por defecto por cadenas aleatorias
- Se recomienda generar las claves con `openssl rand -base64 32`

### Aislamiento del almacenamiento de claves

| Capa | Clave de configuración | Variable de entorno de la clave |
|----|--------|-------------|
| Cifrado de transmisión | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Cifrado de almacenamiento | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| Ofuscación de IDs | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| Firma JWT | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

El sistema ofrece en `/.well-known/security.txt` un endpoint con la información de contacto de seguridad conforme al estándar RFC 9116, para que los investigadores de seguridad encuentren rápidamente el canal de reporte al descubrir vulnerabilidades.

**Forma de acceso**:

```
GET /.well-known/security.txt
```

**Contenido de la respuesta**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Descripción de los campos**:

| Campo | Descripción |
|------|------|
| Contact | Medio de contacto para reportar vulnerabilidades de seguridad |
| Expires | Fecha de caducidad del archivo; hay que actualizarla periódicamente |
| Preferred-Languages | Idiomas preferidos de comunicación |
| Canonical | URL canónica de este archivo |
| Policy | Enlace a la política de seguridad/divulgación de vulnerabilidades |

Este endpoint no está sujeto a límite de peticiones, autenticación ni otros middlewares; cualquier persona puede acceder directamente.

---

## 11. Configuración de seguridad de Nginx

El proyecto ofrece `docs/nginx-security.conf` como configuración de referencia para el endurecimiento de seguridad del proxy inverso Nginx en producción.

**Medidas de seguridad incluidas**:

| Elemento de configuración | Función |
|--------|------|
| `server_tokens off` | Oculta el número de versión de Nginx |
| `client_max_body_size 10m` | Limita el tamaño del cuerpo de la petición, en coordinación con SecurityMiddleware (erikwang2013/security-php) |
| `limit_req_zone` | Límite de frecuencia de peticiones a nivel de Nginx |
| `limit_conn_zone` | Límite de conexiones concurrentes |
| `add_header` de seguridad | Añade cabeceras de seguridad como X-XSS-Protection a nivel de Nginx |
| `if ($request_method)` | Rechaza métodos HTTP no estándar a nivel de Nginx |
| Configuración SSL/TLS | Configuración moderna de TLS 1.2/1.3, desactiva los cifrados débiles |
| Ocultación de cabeceras del backend | `proxy_hide_header` elimina cabeceras sensibles como la versión de webman |

**Forma de uso**: combine la configuración de `docs/nginx-security.conf` en el bloque server de su Nginx y ajústela según su dominio real y la ruta de los certificados.

---

## 12. Modelo de amenazas

### 12.1 Amenazas protegidas

| Tipo de amenaza | Vector de ataque | Capas de defensa |
|----------|---------|---------|
| Abuso de métodos HTTP | Ataques XST con TRACE/TRACK, túnel proxy CONNECT, sondeo de métodos WebDAV | Lista blanca de métodos 405 del detector http_method de SecurityMiddleware |
| Fuerza bruta dirigida | Intentos repetidos de contraseña contra un usuario concreto | Bloqueo de cuenta (5 fallos bloquean 15 min) + RateLimit (login 10/min) + Captcha |
| Fuerza bruta | Intentos distribuidos de usuario/contraseña desde varias IPs | RateLimit (login 10/min) + Captcha |
| XSS (cross-site scripting) | `<script>`, onerror, javascript: | SecurityMiddleware (erikwang2013/security-php) (5 modos) + cabecera de respuesta X-XSS-Protection + CSP |
| Inyección SQL | UNION SELECT, OR 1=1, evasión con comentarios | SecurityMiddleware (erikwang2013/security-php) (6 modos) + consultas parametrizadas de Eloquent ORM |
| CSRF (falsificación de peticiones entre sitios) | Sitios maliciosos que envían peticiones en nombre del usuario | Validación de Origin/Referer de SecurityMiddleware (erikwang2013/security-php) |
| Traversal de rutas | `../../etc/passwd` | Modo de traversal de rutas de SecurityMiddleware (erikwang2013/security-php) + lista blanca de extensiones de UploadController |
| Inyección de comandos | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityMiddleware (erikwang2013/security-php) (4 modos) |
| Secuestro de sesión | Robo del token JWT | Validez corta del JWT (2h) + lista negra de cierre de sesión + segunda confirmación de contraseña en operaciones sensibles |
| Enumeración de IDs | Recorrer IDs numéricos para adivinar el volumen de datos | Hashids ofusca los IDs a cadenas aleatorias |
| Fuga de datos | Exfiltración de la base de datos / intermediario / fugas de logs | Cifrado/enmascarado en tres capas + filtrado de campos sensibles de OperationLog |
| Ataques DoS | Cuerpos de petición enormes / peticiones de alta frecuencia | Límite de 10MB del cuerpo de la petición + RateLimit 60/min + lista negra de IP |
| Escalada de privilegios | Usuarios con pocos permisos acceden a interfaces de administración | Autorización RBAC con granularidad method.path |
| Ataques de subida de archivos | Extensión doble shell.php.png | Detección de archivos maliciosos de SecurityMiddleware (erikwang2013/security-php) |

### 12.2 Limitaciones conocidas

| Limitación | Alcance del impacto | Medidas de mitigación |
|------|---------|---------|
| La protección CSRF solo es efectiva en navegadores | Los clientes no basados en navegador (curl, Postman, apps móviles) pueden omitir la comprobación Origin/Referer | Los clientes no basados en navegador no son vulnerables a CSRF por naturaleza; se depende de la autenticación JWT en lugar de cookies |
| Si Redis no está disponible, el límite de peticiones y la lista negra se degradan a fail-open | Un atacante podría saltarse el límite de peticiones y el bloqueo de alta frecuencia | Monitorizar la disponibilidad de Redis con alertas; la lista negra de IP admite tres backends (file/redis/cache) con degradación |
| Sin motor WAF independiente | Detección basada en coincidencia de expresiones regulares, no es un motor de reglas WAF dedicado | En producción se recomienda anteponer Nginx ModSecurity o Cloudflare WAF |
| El JWT sin estado no puede invalidarse activamente | Los tokens no pueden revocarse desde el servidor antes de caducar (excepto por lista negra) | Lista negra + TTL corto de 2h para reducir la ventana de riesgo |
| Los endpoints de administración no tienen un límite de peticiones especial | Las interfaces de administración comparten el límite por defecto de 60/min con las interfaces normales | La frecuencia de las operaciones de administración es naturalmente baja; por ahora no es necesario diferenciar |
| Límite de retroceso de PCRE | El paquete incluye un límite de 1 000 000 de retrocesos con restauración en finally; las entradas extremadamente complejas aún suponen un riesgo de rendimiento | El límite del cuerpo de la petición (10MB) actúa como respaldo |
## 13. Seguridad del portal de cliente y pagos (M7–M10)
Verificación de firma de webhooks Stripe / PayPal (HMAC-SHA256 / verify-webhook-signature); claves de pago cifradas con `Encryptable` en `logistics_system_config`; USDT TRC20 verificado automáticamente vía Tronscan, BEP20 / ERC20 manual; X-API-Key definido por el cliente (≥16 caracteres) almacenado como sha256. Detección de ataques en la pasarela (M11): `ecat-security` SecurityBodyLayer integrado en la pasarela, las cargas de ataque se bloquean en la capa de pasarela. **13.5 Seguridad CDN (M12)**: plan gratuito de Cloudflare — HTTPS completo + WAF de doble capa (reglas gestionadas en el borde + detección de capa de aplicación de pasarela); origen Tunnel sin exposición pública; callbacks por subdominio de solo DNS directo para no perder pedidos ante caída del CDN; limitación de velocidad por X-API-Key, independiente de las IP de borde del CDN; endpoints autenticados siempre no-store para evitar mezcla de caché entre usuarios. **13.6 Gestión de claves de proveedores CDN (M13)**: perfiles de proveedores CDN (access_key / access_secret / lista de dominios) en la tabla `logistics_cdn_provider`, campos secretos cifrados con `Encryptable`, mantenimiento vía CRUD `/admin/cdn/provider`.
