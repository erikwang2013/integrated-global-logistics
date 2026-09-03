# Diagramas de arquitectura y lógica de negocio

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](ARCHITECTURE.md) | [English](ARCHITECTURE.en.md) | [한국어](ARCHITECTURE.ko.md) | [Русский](ARCHITECTURE.ru.md) | [Deutsch](ARCHITECTURE.de.md) | [Français](ARCHITECTURE.fr.md) | [Español](ARCHITECTURE.es.md) | [Português](ARCHITECTURE.pt.md) | [हिन्दी](ARCHITECTURE.hi.md) | [العربية](ARCHITECTURE.ar.md) | [বাংলা](ARCHITECTURE.bn.md) | [Bahasa Indonesia](ARCHITECTURE.id.md) | [日本語](ARCHITECTURE.ja.md)

> Los siguientes diagramas Mermaid se renderizan automáticamente en GitHub / GitLab / VS Code. En otros entornos, utilice [Mermaid Live Editor](https://mermaid.live/).

---

## 1. Topología del sistema

```mermaid
flowchart TB
    subgraph "Capa de cliente"
        A1["Flutter Web<br/>Panel de administración PC<br/>(Puerto 3000)"]
        A2["HarmonyOS ArkTS<br/>Cliente móvil/tableta"]
    end

    subgraph "Capa de puerta de enlace/edge (Nginx Edge)"
        B1["Nodo edge Nginx<br/>Docker nginx:alpine<br/>Proxy inverso + HTTPS + Gzip<br/>Servicio de archivos estáticos"]
    end

    subgraph "Capa de aplicación (webman v2)"
        C0["Middleware ApiVersion<br/>Validación de la cabecera API-Version"]
        C1["Middleware AdminAuth<br/>Validación JWT"]
        C2["Middleware AdminPermission<br/>Verificación de permisos RBAC"]
        C3["Controllers del panel<br/>Dashboard / User / Role / Permission"]
        C4["Controllers públicos v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Capa de almacenamiento"
        D1[("MySQL 8.0<br/>Almacenamiento principal<br/>Prefijo de tablas logistics_")]
        D2[("Elasticsearch<br/>Búsqueda de texto completo<br/>Prefijo de índices logistics_")]
        D3[("Redis<br/>Sesión / Caché<br/>Almacenamiento de captcha")]
    end

    subgraph "Externo"
        E1["DevEco Studio<br/>Compilación HarmonyOS"]
        E2["Flutter SDK<br/>Compilación Web"]
    end

    A1 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    A2 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    B1 --> C0
    C0 --> C1
    C1 --> C2
    C2 --> C3
    C0 --> C4
    C3 --> C5
    C4 --> C5
    C3 --> D1
    C4 --> D1
    C3 --> D2
    C4 --> D2
    C1 --> D3

    style A1 fill:#1677FF,color:#fff
    style A2 fill:#1677FF,color:#fff
    style B1 fill:#722ED1,color:#fff
    style C0 fill:#EB2F96,color:#fff
    style C1 fill:#FA8C16,color:#fff
    style C2 fill:#FA8C16,color:#fff
    style C3 fill:#52C41A,color:#fff
    style C4 fill:#52C41A,color:#fff
    style C5 fill:#52C41A,color:#fff
    style D1 fill:#1890FF,color:#fff
    style D2 fill:#1890FF,color:#fff
    style D3 fill:#1890FF,color:#fff
```

---

## 2. Arquitectura por capas del backend

```mermaid
flowchart TD
    subgraph "Capa de rutas Route Layer"
        R1["config/route.php<br/>Asignación URL → Controller"]
    end

    subgraph "Capa de middleware Middleware Layer"
        M_RL["RateLimit<br/>Límite de peticiones con ventana deslizante Redis<br/>Cabeceras de respuesta X-RateLimit"]
        M_SF["SecurityFilter<br/>Bloqueo por detección de ataques<br/>XSS/inyección SQL/traversal de rutas/CSRF"]
        M1["AdminAuth<br/>Validación del token JWT<br/>Inyecta adminId"]
        M2["AdminPermission<br/>Autorización RBAC<br/>Coincidencia method.path<br/>Permisos en caché Redis 60s"]
    end

    subgraph "Capa de controllers Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + búsqueda + paginación"]
        CT3["RoleController<br/>CRUD + sincronización de permisos"]
        CT4["PermissionController<br/>CRUD + construcción del árbol"]
        CT5["DashboardController<br/>estadísticas/tendencias/distribución"]
        CT6["ExportController<br/>Exportación Excel/PDF"]
        CT7["CaptchaController<br/>Generación/validación de captcha"]
        CT8["AuthController<br/>login/registro/renovación"]
    end

    subgraph "Capa de servicios Service Layer"
        S1["HashidsService<br/>Codificación/decodificación de IDs"]
        S2["SnowflakeService<br/>Generación de IDs únicos globales"]
        S3["EncryptionService<br/>Cifrado/descifrado + enmascarado"]
    end

    subgraph "Capa de modelos Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Capa de drivers Driver Layer"
        D1["MySQL PDO"]
        D2["Elasticsearch HTTP"]
        D3["Redis"]
    end

    R1 --> M_SF --> M_RL
    M_RL --> M1
    M1 --> M2
    M2 --> CT2 & CT3 & CT4 & CT5 & CT6
    M_RL --> CT7 & CT8
    CT1 -.->|extends| CT2 & CT3 & CT4 & CT5 & CT6
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> S1 & S2 & S3
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> MD1 & MD2 & MD3 & MD4 & MD5
    MD1 & MD2 & MD3 & MD4 & MD5 --> D1
    MD1 --> D2
    CT7 --> D3

    style R1 fill:#722ED1,color:#fff
    style M_SF fill:#FF4D4F,color:#fff
    style M_RL fill:#EB2F96,color:#fff
    style M1 fill:#FA8C16,color:#fff
    style M2 fill:#FA8C16,color:#fff
    style CT1 fill:#1677FF,color:#fff
```

---

## 3. Ciclo de vida de una petición

```mermaid
sequenceDiagram
    participant C as Cliente
    participant N as Nginx
    participant MW_LOC as Locale
    participant MW_SF as SecurityFilter
    participant MW_RL as RateLimit
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL
    participant OPLOG as OperationLog

    C->>N: Petición HTTPS<br/>Cabecera: Accept-Language: zh_CN
    N->>MW_LOC: Reenvío

    MW_LOC->>MW_LOC: locale = zh_CN (Accept-Language / ?lang=)
    MW_LOC->>MW_SF: Aprobado

    alt Método HTTP no estándar (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Método permitido (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: La comprobación de la lista blanca de métodos ha pasado
    end

    alt Se detecta un ataque
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Aprobado

    alt Se activa el límite de peticiones
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW1: Aprobado

    alt Token ausente o no válido
        MW1-->>C: 401 Unauthorized
    else Token válido
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt Sin permiso
        MW2-->>C: 403 Forbidden
    else Con permiso
        MW2->>CTL: Entrada al controller
    end

    CTL->>CTL: Validación de parámetros (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Operación sensible (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Contraseña incorrecta
            CTL-->>C: 422 Fallo de verificación de contraseña
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: El cast encryptable descifra automáticamente
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: cadena hash

    CTL->>CTL: Construcción de la respuesta JSON
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Registro de la operación (POST/PUT/DELETE)
```

---

## 4. Flujo de autenticación y captcha

```mermaid
sequenceDiagram
    participant U as Usuario
    participant CL as Cliente
    participant SV as Servidor
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Paso 1: Obtener el captcha ===
    CL->>SV: POST /api/v1/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Genera la imagen de fondo 300×200
    CAP->>CAP: Coloca N objetivos en chino de forma aleatoria
    CAP->>CAP: Genera key, almacena targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Paso 2: El usuario hace clic ===
    CL->>CL: Renderiza la imagen del captcha
    CL->>CL: Mensaje: "Haz clic en orden: árbol → pájaro → flor"
    U->>CL: Hace clic en las posiciones del texto en la imagen
    CL->>CL: Recopila clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Paso 3: Inicio de sesión ===
    CL->>SV: POST /api/v1/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Captcha incorrecto
        CAP-->>SV: false
        SV-->>CL: 422 Captcha incorrecto
    else Captcha correcto
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Credenciales incorrectas
            SV-->>CL: 401 Nombre de usuario o contraseña incorrectos
        else Credenciales correctas
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Peticiones posteriores ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { datos del panel }
```

---

## 5. Modelo de permisos RBAC

```mermaid
flowchart LR
    subgraph "Usuario"
        U1["admin<br/>(Superadministrador)"]
        U2["editor<br/>(Editor)"]
        U3["viewer<br/>(Solo lectura)"]
    end

    subgraph "Rol"
        R1["super_admin<br/>Identificador de permiso: *"]
        R2["editor<br/>Identificador de permiso: get.*, post.*"]
        R3["viewer<br/>Identificador de permiso: get.*"]
    end

    subgraph "Permiso (árbol)"
        P1["dashboard<br/>type=1 menú"]
        P2["user<br/>type=1 menú"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 botón"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (todos los permisos)"| P1 & P2 & P3 & P4 & P5 & P6
    R2 --> P1 & P2 & P3 & P4
    R3 --> P1 & P3

    P2 --> P3 & P4 & P5
    P1 --> P6

    style U1 fill:#1677FF,color:#fff
    style R1 fill:#FA8C16,color:#fff
    style P1 fill:#52C41A,color:#fff
```

```mermaid
flowchart TD
    subgraph "Tipos de permiso"
        T1["type=1 menú<br/>Controla la visibilidad de la barra lateral"]
        T2["type=2 botón<br/>Controla los botones de acción de la página"]
        T3["type=3 API<br/>Controla el acceso a las interfaces"]
    end

    subgraph "Formato del identificador de permiso"
        F1["{method}.{path}<br/>Ej.: get.admin/user<br/>Ej.: post.admin/user<br/>Ej.: delete.admin/role"]
    end

    subgraph "Flujo de evaluación"
        J1["Extraer Token → adminId"]
        J2["Buscar los roles del usuario"]
        J3["Recopilar todos los slugs de permiso"]
        J4["Construir method.path"]
        J5{"¿Coincide?"}
        J6["Permitir"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"Sí / slug=*"| J6
        J5 -->|No| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Ciclo de vida completo de los IDs

```mermaid
flowchart LR
    subgraph "1. Generación"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>Ej.: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Almacenamiento"
        S1["Tabla MySQL logistics_*<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Campos sensibles<br/>cast encryptable<br/>Cifrado AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Transmisión"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["Cadena hashid<br/>Ej.: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Decodificación inversa"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Capas de cifrado de datos

```mermaid
flowchart TB
    subgraph "Cifrado en la capa de transmisión (encryption)"
        E1["El cliente envía datos sensibles"]
        E2["Cifrado AES-256-CBC"]
        E3["Texto cifrado en la transmisión de la API"]
        E4["El servidor descifra y procesa"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Cifrado en la capa de almacenamiento (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Escritura: cifrado automático"]
        D3["MySQL VARCHAR(500)<br/>Almacena texto cifrado"]
        D4["Lectura: descifrado automático"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Enmascarado en la capa de presentación (mask)"
        M1["phone: 138****1234"]
        M2["email: a***@example.com"]
        M3["id_card: ********"]
        D4 --> M1 & M2 & M3
    end

    E4 --> D1

    style E2 fill:#1677FF,color:#fff
    style D2 fill:#FA8C16,color:#fff
    style M1 fill:#52C41A,color:#fff
```

---

## 8. Relaciones ER de la base de datos

```mermaid
erDiagram
    logistics_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "Cifrado"
        VARCHAR phone "Cifrado"
        VARCHAR id_card "Cifrado"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Borrado lógico"
    }

    logistics_admin_role {
        BIGINT id PK "Snowflake"
        VARCHAR name
        VARCHAR slug UK
        VARCHAR description
        TINYINT status
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_permission {
        BIGINT id PK "Snowflake"
        BIGINT parent_id FK "Autoreferencia"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1menú 2botón 3API"
        VARCHAR icon
        VARCHAR path
        INT sort
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_user_role {
        BIGINT user_id PK_FK
        BIGINT role_id PK_FK
    }

    logistics_admin_role_permission {
        BIGINT role_id PK_FK
        BIGINT permission_id PK_FK
    }

    logistics_operation_log {
        BIGINT id PK "Snowflake"
        BIGINT user_id FK
        VARCHAR action
        VARCHAR method
        VARCHAR path
        VARCHAR ip
        VARCHAR source "Origen"
        TEXT input "Enmascarado"
        DATETIME created_at
    }

    logistics_system_config {
        BIGINT id PK "Snowflake"
        VARCHAR group
        VARCHAR key
        TEXT value
        VARCHAR type
        VARCHAR description
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_user ||--o{ logistics_admin_user_role : "user_id"
    logistics_admin_role ||--o{ logistics_admin_user_role : "role_id"
    logistics_admin_role ||--o{ logistics_admin_role_permission : "role_id"
    logistics_admin_permission ||--o{ logistics_admin_role_permission : "permission_id"
    logistics_admin_user ||--o{ logistics_operation_log : "user_id"
    logistics_admin_permission ||--o{ logistics_admin_permission : "parent_id"
```

---

## 9. Flujo de negocio de la exportación

```mermaid
sequenceDiagram
    participant C as Cliente
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Sistema de archivos

    Note over C,FS: === Exportación Excel ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Datos
    CTL->>CTL: Descifra los campos sensibles
    CTL->>CTL: Enmascarado (maskPhone/maskEmail)
    CTL->>CTL: Construcción con PhpSpreadsheet<br/>Encabezado azul con texto blanco<br/>Filas de datos con bordes finos<br/>Primera fila fijada<br/>Autofiltro
    CTL->>FS: Escribe runtime/tmp/export_*.xlsx
    CTL-->>C: Descarga del archivo

    Note over C,FS: === Exportación PDF ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>Encabezado: título + copyright + hora<br/>Contenido: tabla o tarjetas<br/>Pie: copyright no removible
    CTL->>CTL: Renderizado con Dompdf en A4 horizontal
    CTL->>FS: Escribe runtime/tmp/export_*.pdf
    CTL-->>C: Descarga del archivo
```

---

## 10. Árbol de componentes de Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Formulario de inicio de sesión<br/>usuario/contraseña/captcha"]
    LF --> CAPTCHA["Componente de captcha de clic<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Marcador de clic Circle"]

    DB --> SIDEBAR["Barra lateral NavigationDrawer<br/>Plegable 64px / 240px<br/>Panel/Usuarios/Roles/Config/Logs"]
    DB --> HEADER["Barra superior 56px<br/>Botón de plegado + menú de usuario<br/>Cerrar sesión con AlertDialog"]
    DB --> CONTENT["Área de contenido"]
    CONTENT --> DASH["DashboardPage<br/>Tarjetas de estadísticas GridView<br/>Gráfico de líneas de tendencia LineChart<br/>Gráfico circular de distribución PieChart<br/>Operaciones recientes ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. Rutas de páginas de HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>Arranque"]
    EA -->|"Sin Token"| LP["LoginPage<br/>Página de inicio de sesión"]
    EA -->|"Con Token"| DP["DashboardPage<br/>Panel"]

    LP -->|"Inicio de sesión correcto<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>Lista de usuarios"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Perfil"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>Detalle de usuario/crear/editar"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"Cerrar sesión<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Panorama completo de la defensa en profundidad

```mermaid
flowchart TB
    subgraph "Capa 1: Verificación humano-máquina"
        L1["Captcha de clic<br/>Click Captcha<br/>Obligatorio en login/registro"]
    end

    subgraph "Capa 2: Confirmación de operación"
        L2["Segunda confirmación de contraseña<br/>confirmPassword()<br/>Obligatorio para operaciones DELETE"]
    end

    subgraph "Capa 3: Seguridad de la transmisión"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Capa 4: Autenticación de identidad"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "Capa 5: Autorización de permisos"
        L5["RBAC<br/>Granularidad method.path<br/>Superadministrador *"]
    end

    subgraph "Capa 6: Protección de datos"
        L6["ID de interfaz: cifrado con Hashids<br/>Cuerpo de petición: cifrado con Encryption<br/>Capa de almacenamiento: cifrado con Encryptable<br/>Exportación: enmascarado + copyright"]
    end

    subgraph "Capa 7: Auditoría y trazabilidad"
        L7["OperationLog<br/>Registra todas las operaciones<br/>usuario/IP/hora/origen/parámetros"]
    end

    L1 --> L2 --> L3 --> L4 --> L5 --> L6 --> L7

    style L1 fill:#1677FF,color:#fff
    style L2 fill:#1677FF,color:#fff
    style L3 fill:#FA8C16,color:#fff
    style L4 fill:#FA8C16,color:#fff
    style L5 fill:#52C41A,color:#fff
    style L6 fill:#722ED1,color:#fff
    style L7 fill:#FF4D4F,color:#fff
```

---

## 13. Topología de despliegue

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Servidor web"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → redirección 443<br/>gzip on"]
        STA["Archivos estáticos<br/>Flutter Web build/"]
    end

    subgraph "Servidores de aplicación (escalables horizontalmente)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "Capa de datos"
        MYSQL["MySQL 8.0<br/>Replicación maestro-esclavo<br/>Prefijo logistics_"]
        ES["Elasticsearch 8.x<br/>Clúster de 3 nodos<br/>Prefijo logistics_"]
        REDIS["Redis 7.x<br/>Modo Sentinel<br/>poster:captcha:*"]
    end

    subgraph "Monitorización"
        MON["Grafana<br/>+ Prometheus"]
    end

    DNS --> NGX
    NGX --> STA
    NGX --> WM1 & WM2 & WM3
    WM1 & WM2 & WM3 --> MYSQL
    WM1 & WM2 & WM3 --> ES
    WM1 & WM2 & WM3 --> REDIS
    WM1 & WM2 & WM3 --> MON

    style NGX fill:#722ED1,color:#fff
    style WM1 fill:#1677FF,color:#fff
    style WM2 fill:#1677FF,color:#fff
    style WM3 fill:#1677FF,color:#fff
    style MYSQL fill:#1890FF,color:#fff
    style ES fill:#1890FF,color:#fff
    style REDIS fill:#1890FF,color:#fff
```
