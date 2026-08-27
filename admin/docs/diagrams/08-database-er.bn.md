> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](08-database-er.md) | [English](08-database-er.en.md) | [한국어](08-database-er.ko.md) | [Русский](08-database-er.ru.md) | [Deutsch](08-database-er.de.md) | [Français](08-database-er.fr.md) | [Español](08-database-er.es.md) | [Português](08-database-er.pt.md) | [हिन्दी](08-database-er.hi.md) | [العربية](08-database-er.ar.md) | [বাংলা](08-database-er.bn.md) | [Bahasa Indonesia](08-database-er.id.md) | [日本語](08-database-er.ja.md)

# ডেটাবেস ER সম্পর্ক

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Snowflake জেনারেশন"
        VARCHAR username UK "ব্যবহারকারীর নাম"
        VARCHAR password "bcrypt হ্যাশ"
        VARCHAR real_name "প্রকৃত নাম"
        VARCHAR avatar "অ্যাভাটার URL"
        VARCHAR email "এনক্রিপ্টেড স্টোরেজ"
        VARCHAR phone "এনক্রিপ্টেড স্টোরেজ"
        VARCHAR id_card "এনক্রিপ্টেড স্টোরেজ"
        TINYINT status "0 নিষ্ক্রিয় 1 সক্রিয়"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "সফট ডিলিট"
    }

    erik_admin_role {
        BIGINT id PK "Snowflake জেনারেশন"
        VARCHAR name "ভূমিকার নাম"
        VARCHAR slug UK "ভূমিকা আইডি"
        VARCHAR description "বিবরণ"
        TINYINT status "0 নিষ্ক্রিয় 1 সক্রিয়"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Snowflake জেনারেশন"
        BIGINT parent_id FK "প্যারেন্ট পারমিশন ID"
        VARCHAR name "পারমিশনের নাম"
        VARCHAR slug "পারমিশন আইডি"
        TINYINT type "1 মেনু 2 বোতাম 3 API"
        VARCHAR icon "মেনু আইকন"
        VARCHAR path "রাউট পাথ"
        INT sort "সাজানো"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK "ব্যবহারকারী ID"
        BIGINT role_id PK_FK "ভূমিকা ID"
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK "ভূমিকা ID"
        BIGINT permission_id PK_FK "পারমিশন ID"
    }

    erik_operation_log {
        BIGINT id PK "Snowflake জেনারেশন"
        BIGINT user_id FK "অপারেটিং ব্যবহারকারী"
        VARCHAR action "অপারেশন অ্যাকশন"
        VARCHAR method "অনুরোধ মেথড"
        VARCHAR path "অনুরোধ পাথ"
        VARCHAR ip "অপারেশন IP"
        TEXT input "অনুরোধ প্যারামিটার মাস্কিং"
        DATETIME created_at "অপারেশনের সময়"
    }

    erik_system_config {
        BIGINT id PK "Snowflake জেনারেশন"
        VARCHAR group_name "কনফিগারেশন গ্রুপ"
        VARCHAR key_name "কনফিগারেশন কী"
        TEXT value "কনফিগারেশন মান"
        VARCHAR type "মানের ধরন"
        VARCHAR description "ব্যাখ্যা"
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
