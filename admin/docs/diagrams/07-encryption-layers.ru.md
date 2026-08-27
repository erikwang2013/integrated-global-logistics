> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](07-encryption-layers.md) | [English](07-encryption-layers.en.md) | [한국어](07-encryption-layers.ko.md) | [Русский](07-encryption-layers.ru.md) | [Deutsch](07-encryption-layers.de.md) | [Français](07-encryption-layers.fr.md) | [Español](07-encryption-layers.es.md) | [Português](07-encryption-layers.pt.md) | [हिन्दी](07-encryption-layers.hi.md) | [العربية](07-encryption-layers.ar.md) | [বাংলা](07-encryption-layers.bn.md) | [Bahasa Indonesia](07-encryption-layers.id.md) | [日本語](07-encryption-layers.ja.md)

# Слои шифрования данных

```mermaid
flowchart TB
    subgraph transport["Шифрование на транспортном уровне — encryption"]
        e1["клиент отправляет чувствительные данные"]
        e2["шифрование AES-256-CBC"]
        e3["шифротекст в API-передаче"]
        e4["дешифрование и обработка на сервере"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["Шифрование на уровне хранения — encryptable"]
        d1["настройка Model casts<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["автоматическое шифрование при записи"]
        d3["хранение шифротекста в MySQL VARCHAR(500)"]
        d4["автоматическое дешифрование при чтении"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["Маскирование на уровне отображения"]
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
