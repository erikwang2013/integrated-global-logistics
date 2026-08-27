> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](03-request-lifecycle.md) | [English](03-request-lifecycle.en.md) | [한국어](03-request-lifecycle.ko.md) | [Русский](03-request-lifecycle.ru.md) | [Deutsch](03-request-lifecycle.de.md) | [Français](03-request-lifecycle.fr.md) | [Español](03-request-lifecycle.es.md) | [Português](03-request-lifecycle.pt.md) | [हिन्दी](03-request-lifecycle.hi.md) | [العربية](03-request-lifecycle.ar.md) | [বাংলা](03-request-lifecycle.bn.md) | [Bahasa Indonesia](03-request-lifecycle.id.md) | [日本語](03-request-lifecycle.ja.md)

# Ciclo de vida de una solicitud

```mermaid
sequenceDiagram
    actor C as Cliente
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: Petición HTTPS
    N->>MW1: Reenviar solicitud

    alt Token ausente o no válido
        MW1-->>C: 401 Unauthorized
    else Token válido
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: Establecer $request->adminId
    end

    alt Sin permisos
        MW2-->>C: 403 Forbidden
    else Con permisos
        MW2->>CTL: Entrar al controlador
    end

    CTL->>CTL: Validación de parámetros
    CTL->>CTL: decodeId(hashid)

    opt Operaciones sensibles
        CTL->>CTL: confirmPassword()
        alt Contraseña incorrecta
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: Descifrado automático de encryptable
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: Cadena hashid

    CTL-->>C: 200 JSON
```
