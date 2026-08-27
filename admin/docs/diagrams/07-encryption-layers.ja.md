> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](07-encryption-layers.md) | [English](07-encryption-layers.en.md) | [한국어](07-encryption-layers.ko.md) | [Русский](07-encryption-layers.ru.md) | [Deutsch](07-encryption-layers.de.md) | [Français](07-encryption-layers.fr.md) | [Español](07-encryption-layers.es.md) | [Português](07-encryption-layers.pt.md) | [हिन्दी](07-encryption-layers.hi.md) | [العربية](07-encryption-layers.ar.md) | [বাংলা](07-encryption-layers.bn.md) | [Bahasa Indonesia](07-encryption-layers.id.md) | [日本語](07-encryption-layers.ja.md)

# データ暗号化の階層

```mermaid
flowchart TB
    subgraph transport["転送層の暗号化 - encryption"]
        e1["クライアントが機密データを送信"]
        e2["AES-256-CBC 暗号化"]
        e3["API転送の暗号文"]
        e4["サーバーが復号して処理"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["保存層の暗号化 - encryptable"]
        d1["Model casts設定<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["書き込み時に自動暗号化"]
        d3["MySQL VARCHAR(500)で暗号文を保存"]
        d4["読み出し時に自動復号"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["表示層のマスキング"]
        m1["phone: 138****1234"]
        m2["email: a***@example.com"]
        m3["id_card: ********"]
        d4 --> m1 & m2 & m3
    end

    e4 --> d1

    style e2 fill:#1677FF,color:#fff
    style d2 fill:#FA8C16,color:#fff
    style m1 fill:#52C41A,color:#fff
```
