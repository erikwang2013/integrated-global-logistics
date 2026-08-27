> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](07-encryption-layers.md) | [English](07-encryption-layers.en.md) | [한국어](07-encryption-layers.ko.md) | [Русский](07-encryption-layers.ru.md) | [Deutsch](07-encryption-layers.de.md) | [Français](07-encryption-layers.fr.md) | [Español](07-encryption-layers.es.md) | [Português](07-encryption-layers.pt.md) | [हिन्दी](07-encryption-layers.hi.md) | [العربية](07-encryption-layers.ar.md) | [বাংলা](07-encryption-layers.bn.md) | [Bahasa Indonesia](07-encryption-layers.id.md) | [日本語](07-encryption-layers.ja.md)

# डेटा एन्क्रिप्शन परतें

```mermaid
flowchart TB
    subgraph transport["ट्रांसमिशन परत एन्क्रिप्शन - encryption"]
        e1["क्लाइंट संवेदनशील डेटा भेजता है"]
        e2["AES-256-CBC एन्क्रिप्शन"]
        e3["API पर सिफ़रटेक्स्ट ट्रांसमिशन"]
        e4["सर्वर डिक्रिप्शन और प्रोसेसिंग"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["स्टोरेज परत एन्क्रिप्शन - encryptable"]
        d1["Model casts कॉन्फ़िगरेशन<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["लिखते समय स्वतः एन्क्रिप्शन"]
        d3["MySQL VARCHAR(500) सिफ़रटेक्स्ट स्टोरेज"]
        d4["पढ़ते समय स्वतः डिक्रिप्शन"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["प्रदर्शन परत मास्किंग"]
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
