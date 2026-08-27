> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](08-database-er.md) | [English](08-database-er.en.md) | [한국어](08-database-er.ko.md) | [Русский](08-database-er.ru.md) | [Deutsch](08-database-er.de.md) | [Français](08-database-er.fr.md) | [Español](08-database-er.es.md) | [Português](08-database-er.pt.md) | [हिन्दी](08-database-er.hi.md) | [العربية](08-database-er.ar.md) | [বাংলা](08-database-er.bn.md) | [Bahasa Indonesia](08-database-er.id.md) | [日本語](08-database-er.ja.md)

# データベース ER 関係

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Snowflake生成"
        VARCHAR username UK "ユーザー名"
        VARCHAR password "bcryptハッシュ"
        VARCHAR real_name "氏名"
        VARCHAR avatar "アバターURL"
        VARCHAR email "暗号化して保存"
        VARCHAR phone "暗号化して保存"
        VARCHAR id_card "暗号化して保存"
        TINYINT status "0無効1有効"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "論理削除"
    }

    erik_admin_role {
        BIGINT id PK "Snowflake生成"
        VARCHAR name "ロール名"
        VARCHAR slug UK "ロール識別子"
        VARCHAR description "説明"
        TINYINT status "0無効1有効"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Snowflake生成"
        BIGINT parent_id FK "親権限ID"
        VARCHAR name "権限名"
        VARCHAR slug "権限識別子"
        TINYINT type "1メニュー2ボタン3API"
        VARCHAR icon "メニューアイコン"
        VARCHAR path "ルートパス"
        INT sort "並び順"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK "ユーザーID"
        BIGINT role_id PK_FK "ロールID"
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK "ロールID"
        BIGINT permission_id PK_FK "権限ID"
    }

    erik_operation_log {
        BIGINT id PK "Snowflake生成"
        BIGINT user_id FK "操作ユーザー"
        VARCHAR action "操作内容"
        VARCHAR method "リクエストメソッド"
        VARCHAR path "リクエストパス"
        VARCHAR ip "操作IP"
        TEXT input "リクエストパラメータをマスキング"
        DATETIME created_at "操作時刻"
    }

    erik_system_config {
        BIGINT id PK "Snowflake生成"
        VARCHAR group_name "設定グループ"
        VARCHAR key_name "設定キー"
        TEXT value "設定値"
        VARCHAR type "値の型"
        VARCHAR description "説明"
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
