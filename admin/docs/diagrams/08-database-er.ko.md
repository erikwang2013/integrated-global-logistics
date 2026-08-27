> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](08-database-er.md) | [English](08-database-er.en.md) | [한국어](08-database-er.ko.md) | [Русский](08-database-er.ru.md) | [Deutsch](08-database-er.de.md) | [Français](08-database-er.fr.md) | [Español](08-database-er.es.md) | [Português](08-database-er.pt.md) | [हिन्दी](08-database-er.hi.md) | [العربية](08-database-er.ar.md) | [বাংলা](08-database-er.bn.md) | [Bahasa Indonesia](08-database-er.id.md) | [日本語](08-database-er.ja.md)

# 데이터베이스 ER 관계

```mermaid
erDiagram
    logistics_admin_user {
        BIGINT id PK "Snowflake 생성"
        VARCHAR username UK "사용자 이름"
        VARCHAR password "bcrypt 해시"
        VARCHAR real_name "실명"
        VARCHAR avatar "아바타 URL"
        VARCHAR email "암호화 저장"
        VARCHAR phone "암호화 저장"
        VARCHAR id_card "암호화 저장"
        TINYINT status "0 비활성화 1 활성화"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "소프트 삭제"
    }

    logistics_admin_role {
        BIGINT id PK "Snowflake 생성"
        VARCHAR name "역할 이름"
        VARCHAR slug UK "역할 식별자"
        VARCHAR description "설명"
        TINYINT status "0 비활성화 1 활성화"
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_permission {
        BIGINT id PK "Snowflake 생성"
        BIGINT parent_id FK "상위 권한 ID"
        VARCHAR name "권한 이름"
        VARCHAR slug "권한 식별자"
        TINYINT type "1 메뉴 2 버튼 3 API"
        VARCHAR icon "메뉴 아이콘"
        VARCHAR path "라우트 경로"
        INT sort "정렬"
        DATETIME created_at
        DATETIME updated_at
    }

    logistics_admin_user_role {
        BIGINT user_id PK_FK "사용자 ID"
        BIGINT role_id PK_FK "역할 ID"
    }

    logistics_admin_role_permission {
        BIGINT role_id PK_FK "역할 ID"
        BIGINT permission_id PK_FK "권한 ID"
    }

    logistics_operation_log {
        BIGINT id PK "Snowflake 생성"
        BIGINT user_id FK "작업 사용자"
        VARCHAR action "작업 동작"
        VARCHAR method "요청 메서드"
        VARCHAR path "요청 경로"
        VARCHAR ip "작업 IP"
        TEXT input "요청 파라미터 마스킹"
        DATETIME created_at "작업 시간"
    }

    logistics_system_config {
        BIGINT id PK "Snowflake 생성"
        VARCHAR group_name "설정 그룹"
        VARCHAR key_name "설정 키"
        TEXT value "설정 값"
        VARCHAR type "값 유형"
        VARCHAR description "설명"
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
