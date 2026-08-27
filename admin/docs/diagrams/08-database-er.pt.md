> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](08-database-er.md) | [English](08-database-er.en.md) | [한국어](08-database-er.ko.md) | [Русский](08-database-er.ru.md) | [Deutsch](08-database-er.de.md) | [Français](08-database-er.fr.md) | [Español](08-database-er.es.md) | [Português](08-database-er.pt.md) | [हिन्दी](08-database-er.hi.md) | [العربية](08-database-er.ar.md) | [বাংলা](08-database-er.bn.md) | [Bahasa Indonesia](08-database-er.id.md) | [日本語](08-database-er.ja.md)

# Relações ER do banco de dados

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Gerado por Snowflake"
        VARCHAR username UK "Nome de usuário"
        VARCHAR password "Hash bcrypt"
        VARCHAR real_name "Nome real"
        VARCHAR avatar "URL do avatar"
        VARCHAR email "Armazenamento criptografado"
        VARCHAR phone "Armazenamento criptografado"
        VARCHAR id_card "Armazenamento criptografado"
        TINYINT status "0 desabilitado 1 habilitado"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Soft delete"
    }

    erik_admin_role {
        BIGINT id PK "Gerado por Snowflake"
        VARCHAR name "Nome da role"
        VARCHAR slug UK "Identificador da role"
        VARCHAR description "Descrição"
        TINYINT status "0 desabilitado 1 habilitado"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Gerado por Snowflake"
        BIGINT parent_id FK "ID da permissão pai"
        VARCHAR name "Nome da permissão"
        VARCHAR slug "Identificador da permissão"
        TINYINT type "1 menu 2 botão 3 API"
        VARCHAR icon "Ícone do menu"
        VARCHAR path "Caminho da rota"
        INT sort "Ordenação"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK "ID do usuário"
        BIGINT role_id PK_FK "ID da role"
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK "ID da role"
        BIGINT permission_id PK_FK "ID da permissão"
    }

    erik_operation_log {
        BIGINT id PK "Gerado por Snowflake"
        BIGINT user_id FK "Usuário da operação"
        VARCHAR action "Ação executada"
        VARCHAR method "Método da requisição"
        VARCHAR path "Caminho da requisição"
        VARCHAR ip "IP da operação"
        TEXT input "Parâmetros da requisição mascarados"
        DATETIME created_at "Horário da operação"
    }

    erik_system_config {
        BIGINT id PK "Gerado por Snowflake"
        VARCHAR group_name "Grupo de configuração"
        VARCHAR key_name "Chave de configuração"
        TEXT value "Valor da configuração"
        VARCHAR type "Tipo do valor"
        VARCHAR description "Descrição"
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
