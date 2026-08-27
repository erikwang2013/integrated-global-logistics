> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](11-security-defense.md) | [English](11-security-defense.en.md) | [한국어](11-security-defense.ko.md) | [Русский](11-security-defense.ru.md) | [Deutsch](11-security-defense.de.md) | [Français](11-security-defense.fr.md) | [Español](11-security-defense.es.md) | [Português](11-security-defense.pt.md) | [हिन्दी](11-security-defense.hi.md) | [العربية](11-security-defense.ar.md) | [বাংলা](11-security-defense.bn.md) | [Bahasa Indonesia](11-security-defense.id.md) | [日本語](11-security-defense.ja.md)

# Defensa en profundidad de seguridad

```mermaid
flowchart TB
    l1["Capa 1: Verificación humano-máquina<br/>Captcha de clic ClickCaptcha<br/>Verificación obligatoria en login/registro"]
    l2["Capa 2: Confirmación de operaciones<br/>Segunda confirmación de contraseña<br/>Obligatoria para operaciones DELETE"]
    l3["Capa 3: Seguridad de transporte<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["Capa 4: Autenticación de identidad<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["Capa 5: Autorización de permisos<br/>Granularidad method.path de RBAC<br/>Superadministrador *"]
    l6["Capa 6: Protección de datos<br/>ID: cifrado Hashids<br/>Solicitudes: cifrado Encryption<br/>Almacenamiento: cifrado Encryptable<br/>Exportación: enmascaramiento + copyright"]
    l7["Capa 7: Auditoría y trazabilidad<br/>OperationLog<br/>Usuario/IP/Hora/Parámetros"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
