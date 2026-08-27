> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](01-system-architecture.md) | [English](01-system-architecture.en.md) | [한국어](01-system-architecture.ko.md) | [Русский](01-system-architecture.ru.md) | [Deutsch](01-system-architecture.de.md) | [Français](01-system-architecture.fr.md) | [Español](01-system-architecture.es.md) | [Português](01-system-architecture.pt.md) | [हिन्दी](01-system-architecture.hi.md) | [العربية](01-system-architecture.ar.md) | [বাংলা](01-system-architecture.bn.md) | [Bahasa Indonesia](01-system-architecture.id.md) | [日本語](01-system-architecture.ja.md)

# Systemarchitektur (Topologie)

```mermaid
flowchart TB
    subgraph clients["Clientschicht"]
        flutter["Flutter Web<br/>PC-Verwaltungskonsole"]
        harmony["HarmonyOS ArkTS<br/>Smartphone-/Tablet-Client"]
    end

    subgraph gateway["Gatewayschicht"]
        nginx["Nginx<br/>HTTPS-Reverse-Proxy<br/>Gzip-Komprimierung"]
    end

    subgraph app["Anwendungsschicht - webman v2"]
        auth["AdminAuth<br/>JWT-Verifizierung"]
        perm["AdminPermission<br/>RBAC-Autorisierung"]
        admin["Admin-Controller<br/>Dashboard/User/Role/Permission"]
        public["Öffentliche Controller<br/>Captcha/Auth"]
        common["Common Services<br/>Hashids/Snowflake/Encryption"]
    end

    subgraph storage["Speicherschicht"]
        mysql[("MySQL 8.0<br/>Primärspeicher - erik_-Präfix")]
        es[("Elasticsearch<br/>Volltextsuche - erik_-Präfix")]
        redis[("Redis<br/>Session/Cache/Captcha")]
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
