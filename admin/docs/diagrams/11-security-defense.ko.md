> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](11-security-defense.md) | [English](11-security-defense.en.md) | [한국어](11-security-defense.ko.md) | [Русский](11-security-defense.ru.md) | [Deutsch](11-security-defense.de.md) | [Français](11-security-defense.fr.md) | [Español](11-security-defense.es.md) | [Português](11-security-defense.pt.md) | [हिन्दी](11-security-defense.hi.md) | [العربية](11-security-defense.ar.md) | [বাংলা](11-security-defense.bn.md) | [Bahasa Indonesia](11-security-defense.id.md) | [日本語](11-security-defense.ja.md)

# 보안 심층 방어

```mermaid
flowchart TB
    l1["1층: 사람-기계 검증<br/>클릭 캡차 ClickCaptcha<br/>로그인/가입 강제 검증"]
    l2["2층: 작업 확인<br/>비밀번호 재확인<br/>DELETE 작업 필수"]
    l3["3층: 전송 보안<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["4층: 신원 인증<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["5층: 권한 인가<br/>RBAC method.path 세분화<br/>슈퍼 관리자 *"]
    l6["6층: 데이터 보호<br/>ID: Hashids 암호화<br/>요청: Encryption 암호화<br/>저장: Encryptable 암호화<br/>내보내기: 마스킹+저작권"]
    l7["7층: 감사 추적<br/>OperationLog<br/>사용자/IP/시간/파라미터"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
