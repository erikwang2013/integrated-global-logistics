# Panel de administración abierto — Documento de diseño

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](DESIGN.md) | [English](DESIGN.en.md) | [한국어](DESIGN.ko.md) | [Русский](DESIGN.ru.md) | [Deutsch](DESIGN.de.md) | [Français](DESIGN.fr.md) | [Español](DESIGN.es.md) | [Português](DESIGN.pt.md) | [हिन्दी](DESIGN.hi.md) | [العربية](DESIGN.ar.md) | [বাংলা](DESIGN.bn.md) | [Bahasa Indonesia](DESIGN.id.md) | [日本語](DESIGN.ja.md)

> Para los diagramas Mermaid detallados, consulte [ARCHITECTURE.es.md](ARCHITECTURE.es.md) (se renderizan automáticamente en GitHub/GitLab/VS Code).

## 1. Arquitectura del sistema

> **Lista de funciones**: autenticación(login/register/refresh/logout + bloqueo de cuenta + límite de sesiones) | panel(caché Redis) | CRUD de usuarios + en lote + importación | roles y permisos(RBAC) | configuración del sistema | auditoría de operaciones(8 orígenes) | archivos(subida + exportación + enmascarado) | seguridad(18 capas de defensa) | operaciones(health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Arquitectura del backend

### 2.1 Diseño por capas

| Capa | Directorio | Responsabilidad |
|---|------|------|
| Rutas | `config/route.php` | Asignación de URLs a controllers, enlace de middleware, rutas versionadas |
| Middleware | `app/middleware/` | Bloqueo de ataques(SecurityFilter), límite de peticiones(RateLimit), autenticación(JWT), autorización(RBAC), versión de API |
| Controllers | 14: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs (panel de administración) + Captcha/Auth (API v1) | Validación de parámetros de petición, lógica de negocio, formateo de respuestas |
| Servicios de negocio | `app/service/` | Lógica de negocio reutilizable (reservado) |
| Modelos de datos | `app/model/` | Mapeo ORM, relaciones, cifrado/descifrado de campos |
| Utilidades comunes | `app/common/` | Servicios Hashids, Snowflake y Encryption |

### 2.2 Ciclo de vida de una petición

```
Petición del cliente
  │
  ▼
Servidor HTTP webman (workerman)
  │
  ▼
Coincidencia de rutas
  │
  ▼
Cadena de middleware:
  Locale ──────────────► Detección de idioma Accept-Language / ?lang=
  │
  ▼
  SecurityFilter ──────► Comprobación de métodos HTTP → 405 (solo se permiten GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     Bloqueo de ataques XSS/inyección SQL/traversal de rutas/inyección de comandos/CSRF (403)
  ▼
  RateLimit ───────────► Límite de peticiones con ventana deslizante Redis
  │ (si falla devuelve 429 + cabecera Retry-After)
  ▼
  AdminAuth ──────────► Validación JWT, inyecta $request->adminId
  │ (si falla devuelve 401)
  ▼
  AdminPermission ────► Verificación de permisos RBAC (caché Redis de 60s)
  │ (si falla devuelve 403)
  ▼
  OperationLog ───────► Registro de operaciones (POST/PUT/DELETE), detección automática del origen
  │
  ▼
Controller::method()
  │
  ├─► Validación de parámetros (validator)
  ├─► Confirmación de operaciones sensibles (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Operación con Model (cifrado/descifrado automático con encryptable)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 Ciclo de vida de los IDs

```
Generación (Snowflake) → almacenamiento (MySQL BIGINT) → transmisión (codificación Hashids) → externo (cadena hash)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Sistema de cifrado de datos

```
Capa de transmisión (encryption)     — AES-256-CBC, clave independiente
Capa de almacenamiento (encryptable) — AES-128-ECB, clave independiente, procesado automático con Model $casts
Capa de presentación (mask)          — teléfono: 138****1234, correo: a***@example.com
```

## 3. Diseño de la base de datos

### 3.1 Relaciones ER

```
logistics_admin_user ──┬── logistics_admin_user_role ──┬── logistics_admin_role
  (usuarios)      │    (relación usuario-rol) │     (roles)
                  │                          │
                  │                    logistics_admin_role_permission
                  │                     (relación rol-permiso)
                  │                          │
                  │                          ▼
                  │                    logistics_admin_permission
                  │                      (permisos/menús)
                  │
                  ▼
           logistics_operation_log
             (registros de operaciones)

logistics_system_config (configuración del sistema) — tabla independiente
```

### 3.2 Estructura de las tablas principales

| Nombre de la tabla | Número de campos | Descripción |
|------|-------|------|
| `logistics_admin_user` | 14 | Usuarios del panel; phone/email/id_card se almacenan cifrados; soporta borrado lógico |
| `logistics_admin_role` | 7 | Roles, slug único |
| `logistics_admin_permission` | 10 | Árbol de permisos (parent_id autoreferenciado), type: 1=menú 2=botón 3=API |
| `logistics_admin_user_role` | 2 | Tabla intermedia muchos a muchos usuario-rol |
| `logistics_admin_role_permission` | 2 | Tabla intermedia muchos a muchos rol-permiso |
| `logistics_system_config` | 8 | Configuración de pares clave-valor, group+key único conjunto |
| `logistics_operation_log` | 9 | Registros de auditoría de operaciones (incluye el origen source) |

### 3.3 Normas de las claves primarias

- Tipo: `BIGINT UNSIGNED NOT NULL`
- Característica: **no autoincremental**, generada en la capa de aplicación con el algoritmo Snowflake
- Ventajas: única globalmente, amigable para sistemas distribuidos, incremento de tendencia favorable a los índices, no expone el volumen de negocio
- Configuración: datacenter_id(0-31) + worker_id(0-31), soporta 1024 nodos concurrentes

## 4. Diseño de la API

### 4.1 Normas de URLs

```
Interfaces públicas:  /api/v1/captcha/{generate|verify}
                      /api/v1/auth/{login|register|refresh}

Panel de administración:  /admin/{resource}[/{hashid}]
                          /admin/export/{excel|pdf}

Rutas de recursos:
  GET    /admin/user          → lista
  POST   /admin/user          → creación
  GET    /admin/user/{hashid} → detalle
  PUT    /admin/user/{hashid} → actualización
  DELETE /admin/user/{hashid} → borrado (requiere confirmación de contraseña)

Configuración del sistema:  /admin/config[/{hashid}]
Registros de operaciones:   /admin/log
Perfil:                     /admin/profile[/password|/logout]
Importación:                /admin/import/users
Subida:                     /admin/upload
En lote:                    /admin/user/batch/{destroy|status}
Documentación:              /api/docs     (OpenAPI 3.0)
Health:                     /health
```

### 4.2 Estrategia de versiones de la API

La versión de la API se controla mediante una cabecera y **no aparece en la ruta de la URL**:

```http
```

| Mecanismo | Descripción |
|------|------|
| Versión por defecto | Si no se envía la cabecera `API-Version`, por defecto es `v1` |
| Validación | El middleware `ApiVersion` valida; las versiones no soportadas devuelven 400 |
| Rutas | La función auxiliar `v()` resuelve dinámicamente la clase del controller según la versión |
| Directorios | Los controllers se organizan por versión: `app/api/{version}/controller/` |

Ejemplo de ampliación — añadir una API v2:
1. Crear `app/api/v2/controller/AuthController.php`
2. Añadir `'v2'` a la constante `SUPPORTED` del middleware `ApiVersion`
3. No es necesario modificar las definiciones de rutas

```bash
# Usar v1
curl /api/v1/auth/login

# Usar v2
curl /api/v1/auth/login

# Sin cabecera, por defecto v1
curl /api/v1/auth/login
```

### 4.3 Estrategia de límite de peticiones

Basada en el algoritmo de ventana deslizante con Redis Sorted Set, ejecutada con scripts Lua atómicos:

| Interfaz | Límite |
|------|------|
| Por defecto | 60 peticiones/minuto/IP/ruta |
| POST /api/v1/auth/login | 10 peticiones/minuto |
| POST /api/v1/auth/register | 5 peticiones/minuto |

Al superar el límite se devuelve 429; las cabeceras de respuesta incluyen X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 Respuesta unificada

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Significado | Escenario |
|------|------|---------|
| 0 | Éxito | Respuesta normal |
| 400 | Error de parámetros | Formato de petición incorrecto |
| 401 | No autenticado | Token ausente/caducado/no válido |
| 403 | Sin permiso | El rol del usuario no incluye el permiso requerido |
| 404 | No existe | Recurso no encontrado |
| 422 | Fallo de validación | Los parámetros del formulario no cumplen las reglas / fallo de confirmación de contraseña |
| 500 | Error del servidor | Excepción inesperada |

### 4.5 Flujo de autenticación (con captcha de clic)

```
Cliente                                Servidor
  │                                    │
  │  ① POST /api/v1/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② el usuario hace clic en las     │
  │     posiciones del texto           │
  │                                    │
  │  ③ POST /api/v1/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 Modelo de permisos (RBAC)

```
  Usuario ──┬── Rol ──┬── Permiso
  User     Role      Permission
                 │
                 ├── type=1: menú (controla la visibilidad de la barra lateral)
                 ├── type=2: botón (controla las acciones de la página)
                 └── type=3: API  (controla el acceso a las interfaces)

  Formato del identificador de permiso: {method}.{path}
  Ej.: get.admin/user  post.admin/user  delete.admin/user
  Identificador del superadministrador: * (omite todas las comprobaciones de permisos)
```

### 4.7 Segunda confirmación de operaciones sensibles

Las operaciones sensibles como eliminar usuarios, roles y permisos requieren enviar la contraseña del usuario actual en el cuerpo de la petición para la reverificación de identidad:

```
Cliente                            Servidor
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → si la contraseña es incorrecta devuelve 422
  │                                │ → si la contraseña es correcta continúa la ejecución
  │◄── 200 { code: 0 }           │
```

El frontend muestra un diálogo de confirmación antes de activar la operación de borrado, recopila la contraseña del usuario y luego envía la petición.

## 5. Diseño del frontend

### 5.1 Panel de administración Flutter Web

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

Características: barra lateral plegable, Material 3 con doble tema, tablas de datos de alta densidad, diálogos, interacciones de hover con el ratón

### 5.2 Móvil HarmonyOS

Rutas de páginas:

| Página | Ruta | Descripción |
|------|------|------|
| LoginPage | `pages/LoginPage` | Usuario/contraseña + captcha de clic para iniciar sesión |
| DashboardPage | `pages/DashboardPage` | Tarjetas de estadísticas + operaciones recientes |
| UserListPage | `pages/UserListPage` | Lista de usuarios, búsqueda + pull-to-refresh + carga al deslizar |
| UserDetailPage | `pages/UserDetailPage` | Crear/editar/ver/eliminar (confirmación con AlertDialog) |
| ProfilePage | `pages/ProfilePage` | Perfil, cerrar sesión (confirmación con AlertDialog) |

Flujo de datos: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Diseño de seguridad

### 6.1 Defensa en profundidad

| Nivel | Medida |
|------|------|
| Restricción de métodos | SecurityFilter con lista blanca de métodos HTTP; solo se permiten GET/POST/PUT/DELETE/OPTIONS/HEAD; los métodos no estándar devuelven 405 |
| Bloqueo de ataques | Middleware SecurityFilter: detección y bloqueo de XSS/inyección SQL/traversal de rutas/inyección de comandos/CSRF |
| Verificación humano-máquina | Captcha de clic (Click Captcha), validación obligatoria en login/registro |
| Bloqueo de cuenta | 5 inicios de sesión fallidos consecutivos bloquean la cuenta 15 minutos; durante el bloqueo se devuelve 429 |
| Límite de sesiones | Un mismo usuario puede tener como máximo 3 tokens concurrentes; al superarse, el token más antiguo se añade automáticamente a la lista negra |
| Límite de peticiones | Middleware RateLimit, ventana deslizante Redis, atómico con Lua |
| CSP | La cabecera Content-Security-Policy restringe el origen de los recursos, previene XSS e inyección de datos |
| Confirmación de operaciones | Las operaciones sensibles (p. ej. borrado) requieren introducir la contraseña del usuario actual para la segunda confirmación |
| Transmisión | HTTPS + JWT Bearer Token |
| IDs de interfaz | Cifrado con Hashids; no se puede deducir el ID real desde el exterior |
| Cuerpo de petición | Cifrado AES-256-CBC de campos sensibles |
| Base de datos | Claves primarias BIGINT (no exponen el incremento automático) |
| Base de datos | Almacenamiento cifrado AES-128-ECB de campos sensibles |
| Autenticación | JWT HS256, caducidad de 2h + refresh token |
| Autorización | RBAC, control de permisos con granularidad method.path |
| Auditoría | OperationLog registra todas las operaciones (incluye la detección automática del origen source) |

### 6.2 Gestión de claves

```
JWT_SECRET          → inyectada por variable de entorno, cadena aleatoria de 64 caracteres
HASHIDS_SALT        → valor salt único; si se filtra hay que cambiarla globalmente
ENCRYPTION_KEY      → clave de cifrado de la transmisión de la API, 32 bytes
ENCRYPTABLE_KEY     → clave de cifrado del almacenamiento en DB, independiente de la clave de transmisión
SCOUT_HOSTS         → dirección de ES, despliegue en intranet
```

### 6.3 Protección de datos sensibles

| Escenario | Campo | Medida |
|------|------|------|
| Lista | phone | Enmascarado: 138****1234 |
| Lista | email | Enmascarado: a***@example.com |
| Detalle | phone/email | Interfaz que descifra |
| Exportación Excel | phone/email | Se exportan enmascarados |
| Exportación PDF | Todos los campos | Enmascarado + marca de agua de copyright no removible |
| Almacenamiento | phone/email/id_card | Cifrado a texto cifrado con encryptable |

## 7. Diseño de la exportación

### 7.1 Exportación de Excel

```
Petición: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() consulta los datos (límite 10000)
  → enmascara los campos sensibles
  → construcción con PhpSpreadsheet (encabezado azul con texto blanco + primera fila fijada + autofiltro)
  → escritura en runtime/tmp/ → respuesta de descarga
```

### 7.2 Exportación de PDF

```
Petición: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + CSS en línea + copyright en el encabezado + copyright no removible en el pie
  → renderizado con Dompdf en A4 horizontal
  → escritura en runtime/tmp/ → respuesta de descarga
```

## 8. Arquitectura de despliegue

### 8.1 Topología recomendada

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                    Archivos estáticos: Flutter Web build/
```

### 8.2 Docker Compose (recomendado para producción)

El `docker-compose.yml` de la raíz del proyecto orquesta todos los servicios de la topología anterior:

| Servicio | Imagen/construcción | Puerto | Descripción |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Proxy inverso + archivos estáticos + Gzip |
| `app` | Construcción local con `Dockerfile` | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Base de datos principal, volúmenes de datos persistentes |
| `redis` | redis:7-alpine | 6379 | Caché / límite de peticiones / captcha |
| `elasticsearch` | elasticsearch:8.x | 9200 | Búsqueda de texto completo |

Antes de iniciar, sustituya las claves del `docker-compose.yml` (`JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY`, etc.) por cadenas aleatorias.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

La integración continua con GitHub Actions se define en `.github/workflows/ci.yml`:
- Comprobación de sintaxis PHP (`php -l`)
- Pruebas unitarias PHPUnit
- Análisis estático de Flutter (`flutter analyze`)

### 8.4 Copia de seguridad de la base de datos

`database/backup/backup.sh` — copia con mysqldump + gzip, limpia automáticamente las copias de más de 30 días.
`database/backup/restore.sh` — selección interactiva y restauración de la copia.

### 8.5 Monitorización

El endpoint `GET /metrics` (`MetricsController`) expone 5 métricas gauge en Prometheus text format: número total de peticiones HTTP, usuarios activos, estado de la conexión de base de datos/Redis y uso de memoria.

### 8.6 Requisitos del entorno

| Componente | Versión mínima | Configuración recomendada |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ con OPcache habilitado |
| MySQL | 8.0+ | 8.0+ con replicación maestro-esclavo |
| Elasticsearch | 7.x | 8.x con clúster de 3 nodos |
| Redis | 6.x | 7.x en modo Sentinel |
| Nginx | 1.20+ | Proxy inverso + gzip + SSL |
| Flutter SDK | 3.41+ | Última versión estable |
| HarmonyOS | API 12 | DevEco Studio 5.x |
