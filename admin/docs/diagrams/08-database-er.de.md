> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](08-database-er.md) | [English](08-database-er.en.md) | [한국어](08-database-er.ko.md) | [Русский](08-database-er.ru.md) | [Deutsch](08-database-er.de.md) | [Français](08-database-er.fr.md) | [Español](08-database-er.es.md) | [Português](08-database-er.pt.md) | [हिन्दी](08-database-er.hi.md) | [العربية](08-database-er.ar.md) | [বাংলা](08-database-er.bn.md) | [Bahasa Indonesia](08-database-er.id.md) | [日本語](08-database-er.ja.md)

# ER-Beziehungen der Datenbank

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "per Snowflake generiert"
        VARCHAR username UK "Benutzername"
        VARCHAR password "bcrypt-Hash"
        VARCHAR real_name "Echter Name"
        VARCHAR avatar "Avatar-URL"
        VARCHAR email "verschlüsselt gespeichert"
        VARCHAR phone "verschlüsselt gespeichert"
        VARCHAR id_card "verschlüsselt gespeichert"
        TINYINT status "0 deaktiviert, 1 aktiviert"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Soft Delete"
    }

    erik_admin_role {
        BIGINT id PK "per Snowflake generiert"
        VARCHAR name "Rollenname"
        VARCHAR slug UK "Rollenkennung"
        VARCHAR description "Beschreibung"
        TINYINT status "0 deaktiviert, 1 aktiviert"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "per Snowflake generiert"
        BIGINT parent_id FK "ID der übergeordneten Berechtigung"
        VARCHAR name "Berechtigungsname"
        VARCHAR slug "Berechtigungskennung"
        TINYINT type "1 Menü, 2 Schaltfläche, 3 API"
        VARCHAR icon "Menü-Symbol"
        VARCHAR path "Routenpfad"
        INT sort "Sortierung"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK "Benutzer-ID"
        BIGINT role_id PK_FK "Rollen-ID"
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK "Rollen-ID"
        BIGINT permission_id PK_FK "Berechtigungs-ID"
    }

    erik_operation_log {
        BIGINT id PK "per Snowflake generiert"
        BIGINT user_id FK "Ausführender Benutzer"
        VARCHAR action "Aktion"
        VARCHAR method "Request-Methode"
        VARCHAR path "Request-Pfad"
        VARCHAR ip "Aktions-IP"
        TEXT input "Request-Parameter (maskiert)"
        DATETIME created_at "Aktionszeitpunkt"
    }

    erik_system_config {
        BIGINT id PK "per Snowflake generiert"
        VARCHAR group_name "Konfigurationsgruppe"
        VARCHAR key_name "Konfigurationsschlüssel"
        TEXT value "Konfigurationswert"
        VARCHAR type "Werttyp"
        VARCHAR description "Beschreibung"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user ||--o{ erik_admin_user_role : user_id
    erik_admin_role ||--o{ erik_admin_user_role : role_id
    erik_admin_role ||--o{ erik_admin_role_permission : role_id
    erik_admin_permission ||--o{ erik_admin_role_permission : permission_id
    erik_admin_user ||--o{ erik_operation_log : user_id
    erik_admin_permission ||--o{ erik_admin_permission : parent_id
```
