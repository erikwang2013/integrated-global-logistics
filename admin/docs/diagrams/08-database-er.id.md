> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](08-database-er.md) | [English](08-database-er.en.md) | [한국어](08-database-er.ko.md) | [Русский](08-database-er.ru.md) | [Deutsch](08-database-er.de.md) | [Français](08-database-er.fr.md) | [Español](08-database-er.es.md) | [Português](08-database-er.pt.md) | [हिन्दी](08-database-er.hi.md) | [العربية](08-database-er.ar.md) | [বাংলা](08-database-er.bn.md) | [Bahasa Indonesia](08-database-er.id.md) | [日本語](08-database-er.ja.md)

# Relasi ER Basis Data

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Dihasilkan Snowflake"
        VARCHAR username UK "Nama pengguna"
        VARCHAR password "Hash bcrypt"
        VARCHAR real_name "Nama asli"
        VARCHAR avatar "URL avatar"
        VARCHAR email "Disimpan terenkripsi"
        VARCHAR phone "Disimpan terenkripsi"
        VARCHAR id_card "Disimpan terenkripsi"
        TINYINT status "0 nonaktif 1 aktif"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Penghapusan lunak"
    }

    erik_admin_role {
        BIGINT id PK "Dihasilkan Snowflake"
        VARCHAR name "Nama role"
        VARCHAR slug UK "Identifikasi role"
        VARCHAR description "Deskripsi"
        TINYINT status "0 nonaktif 1 aktif"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Dihasilkan Snowflake"
        BIGINT parent_id FK "ID izin induk"
        VARCHAR name "Nama izin"
        VARCHAR slug "Identifikasi izin"
        TINYINT type "1 menu 2 tombol 3 API"
        VARCHAR icon "Ikon menu"
        VARCHAR path "Jalur rute"
        INT sort "Urutan"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK "ID pengguna"
        BIGINT role_id PK_FK "ID role"
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK "ID role"
        BIGINT permission_id PK_FK "ID izin"
    }

    erik_operation_log {
        BIGINT id PK "Dihasilkan Snowflake"
        BIGINT user_id FK "Pengguna operasi"
        VARCHAR action "Aksi operasi"
        VARCHAR method "Metode permintaan"
        VARCHAR path "Jalur permintaan"
        VARCHAR ip "IP operasi"
        TEXT input "Parameter permintaan dimasking"
        DATETIME created_at "Waktu operasi"
    }

    erik_system_config {
        BIGINT id PK "Dihasilkan Snowflake"
        VARCHAR group_name "Grup konfigurasi"
        VARCHAR key_name "Kunci konfigurasi"
        TEXT value "Nilai konfigurasi"
        VARCHAR type "Tipe nilai"
        VARCHAR description "Keterangan"
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
