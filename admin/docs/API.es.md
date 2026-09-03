> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](API.md) | [English](API.en.md) | [한국어](API.ko.md) | [Русский](API.ru.md) | [Deutsch](API.de.md) | [Français](API.fr.md) | [Español](API.es.md) | [Português](API.pt.md) | [हिन्दी](API.hi.md) | [العربية](API.ar.md) | [বাংলা](API.bn.md) | [Bahasa Indonesia](API.id.md) | [日本語](API.ja.md)

# Documento de referencia de la API

## 1. Descripción general

El panel de administración abierto (open-admin) está construido sobre webman v2 y ofrece una API JSON RESTful. Todas las interfaces del panel de administración requieren autenticación JWT y verificación de permisos RBAC; las interfaces públicas se enrutan a controladores versionados mediante la cabecera de versión de API.

- **URL base**: `http://localhost:8787`
- **Idioma**: se cambia mediante la cabecera `Accept-Language` o el parámetro `?lang=zh_CN|en` (por defecto zh_CN), el middleware Locale lo detecta automáticamente

> **Resumen de endpoints**: autenticación(5) | panel(1) | usuarios(7) | roles(4) | permisos(4) | configuración(4) | logs(1) | perfil(3) | importación/exportación(3) | subida(1) | operaciones(4: health/metrics/docs/security.txt) | 37 endpoints en total
- **Autenticación**: `Authorization: Bearer <token>` (JWT)
- **Formato de respuesta**: `{ "code": 0, "message": "success", "data": {...} }`
- **Endpoint de documentación**: `GET /api/docs` devuelve la especificación JSON OpenAPI 3.0

### Requisitos de las peticiones

- Solo se permiten los métodos `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD`; el uso de otros métodos HTTP (como TRACE, CONNECT, PATCH) devuelve 405
- Todas las peticiones `POST` / `PUT` deben establecer `Content-Type: application/json` (excepto la subida de archivos); de lo contrario se devuelve 415
- El tamaño del cuerpo de la petición no debe superar los 10MB; de lo contrario se devuelve 413
- El filtro de seguridad analiza todas las entradas de las peticiones en busca de XSS, inyección SQL, traversal de rutas e inyección de comandos; si hay coincidencia se devuelve 403
- 5 intentos de inicio de sesión fallidos consecutivos activan el bloqueo de cuenta (15 minutos); durante el bloqueo, las peticiones de inicio de sesión devuelven 429
- Un mismo usuario puede mantener como máximo 3 tokens válidos simultáneamente; al superarse, el token más antiguo se añade automáticamente a la lista negra

## 2. Códigos de error

| code | Significado | Escenario |
|------|------|---------|
| 0 | Éxito | |
| 400 | Error en los parámetros de la petición | Formato de petición incorrecto |
| 401 | No autenticado | Token ausente / caducado / en la lista negra |
| 403 | Sin permiso / bloqueo de seguridad | Permisos RBAC insuficientes / coincidencia con SecurityFilter |
| 404 | Recurso inexistente | El destino de consulta/actualización/borrado no existe |
| 405 | Método de petición no permitido | Solo se permiten GET/POST/PUT/DELETE/OPTIONS/HEAD; los métodos no estándar se rechazan directamente |
| 413 | Cuerpo de petición demasiado grande | Content-Length superior a 10MB |
| 415 | Tipo de medio no soportado | El Content-Type de las peticiones POST/PUT no es JSON ni subida de archivos |
| 422 | Fallo de validación de parámetros | Campos obligatorios ausentes, formato incorrecto, validación de negocio no superada |
| 429 | Demasiadas peticiones | RateLimit activado / bloqueo de cuenta (5 inicios de sesión fallidos consecutivos bloquean 15 minutos) |
| 500 | Error interno del servidor | |

## 3. Endpoints públicos

Todos los endpoints públicos están montados bajo el grupo `/api` y se distribuyen mediante el middleware `ApiVersion` según la cabecera `API-Version` al controlador versionado correspondiente (por ejemplo, `app\api\v1\controller\AuthController`).

### 3.1 Health check

```
GET /health
```

- **Autenticación**: ninguna
- **Límite de peticiones**: ninguno

**Ejemplo de respuesta**:
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

Los valores de `database`, `redis`, `elasticsearch`: `"ok"` | `"unavailable"`. `elasticsearch` devuelve `"unavailable"` cuando ES no es accesible; si el estado de salud del clúster no es green/yellow, devuelve el valor de status real (por ejemplo, `"red"`).

### 3.2 Documentación de la API

```
GET /api/docs
```

- **Autenticación**: ninguna
- **Límite de peticiones**: predeterminado global (60 peticiones/minuto)
- **Respuesta**: especificación JSON OpenAPI 3.0.3 con todas las definiciones de endpoints, parámetros y Schemas

### 3.3 Generar captcha

```
POST /api/v1/captcha/generate
```

- **Autenticación**: ninguna
- **Límite de peticiones**: predeterminado global (60 peticiones/minuto)

**Cuerpo de la petición**:
```json
{
  "difficulty": "medium"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| difficulty | string | No | `easy` / `medium` / `hard`, por defecto `medium` |

**Ejemplo de respuesta** — tipo clic (`type: "click"`):
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

**Ejemplo de respuesta** — tipo deslizador (`type: "slider"`):
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

**Ejemplo de respuesta** — tipo rotación (`type: "rotate"`):
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

| Campo | Tipo | Descripción |
|------|------|------|
| key | string | Identificador del captcha, se devuelve al validar |
| type | string | Tipo de captcha: `click` / `slider` / `rotate` |
| image | string | Imagen como data URI en base64 |
| extra | object | Datos adicionales según el tipo (ver más abajo) |

**`extra` según el tipo**:

| type | Campos de extra | Tipo | Descripción |
|------|-----------|------|------|
| click | targets | array | Objetivos de clic, incluyen `order`(orden) `text`(texto indicativo) `x` `y`(coordenadas) |
| slider | x, y | int | Coordenadas de la esquina superior izquierda del hueco (basadas en un lienzo de 300×200) |
| slider | puzzle_w, puzzle_h | int | Ancho y alto de la pieza del puzle |
| slider | puzzle | string | Pieza del puzle como data URI en base64 |
| rotate | angle | int | Ángulo de rotación correcto (0-359); hay que rotar `360-angle` para enderezar la imagen |

### 3.4 Validar captcha

```
POST /api/v1/captcha/verify
```

- **Autenticación**: ninguna
- **Límite de peticiones**: predeterminado global (60 peticiones/minuto)

**Cuerpo de la petición** — tipo clic (`type: "click"`):
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

**Cuerpo de la petición** — tipo deslizador (`type: "slider"`):
```json
{
  "key": "def456abc789",
  "type": "slider",
  "clicks": 120
}
```

**Cuerpo de la petición** — tipo rotación (`type: "rotate"`):
```json
{
  "key": "ghi789abc012",
  "type": "rotate",
  "clicks": 315
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| key | string | Sí | Clave del captcha, devuelta por generate |
| type | string | Sí | Tipo de captcha, debe coincidir con el `type` devuelto por generate |
| clicks | variante | Sí | Datos de la respuesta; el formato varía según el type (ver más abajo) |

**`clicks` según el tipo**:

| type | Tipo de clicks | Descripción | Tolerancia de error |
|------|------------|------|---------|
| click | `[{x:int, y:int}]` | Matriz de coordenadas de clic, en orden de `order` | radio de 18px |
| slider | `int` | Desplazamiento del deslizador en el eje X | ±4px |
| rotate | `int` | Ángulo de rotación (0-359) | ±5° |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

Una vez validado, el backend escribe `captcha_verified:{key}` en Redis (TTL 300s) y el endpoint de inicio de sesión lo usa como señal de autorización.
Si la validación falla, `code` es 422, `message` es `"验证失败，请重试"` y `data.valid` es `false`.

### 3.5 Inicio de sesión

```
POST /api/v1/auth/login
```

- **Autenticación**: ninguna
- **Límite de peticiones**: 10 peticiones/minuto (por IP + ruta)

**Cuerpo de la petición**:
```json
{
  "username": "admin",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "captcha_key": "abc123def456"
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| username | string | Sí | min:3, max:50 | Nombre de usuario |
| password | string | Sí | min:6, max:32 (texto plano) | Cifrado AES-256-CBC-HMAC y codificado en Base64 (compatible con texto plano) |
| captcha_key | string | Sí | | Clave del captcha (primero hay que validarla en `/api/v1/captcha/verify`) |

### Protocolo de cifrado de contraseñas

Se usa **cifrado asimétrico RSA-2048**; la clave pública está en el código del frontend (puede exponerse de forma segura) y la clave privada solo la posee el servidor.

```
Flujo de cifrado (cliente):
  Clave pública RSA (PEM) → cifrado PKCS1v1.5 → codificación Base64 → transmisión

Flujo de descifrado (servidor, con retroceso por niveles):
  1. Descifrar con la clave privada RSA → si tiene éxito y es UTF-8 válido → usar el resultado descifrado
  2. Descifrado AES-256-CBC-HMAC → si tiene éxito → usar el resultado (compatibilidad con clientes antiguos)
  3. Retroceso a texto plano → usar directamente la entrada original
```

La clave pública está integrada en la aplicación del frontend y no se transmite por la red. La clave privada solo se almacena en `RSA_PRIVATE_KEY` del `.env` y no debe filtrarse.

> El cifrado simétrico AES es una solución de compatibilidad con versiones antiguas; se eliminará cuando todos los clientes migren a RSA.

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| access_token | string | Token de acceso JWT |
| refresh_token | string | Token de renovación JWT |
| expires_in | int | Validez del token de acceso en segundos, por defecto 7200 |
| user.id | string | ID de usuario cifrado con hashid |
| user.username | string | Nombre de usuario |
| user.real_name | string | Nombre real |

**Posibles errores**:
- 422: fallo de validación de parámetros (faltan campos obligatorios, formato incorrecto)
- 422: primero debe completarse la validación del captcha (captcha_key no ha pasado por `/api/v1/captcha/verify`)
- 401: nombre de usuario o contraseña incorrectos
- 403: la cuenta está deshabilitada
- 429: la cuenta está bloqueada; inténtelo de nuevo en 15 minutos (se activa tras 5 inicios de sesión fallidos consecutivos)

### 3.6 Registro

```
POST /api/v1/auth/register
```

- **Autenticación**: ninguna
- **Límite de peticiones**: 5 peticiones/minuto (por IP + ruta)

**Cuerpo de la petición**:
```json
{
  "username": "newuser",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "real_name": "新用户",
  "captcha_key": "abc123def456"
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| username | string | Sí | min:3, max:50 | Nombre de usuario (único) |
| password | string | Sí | min:6, max:32 (texto plano) | Cifrado AES-256-CBC-HMAC y codificado en Base64 |
| real_name | string | Sí | max:50 | Nombre real |
| captcha_key | string | Sí | | Clave del captcha (primero hay que validarla en `/api/v1/captcha/verify`) |

**Ejemplo de respuesta**:
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

Tras un registro exitoso se devuelven directamente los tokens JWT; el estado del usuario está habilitado por defecto (status=1).

### 3.7 Renovar token

```
POST /api/v1/auth/refresh
```

- **Autenticación**: ninguna
- **Límite de peticiones**: predeterminado global (60 peticiones/minuto)

**Cuerpo de la petición**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| refresh_token | string | Sí | El refresh_token obtenido en el inicio de sesión/registro |

**Ejemplo de respuesta**:
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

Una renovación exitosa devuelve un nuevo access_token y un nuevo refresh_token; el token antiguo queda automáticamente invalidado. Al renovar se actualizan la hora del último inicio de sesión y la IP del usuario.

**Posibles errores**:
- 422: falta el token de renovación
- 401: el token de renovación no es válido o ha caducado

### 3.8 Métricas de monitorización Prometheus

```
GET /metrics
```

- **Autenticación**: ninguna
- **Límite de peticiones**: ninguno
- **Formato de respuesta**: Prometheus text format (`text/plain; version=0.0.4`)

Endpoint público de métricas Prometheus para que Grafana/Prometheus las capturen.

**Ejemplo de respuesta**:
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

| Nombre de la métrica | Tipo | Descripción |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Número total acumulado de peticiones HTTP |
| `openadmin_active_users` | gauge | Usuarios activos actuales (con inicio de sesión en las últimas 24 horas) |
| `openadmin_db_connection_status` | gauge | Estado de la conexión de base de datos, 1=normal, 0=anomalía |
| `openadmin_redis_connection_status` | gauge | Estado de la conexión de Redis, 1=normal, 0=anomalía |
| `openadmin_memory_usage_bytes` | gauge | Uso de memoria actual del proceso PHP (bytes) |

## 4. Panel

Todas las interfaces del panel de administración están montadas bajo el grupo `/admin` y pasan por tres middlewares: `AdminAuth` (autenticación JWT), `AdminPermission` (verificación de permisos RBAC) y `OperationLog` (registro de operaciones).

### 4.1 Datos del panel

```
GET /admin/dashboard
```

- **Autenticación**: JWT + RBAC
- **Caché**: Redis 5 minutos

**Ejemplo de respuesta**:
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
        "path": "/api/v1/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| Campos de stats | Tipo | Descripción |
|------|------|------|
| label | string | Nombre de la métrica |
| value | string | Valor de la métrica (tipo cadena) |
| icon | string | Nombre del icono de Material |
| color | string | Color de la tarjeta |
| trend | float? | Tasa de crecimiento día a día (porcentaje); solo "用户总数" tiene este campo |

| Campos de trends | Tipo | Descripción |
|------|------|------|
| dates | array{string} | Secuencia de fechas de los últimos 30 días |
| series | array{object} | Datos de la línea de tendencia; cada una incluye name (nombre), data (matriz de valores) y color (color) |

## 5. Gestión de usuarios

Todos los `id` devueltos por las interfaces de gestión de usuarios son cadenas cifradas con hashid. El campo de contraseña queda excluido de las respuestas. El teléfono y el correo electrónico se muestran enmascarados en las interfaces de lista y en texto plano en las interfaces de detalle (los campos cifrados de la base de datos se descifran automáticamente con el trait Encryptable).

### 5.1 Lista de usuarios

```
GET /admin/user
```

- **Autenticación**: JWT + RBAC

**Parámetros de consulta**:

| Parámetro | Tipo | Obligatorio | Valor predeterminado | Descripción |
|------|------|------|------|------|
| page | int | No | 1 | Número de página |
| limit | int | No | 15 | Número de elementos por página |
| keyword | string | No | | Palabra clave de búsqueda, coincide con nombre de usuario y nombre real |
| status | int | No | | Filtro de estado, 0=deshabilitado, 1=habilitado |

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | ID de usuario cifrado con hashid |
| username | string | Nombre de usuario |
| real_name | string | Nombre real |
| phone | string | Teléfono enmascarado (formato `138****5678`) |
| email | string | Correo electrónico enmascarado (formato `a***@example.com`) |
| status | int | 1=habilitado, 0=deshabilitado |
| last_login_at | string | Hora del último inicio de sesión (datetime) |
| created_at | string | Hora de creación (datetime) |

### 5.2 Crear usuario

```
POST /admin/user
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la petición**:
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

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| username | string | Sí | min:3, max:50 | Nombre de usuario (único) |
| password | string | Sí | min:6, max:32 | Contraseña (almacenada con bcrypt) |
| real_name | string | Sí | max:50 | Nombre real |
| phone | string | No | | Teléfono (almacenado cifrado con Encryptable) |
| email | string | No | | Correo electrónico (almacenado cifrado con Encryptable) |
| status | int | No | in:0,1 | Estado, por defecto 1 (habilitado) |

**Ejemplo de respuesta**:
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

**Posibles errores**:
- 422: el nombre de usuario ya existe
- 422: fallo de validación de parámetros (faltan campos obligatorios)

### 5.3 Detalle de usuario

```
GET /admin/user/{id}
```

- **Autenticación**: JWT + RBAC
- **Parámetro de ruta**: `{id}` es el ID de usuario cifrado con hashid

**Ejemplo de respuesta**:
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

En la interfaz de detalle, `phone` y `email` se devuelven en texto plano (en la base de datos están cifrados; el cast Encryptable los descifra automáticamente), sin enmascarar. `password` e `id_card` nunca aparecen en la respuesta.

**Posibles errores**:
- 404: el usuario no existe

### 5.4 Actualizar usuario

```
PUT /admin/user/{id}
```

- **Autenticación**: JWT + RBAC
- **Parámetro de ruta**: `{id}` es el ID de usuario cifrado con hashid

**Cuerpo de la petición**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| real_name | string | No | Nombre real; si no se envía, se mantiene el valor original |
| password | string | No | Nueva contraseña; si es una cadena vacía o no se envía, no se modifica |
| phone | string | No | Teléfono |
| email | string | No | Correo electrónico |
| status | int | No | 0=deshabilitado, 1=habilitado |

**Ejemplo de respuesta**:
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

**Posibles errores**:
- 404: el usuario no existe

### 5.5 Eliminar usuario

```
DELETE /admin/user/{id}
```

- **Autenticación**: JWT + RBAC
- **Parámetro de ruta**: `{id}` es el ID de usuario cifrado con hashid
- **Operación sensible**: requiere una segunda confirmación de contraseña

**Cuerpo de la petición**:
```json
{
  "password": "admin_password"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| password | string | Sí | Contraseña del usuario actualmente conectado (segunda confirmación) |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Se ejecuta un borrado lógico (SoftDeletes de Eloquent): los datos se marcan con `deleted_at` y no se eliminan físicamente.

**Posibles errores**:
- 404: el usuario no existe
- 422: las operaciones sensibles requieren introducir la contraseña de confirmación (password vacío)
- 422: fallo de verificación de contraseña (la contraseña no coincide)

### 5.6 Borrado de usuarios en lote

```
POST /admin/user/batch/destroy
```

- **Autenticación**: JWT + RBAC
- **Operación sensible**: requiere una segunda confirmación de contraseña

**Cuerpo de la petición**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| ids | array{string} | Sí | Matriz de IDs de usuario cifrados con hashid |
| password | string | Sí | Contraseña del usuario actualmente conectado (segunda confirmación) |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Se ejecuta un borrado lógico; `data.count` es el número real de elementos borrados.

**Posibles errores**:
- 422: seleccione los usuarios que desea eliminar (ids vacío)
- 422: ID no válido (fallo de decodificación hashid)
- 422: fallo de verificación de contraseña

### 5.7 Habilitar/deshabilitar usuarios en lote

```
POST /admin/user/batch/status
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la petición**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| ids | array{string} | Sí | Matriz de IDs de usuario cifrados con hashid |
| status | int | Sí | 0=deshabilitado, 1=habilitado |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

El `message` cambia dinámicamente según el valor de status: `"批量启用成功"` o `"批量禁用成功"`.

**Posibles errores**:
- 422: seleccione usuarios (ids vacío)
- 422: valor de estado no válido (status no es 0 ni 1)

## 6. Gestión de roles

### 6.1 Lista de roles

```
GET /admin/role
```

- **Autenticación**: JWT + RBAC

**Parámetros de consulta**:

| Parámetro | Tipo | Obligatorio | Valor predeterminado | Descripción |
|------|------|------|------|------|
| page | int | No | 1 | Número de página |
| limit | int | No | 15 | Número de elementos por página |

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | ID de rol cifrado con hashid |
| name | string | Nombre del rol |
| slug | string | Identificador del rol (único, se usa para la comprobación de permisos) |
| description | string | Descripción del rol |
| status | int | 1=habilitado, 0=deshabilitado |
| users_count | int | Número de usuarios con este rol |

### 6.2 Crear rol

```
POST /admin/role
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la petición**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| name | string | Sí | max:50 | Nombre del rol |
| slug | string | Sí | max:50 | Identificador del rol |
| description | string | No | | Descripción del rol, por defecto cadena vacía |
| status | int | No | | Estado, por defecto 1 |
| permission_ids | array{int} | No | | Matriz de IDs de permisos (IDs INT originales, no hashid) |

**Ejemplo de respuesta**:
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

### 6.3 Actualizar rol

```
PUT /admin/role/{id}
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la petición**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| name | string | No | Nombre del rol |
| description | string | No | Descripción |
| status | int | No | 0=deshabilitado, 1=habilitado |
| permission_ids | array{int} | No | Matriz de IDs de permisos; si se envía, se sincronizan (sobrescriben) los permisos del rol |

**Ejemplo de respuesta**:
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

### 6.4 Eliminar rol

```
DELETE /admin/role/{id}
```

- **Autenticación**: JWT + RBAC
- **Operación sensible**: requiere una segunda confirmación de contraseña

**Cuerpo de la petición**:
```json
{
  "password": "admin_password"
}
```

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Al eliminar, se deshacen automáticamente las relaciones del rol con todos los permisos y usuarios, y luego se elimina físicamente el registro del rol.

## 7. Gestión de permisos

Los permisos usan una estructura de árbol (auto-referencia mediante parent_id) y se dividen en tres tipos. La interfaz de lista devuelve el árbol de permisos completo.

### 7.1 Árbol de permisos

```
GET /admin/permission
```

- **Autenticación**: JWT + RBAC

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | Cifrado con hashid |
| parent_id | string | Hashid del permiso padre; "0" indica el nodo raíz |
| name | string | Nombre del permiso |
| slug | string | Identificador del permiso (identificador de ruta/botón) |
| type | int | 1=menú, 2=botón, 3=API |
| icon | string | Icono del menú (nombre de icono de Material) |
| path | string | Ruta del frontend |
| sort | int | Valor de orden (ascendente) |
| children | array? | Lista de subpermisos (recursiva); no se incluye si no hay nodos hijos |

### 7.2 Crear permiso

```
POST /admin/permission
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la petición**:
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

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| parent_id | int | No | | ID del permiso padre (tipo INT original), por defecto 0 |
| name | string | Sí | max:50 | Nombre del permiso |
| slug | string | Sí | max:100 | Identificador del permiso |
| type | int | Sí | in:1,2,3 | 1=menú, 2=botón, 3=API |
| icon | string | No | | Icono del menú, por defecto vacío |
| path | string | No | | Ruta del frontend, por defecto vacía |
| sort | int | No | | Valor de orden, por defecto 0 |

**Ejemplo de respuesta**:
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

### 7.3 Actualizar permiso

```
PUT /admin/permission/{id}
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la petición**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| name | string | No | Nombre del permiso |
| icon | string | No | Icono |
| path | string | No | Ruta |
| sort | int | No | Valor de orden |

### 7.4 Eliminar permiso

```
DELETE /admin/permission/{id}
```

- **Autenticación**: JWT + RBAC
- **Operación sensible**: requiere una segunda confirmación de contraseña

**Cuerpo de la petición**:
```json
{
  "password": "admin_password"
}
```

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Al eliminar se borran en cascada todos los subpermisos (registros con `parent_id` igual al ID del permiso actual) y se deshacen las relaciones con todos los roles.

## 8. Configuración del sistema

La configuración del sistema es única por la combinación `group` + `key`.

### 8.1 Lista de configuración

```
GET /admin/config
```

- **Autenticación**: JWT + RBAC

**Parámetros de consulta**:

| Parámetro | Tipo | Obligatorio | Valor predeterminado | Descripción |
|------|------|------|------|------|
| page | int | No | 1 | Número de página |
| limit | int | No | 15 | Número de elementos por página |
| group | string | No | | Filtrar por grupo de configuración |

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | hashid |
| group | string | Grupo de configuración (por ejemplo, `system`, `email`, `storage`) |
| key | string | Clave de configuración |
| value | string | Valor de configuración |
| type | string | Indicación del tipo de valor (`string`, `integer`, `boolean`, `json`, etc.) |
| description | string | Descripción de la configuración |

### 8.2 Crear configuración

```
POST /admin/config
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la petición**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| group | string | Sí | max:100 | Grupo de configuración |
| key | string | Sí | max:100 | Clave de configuración (única dentro del mismo grupo) |
| value | string | Sí | | Valor de configuración |
| type | string | No | | Tipo de valor, por defecto `string` |
| description | string | No | | Descripción de la configuración, por defecto vacía |

**Ejemplo de respuesta**:
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

**Posibles errores**:
- 422: el elemento de configuración ya existe (mismo group + key)

### 8.3 Actualizar configuración

```
PUT /admin/config/{id}
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la petición**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| value | string | No | Valor de configuración actualizado |
| type | string | No | Tipo de valor actualizado |
| description | string | No | Texto de descripción actualizado |

### 8.4 Eliminar configuración

```
DELETE /admin/config/{id}
```

- **Autenticación**: JWT + RBAC
- **Operación sensible**: requiere una segunda confirmación de contraseña

**Cuerpo de la petición**:
```json
{
  "password": "admin_password"
}
```

Elimina físicamente el registro de configuración.

## 9. Registros de operaciones

Los registros de operaciones son una interfaz de solo lectura; el middleware `OperationLog` los escribe automáticamente en cada petición POST/PUT/DELETE. Los campos almacenados incluyen `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Lista de registros de operaciones

```
GET /admin/log
```

- **Autenticación**: JWT + RBAC

**Parámetros de consulta**:

| Parámetro | Tipo | Obligatorio | Valor predeterminado | Descripción |
|------|------|------|------|------|
| page | int | No | 1 | Número de página |
| limit | int | No | 15 | Número de elementos por página |
| user_id | int | No | | Filtro exacto por ID de usuario (tipo INT original) |
| action | string | No | | Filtro exacto por acción |
| path | string | No | | Filtro difuso por ruta de petición |
| start_date | string | No | | Fecha de inicio (formato Y-m-d) |
| end_date | string | No | | Fecha de fin (formato Y-m-d) |

**Ejemplo de respuesta**:
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
        "path": "/api/v1/auth/login",
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

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | hashid |
| user_name | string | Nombre del usuario que realizó la operación (obtenido mediante la relación user; las operaciones sin inicio de sesión muestran "系统") |
| action | string | Descripción de la acción |
| method | string | Método HTTP (POST/PUT/DELETE) |
| path | string | Ruta de la petición |
| ip | string | IP del cliente |
| source | string | Origen de la petición |
| input | string | Cadena JSON de los parámetros de la petición (no incluye archivos) |
| created_at | string | Hora de la operación (datetime) |

## 10. Perfil

Las interfaces de perfil solo requieren autenticación JWT (no necesitan verificación de permisos RBAC; el middleware `AdminPermission` debe incluirlas en la lista blanca).

### 10.1 Actualizar información personal

```
PUT /admin/profile
```

- **Autenticación**: JWT

**Cuerpo de la petición**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| real_name | string | No | Nombre real |
| phone | string | No | Teléfono (almacenado cifrado con Encryptable) |
| email | string | No | Correo electrónico (almacenado cifrado con Encryptable) |

**Ejemplo de respuesta**:
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

En la respuesta, `phone` y `email` se devuelven en texto plano; `password` e `id_card` se han eliminado.

### 10.2 Cambiar contraseña

```
PUT /admin/profile/password
```

- **Autenticación**: JWT

**Cuerpo de la petición**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| old_password | string | Sí | | Contraseña actual |
| new_password | string | Sí | min:6, max:32 | Nueva contraseña |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Posibles errores**:
- 422: introduzca la contraseña antigua y la nueva
- 422: la contraseña antigua es incorrecta
- 422: la nueva contraseña debe tener entre 6 y 32 caracteres

### 10.3 Cerrar sesión

```
POST /admin/profile/logout
```

- **Autenticación**: JWT

**Cuerpo de la petición**: ninguno (sin requestBody; el token se lee de la cabecera Authorization)

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Lógica de cierre de sesión: se decodifica el JWT para obtener el tiempo de validez restante (exp - now) y el hash md5 de ese token se escribe en la lista negra de Redis `jwt_blacklist:{md5}` con TTL = tiempo de validez restante. Los tokens de la lista negra son interceptados por el middleware `AdminAuth`, que devuelve 401.

Sin token se devuelve 401. Si el token está caducado o no es válido (la decodificación lanza una excepción), se considera igualmente un cierre de sesión correcto.

## 11. Importación y exportación

### 11.1 Exportar Excel

```
POST /admin/export/excel
```

- **Autenticación**: JWT + RBAC
- **Tipo de respuesta**: descarga de archivo (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Cuerpo de la petición**:
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

| Campo | Tipo | Obligatorio | Valor predeterminado | Descripción |
|------|------|------|------|------|
| table | string | No | `admin_user` | Nombre de la tabla a exportar. Soportadas: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | No | | Matriz de nombres de columna a exportar; si está vacía, se exportan todas las columnas de la tabla |
| conditions | object | No | `{}` | Condiciones de filtro, pares clave-valor; se usan para la cláusula WHERE cuando el valor no está vacío |
| title | string | No | `数据导出` | Título del Excel (se muestra como nombre de la hoja) |

**Tablas y columnas soportadas**:

| table | Columnas disponibles |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Los campos sensibles `phone`, `email` e `id_card` se enmascaran automáticamente al exportar. Límite de datos: 10 000 filas. La primera fila del Excel queda fijada y con autofiltro.

### 11.2 Exportar PDF

```
POST /admin/export/pdf
```

- **Autenticación**: JWT + RBAC
- **Tipo de respuesta**: descarga de archivo (`application/pdf`, A4 horizontal)

**Cuerpo de la petición**:
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

O modo tabla:
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

| Campo | Tipo | Obligatorio | Valor predeterminado | Descripción |
|------|------|------|------|------|
| type | string | No | `table` | Tipo de exportación: `table` / `dashboard` |
| title | string | No | `数据导出` | Título del PDF |
| data | object | No | `{}` | Datos a exportar |

Con `type=dashboard`, `data` debe incluir la matriz `stats` (se renderiza como tarjetas); con `type=table`, `data` debe incluir las matrices `columns` y `rows`.

La plantilla del PDF incluye la información de copyright y la marca de tiempo de la exportación.

### 11.3 Importar usuarios (Excel)

```
POST /admin/import/users
```

- **Autenticación**: JWT + RBAC
- **Tipo de petición**: `multipart/form-data` (subida de archivos)

**Campos del formulario**:

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| file | file | Sí | Formato `.xlsx` o `.xls` |

**Requisitos de las columnas del Excel**:

| Nombre de columna | Obligatorio | Descripción |
|------|------|------|
| username | Sí | Nombre de usuario (único) |
| password | Sí | Contraseña (almacenada con hash bcrypt) |
| real_name | Sí | Nombre real |
| phone | No | Teléfono |
| email | No | Correo electrónico |
| status | No | Estado, por defecto 1 |

La fila 1 son los títulos de columna (no distingue mayúsculas/minúsculas); a partir de la fila 2 están los datos.

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| total | int | Número total de filas (sin contar la fila de títulos) |
| success | int | Número de importaciones correctas |
| failed | int | Número de importaciones fallidas |
| errors | array | Detalle de los fallos; cada elemento incluye row (número de fila del Excel) y reason (motivo del fallo) |

## 12. Subida de archivos

```
POST /admin/upload
```

- **Autenticación**: JWT + RBAC
- **Tipo de petición**: `multipart/form-data`

**Campos del formulario**:

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| file | file | Sí | Archivo a subir |

**Tipos de archivo permitidos**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Tamaño máximo de archivo**: 10MB

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Los archivos se almacenan por fecha en directorios bajo `public/upload/{Y-m-d}/`; el nombre de archivo es `md5(uniqid) + extensión original`. `url` es una ruta relativa a la raíz del sitio.

**Posibles errores**:
- 422: seleccione un archivo (no se ha subido ninguno)
- 422: tipo de archivo no soportado
- 422: el tamaño del archivo no puede superar los 10MB
- 500: fallo de subida del archivo (archivo no válido)

## 13. Cabeceras de respuesta

Todas las interfaces (inyectadas en la capa de middleware global) incluyen las siguientes cabeceras de respuesta:

| Cabecera | Descripción |
|----|------|
| `X-RateLimit-Limit` | Límite de peticiones (número) |
| `X-RateLimit-Remaining` | Peticiones restantes |
| `X-RateLimit-Reset` | Marca de tiempo del reinicio de la ventana de límite |
| `Retry-After` | Solo se devuelve cuando se activa el límite; segundos recomendados de espera |
| `X-Content-Type-Options` | `nosniff` (por defecto en webman; prohíbe la detección de MIME) |
| `X-Frame-Options` | `DENY` (proporcionado por el middleware CORS/configuración base de webman) |

Detalles del límite de peticiones:
- Límite global por defecto: 60 peticiones/minuto / IP+ruta
- Endpoint de inicio de sesión `/api/v1/auth/login`: 10 peticiones/minuto
- Endpoint de registro `/api/v1/auth/register`: 5 peticiones/minuto
- Usa un algoritmo de ventana deslizante atómico en Redis (Lua ZSET) para evitar condiciones de carrera TOCTOU
- Si Redis no está disponible, fail open (se deja pasar) para no bloquear las peticiones

## 14. Flujo de autenticación

Secuencia de autenticación completa:

```
1. El cliente solicita POST /api/v1/captcha/generate
    ↓
   El servidor devuelve: key + type(click|slider|rotate) + imagen base64 + extra(datos según el tipo)
   
2. El usuario completa la interacción del captcha (clic/arrastre/rotación) y el cliente recopila la respuesta
   
3. El cliente solicita POST /api/v1/captcha/verify
   (Cabecera de petición: Content-Type: application/json)
   Cuerpo de la petición: { key, type, clicks }
   - type=click:  clicks = [{x, y}, ...]        // matriz de coordenadas
   - type=slider: clicks = 120                   // desplazamiento en X
   - type=rotate: clicks = 315                   // ángulo de rotación
    ↓
   Servidor:
   a. Lee los datos de captcha:key del almacenamiento (TTL 300s)
   b. Valida la respuesta según el type (click: distancia euclidiana ≤18px / slider: ±4px / rotate: ±5°)
   c. Validación correcta → escribe Redis `captcha_verified:{key}` = 1 (TTL 300s)
   d. Validación fallida → devuelve 422, contador +1, tras 3 intentos la key queda invalidada
    ↓
   El servidor devuelve: { valid: true/false }

4. El cliente solicita POST /api/v1/auth/login
   (Cabecera de petición: Content-Type: application/json)
   Cuerpo de la petición: { username, password(cifrada), captcha_key }
    ↓
   Servidor:
   a. Validación de parámetros → 422
   b. Comprueba si existe captcha_verified:{key} → 422
   c. Elimina captcha_verified:{key} (uso único)
   d. Descifra la contraseña: EncryptionService::decrypt(password) → texto plano
   e. Valida las credenciales del usuario (password_verify) → 401
   f. Comprueba el estado de la cuenta → 403/429
   g. Emite JWT (access + refresh) → 200
   h. Actualiza last_login_at / last_login_ip
    ↓
   El cliente guarda: access_token, refresh_token, expires_in

5. Las peticiones posteriores llevan el JWT
   Cabecera de petición: Authorization: Bearer <access_token>
    ↓
   Middleware AdminAuth:
   a. Extrae el token Bearer
   b. Comprueba la lista negra (Redis jwt_blacklist:{md5}) → 401
   c. Decodifica el JWT y valida la caducidad → 401
   d. Establece $request->adminId = campo sub
    ↓
   Middleware AdminPermission:
   a. Resuelve el identificador de permiso para la ruta del recurso
   b. Consulta los roles del usuario → permisos de los roles y compara
   c. Sin permiso → 403
    ↓
   El Controller procesa la petición
    ↓
   Response + cabeceras X-RateLimit-*

6. Renovación antes de que caduque el access token
   El cliente solicita POST /api/v1/auth/refresh
   Cuerpo de la petición: { refresh_token: "..." }
    ↓
   El servidor decodifica refresh_token → emite nuevos access + refresh
    ↓
   El cliente actualiza los tokens locales

7. Cierre de sesión
   El cliente solicita POST /admin/profile/logout
   Cabecera de petición: Authorization: Bearer <access_token>
    ↓
   Servidor:
   a. Decodifica el JWT y obtiene el TTL restante
   b. Escribe en la lista negra de Redis: jwt_blacklist:{md5(token)} = 1, TTL = tiempo de validez restante
   c. Devuelve éxito
```

### Estructura del JWT

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, TTL por defecto 7200 segundos (controlado por la configuración JWT `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, TTL por defecto 1209600 segundos (controlado por la configuración JWT `refresh_expire`, es decir, 14 días)

### Gestión de seguridad

- Las contraseñas se almacenan con hash `PASSWORD_BCRYPT`
- La capa de transporte de contraseñas usa cifrado AES-256-CBC-HMAC (el cliente cifra → el servidor descifra), con retroceso compatible a texto plano
- Los campos sensibles (phone, email, id_card) se cifran y descifran de forma transparente en la capa de base de datos con `erikwang2013/encryptable`
- Los IDs de la capa API se transmiten cifrados con `erikwang2013/hashids` para evitar exponer la secuencia de IDs original de snowflake
- SecurityFilter analiza globalmente XSS, inyección SQL, traversal de rutas e inyección de comandos; 5 coincidencias de la misma IP en 60 segundos activan una lista negra temporal de 15 minutos
- Las operaciones sensibles (eliminar usuarios, roles, permisos, configuración) requieren la segunda confirmación de la contraseña del usuario actualmente conectado
- Límite de sesiones concurrentes: un mismo usuario puede tener como máximo 3 tokens válidos; cuando inicia sesión en el 4.º dispositivo, el token más antiguo se añade a la fuerza a la lista negra
- Bloqueo de cuenta: 5 inicios de sesión fallidos consecutivos activan un bloqueo de 15 minutos; durante el bloqueo se devuelve 429

### Arquitectura de middleware

El middleware global se aplica a todas las peticiones, en este orden:

```
Cors (preprocesamiento CORS + cabeceras de respuesta)
  → Locale (detección de idioma Accept-Language / ?lang=zh_CN|en)
  → SecurityFilter (restricción de métodos HTTP/tamaño del cuerpo/validación de Content-Type/XSS/inyección SQL/traversal de rutas/inyección de comandos/bloqueo de ataques CSRF)
  → RateLimit (límite de peticiones con ventana deslizante Redis + bloqueo de cuenta: 5 inicios de sesión fallidos bloquean 15 minutos)
  → AdminAuth (autenticación JWT + lista negra, grupo de rutas /admin)
  → AdminPermission (autorización RBAC / caché Redis de 60s, grupo de rutas /admin)
  → OperationLog (registro automático de POST/PUT/DELETE, incluye detección de origen, grupo de rutas /admin)
```

`/health` y `/api/docs` son endpoints públicos y solo pasan por `Cors → SecurityFilter → RateLimit`.

Mejoras de seguridad:
- **Bloqueo de cuenta**: 5 inicios de sesión fallidos consecutivos bloquean la cuenta durante 15 minutos; durante el bloqueo, el inicio de sesión devuelve 429
- **Límite de sesiones concurrentes**: un mismo usuario puede tener como máximo 3 tokens válidos; al superarse, el token más antiguo se añade automáticamente a la lista negra
- **security.txt**: `GET /.well-known/security.txt` ofrece la información de contacto de seguridad estándar RFC 9116
- **Configuración de seguridad de Nginx**: consulte `docs/nginx-security.conf` para ver un ejemplo completo de endurecimiento del proxy inverso

### Detección del origen de la operación

El middleware OperationLog identifica automáticamente la plataforma del cliente y la escribe en el campo `source` del registro de operaciones:

| Plataforma | Método de detección |
|------|---------|
| `ipados` | UA contiene iPad |
| `macos` | UA contiene Macintosh/Mac OS |
| `windows` | UA contiene Windows |
| `linux` | UA contiene Linux (no Android) |
| `ios` | UA contiene iPhone / iOS / CFNetwork |
| `android` | UA contiene Android |
| `harmonyos` | UA contiene HarmonyOS / OpenHarmony o la cabecera `X-Client-Platform` lo declara explícitamente |
| `web` | Por defecto (no coincide con ninguna de las plataformas anteriores) |

> Detección en dos niveles: cabecera `X-Client-Platform` (declarada por las apps nativas) → inferencia automática por User-Agent (respaldo). El campo `source` de la consulta de registros de operaciones `GET /admin/log` indica el origen.

## 15. Despliegue y operaciones

### Docker Compose

La raíz del proyecto incluye `docker-compose.yml`, que orquesta 5 servicios (Nginx, aplicación webman, MySQL, Redis, Elasticsearch). PHP se construye con `Dockerfile` (basado en `php:8.3-cli`, con OPcache habilitado).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` define el pipeline de integración continua con GitHub Actions:
- Comprobación de sintaxis con `php -l`
- Pruebas unitarias PHPUnit
- Análisis estático con `flutter analyze`

### Copia de seguridad de la base de datos

El directorio `database/backup/` ofrece scripts de copia de seguridad y restauración:
- `backup.sh` — copia comprimida con mysqldump + gzip, limpia automáticamente las copias de más de 30 días
- `restore.sh` — restauración interactiva, lista las copias existentes para que el usuario elija

### Configuración de seguridad de Nginx

Para el despliegue en producción, consulte `docs/nginx-security.conf` para el endurecimiento de seguridad del proxy inverso.

## 16. Interfaz gRPC interna (e-cat → PHP worker)

Solo para el gateway e-cat. En PHP es un servicio workerman h2c (`tcp://0.0.0.0:8792`, proceso `internal_grpc`). El contrato se define en `infrastructure/tracking-gateway/proto/internal.proto` (package `internal.v1`), los mensajes se codifican en protobuf y los metadata deben llevar `x-internal-token` (valor de `config/logistics.php` — `internal_token`, sobrescrito en producción por la variable `INTERNAL_TOKEN`). Un token ausente/incorrecto devuelve `code=401 UNAUTHORIZED`.

### 16.1 Resumen de RPC

| RPC | Petición | Respuesta | Descripción |
|---|---|---|---|
| `Detect` | `{tracking_no}` | `{code, carrier_code, channel, confidence}` | Detección de transportista; si no se reconoce → `404 CARRIER_NOT_DETECTED` |
| `Query` | `{carrier_code, tracking_no, credential_id?}` | `{code, query_no, carrier_code, tracking_no, status, delivered_at, estimated_delivery_at, latest_description, raw_status, events[]}` | Consulta de seguimiento; `credential_id` opcional (para multi-credenciales, debe coincidir con `carrier_code`, si no `400 INVALID_PARAMS`). Cada consulta se guarda en `logistics_tracking_query` |
| `Carriers` | `{}` | `{code, carriers[]}` | Todos los transportistas del registro `{carrier_code, channel}` |

### 16.2 Contrato de errores (campo `code` en la respuesta, status gRPC siempre 0)

| `code` | `error_code` | Escenario |
|---|---|---|
| 400 | `INVALID_PARAMS` | Faltan parámetros / credential_id no coincide |
| 401 | `UNAUTHORIZED` | Token interno incorrecto |
| 404 | `CARRIER_NOT_FOUND` / `TRACKING_NOT_FOUND` / `CARRIER_NOT_DETECTED` | Transportista no registrado / seguimiento no encontrado / no detectable |
| 5001 | `CARRIER_AUTH_ERROR` / `CARRIER_NETWORK_ERROR` / `CARRIER_ERROR` | Error de credenciales del transportista / error de red / error de negocio (incluye mensaje estructurado del proveedor, p. ej. `[SF-INTERNATIONAL A1001] los parámetros obligatorios no pueden estar vacíos`) |
| 500 | `INTERNAL_ERROR` | Excepción interna |


## 17. API externa del gateway e-cat

Gateway de alta frecuencia en Rust (`infrastructure/tracking-gateway`), escucha en `0.0.0.0:8080` (por defecto), las peticiones llevan la cabecera `X-API-Key` (config `api_keys`). El gateway gestiona la autenticación API-Key, el caché Redis (prefijo `logistics:`), el rate limiting, el circuit breaker por transportista y el reenvío round-robin a los workers; las credenciales nunca llegan al gateway.

| 端点 | 说明 |
|---|---|
| `GET /v1/health` | Comprobación de salud (punto público, sin clave) |
| `GET /v1/openapi.json` | Documentación OpenAPI 3.0 (punto público, sin clave) |
| `GET /v1/carriers` | Lista de transportistas (reenvía al gRPC PHP `InternalService.Carriers`) |
| `POST /v1/tracking/query` | Consulta de seguimiento (`{"carrier_code": "...", "tracking_no": "..."}`); sin `carrier_code` el gateway detecta automáticamente (detect se hace dentro de query) |
| `GET /v1/tracking/{query_no}` | Último resultado de seguimiento por número de consulta |
| `POST /v1/subscriptions` | Registrar suscripción de callback (`{"carrier_code", "callback_url", "event_type"?}`, `event_type` por defecto `tracking.update`) |


Semántica adicional del gateway: superar `rate_limit` (por defecto 100/60s) devuelve `429`; fallos consecutivos del transportista activan el circuit breaker (por defecto OPEN 60s tras 5 errores 5xx/transporte) → `503`; worker inalcanzable → `502`. Gateway↔worker es gRPC interno (h2c, `0.0.0.0:8792`), autenticado con el secreto compartido `x-internal-token`, ver sección 16.

### 17.1 Envoltura de respuesta y errores

Todos los puntos devuelven la envoltura unificada `{"code": 0, "message": "ok", "data": ...}`; `code != 0` es error y `data` se omite. El cuerpo de un error de negocio puede llevar `error_code` / `error_message` (diagnóstico del transportista, transmitido tal cual por el gateway); `code` coincide con el estado HTTP:

| HTTP | Significado |
|---|---|
| 400 | Error de parámetros (`tracking_no` ausente / parámetros de suscripción inválidos) |
| 401 | Clave ausente o inválida |
| 404 | Número de consulta no existe |
| 429 | Rate limit (por defecto 100/60s por clave), reintentar con backoff |
| 502 | Worker inalcanzable o fallo del proveedor |
| 503 | Circuit breaker del transportista, reintentar con backoff |

### 17.2 Ejemplos de llamada (curl)

Clave demo `demo-api-key`, gateway por defecto `http://127.0.0.1:8080`:

```bash
# Comprobación de salud (punto público, sin clave)
curl http://127.0.0.1:8080/v1/health

# Documentación OpenAPI (punto público, sin clave)
curl http://127.0.0.1:8080/v1/openapi.json

# Lista de transportistas
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/carriers

# Consulta de seguimiento (transportista opcional)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "tracking_no": "LX123456789CN"}' \
  http://127.0.0.1:8080/v1/tracking/query

# Último resultado por número de consulta
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/tracking/{query_no}

# Registrar suscripción (la URL de callback debe ser http/https pública; el gateway rechaza loopback/redes privadas)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "callback_url": "https://example.com/cb", "event_type": "tracking.update"}' \
  http://127.0.0.1:8080/v1/subscriptions
```

### 17.3 Ejemplos de llamada (SDK)

Hay cinco SDK sin dependencias en el directorio `sdk/` del repositorio (Python / PHP / Node.js / Go / Rust, copiar y usar):

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

Los SDK lanzan una excepción (`TrackingApiError`) cuando `code != 0` en la envoltura o hay fallo de red, con `code` / `message` / `error_code` / `error_message` / `http_status`; reintentar con backoff en errores de rate limit y circuit breaker, no reintentar errores del lado del transportista. Uso completo en `sdk/README.md`.
