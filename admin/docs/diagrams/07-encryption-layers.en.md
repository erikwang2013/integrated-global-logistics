> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](07-encryption-layers.md) | [English](07-encryption-layers.en.md) | [한국어](07-encryption-layers.ko.md) | [Русский](07-encryption-layers.ru.md) | [Deutsch](07-encryption-layers.de.md) | [Français](07-encryption-layers.fr.md) | [Español](07-encryption-layers.es.md) | [Português](07-encryption-layers.pt.md) | [हिन्दी](07-encryption-layers.hi.md) | [العربية](07-encryption-layers.ar.md) | [বাংলা](07-encryption-layers.bn.md) | [Bahasa Indonesia](07-encryption-layers.id.md) | [日本語](07-encryption-layers.ja.md)

# Data Encryption Layers

```mermaid
flowchart TB
    subgraph transport["Transport Layer Encryption - encryption"]
        e1["Client Sends Sensitive Data"]
        e2["AES-256-CBC Encryption"]
        e3["API Transfers Ciphertext"]
        e4["Server Decrypts and Processes"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["Storage Layer Encryption - encryptable"]
        d1["Model casts Configuration<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["Auto-encrypt on Write"]
        d3["MySQL VARCHAR(500) Stores Ciphertext"]
        d4["Auto-decrypt on Read"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["Display Layer Masking"]
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
