> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](03-request-lifecycle.md) | [English](03-request-lifecycle.en.md) | [한국어](03-request-lifecycle.ko.md) | [Русский](03-request-lifecycle.ru.md) | [Deutsch](03-request-lifecycle.de.md) | [Français](03-request-lifecycle.fr.md) | [Español](03-request-lifecycle.es.md) | [Português](03-request-lifecycle.pt.md) | [हिन्दी](03-request-lifecycle.hi.md) | [العربية](03-request-lifecycle.ar.md) | [বাংলা](03-request-lifecycle.bn.md) | [Bahasa Indonesia](03-request-lifecycle.id.md) | [日本語](03-request-lifecycle.ja.md)

# Request Lifecycle

```mermaid
sequenceDiagram
    actor C as Client
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: HTTPS Request
    N->>MW1: Forward Request

    alt Token Missing or Invalid
        MW1-->>C: 401 Unauthorized
    else Token Valid
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: Set $request->adminId
    end

    alt No Permission
        MW2-->>C: 403 Forbidden
    else Has Permission
        MW2->>CTL: Enter Controller
    end

    CTL->>CTL: Parameter Validation
    CTL->>CTL: decodeId(hashid)

    opt Sensitive Operations
        CTL->>CTL: confirmPassword()
        alt Wrong Password
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable Auto Decryption
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: Hashid String

    CTL-->>C: 200 JSON
```
