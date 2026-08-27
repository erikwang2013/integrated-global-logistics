> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](11-security-defense.md) | [English](11-security-defense.en.md) | [한국어](11-security-defense.ko.md) | [Русский](11-security-defense.ru.md) | [Deutsch](11-security-defense.de.md) | [Français](11-security-defense.fr.md) | [Español](11-security-defense.es.md) | [Português](11-security-defense.pt.md) | [हिन्दी](11-security-defense.hi.md) | [العربية](11-security-defense.ar.md) | [বাংলা](11-security-defense.bn.md) | [Bahasa Indonesia](11-security-defense.id.md) | [日本語](11-security-defense.ja.md)

# Security Defense in Depth

```mermaid
flowchart TB
    l1["Layer 1: Human Verification<br/>Click Captcha ClickCaptcha<br/>Mandatory for Login/Register"]
    l2["Layer 2: Operation Confirmation<br/>Password Re-confirmation<br/>Required for DELETE Operations"]
    l3["Layer 3: Transport Security<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["Layer 4: Identity Authentication<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["Layer 5: Permission Authorization<br/>RBAC method.path Granularity<br/>Super Admin *"]
    l6["Layer 6: Data Protection<br/>ID: Hashids Encryption<br/>Request: Encryption Encryption<br/>Storage: Encryptable Encryption<br/>Export: Masking + Copyright"]
    l7["Layer 7: Audit Trail<br/>OperationLog<br/>User/IP/Time/Parameters"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
