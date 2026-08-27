> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](03-request-lifecycle.md) | [English](03-request-lifecycle.en.md) | [한국어](03-request-lifecycle.ko.md) | [Русский](03-request-lifecycle.ru.md) | [Deutsch](03-request-lifecycle.de.md) | [Français](03-request-lifecycle.fr.md) | [Español](03-request-lifecycle.es.md) | [Português](03-request-lifecycle.pt.md) | [हिन्दी](03-request-lifecycle.hi.md) | [العربية](03-request-lifecycle.ar.md) | [বাংলা](03-request-lifecycle.bn.md) | [Bahasa Indonesia](03-request-lifecycle.id.md) | [日本語](03-request-lifecycle.ja.md)

# Cycle de vie d'une requête

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

    C->>N: Requête HTTPS
    N->>MW1: Transmet la requête

    alt Jeton manquant ou invalide
        MW1-->>C: 401 Unauthorized
    else Jeton valide
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: Définit $request->adminId
    end

    alt Sans autorisation
        MW2-->>C: 403 Forbidden
    else Avec autorisation
        MW2->>CTL: Entre dans le contrôleur
    end

    CTL->>CTL: Validation des paramètres
    CTL->>CTL: decodeId(hashid)

    opt Opérations sensibles
        CTL->>CTL: confirmPassword()
        alt Mot de passe incorrect
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: Décryptage automatique encryptable
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: Chaîne hashid

    CTL-->>C: 200 JSON
```
