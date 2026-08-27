> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](01-system-architecture.md) | [English](01-system-architecture.en.md) | [한국어](01-system-architecture.ko.md) | [Русский](01-system-architecture.ru.md) | [Deutsch](01-system-architecture.de.md) | [Français](01-system-architecture.fr.md) | [Español](01-system-architecture.es.md) | [Português](01-system-architecture.pt.md) | [हिन्दी](01-system-architecture.hi.md) | [العربية](01-system-architecture.ar.md) | [বাংলা](01-system-architecture.bn.md) | [Bahasa Indonesia](01-system-architecture.id.md) | [日本語](01-system-architecture.ja.md)

# 시스템 토폴로지 아키텍처

```mermaid
flowchart TB
    subgraph clients["클라이언트 계층"]
        flutter["Flutter Web<br/>PC 관리자 콘솔"]
        harmony["HarmonyOS ArkTS<br/>모바일/태블릿 클라이언트"]
    end

    subgraph gateway["게이트웨이 계층"]
        nginx["Nginx<br/>HTTPS 리버스 프록시<br/>Gzip 압축"]
    end

    subgraph app["애플리케이션 계층 - webman v2"]
        auth["AdminAuth<br/>JWT 검증"]
        perm["AdminPermission<br/>RBAC 인가"]
        admin["관리자 Controller<br/>Dashboard/User/Role/Permission"]
        public["공개 Controller<br/>Captcha/Auth"]
        common["Common Services<br/>Hashids/Snowflake/Encryption"]
    end

    subgraph storage["저장소 계층"]
        mysql[("MySQL 8.0<br/>주 저장소 - logistics_ 접두사")]
        es[("Elasticsearch<br/>전문 검색 - logistics_ 접두사")]
        redis[("Redis<br/>Session/캐시/Captcha")]
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
