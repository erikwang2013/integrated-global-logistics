> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](06-id-lifecycle.md) | [English](06-id-lifecycle.en.md) | [한국어](06-id-lifecycle.ko.md) | [Русский](06-id-lifecycle.ru.md) | [Deutsch](06-id-lifecycle.de.md) | [Français](06-id-lifecycle.fr.md) | [Español](06-id-lifecycle.es.md) | [Português](06-id-lifecycle.pt.md) | [हिन्दी](06-id-lifecycle.hi.md) | [العربية](06-id-lifecycle.ar.md) | [বাংলা](06-id-lifecycle.bn.md) | [Bahasa Indonesia](06-id-lifecycle.id.md) | [日本語](06-id-lifecycle.ja.md)

# Ciclo de vida completo do ID

```mermaid
flowchart LR
    subgraph gen["1. Geração"]
        g1["SnowflakeService::generate()"]
        g2["datacenter_id(5bit) + worker_id(5bit)<br/>+ timestamp(41bit) + sequence(12bit)"]
        g3["BIGINT(18)<br/>Ex.: 1750123456789"]
        g1 --> g2 --> g3
    end

    subgraph store["2. Armazenamento"]
        s1["Tabelas logistics_* do MySQL<br/>id BIGINT UNSIGNED NOT NULL"]
        s2["Cast encryptable para campos sensíveis<br/>armazenamento criptografado AES-128-ECB"]
        g3 --> s1 --> s2
    end

    subgraph transfer["3. Transferência"]
        t1["HashidsService::encode(bigint)"]
        t2["String hashid<br/>Ex.: aB3xK9mW2pQ7rT5v"]
        s1 --> t1 --> t2
    end

    subgraph reverse["4. Decodificação reversa"]
        r1["HashidsService::decode(hashid)"]
        r2["BIGINT"]
        t2 --> r1 --> r2
    end

    style g1 fill:#1677FF,color:#fff
    style s2 fill:#FA8C16,color:#fff
    style t1 fill:#52C41A,color:#fff
    style r1 fill:#722ED1,color:#fff
```
