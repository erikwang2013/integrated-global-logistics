> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](CLAUDE.md) | [English](CLAUDE.en.md) | [한국어](CLAUDE.ko.md) | [Русский](CLAUDE.ru.md) | [Deutsch](CLAUDE.de.md) | [Français](CLAUDE.fr.md) | [Español](CLAUDE.es.md) | [Português](CLAUDE.pt.md) | [हिन्दी](CLAUDE.hi.md) | [العربية](CLAUDE.ar.md) | [বাংলা](CLAUDE.bn.md) | [Bahasa Indonesia](CLAUDE.id.md) | [日本語](CLAUDE.ja.md)

# Panel de administración abierto (open-admin)

Sistema de panel de administración full-stack basado en webman v2 + Flutter.

## Declaración de copyright

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **No modificable, no removible, irrevocable.** Todos los archivos nuevos deben incluir la declaración de copyright anterior como comentario en la cabecera del archivo.

## Lista de funciones

| Dominio | Función |
|----|------|
| Autenticación | Inicio de sesión/registro/refresco/cierre de sesión + captcha + bloqueo de cuenta + límite de sesiones |
| Panel de control | Estadísticas en tiempo real/tendencias/distribución/registros (caché Redis de 5 min) |
| Usuarios | CRUD + borrado masivo/habilitar-deshabilitar + importación Excel |
| Roles y permisos | CRUD + árbol de permisos + autorización RBAC method.path |
| Configuración del sistema | CRUD de pares clave-valor |
| Auditoría de operaciones | Consulta de registros + detección automática de origen en 8 plataformas |
| Archivos | Carga + exportación Excel/PDF (enmascarado de datos sensibles) |
| Seguridad | Defensa en profundidad de 18 capas (XSS/inyección SQL/CSRF/límite de peticiones/CSP...) |
| Operaciones | Comprobación de salud/métricas Prometheus/documentación API/security.txt + Docker + CI/CD |

## Stack tecnológico

### Backend
- PHP 8.3+, webman v2 (workerman/webman)
- Base de datos: MySQL 8.0+, prefijo de tabla `logistics_`
- Clave primaria: BIGINT no autoincremental, generada por `erikwang2013/snowflake-php`
- Cifrado/descifrado de IDs en la capa API: `erikwang2013/hashids`
- Autenticación JWT: `erikwang2013/jwt-webman`
- Cifrado/descifrado de datos sensibles de la API: `erikwang2013/encryption`
- Cifrado/descifrado de campos sensibles de la base de datos: `erikwang2013/encryptable`
- Sincronización y consulta ES: `erikwang2013/webman-scout`
- Banderas de países: `erikwang2013/season`

### Frontend
- Flutter 3.x, código fuente en el directorio `apps/flutter/`
- La versión web está diseñada con estilo de panel de administración para PC (no estilo de app móvil)
- Compatible con cliente y administrador
- HarmonyOS ArkTS, código fuente en el directorio `apps/harmonyos/`

## Estructura del proyecto

```
open-admin/
├── app/
│   ├── admin/controller/       # Controladores del panel de administración (14)
│   │   ├── BaseController.php      # Controlador base
│   │   ├── DashboardController.php # Panel de control (caché Redis)
│   │   ├── UserController.php      # CRUD de usuarios + operaciones masivas
│   │   ├── RoleController.php      # CRUD de roles
│   │   ├── PermissionController.php# CRUD de permisos
│   │   ├── ConfigController.php    # CRUD de configuración del sistema
│   │   ├── LogController.php       # Consulta de registros de operaciones
│   │   ├── ProfileController.php   # Centro personal + cierre de sesión
│   │   ├── ExportController.php    # Exportación Excel/PDF
│   │   ├── ImportController.php    # Importación de usuarios por Excel
│   │   ├── UploadController.php    # Carga de archivos
│   │   ├── HealthController.php    # Comprobación de salud
│   │   ├── DocsController.php      # Documentación OpenAPI
│   │   └── MetricsController.php   # Métricas de monitorización Prometheus
│   ├── api/v1/controller/      # Controladores API v1 (control por cabecera de versión)
│   │   ├── CaptchaController.php
│   │   └── AuthController.php
│   ├── common/                 # Clase de utilidades comunes
│   │   ├── HashidsService.php
│   │   ├── SnowflakeService.php
│   │   └── EncryptionService.php
│   ├── common/                 # Definiciones comunes (incluye definiciones de Apidoc)
│   ├── middleware/             # Middleware (8)
│   │   ├── Cors.php            # CORS (global)
│   │   ├── SecurityFilter.php  # Intercepción de ataques (global: XSS/inyección SQL/traversal de rutas/inyección de comandos/CSRF)
│   │   ├── RateLimit.php       # Límite de peticiones Redis (global, atómico con Lua)
│   │   ├── AdminAuth.php       # Autenticación JWT + lista negra
│   │   ├── AdminPermission.php # Validación de permisos RBAC (caché Redis 60s)
│   │   └── OperationLog.php    # Registro automático de operaciones (incluye detección de origen)
│   ├── model/                  # Modelos de datos
│   ├── queue/                  # Tareas de cola
│   └── process/                # Procesos (Http, Monitor)
├── apps/
│   ├── flutter/                # Panel de administración web Flutter
│   │   └── lib/app/
│   │       ├── pages/          # 6 páginas completas
│   │       │   ├── dashboard/  # Panel de control
│   │       │   ├── login/      # Inicio de sesión
│   │       │   ├── user/       # Gestión de usuarios
│   │       │   ├── role/       # Roles y permisos
│   │       │   ├── config/     # Configuración del sistema
│   │       │   ├── log/        # Registros de operaciones
│   │       │   └── profile/    # Centro personal
│   │       ├── services/       # ApiService + AuthService
│   │       ├── layouts/        # Diseño responsivo
│   │       └── theme/          # Tema Material 3
│   └── harmonyos/              # Cliente HarmonyOS
├── config/                     # Archivos de configuración
│   ├── route.php               # Rutas + política de versiones de API
│   └── middleware.php           # Registro global de middleware
├── database/
│   ├── install.sql             # Script de instalación completo (todas las SQL combinadas)
│   └── backup/                 # Scripts de copia de seguridad de la base de datos
│       ├── backup.sh           # mysqldump+gzip, retención de 30 días
│       └── restore.sh          # Restauración interactiva
├── docs/                       # Documentación
│   ├── ARCHITECTURE.md         # Diagrama de arquitectura Mermaid
│   ├── DESIGN.md               # Documento de diseño
│   ├── SECURITY.md             # Diseño de arquitectura de seguridad
│   ├── API.md                  # Documento de referencia de API
│   ├── nginx-security.conf     # Configuración de referencia de seguridad de Nginx
│   ├── diagrams/               # Diagramas de arquitectura desglosados
│   └── superpowers/            # Especificaciones y planes
│       ├── specs/              # Especificaciones de diseño
│       └── plans/              # Planes de implementación
├── public/                     # Punto de entrada público
├── runtime/                    # Archivos en tiempo de ejecución
├── tests/                      # Pruebas
├── vendor/                     # Dependencias de Composer
├── CLAUDE.md                   # Este archivo
├── README.md                   # Documentación en chino
├── docs/translations/README.en.md                # Documentación en inglés
├── docs/translations/README.ko.md ... README.ja.md  # Documentación multilingüe (coreano/ruso/alemán/francés/español/portugués/hindi/árabe/bengalí/indonesio/japonés)
├── .env                        # Variables de entorno (no incluidas en el control de versiones)
├── .env.example                # Plantilla de variables de entorno
├── .env.docker                 # Variables de entorno de Docker
├── composer.json               # Dependencias PHP
├── Dockerfile                  # Construcción de Docker
├── docker-compose.yml          # Orquestación de Docker
└── .github/
    └── workflows/
        └── ci.yml              # Pipeline CI/CD (sintaxis PHP + PHPUnit + flutter analyze)
```

## Cadena de ejecución de middleware

```
Global:  Cors → Locale(Accept-Language) → SecurityFilter(comprobación de método→405) → RateLimit → {middleware de ruta}
/admin: Cors → Locale(Accept-Language) → SecurityFilter(comprobación de método→405) → RateLimit → AdminAuth → AdminPermission → OperationLog → Controller
/api:   Cors → Locale(Accept-Language) → SecurityFilter(comprobación de método→405) → RateLimit → Controller
/health: Cors → Locale(Accept-Language) → SecurityFilter(comprobación de método→405) → RateLimit → Controller
```

## Mejoras de seguridad

- **Restricción de métodos HTTP**: SecurityFilter solo permite GET/POST/PUT/DELETE/OPTIONS/HEAD; los métodos no estándar devuelven 405
- **Cabecera CSP**: se inyectan Content-Security-Policy + X-Permitted-Cross-Domain-Policies en todas las respuestas
- **Bloqueo de cuenta**: después de 5 intentos de inicio de sesión fallidos consecutivos, la cuenta se bloquea durante 15 minutos
- **Límite de sesiones concurrentes**: un mismo usuario puede tener como máximo 3 tokens válidos; al superarlo, el token más antiguo se añade a la lista negra
- **security.txt**: endpoint `/.well-known/security.txt` RFC 9116
- **Configuración de seguridad de Nginx**: `docs/nginx-security.conf` como referencia de endurecimiento del proxy inverso

## Política de versiones de API

La versión se controla mediante la cabecera de petición `API-Version` (por defecto `v1`) y no aparece en la URL:

```bash
curl http://localhost:8787/api/v1/auth/login
```

Para añadir una versión nueva solo hay que crear el directorio `app/api/{version}/controller/` y registrarlo en el middleware `ApiVersion`.

## Política de límite de peticiones

Ventana deslizante de Redis (atómica con Lua), por defecto 60 peticiones/minuto/IP/ruta:
- Inicio de sesión: 10 por minuto
- Registro: 5 por minuto
- Cabeceras de respuesta: `X-RateLimit-Limit/Remaining/Reset`; al superar el límite se añade `Retry-After`

## Normas de código

### PHP
- Las referencias a funciones/clases globales no llevan `\` inicial; usar `use` para importar
- Los archivos de configuración deben incluir comentarios en chino que expliquen el significado de cada opción
- Todos los archivos `.php` nuevos deben incluir la declaración de copyright en la cabecera

### Base de datos
- Prefijo de tabla: `logistics_`
- Clave primaria `id`: tipo BIGINT, no autoincremental, generada por snowflake
- Los campos sensibles usan el trait `erikwang2013/encryptable` para cifrar/descifrar automáticamente
- Los archivos de migración usan formato SQL

### Flutter
- La versión web usa estilo de panel de administración para PC (barra lateral + barra superior + área de contenido)
- Gestión de estado con GetX, singleton `ApiService` (Dio + interceptores JWT)
- La persistencia de tokens usa `shared_preferences`
- Puntos de ruptura responsivos: móvil (< 768px) y escritorio (>= 768px)

### HarmonyOS
- Usar el cliente HTTP nativo `@ohos.net.http`
- Refresco imperceptible de tokens: al recibir 401, llamar automáticamente a `/api/v1/auth/refresh`
- Si el refresco falla, redirigir automáticamente a la página de inicio de sesión

## Despliegue

### Docker Compose (recomendado para producción)

El `docker-compose.yml` de la raíz del proyecto orquesta 5 servicios:

| Servicio | Descripción |
|------|------|
| `nginx` | Proxy inverso Nginx (80/443), servicio de archivos estáticos |
| `app` | Aplicación webman PHP 8.3, construida con `Dockerfile` (incluye OPcache) |
| `mysql` | MySQL 8.0, persistencia con volumen de datos |
| `redis` | Redis 7 Alpine, caché/límite de peticiones/Sesión |
| `elasticsearch` | Elasticsearch 8.x, búsqueda de texto completo |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` define el pipeline de GitHub Actions:

- Comprobación de sintaxis de PHP (`php -l`)
- Pruebas unitarias de PHPUnit
- Análisis estático de Flutter (`flutter analyze`)

### Copia de seguridad de la base de datos

`database/backup/backup.sh` — mysqldump + gzip, elimina automáticamente las copias de hace más de 30 días.
`database/backup/restore.sh` — restauración interactiva, muestra las copias disponibles para elegir.

### Monitorización

El endpoint `GET /metrics` (`MetricsController`) genera el formato de texto de Prometheus, con 5 métricas gauge:
- `openadmin_http_requests_total` — total de peticiones
- `openadmin_active_users` — número de usuarios activos
- `openadmin_db_connection_status` — estado de la conexión a la base de datos (0/1)
- `openadmin_redis_connection_status` — estado de la conexión a Redis (0/1)
- `openadmin_memory_usage_bytes` — uso de memoria
