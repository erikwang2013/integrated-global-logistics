> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](03-request-lifecycle.md) | [English](03-request-lifecycle.en.md) | [한국어](03-request-lifecycle.ko.md) | [Русский](03-request-lifecycle.ru.md) | [Deutsch](03-request-lifecycle.de.md) | [Français](03-request-lifecycle.fr.md) | [Español](03-request-lifecycle.es.md) | [Português](03-request-lifecycle.pt.md) | [हिन्दी](03-request-lifecycle.hi.md) | [العربية](03-request-lifecycle.ar.md) | [বাংলা](03-request-lifecycle.bn.md) | [Bahasa Indonesia](03-request-lifecycle.id.md) | [日本語](03-request-lifecycle.ja.md)

# अनुरोध जीवनचक्र

```mermaid
sequenceDiagram
    actor C as क्लाइंट
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: HTTPS अनुरोध
    N->>MW1: अनुरोध अग्रेषित करें

    alt Token गायब या अमान्य
        MW1-->>C: 401 Unauthorized
    else Token मान्य
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId सेट करें
    end

    alt कोई अनुमति नहीं
        MW2-->>C: 403 Forbidden
    else अनुमति है
        MW2->>CTL: कंट्रोलर में प्रवेश
    end

    CTL->>CTL: पैरामीटर सत्यापन
    CTL->>CTL: decodeId(hashid)

    opt संवेदनशील ऑपरेशन
        CTL->>CTL: confirmPassword()
        alt पासवर्ड गलत
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable स्वतः डिक्रिप्शन
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: hashid स्ट्रिंग

    CTL-->>C: 200 JSON
```
