> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](08-database-er.md) | [English](08-database-er.en.md) | [한국어](08-database-er.ko.md) | [Русский](08-database-er.ru.md) | [Deutsch](08-database-er.de.md) | [Français](08-database-er.fr.md) | [Español](08-database-er.es.md) | [Português](08-database-er.pt.md) | [हिन्दी](08-database-er.hi.md) | [العربية](08-database-er.ar.md) | [বাংলা](08-database-er.bn.md) | [Bahasa Indonesia](08-database-er.id.md) | [日本語](08-database-er.ja.md)

# علاقات ER لقاعدة البيانات

```mermaid
erDiagram
    logistics_admin_user {
        BIGINT id PK "توليد Snowflake"
        VARCHAR username UK "اسم المستخدم"
        VARCHAR password "تجزئة bcrypt"
        VARCHAR real_name "الاسم الحقيقي"
        VARCHAR avatar "رابط الصورة الرمزية"
        VARCHAR email "تخزين مشفّر"
        VARCHAR phone "تخزين مشفّر"
        VARCHAR id_card "تخزين مشفّر"
        TINYINT status "0 معطّل 1 مفعّل"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "حذف ناعم"
    }

    logistics_admin_role {
        BIGINT id PK "توليد Snowflake"
        VARCHAR name "اسم الدور"
        VARCHAR slug UK "معرّف الدور"
        VARCHAR description "الوصف"
        TINYINT status "0 معطّل 1 مفعّل"
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_permission {
        BIGINT id PK "توليد Snowflake"
        BIGINT parent_id FK "معرّف الصلاحية الأب"
        VARCHAR name "اسم الصلاحية"
        VARCHAR slug "معرّف الصلاحية"
        TINYINT type "1 قائمة 2 زر 3 API"
        VARCHAR icon "أيقونة القائمة"
        VARCHAR path "مسار التوجيه"
        INT sort "الترتيب"
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_user_role {
        BIGINT user_id PK_FK "معرّف المستخدم"
        BIGINT role_id PK_FK "معرّف الدور"
    }

    logistics_admin_role_permission {
        BIGINT role_id PK_FK "معرّف الدور"
        BIGINT permission_id PK_FK "معرّف الصلاحية"
    }

    logistics_operation_log {
        BIGINT id PK "توليد Snowflake"
        BIGINT user_id FK "المستخدم الذي نفّذ العملية"
        VARCHAR action "إجراء العملية"
        VARCHAR method "طريقة الطلب"
        VARCHAR path "مسار الطلب"
        VARCHAR ip "عنوان IP للعملية"
        TEXT input "معاملات الطلب مع إخفاء البيانات"
        DATETIME created_at "وقت العملية"
    }

    logistics_system_config {
        BIGINT id PK "توليد Snowflake"
        VARCHAR group_name "مجموعة الإعدادات"
        VARCHAR key_name "مفتاح الإعداد"
        TEXT value "قيمة الإعداد"
        VARCHAR type "نوع القيمة"
        VARCHAR description "الشرح"
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
