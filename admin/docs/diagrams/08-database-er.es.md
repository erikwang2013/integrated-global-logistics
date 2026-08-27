> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](08-database-er.md) | [English](08-database-er.en.md) | [한국어](08-database-er.ko.md) | [Русский](08-database-er.ru.md) | [Deutsch](08-database-er.de.md) | [Français](08-database-er.fr.md) | [Español](08-database-er.es.md) | [Português](08-database-er.pt.md) | [हिन्दी](08-database-er.hi.md) | [العربية](08-database-er.ar.md) | [বাংলা](08-database-er.bn.md) | [Bahasa Indonesia](08-database-er.id.md) | [日本語](08-database-er.ja.md)

# Relaciones ER de la base de datos

```mermaid
erDiagram
    logistics_admin_user {
        BIGINT id PK "Generado por Snowflake"
        VARCHAR username UK "Nombre de usuario"
        VARCHAR password "Hash bcrypt"
        VARCHAR real_name "Nombre real"
        VARCHAR avatar "URL del avatar"
        VARCHAR email "Almacenamiento cifrado"
        VARCHAR phone "Almacenamiento cifrado"
        VARCHAR id_card "Almacenamiento cifrado"
        TINYINT status "0 deshabilitado, 1 habilitado"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Eliminación suave"
    }

    logistics_admin_role {
        BIGINT id PK "Generado por Snowflake"
        VARCHAR name "Nombre del rol"
        VARCHAR slug UK "Identificador del rol"
        VARCHAR description "Descripción"
        TINYINT status "0 deshabilitado, 1 habilitado"
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_permission {
        BIGINT id PK "Generado por Snowflake"
        BIGINT parent_id FK "ID del permiso padre"
        VARCHAR name "Nombre del permiso"
        VARCHAR slug "Identificador del permiso"
        TINYINT type "1 menú, 2 botón, 3 API"
        VARCHAR icon "Icono del menú"
        VARCHAR path "Ruta"
        INT sort "Orden"
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_user_role {
        BIGINT user_id PK_FK "ID del usuario"
        BIGINT role_id PK_FK "ID del rol"
    }

    logistics_admin_role_permission {
        BIGINT role_id PK_FK "ID del rol"
        BIGINT permission_id PK_FK "ID del permiso"
    }

    logistics_operation_log {
        BIGINT id PK "Generado por Snowflake"
        BIGINT user_id FK "Usuario que operó"
        VARCHAR action "Acción"
        VARCHAR method "Método de la solicitud"
        VARCHAR path "Ruta de la solicitud"
        VARCHAR ip "IP de la operación"
        TEXT input "Parámetros de solicitud enmascarados"
        DATETIME created_at "Hora de la operación"
    }

    logistics_system_config {
        BIGINT id PK "Generado por Snowflake"
        VARCHAR group_name "Grupo de configuración"
        VARCHAR key_name "Clave de configuración"
        TEXT value "Valor de configuración"
        VARCHAR type "Tipo de valor"
        VARCHAR description "Descripción"
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_user ||--o{ logistics_admin_user_role : user_id
    logistics_admin_role ||--o{ logistics_admin_user_role : role_id
    logistics_admin_role ||--o{ logistics_admin_role_permission : role_id
    logistics_admin_permission ||--o{ logistics_admin_role_permission : permission_id
    logistics_admin_user ||--o{ logistics_operation_log : user_id
    logistics_admin_permission ||--o{ logistics_admin_permission : parent_id
```
