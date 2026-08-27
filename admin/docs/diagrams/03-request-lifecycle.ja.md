> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](03-request-lifecycle.md) | [English](03-request-lifecycle.en.md) | [한국어](03-request-lifecycle.ko.md) | [Русский](03-request-lifecycle.ru.md) | [Deutsch](03-request-lifecycle.de.md) | [Français](03-request-lifecycle.fr.md) | [Español](03-request-lifecycle.es.md) | [Português](03-request-lifecycle.pt.md) | [हिन्दी](03-request-lifecycle.hi.md) | [العربية](03-request-lifecycle.ar.md) | [বাংলা](03-request-lifecycle.bn.md) | [Bahasa Indonesia](03-request-lifecycle.id.md) | [日本語](03-request-lifecycle.ja.md)

# リクエストライフサイクル

```mermaid
sequenceDiagram
    actor C as クライアント
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: HTTPS リクエスト
    N->>MW1: リクエスト転送

    alt Token欠落または無効
        MW1-->>C: 401 Unauthorized
    else Token有効
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId を設定
    end

    alt 権限なし
        MW2-->>C: 403 Forbidden
    else 権限あり
        MW2->>CTL: コントローラーへ進入
    end

    CTL->>CTL: パラメータ検証
    CTL->>CTL: decodeId(hashid)

    opt 機密操作
        CTL->>CTL: confirmPassword()
        alt パスワードエラー
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable自動復号
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: hashid文字列

    CTL-->>C: 200 JSON
```
