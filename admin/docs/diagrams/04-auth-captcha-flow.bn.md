> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](04-auth-captcha-flow.md) | [English](04-auth-captcha-flow.en.md) | [한국어](04-auth-captcha-flow.ko.md) | [Русский](04-auth-captcha-flow.ru.md) | [Deutsch](04-auth-captcha-flow.de.md) | [Français](04-auth-captcha-flow.fr.md) | [Español](04-auth-captcha-flow.es.md) | [Português](04-auth-captcha-flow.pt.md) | [हिन्दी](04-auth-captcha-flow.hi.md) | [العربية](04-auth-captcha-flow.ar.md) | [বাংলা](04-auth-captcha-flow.bn.md) | [Bahasa Indonesia](04-auth-captcha-flow.id.md) | [日本語](04-auth-captcha-flow.ja.md)

# প্রমাণীকরণ ও ক্যাপচা প্রক্রিয়া

```mermaid
sequenceDiagram
    actor U as ব্যবহারকারী
    participant CL as ক্লায়েন্ট
    participant SV as সার্ভার
    participant CAP as Captcha
    participant JWT as JWT Service

    rect rgb(230, 240, 255)
    Note over U,CAP: প্রথম ধাপ: ক্যাপচা প্রাপ্তি
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP-->>SV: key, image(base64 PNG), targets
    SV-->>CL: 200 {key, image, extra.targets}
    end

    rect rgb(230, 255, 230)
    Note over U,CAP: দ্বিতীয় ধাপ: ব্যবহারকারীর ক্লিক
    CL->>CL: ছবি রেন্ডার, নির্দেশনা "দয়া করে ক্লিক করুন: গাছ→পাখি→ফুল"
    U->>CL: চিত্রের টেক্সট অবস্থানগুলোতে পর্যায়ক্রমে ক্লিক
    CL->>CL: ক্লিক সংগ্রহ: clicks:[{x,y},{x,y},{x,y}]
    end

    rect rgb(255, 240, 230)
    Note over U,CAP: তৃতীয় ধাপ: লগইন যাচাইকরণ
    CL->>SV: POST /api/auth/login {username,password,captcha_key,clicks}
    SV->>CAP: captcha_verify(key,'click',clicks)

    alt ক্যাপচা ভুল
        CAP-->>SV: false
        SV-->>CL: 422 ক্যাপচা ভুল
    else ক্যাপচা সঠিক
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt প্রমাণপত্র ভুল
            SV-->>CL: 401 ব্যবহারকারীর নাম বা পাসওয়ার্ড ভুল
        else প্রমাণপত্র সঠিক
            SV->>JWT: jwt()->create()
            JWT-->>SV: access_token(2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token(14d)
            SV-->>CL: 200 {tokens, user}
        end
    end
    end

    rect rgb(245, 245, 255)
    Note over U,CL: চতুর্থ ধাপ: পরবর্তী অনুরোধ
    CL->>SV: GET /admin/dashboard
    Note right of CL: Authorization: Bearer token
    SV->>JWT: jwt()->verify()
    JWT-->>SV: {sub, username}
    SV-->>CL: 200 {dashboard data}
    end
```
