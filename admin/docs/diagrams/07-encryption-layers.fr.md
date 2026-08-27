> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](07-encryption-layers.md) | [English](07-encryption-layers.en.md) | [한국어](07-encryption-layers.ko.md) | [Русский](07-encryption-layers.ru.md) | [Deutsch](07-encryption-layers.de.md) | [Français](07-encryption-layers.fr.md) | [Español](07-encryption-layers.es.md) | [Português](07-encryption-layers.pt.md) | [हिन्दी](07-encryption-layers.hi.md) | [العربية](07-encryption-layers.ar.md) | [বাংলা](07-encryption-layers.bn.md) | [Bahasa Indonesia](07-encryption-layers.id.md) | [日本語](07-encryption-layers.ja.md)

# Couches de chiffrement des données

```mermaid
flowchart TB
    subgraph transport["Chiffrement de la couche de transport - encryption"]
        e1["Le client envoie des données sensibles"]
        e2["Chiffrement AES-256-CBC"]
        e3["Texte chiffré transmis via l'API"]
        e4["Le serveur déchiffre et traite"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["Chiffrement de la couche de stockage - encryptable"]
        d1["Configuration des casts du modèle<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["Chiffrement automatique à l'écriture"]
        d3["MySQL VARCHAR(500) stocke le texte chiffré"]
        d4["Déchiffrement automatique à la lecture"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["Masquage de la couche d'affichage"]
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
