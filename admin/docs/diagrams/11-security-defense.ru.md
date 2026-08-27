> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](11-security-defense.md) | [English](11-security-defense.en.md) | [한국어](11-security-defense.ko.md) | [Русский](11-security-defense.ru.md) | [Deutsch](11-security-defense.de.md) | [Français](11-security-defense.fr.md) | [Español](11-security-defense.es.md) | [Português](11-security-defense.pt.md) | [हिन्दी](11-security-defense.hi.md) | [العربية](11-security-defense.ar.md) | [বাংলা](11-security-defense.bn.md) | [Bahasa Indonesia](11-security-defense.id.md) | [日本語](11-security-defense.ja.md)

# Эшелонированная защита безопасности

```mermaid
flowchart TB
    l1["Уровень 1: проверка человек-машина<br/>кликовая капча ClickCaptcha<br/>обязательная при входе/регистрации"]
    l2["Уровень 2: подтверждение операции<br/>повторный ввод пароля<br/>обязателен для DELETE-операций"]
    l3["Уровень 3: безопасность передачи<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["Уровень 4: аутентификация<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["Уровень 5: авторизация прав<br/>RBAC с гранулярностью method.path<br/>суперадминистратор *"]
    l6["Уровень 6: защита данных<br/>ID: шифрование Hashids<br/>запрос: шифрование Encryption<br/>хранение: шифрование Encryptable<br/>экспорт: маскирование + копирайт"]
    l7["Уровень 7: аудит и трассировка<br/>OperationLog<br/>пользователь/IP/время/параметры"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
