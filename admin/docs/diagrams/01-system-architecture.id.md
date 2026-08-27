> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](01-system-architecture.md) | [English](01-system-architecture.en.md) | [한국어](01-system-architecture.ko.md) | [Русский](01-system-architecture.ru.md) | [Deutsch](01-system-architecture.de.md) | [Français](01-system-architecture.fr.md) | [Español](01-system-architecture.es.md) | [Português](01-system-architecture.pt.md) | [हिन्दी](01-system-architecture.hi.md) | [العربية](01-system-architecture.ar.md) | [বাংলা](01-system-architecture.bn.md) | [Bahasa Indonesia](01-system-architecture.id.md) | [日本語](01-system-architecture.ja.md)

# Arsitektur Topologi Sistem

```mermaid
flowchart TB
    subgraph clients["Lapisan Klien"]
        flutter["Flutter Web<br/>Panel Admin PC"]
        harmony["HarmonyOS ArkTS<br/>Klien ponsel/tablet"]
    end

    subgraph gateway["Lapisan Gateway"]
        nginx["Nginx<br/>Proksi balik HTTPS<br/>Kompresi Gzip"]
    end

    subgraph app["Lapisan Aplikasi - webman v2"]
        auth["AdminAuth<br/>Verifikasi JWT"]
        perm["AdminPermission<br/>Otorisasi RBAC"]
        admin["Controller Admin<br/>Dashboard/User/Role/Permission"]
        public["Controller Publik<br/>Captcha/Auth"]
        common["Common Services<br/>Hashids/Snowflake/Encryption"]
    end

    subgraph storage["Lapisan Penyimpanan"]
        mysql[("MySQL 8.0<br/>Penyimpanan utama - prefiks erik_")]
        es[("Elasticsearch<br/>Pencarian teks lengkap - prefiks erik_")]
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
