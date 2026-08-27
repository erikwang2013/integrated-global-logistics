> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](01-system-architecture.md) | [English](01-system-architecture.en.md) | [한국어](01-system-architecture.ko.md) | [Русский](01-system-architecture.ru.md) | [Deutsch](01-system-architecture.de.md) | [Français](01-system-architecture.fr.md) | [Español](01-system-architecture.es.md) | [Português](01-system-architecture.pt.md) | [हिन्दी](01-system-architecture.hi.md) | [العربية](01-system-architecture.ar.md) | [বাংলা](01-system-architecture.bn.md) | [Bahasa Indonesia](01-system-architecture.id.md) | [日本語](01-system-architecture.ja.md)

# Arquitetura de topologia do sistema

```mermaid
flowchart TB
    subgraph clients["Camada de clientes"]
        flutter["Flutter Web<br/>Painel administrativo PC"]
        harmony["HarmonyOS ArkTS<br/>Cliente celular/tablet"]
    end

    subgraph gateway["Camada de gateway"]
        nginx["Nginx<br/>Proxy reverso HTTPS<br/>Compressão Gzip"]
    end

    subgraph app["Camada de aplicação - webman v2"]
        auth["AdminAuth<br/>Validação JWT"]
        perm["AdminPermission<br/>Autorização RBAC"]
        admin["Controllers de administração<br/>Dashboard/User/Role/Permission"]
        public["Controllers públicos<br/>Captcha/Auth"]
        common["Serviços comuns<br/>Hashids/Snowflake/Encryption"]
    end

    subgraph storage["Camada de armazenamento"]
        mysql[("MySQL 8.0<br/>Armazenamento principal - prefixo logistics_")]
        es[("Elasticsearch<br/>Busca em texto completo - prefixo logistics_")]
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
