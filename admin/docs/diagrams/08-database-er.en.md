> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](08-database-er.md) | [English](08-database-er.en.md) | [한국어](08-database-er.ko.md) | [Русский](08-database-er.ru.md) | [Deutsch](08-database-er.de.md) | [Français](08-database-er.fr.md) | [Español](08-database-er.es.md) | [Português](08-database-er.pt.md) | [हिन्दी](08-database-er.hi.md) | [العربية](08-database-er.ar.md) | [বাংলা](08-database-er.bn.md) | [Bahasa Indonesia](08-database-er.id.md) | [日本語](08-database-er.ja.md)

# Database ER Relationships

```mermaid
erDiagram
    logistics_admin_user {
        BIGINT id PK "Snowflake Generated"
        VARCHAR username UK "Username"
        VARCHAR password "bcrypt Hash"
        VARCHAR real_name "Real Name"
        VARCHAR avatar "Avatar URL"
        VARCHAR email "Encrypted Storage"
        VARCHAR phone "Encrypted Storage"
        VARCHAR id_card "Encrypted Storage"
        TINYINT status "0 Disabled 1 Enabled"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Soft Delete"
    }

    logistics_admin_role {
        BIGINT id PK "Snowflake Generated"
        VARCHAR name "Role Name"
        VARCHAR slug UK "Role Identifier"
        VARCHAR description "Description"
        TINYINT status "0 Disabled 1 Enabled"
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_permission {
        BIGINT id PK "Snowflake Generated"
        BIGINT parent_id FK "Parent Permission ID"
        VARCHAR name "Permission Name"
        VARCHAR slug "Permission Identifier"
        TINYINT type "1 Menu 2 Button 3 API"
        VARCHAR icon "Menu Icon"
        VARCHAR path "Route Path"
        INT sort "Sort Order"
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_user_role {
        BIGINT user_id PK_FK "User ID"
        BIGINT role_id PK_FK "Role ID"
    }

    logistics_admin_role_permission {
        BIGINT role_id PK_FK "Role ID"
        BIGINT permission_id PK_FK "Permission ID"
    }

    logistics_operation_log {
        BIGINT id PK "Snowflake Generated"
        BIGINT user_id FK "Operating User"
        VARCHAR action "Action"
        VARCHAR method "Request Method"
        VARCHAR path "Request Path"
        VARCHAR ip "Operation IP"
        TEXT input "Masked Request Parameters"
        DATETIME created_at "Operation Time"
    }

    logistics_system_config {
        BIGINT id PK "Snowflake Generated"
        VARCHAR group_name "Config Group"
        VARCHAR key_name "Config Key"
        TEXT value "Config Value"
        VARCHAR type "Value Type"
        VARCHAR description "Description"
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
