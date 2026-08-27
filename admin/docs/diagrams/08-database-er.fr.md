> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](08-database-er.md) | [English](08-database-er.en.md) | [한국어](08-database-er.ko.md) | [Русский](08-database-er.ru.md) | [Deutsch](08-database-er.de.md) | [Français](08-database-er.fr.md) | [Español](08-database-er.es.md) | [Português](08-database-er.pt.md) | [हिन्दी](08-database-er.hi.md) | [العربية](08-database-er.ar.md) | [বাংলা](08-database-er.bn.md) | [Bahasa Indonesia](08-database-er.id.md) | [日本語](08-database-er.ja.md)

# Relations ER de la base de données

```mermaid
erDiagram
    logistics_admin_user {
        BIGINT id PK "Généré par Snowflake"
        VARCHAR username UK "Nom d'utilisateur"
        VARCHAR password "Hash bcrypt"
        VARCHAR real_name "Nom réel"
        VARCHAR avatar "URL de l'avatar"
        VARCHAR email "Stockage chiffré"
        VARCHAR phone "Stockage chiffré"
        VARCHAR id_card "Stockage chiffré"
        TINYINT status "0 désactivé, 1 activé"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Suppression douce"
    }

    logistics_admin_role {
        BIGINT id PK "Généré par Snowflake"
        VARCHAR name "Nom du rôle"
        VARCHAR slug UK "Identifiant du rôle"
        VARCHAR description "Description"
        TINYINT status "0 désactivé, 1 activé"
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_permission {
        BIGINT id PK "Généré par Snowflake"
        BIGINT parent_id FK "ID de la permission parente"
        VARCHAR name "Nom de la permission"
        VARCHAR slug "Identifiant de la permission"
        TINYINT type "1 menu, 2 bouton, 3 API"
        VARCHAR icon "Icône du menu"
        VARCHAR path "Chemin de route"
        INT sort "Tri"
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_user_role {
        BIGINT user_id PK_FK "ID de l'utilisateur"
        BIGINT role_id PK_FK "ID du rôle"
    }

    logistics_admin_role_permission {
        BIGINT role_id PK_FK "ID du rôle"
        BIGINT permission_id PK_FK "ID de la permission"
    }

    logistics_operation_log {
        BIGINT id PK "Généré par Snowflake"
        BIGINT user_id FK "Utilisateur ayant effectué l'opération"
        VARCHAR action "Action effectuée"
        VARCHAR method "Méthode de requête"
        VARCHAR path "Chemin de requête"
        VARCHAR ip "IP de l'opération"
        TEXT input "Paramètres de requête masqués"
        DATETIME created_at "Heure de l'opération"
    }

    logistics_system_config {
        BIGINT id PK "Généré par Snowflake"
        VARCHAR group_name "Groupe de configuration"
        VARCHAR key_name "Clé de configuration"
        TEXT value "Valeur de configuration"
        VARCHAR type "Type de valeur"
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
