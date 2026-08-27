> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](01-system-architecture.md) | [English](01-system-architecture.en.md) | [한국어](01-system-architecture.ko.md) | [Русский](01-system-architecture.ru.md) | [Deutsch](01-system-architecture.de.md) | [Français](01-system-architecture.fr.md) | [Español](01-system-architecture.es.md) | [Português](01-system-architecture.pt.md) | [हिन्दी](01-system-architecture.hi.md) | [العربية](01-system-architecture.ar.md) | [বাংলা](01-system-architecture.bn.md) | [Bahasa Indonesia](01-system-architecture.id.md) | [日本語](01-system-architecture.ja.md)

# Системная топология

```mermaid
flowchart TB
    subgraph clients["Клиентский слой"]
        flutter["Flutter Web<br/>PC админ-панель"]
        harmony["HarmonyOS ArkTS<br/>клиент для телефона/планшета"]
    end

    subgraph gateway["Шлюзовой слой"]
        nginx["Nginx<br/>HTTPS обратный прокси<br/>Gzip-сжатие"]
    end

    subgraph app["Прикладной слой — webman v2"]
        auth["AdminAuth<br/>JWT-проверка"]
        perm["AdminPermission<br/>RBAC-авторизация"]
        admin["Контроллеры админки<br/>Dashboard/User/Role/Permission"]
        public["Публичные контроллеры<br/>Captcha/Auth"]
        common["Common Services<br/>Hashids/Snowflake/Encryption"]
    end

    subgraph storage["Слой хранения"]
        mysql[("MySQL 8.0<br/>основное хранилище — префикс erik_")]
        es[("Elasticsearch<br/>полнотекстовый поиск — префикс erik_")]
        redis[("Redis<br/>Session/кэш/Captcha")]
    end

    flutter --> nginx
    harmony --> nginx
    nginx --> auth
    auth --> perm
    perm --> admin
    auth --> public
    admin --> common
    public --> common
    admin --> mysql
    public --> mysql
    admin --> es
    public --> es
    auth --> redis
    public --> redis

    style flutter fill:#1677FF,color:#fff
    style harmony fill:#1677FF,color:#fff
    style nginx fill:#722ED1,color:#fff
    style auth fill:#FA8C16,color:#fff
    style perm fill:#FA8C16,color:#fff
    style common fill:#52C41A,color:#fff
    style mysql fill:#1890FF,color:#fff
    style es fill:#1890FF,color:#fff
    style redis fill:#1890FF,color:#fff
```
