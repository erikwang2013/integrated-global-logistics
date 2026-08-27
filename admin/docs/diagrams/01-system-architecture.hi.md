> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](01-system-architecture.md) | [English](01-system-architecture.en.md) | [한국어](01-system-architecture.ko.md) | [Русский](01-system-architecture.ru.md) | [Deutsch](01-system-architecture.de.md) | [Français](01-system-architecture.fr.md) | [Español](01-system-architecture.es.md) | [Português](01-system-architecture.pt.md) | [हिन्दी](01-system-architecture.hi.md) | [العربية](01-system-architecture.ar.md) | [বাংলা](01-system-architecture.bn.md) | [Bahasa Indonesia](01-system-architecture.id.md) | [日本語](01-system-architecture.ja.md)

# सिस्टम टोपोलॉजी आर्किटेक्चर

```mermaid
flowchart TB
    subgraph clients["क्लाइंट परत"]
        flutter["Flutter Web<br/>PC प्रशासन पैनल"]
        harmony["HarmonyOS ArkTS<br/>मोबाइल/टैबलेट क्लाइंट"]
    end

    subgraph gateway["गेटवे परत"]
        nginx["Nginx<br/>HTTPS रिवर्स प्रॉक्सी<br/>Gzip कंप्रेशन"]
    end

    subgraph app["एप्लिकेशन परत - webman v2"]
        auth["AdminAuth<br/>JWT सत्यापन"]
        perm["AdminPermission<br/>RBAC प्राधिकरण"]
        admin["प्रशासन Controller<br/>Dashboard/User/Role/Permission"]
        public["सार्वजनिक Controller<br/>Captcha/Auth"]
        common["Common Services<br/>Hashids/Snowflake/Encryption"]
    end

    subgraph storage["स्टोरेज परत"]
        mysql[("MySQL 8.0<br/>मुख्य स्टोरेज - erik_ प्रीफ़िक्स")]
        es[("Elasticsearch<br/>फुल-टेक्स्ट सर्च - erik_ प्रीफ़िक्स")]
        redis[("Redis<br/>Session/कैश/Captcha")]
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
