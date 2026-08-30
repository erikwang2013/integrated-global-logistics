> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](../README.md) | [English](README.en.md) | [한국어](README.ko.md) | [Русский](README.ru.md) | [Deutsch](README.de.md) | [Français](README.fr.md) | [Español](README.es.md) | [Português](README.pt.md) | [हिन्दी](README.hi.md) | [العربية](README.ar.md) | [বাংলা](README.bn.md) | [Bahasa Indonesia](README.id.md) | [日本語](README.ja.md)

# Panel de administración abierto (open-admin)

Sistema de panel de administración full-stack basado en webman v2 + Flutter.

> [Diagrama de arquitectura](docs/ARCHITECTURE.es.md) | [Documento de diseño](docs/DESIGN.es.md) | [Arquitectura de seguridad](docs/SECURITY.es.md) | [Referencia de API](docs/API.es.md)

## Lista de funciones

<img src="../../../docs/diagrams/description.svg" alt="Lista de funciones" width="100%">


| Dominio | Función | Descripción |
|--------|------|------|
| 🔐 Autenticación | Inicio de sesión/renovación de token/cierre de sesión | Captcha de clic + JWT + lista negra |
| | Bloqueo de cuenta | 5 intentos fallidos bloquean 15 minutos |
| | Límite de sesiones concurrentes | Máximo 3 tokens válidos por usuario |
| 📊 Panel | Estadísticas en tiempo real/gráfico de tendencias/gráfico de distribución/operaciones recientes | Caché Redis de 5 minutos |
| 👥 Gestión de usuarios | CRUD + borrado en lote/habilitar-deshabilitar | Borrado lógico + confirmación secundaria de contraseña |
| | Importación masiva de Excel | Validación línea por línea + informe de errores |
| 🔒 Roles y permisos | CRUD de roles + árbol de permisos | Autorización con granularidad RBAC method.path |
| ⚙ Configuración del sistema | CRUD de pares clave-valor | Gestión por grupos |
| 📋 Auditoría de operaciones | Consulta de registros + detección de origen | Reconocimiento automático de 8 plataformas |
| 📁 Gestión de archivos | Subida/exportación Excel/exportación PDF | Enmascarado automático de datos sensibles |
| 🛡 Protección de seguridad | 18 capas de defensa en profundidad | XSS/inyección SQL/traversal de rutas/inyección de comandos/CSRF/límite de peticiones/CSP... |
| 🏥 Operaciones | Health check/metrics/documentación de API/security.txt | Prometheus + OpenAPI 3.0 + documentación interactiva hg/apidoc |
| 🌐 Internacionalización | Cambio chino/inglés | Cabecera Accept-Language / parámetro ?lang= |

## Pila tecnológica

| Capa | Tecnología | Descripción |
|---|------|------|
| Framework backend | webman v2 (workerman) | Framework PHP de procesos residentes de altísimo rendimiento |
| Versión de PHP | 8.3+ | |
| Base de datos | MySQL 8.0+ | Prefijo de tablas `logistics_`, claves primarias BIGINT no autoincrementales |
| Motor de búsqueda | Elasticsearch | Sincronización y consulta mediante `webman-scout` |
| Frontend del panel | Flutter 3.x | La versión web usa estilo de panel de administración de PC (`apps/flutter/`) |
| Móvil | HarmonyOS ArkTS | Cliente nativo de HarmonyOS (`apps/harmonyos/`), compatible con teléfono/tableta/2 en 1 |

## Dependencias principales

| Paquete | Uso |
|---|------|
| `erikwang2013/snowflake-php` | Generación de claves primarias BIGINT únicas globalmente mediante el algoritmo Snowflake |
| `erikwang2013/hashids` | Cifrado/descifrado de IDs en la capa de API para ocultar los IDs reales de la base de datos |
| `erikwang2013/jwt-webman` | Emisión y verificación de tokens de autenticación JWT |
| `erikwang2013/encryption` | Cifrado/descifrado de datos sensibles en la capa de transporte de la API |
| `erikwang2013/encryptable` | Cifrado/descifrado automático de campos sensibles en la capa de almacenamiento de la base de datos |
| `erikwang2013/webman-scout` | Sincronización de datos con Elasticsearch y búsqueda de texto completo |
| `erikwang2013/season` | Datos de banderas de países |
| `erikwang2013/poster-php` | Generación y verificación de captcha de clic + generación de pósteres |
| `phpoffice/phpspreadsheet` | Exportación de Excel |
| `barryvdh/laravel-dompdf` | Exportación de PDF (basado en Dompdf) |

## Estructura del proyecto

```
open-admin/
├── app/
│   ├── admin/controller/       # Controladores del panel de administración
│   │   ├── DashboardController.php # Panel (caché Redis)
│   │   ├── UserController.php      # CRUD de usuarios + operaciones en lote
│   │   ├── RoleController.php      # CRUD de roles
│   │   ├── PermissionController.php# CRUD de permisos
│   │   ├── ConfigController.php    # CRUD de configuración del sistema
│   │   ├── LogController.php       # Consulta de registros de operaciones
│   │   ├── ProfileController.php   # Perfil + cierre de sesión
│   │   ├── ExportController.php    # Exportación Excel/PDF
│   │   ├── ImportController.php    # Importación de usuarios desde Excel
│   │   ├── UploadController.php    # Subida de archivos
│   │   ├── HealthController.php    # Health check
│   │   ├── DocsController.php      # Documentación OpenAPI
│   │   └── BaseController.php      # Controlador base
│   ├── api/
│   │   └── v1/controller/          # Controladores API v1 (la versión se controla con la cabecera API-Version)
│   │       ├── CaptchaController.php # Captcha de clic
│   │       └── AuthController.php    # Inicio de sesión/renovación de token
│   ├── common/                 # Clases de utilidades comunes
│   │   ├── HashidsService.php  # Codificación/decodificación de IDs
│   │   ├── SnowflakeService.php# Generación de IDs Snowflake
│   │   └── EncryptionService.php # Cifrado/descifrado de datos + enmascarado
│   ├── middleware/             # Middleware
│   │   ├── Cors.php            # CORS
│   │   ├── SecurityFilter.php  # Bloqueo por detección de ataques (restricción de métodos HTTP/XSS/inyección SQL/traversal de rutas/inyección de comandos/CSRF)
│   │   ├── RateLimit.php       # Límite de peticiones Redis (ventana deslizante + cabeceras de respuesta)
│   │   ├── ApiVersion.php      # Validación de versión de API
│   │   ├── AdminAuth.php       # Autenticación JWT + lista negra
│   │   ├── AdminPermission.php # Verificación de permisos RBAC
│   │   └── OperationLog.php    # Registro automático de operaciones (incluye detección de origen)
│   └── model/                  # Modelos de datos
├── apps/
│   ├── flutter/                # Panel de administración Flutter Web (estilo PC)
│   │   └── lib/app/
│   │       ├── pages/          # 5 páginas completas (panel/usuarios/roles/config/logs/perfil)
│   │       ├── services/       # ApiService (interceptor JWT) + AuthService (persistencia de tokens)
│   │       └── layouts/        # Diseño de panel responsivo (barra lateral + barra superior + área de contenido)
│   └── harmonyos/              # Cliente nativo de HarmonyOS (renovación transparente de tokens)
├── config/                     # Archivos de configuración (con comentarios en chino)
│   ├── route.php               # Rutas + estrategia de versiones de API
│   ├── middleware.php           # Registro de middleware global
│   └── ...                     # Configuración de cada componente
├── database/install.sql        # Script de instalación SQL (incluye datos semilla de permisos)
├── public/                     # Entrada pública
├── runtime/                    # Archivos de runtime
└── vendor/                     # Dependencias de Composer
```

## Requisitos del entorno

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (solo necesario para el desarrollo del frontend)
- Elasticsearch >= 7.x (opcional, necesario para la función de búsqueda)

## Inicio rápido

### 1. Instalar dependencias

```bash
composer install
```

### 2. Configurar las variables de entorno

Copie y modifique las variables de entorno (opcional; si no se configuran, se usan los valores predeterminados de `config/*.php`):

```bash
cp .env.example .env
```

Variables de configuración clave:

| Variable de entorno | Descripción | Valor predeterminado |
|---------|------|--------|
| `JWT_SECRET` | Clave de firma JWT | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Valor salt de Hashids | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | Clave de cifrado de la API | Valor predeterminado de 32 bytes |
| `SNOWFLAKE_DATACENTER_ID` | ID del centro de datos (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID del nodo de trabajo (0-31) | `1` |
| `SCOUT_HOSTS` | Dirección de ES | `http://localhost:9200` |

**En producción, cambie todas las claves por cadenas aleatorias.**

### 3. Instalación en un clic

Tras iniciar el servicio, acceda al asistente de instalación desde el navegador para inicializar la base de datos y crear la cuenta de administrador:

```bash
php start.php start
```

Escucha por defecto en `http://0.0.0.0:8787` (el puerto se puede modificar en `config/server.php`).

Abra **`http://localhost:8787/install`** en el navegador y rellene los campos según el asistente:

| Paso | Contenido |
|------|------|
| ① Configuración de base de datos | Dirección del host, puerto, nombre de la base de datos, usuario y contraseña |
| ② Configuración del administrador | Usuario y contraseña del administrador (por defecto admin / admin888) |

Al hacer clic en «Iniciar instalación», se crean automáticamente las tablas, se siembran los datos de permisos, se crea la cuenta de administrador y se escriben los ajustes de base de datos en `.env`.

> Tras la instalación se genera el archivo de bloqueo `runtime/install.lock`. Para reinstalar, basta con eliminar este archivo.

### 4. Iniciar sesión

Acceda a `http://localhost:8787` e inicie sesión con la cuenta de administrador configurada durante la instalación.

### 5. Iniciar el frontend (opcional)

**Panel de administración Flutter (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (estilo de panel de administración PC)
```

**Cliente HarmonyOS (móvil):**

Abra el directorio `apps/harmonyos/` con DevEco Studio y ejecútelo conectando un dispositivo real o un emulador.

### 6. Despliegue con Docker Compose en un clic (recomendado para producción)

El proyecto ofrece una solución de orquestación Docker completa con 5 servicios: Nginx, PHP (aplicación webman), MySQL, Redis y Elasticsearch.

```bash
# 1. Configurar las variables de entorno de Docker
cp .env.docker .env

# 2. Iniciar todos los servicios
docker-compose up -d

# 3. Abrir el asistente de instalación en el navegador para completar la inicialización
# http://localhost:8787/install  (rellenar información de base de datos y administrador)
# o ejecutar la migración SQL manualmente (dentro del contenedor app):
# docker-compose exec app mysql -h mysql -u root -p < database/install.sql

# 4. Acceso
# http://localhost:8787  (webman)
# http://localhost:8080  (proxy inverso de Nginx)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, basado en `php:8.3-cli`
- `docker-compose.yml`: 5 servicios orquestados, red aislada, volúmenes de datos persistentes
- `.env.docker`: variables de entorno específicas para Docker


## Convenciones de base de datos

- **Prefijo de tablas**: `logistics_`
- **Clave primaria**: en todas las tablas la clave primaria es `id BIGINT UNSIGNED NOT NULL`, **prohibido AUTO_INCREMENT**
- **Generación de IDs**: los IDs de clave primaria los genera `SnowflakeService::generate()` en la capa de aplicación, únicos de forma distribuida
- **Campos obligatorios**: cada tabla debe incluir `id`, `created_at`, `updated_at`
- **Borrado lógico**: las tablas que lo necesiten añaden `deleted_at DATETIME DEFAULT NULL`
- **Campos sensibles**: el teléfono, el correo electrónico, el número de documento de identidad, etc., se cifran y descifran automáticamente con el plugin `encryptable`; la columna de la base de datos usa `VARCHAR(500)` para almacenar el texto cifrado

## Documentación de la API

La referencia completa de la API (formato de respuesta unificado, códigos de error, detalles de todos los endpoints, flujo de autenticación, política de límite de peticiones y cadena de middleware) está en **[docs/API.es.md](docs/API.es.md)**. Puntos clave:

- **Formato de respuesta unificado**: `{ "code": 0, "message": "success", "data": {...} }`, `code=0` significa éxito
- **Códigos de error**: `400` error de parámetros / `401` no autenticado / `403` sin permiso / `404` no existe / `422` error de validación / `429` límite de peticiones / `500` error del servidor
- **Versión de la API**: se controla mediante la cabecera `API-Version: v1` (por defecto v1 si no se envía), no aparece en la URL
- **Autenticación**: `Authorization: Bearer <token>`; el access_token tiene una validez de 2 horas y el refresh_token de 14 días
- **Tratamiento de IDs**: los IDs de las peticiones/respuestas son cadenas cifradas con hashids; no se exponen los IDs reales de la base de datos

## Notas sobre el frontend

### Panel de administración Flutter (estilo PC)

- **Diseño**: barra lateral (plegable 64px/240px) + barra superior + área de contenido, con tres puntos de interrupción responsivos (móvil/tableta/escritorio)
- **Páginas**: inicio de sesión, panel, gestión de usuarios, roles y permisos, configuración del sistema, registros de operaciones y perfil
- **Gestión de estado**: GetX (`ApiService` singleton + `AuthService` con persistencia de tokens)
- **Panel**: tarjetas de estadísticas, gráfico de líneas de tendencias (fl_chart), gráfico circular y registro de operaciones recientes
- **Exportación**: exportación Excel/PDF; el PDF incluye información de copyright no removible
- **Operaciones en lote**: borrado en lote con selección múltiple, habilitar/deshabilitar en lote
- **Tema**: Material 3 con temas claro/oscuro

### Móvil HarmonyOS

- **Páginas**: inicio de sesión, panel, lista/detalle de usuarios, perfil
- **Autenticación**: JWT Bearer + renovación automática y transparente del token al recibir 401; si falla la renovación, redirección automática a la página de inicio de sesión
- **Almacenamiento**: el token se gestiona mediante AppStorage

## Convenciones de desarrollo

- Las referencias a funciones/clases globales no llevan `\` inicial; se usan importaciones `use`
- Todos los archivos PHP deben incluir la declaración de copyright en la cabecera
- Todos los archivos de configuración deben incluir comentarios en chino explicando cada opción
- Las claves primarias de la base de datos deben generarse con snowflake en la capa de aplicación; prohibido el autoincremento
- Todos los IDs de los parámetros y respuestas de la capa API deben cifrarse/descifrarse con hashids
- El middleware AdminPermission usa caché Redis para los permisos de usuario (TTL=60s), eliminando el cuello de botella de las consultas N+1

## Despliegue

### Docker Compose (recomendado)

La raíz del proyecto incluye `docker-compose.yml`, que orquesta 5 servicios:

| Servicio | Imagen | Puerto |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | Build local con `Dockerfile` | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

La imagen de PHP se construye con `Dockerfile`, a partir de la imagen base `php:8.3-cli`, con OPcache habilitado.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

Pipeline de integración continua con GitHub Actions: `.github/workflows/ci.yml`

- Comprobación de sintaxis PHP (`php -l`)
- Pruebas unitarias PHPUnit
- Análisis estático de Flutter (`flutter analyze`)

### Copia de seguridad de la base de datos

Directorio `database/backup/`:

- `backup.sh` — copia de seguridad con mysqldump + gzip, limpia automáticamente las copias de más de 30 días
- `restore.sh` — restauración interactiva, lista las copias disponibles para elegir

### Configuración de seguridad de Nginx

Para el despliegue en producción, consulte `docs/nginx-security.conf` para el endurecimiento de seguridad del proxy inverso.

## El código abierto no es fácil: ¡agradecemos tu apoyo!

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](./docs/weixinpay.png "WeChat") | ![Alipay](./docs/alipay.png "Alipay") |

### Donación por transferencia internacional (remesa transfronteriza)

**Información del beneficiario**

- Nombre del beneficiario: WANG KEXUN
- Número de cuenta del beneficiario: 881015918251

**Banco receptor**

- Código SWIFT de ZA Bank: AABLHKHHXXX
- Nombre del banco: ZA Bank Limited
- Código bancario: 387
- Dirección del banco: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Banco intermediario para remesas transfronterizas (si es necesario)**

> Esta es la información del banco intermediario (banco corresponsal) para remesas transfronterizas; no es la información del banco receptor. Consulte a su banco remitente si es necesario proporcionar los datos del banco intermediario.

- **Para transferencias en dólares de Hong Kong (HKD), renminbi (RMB) y dólares estadounidenses (USD)**, el banco intermediario es Citibank:
  - Nombre del banco: Citibank N.A. Hong Kong
  - Código SWIFT: CITIHKHXXXX
  - Código bancario: 006
  - Nombre de la sucursal: Hong Kong Branch
  - Código de sucursal: 391
  - Dirección del banco: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **Para transferencias en otras divisas**, el banco intermediario es BNY Mellon:
  - Nombre del banco: THE BANK OF NEW YORK MELLON
  - Código SWIFT: IRVTUS3NXXX
  - Dirección del banco: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

---

## Licencia

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
