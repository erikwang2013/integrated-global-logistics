> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](07-encryption-layers.md) | [English](07-encryption-layers.en.md) | [한국어](07-encryption-layers.ko.md) | [Русский](07-encryption-layers.ru.md) | [Deutsch](07-encryption-layers.de.md) | [Français](07-encryption-layers.fr.md) | [Español](07-encryption-layers.es.md) | [Português](07-encryption-layers.pt.md) | [हिन्दी](07-encryption-layers.hi.md) | [العربية](07-encryption-layers.ar.md) | [বাংলা](07-encryption-layers.bn.md) | [Bahasa Indonesia](07-encryption-layers.id.md) | [日本語](07-encryption-layers.ja.md)

# Capas de cifrado de datos

```mermaid
flowchart TB
    subgraph transport["Cifrado de la capa de transporte - encryption"]
        e1["El cliente envía datos sensibles"]
        e2["Cifrado AES-256-CBC"]
        e3["Texto cifrado en la transmisión API"]
        e4["El servidor descifra y procesa"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["Cifrado de la capa de almacenamiento - encryptable"]
        d1["Configuración de casts del Model<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["Cifrado automático al escribir"]
        d3["MySQL VARCHAR(500) almacena el texto cifrado"]
        d4["Descifrado automático al leer"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["Enmascaramiento en la capa de presentación"]
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
