> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](11-security-defense.md) | [English](11-security-defense.en.md) | [한국어](11-security-defense.ko.md) | [Русский](11-security-defense.ru.md) | [Deutsch](11-security-defense.de.md) | [Français](11-security-defense.fr.md) | [Español](11-security-defense.es.md) | [Português](11-security-defense.pt.md) | [हिन्दी](11-security-defense.hi.md) | [العربية](11-security-defense.ar.md) | [বাংলা](11-security-defense.bn.md) | [Bahasa Indonesia](11-security-defense.id.md) | [日本語](11-security-defense.ja.md)

# সিকিউরিটি ডিফেন্স ইন ডেপথ

```mermaid
flowchart TB
    l1["স্তর 1: হিউম্যান-মেশিন যাচাইকরণ<br/>ক্লিক ক্যাপচা ClickCaptcha<br/>লগইন/রেজিস্ট্রেশন বাধ্যতামূলক যাচাই"]
    l2["স্তর 2: অপারেশন নিশ্চিতকরণ<br/>পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ<br/>DELETE অপারেশনের জন্য আবশ্যক"]
    l3["স্তর 3: ট্রান্সমিশন নিরাপত্তা<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["স্তর 4: আইডেন্টিটি প্রমাণীকরণ<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["স্তর 5: পারমিশন অথরাইজেশন<br/>RBAC method.path গ্রানুলারিটি<br/>সুপার অ্যাডমিন *"]
    l6["স্তর 6: ডেটা সুরক্ষা<br/>ID: Hashids এনক্রিপশন<br/>অনুরোধ: Encryption এনক্রিপশন<br/>স্টোরেজ: Encryptable এনক্রিপশন<br/>এক্সপোর্ট: মাস্কিং+কপিরাইট"]
    l7["স্তর 7: অডিট ট্রেস<br/>OperationLog<br/>ব্যবহারকারী/IP/সময়/প্যারামিটার"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
