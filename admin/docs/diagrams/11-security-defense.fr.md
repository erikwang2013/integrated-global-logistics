> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](11-security-defense.md) | [English](11-security-defense.en.md) | [한국어](11-security-defense.ko.md) | [Русский](11-security-defense.ru.md) | [Deutsch](11-security-defense.de.md) | [Français](11-security-defense.fr.md) | [Español](11-security-defense.es.md) | [Português](11-security-defense.pt.md) | [हिन्दी](11-security-defense.hi.md) | [العربية](11-security-defense.ar.md) | [বাংলা](11-security-defense.bn.md) | [Bahasa Indonesia](11-security-defense.id.md) | [日本語](11-security-defense.ja.md)

# Défense en profondeur de la sécurité

```mermaid
flowchart TB
    l1["Couche 1 : vérification humaine<br/>Captcha à clic ClickCaptcha<br/>Vérification obligatoire à la connexion/inscription"]
    l2["Couche 2 : confirmation des opérations<br/>Double confirmation du mot de passe<br/>Obligatoire pour les opérations DELETE"]
    l3["Couche 3 : sécurité des transports<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["Couche 4 : authentification<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["Couche 5 : autorisation<br/>Granularité RBAC method.path<br/>Super administrateur *"]
    l6["Couche 6 : protection des données<br/>ID : chiffrement Hashids<br/>Requêtes : chiffrement Encryption<br/>Stockage : chiffrement Encryptable<br/>Export : masquage + copyright"]
    l7["Couche 7 : audit et traçabilité<br/>OperationLog<br/>Utilisateur/IP/Heure/Paramètres"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
