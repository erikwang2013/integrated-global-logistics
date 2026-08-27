> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](07-encryption-layers.md) | [English](07-encryption-layers.en.md) | [한국어](07-encryption-layers.ko.md) | [Русский](07-encryption-layers.ru.md) | [Deutsch](07-encryption-layers.de.md) | [Français](07-encryption-layers.fr.md) | [Español](07-encryption-layers.es.md) | [Português](07-encryption-layers.pt.md) | [हिन्दी](07-encryption-layers.hi.md) | [العربية](07-encryption-layers.ar.md) | [বাংলা](07-encryption-layers.bn.md) | [Bahasa Indonesia](07-encryption-layers.id.md) | [日本語](07-encryption-layers.ja.md)

# ডেটা এনক্রিপশন স্তর

```mermaid
flowchart TB
    subgraph transport["ট্রান্সমিশন স্তর এনক্রিপশন - encryption"]
        e1["ক্লায়েন্ট সংবেদনশীল ডেটা পাঠায়"]
        e2["AES-256-CBC এনক্রিপশন"]
        e3["API সাইফারটেক্সট ট্রান্সমিশন"]
        e4["সার্ভার ডিক্রিপশন ও প্রক্রিয়াকরণ"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["স্টোরেজ স্তর এনক্রিপশন - encryptable"]
        d1["Model casts কনফিগারেশন<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["লেখার সময় স্বয়ংক্রিয় এনক্রিপশন"]
        d3["MySQL VARCHAR(500) সাইফারটেক্সট স্টোরেজ"]
        d4["পড়ার সময় স্বয়ংক্রিয় ডিক্রিপশন"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["ডিসপ্লে স্তর মাস্কিং"]
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
