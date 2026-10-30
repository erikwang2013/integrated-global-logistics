> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](11-security-defense.md) | [English](11-security-defense.en.md)

# Security Defense in Depth

```mermaid
flowchart TB
    l1["Layer 1: Human Verification<br/>Click Captcha ClickCaptcha<br/>Mandatory for Login/Register"]
    l2["Layer 2: Operation Confirmation<br/>Password Re-confirmation<br/>Required for DELETE Operations"]
    l3["Layer 3: Transport Security<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["Layer 4: Identity Authentication<br/>JWT HS256<br/>access/refresh/client separated<br/>access 2h / refresh 14d single-use"]
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

Additional controls not shown as separate layers:

- **Secrets**: `JWT_SECRET_KEY`, `ENCRYPTABLE_KEY`, `ENCRYPTION_KEY` and `INTERNAL_TOKEN` have no code defaults; startup validation refuses installed environments that still use public defaults.
- **Token lifecycle**: refresh is single-use (`Redis SET NX`) and rotated, old tokens are blacklisted, and disabled admins are rejected on every request.
- **Internal interface**: gRPC listens on loopback `127.0.0.1:8792` and requires `x-internal-token`; empty tokens fail closed on both PHP and gateway sides.
- **Callbacks**: missing `webhook_secret` returns 401 by default; signatures are HMAC-checked; at most 100 events per request.
- **Output**: secret config values are masked (`***` write-back ignored), `system_config` is excluded from export, and operation logs mask `/admin/config` values.
