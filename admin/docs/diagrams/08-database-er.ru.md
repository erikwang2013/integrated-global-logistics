> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](08-database-er.md) | [English](08-database-er.en.md) | [한국어](08-database-er.ko.md) | [Русский](08-database-er.ru.md) | [Deutsch](08-database-er.de.md) | [Français](08-database-er.fr.md) | [Español](08-database-er.es.md) | [Português](08-database-er.pt.md) | [हिन्दी](08-database-er.hi.md) | [العربية](08-database-er.ar.md) | [বাংলা](08-database-er.bn.md) | [Bahasa Indonesia](08-database-er.id.md) | [日本語](08-database-er.ja.md)

# ER-связи базы данных

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "генерация Snowflake"
        VARCHAR username UK "имя пользователя"
        VARCHAR password "bcrypt-хеш"
        VARCHAR real_name "настоящее имя"
        VARCHAR avatar "URL аватара"
        VARCHAR email "шифрованное хранение"
        VARCHAR phone "шифрованное хранение"
        VARCHAR id_card "шифрованное хранение"
        TINYINT status "0 отключён 1 включён"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "мягкое удаление"
    }

    erik_admin_role {
        BIGINT id PK "генерация Snowflake"
        VARCHAR name "название роли"
        VARCHAR slug UK "идентификатор роли"
        VARCHAR description "описание"
        TINYINT status "0 отключён 1 включён"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "генерация Snowflake"
        BIGINT parent_id FK "ID родительского разрешения"
        VARCHAR name "название разрешения"
        VARCHAR slug "идентификатор разрешения"
        TINYINT type "1 меню 2 кнопка 3 API"
        VARCHAR icon "иконка меню"
        VARCHAR path "путь маршрута"
        INT sort "сортировка"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK "ID пользователя"
        BIGINT role_id PK_FK "ID роли"
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK "ID роли"
        BIGINT permission_id PK_FK "ID разрешения"
    }

    erik_operation_log {
        BIGINT id PK "генерация Snowflake"
        BIGINT user_id FK "пользователь операции"
        VARCHAR action "действие"
        VARCHAR method "метод запроса"
        VARCHAR path "путь запроса"
        VARCHAR ip "IP операции"
        TEXT input "маскированные параметры запроса"
        DATETIME created_at "время операции"
    }

    erik_system_config {
        BIGINT id PK "генерация Snowflake"
        VARCHAR group_name "группа конфигурации"
        VARCHAR key_name "ключ конфигурации"
        TEXT value "значение конфигурации"
        VARCHAR type "тип значения"
        VARCHAR description "описание"
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
