> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](11-security-defense.md) | [English](11-security-defense.en.md) | [한국어](11-security-defense.ko.md) | [Русский](11-security-defense.ru.md) | [Deutsch](11-security-defense.de.md) | [Français](11-security-defense.fr.md) | [Español](11-security-defense.es.md) | [Português](11-security-defense.pt.md) | [हिन्दी](11-security-defense.hi.md) | [العربية](11-security-defense.ar.md) | [বাংলা](11-security-defense.bn.md) | [Bahasa Indonesia](11-security-defense.id.md) | [日本語](11-security-defense.ja.md)

# सुरक्षा डिफेंस-इन-डेप्थ

```mermaid
flowchart TB
    l1["परत 1: मानव सत्यापन<br/>क्लिक कैप्चा ClickCaptcha<br/>लॉगिन/रजिस्ट्रेशन पर अनिवार्य सत्यापन"]
    l2["परत 2: ऑपरेशन पुष्टि<br/>पासवर्ड दोबारा पुष्टि<br/>DELETE ऑपरेशन के लिए अनिवार्य"]
    l3["परत 3: ट्रांसमिशन सुरक्षा<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["परत 4: पहचान प्रमाणीकरण<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["परत 5: अनुमति प्राधिकरण<br/>RBAC method.path ग्रैन्युलैरिटी<br/>सुपर एडमिन *"]
    l6["परत 6: डेटा सुरक्षा<br/>ID:Hashids एन्क्रिप्शन<br/>अनुरोध:Encryption एन्क्रिप्शन<br/>स्टोरेज:Encryptable एन्क्रिप्शन<br/>निर्यात:मास्किंग+कॉपीराइट"]
    l7["परत 7: ऑडिट ट्रेसेबिलिटी<br/>OperationLog<br/>उपयोगकर्ता/IP/समय/पैरामीटर"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
