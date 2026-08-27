> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](11-security-defense.md) | [English](11-security-defense.en.md) | [한국어](11-security-defense.ko.md) | [Русский](11-security-defense.ru.md) | [Deutsch](11-security-defense.de.md) | [Français](11-security-defense.fr.md) | [Español](11-security-defense.es.md) | [Português](11-security-defense.pt.md) | [हिन्दी](11-security-defense.hi.md) | [العربية](11-security-defense.ar.md) | [বাংলা](11-security-defense.bn.md) | [Bahasa Indonesia](11-security-defense.id.md) | [日本語](11-security-defense.ja.md)

# Pertahanan Berlapis Keamanan

```mermaid
flowchart TB
    l1["Lapisan 1: Verifikasi manusia<br/>Captcha klik ClickCaptcha<br/>Validasi wajib login/registrasi"]
    l2["Lapisan 2: Konfirmasi aksi<br/>Konfirmasi kata sandi dua kali<br/>Wajib untuk operasi DELETE"]
    l3["Lapisan 3: Keamanan transmisi<br/>HTTPS + JWT Bearer<br/>AES-256-CBC"]
    l4["Lapisan 4: Autentikasi identitas<br/>JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    l5["Lapisan 5: Otorisasi izin<br/>Granularitas RBAC method.path<br/>Super Admin *"]
    l6["Lapisan 6: Perlindungan data<br/>ID: enkripsi Hashids<br/>Permintaan: enkripsi Encryption<br/>Penyimpanan: enkripsi Encryptable<br/>Ekspor: masking + hak cipta"]
    l7["Lapisan 7: Audit dan penelusuran<br/>OperationLog<br/>Pengguna/IP/Waktu/Parameter"]

    l1 --> l2 --> l3 --> l4 --> l5 --> l6 --> l7

    style l1 fill:#1677FF,color:#fff
    style l2 fill:#1677FF,color:#fff
    style l3 fill:#FA8C16,color:#fff
    style l4 fill:#FA8C16,color:#fff
    style l5 fill:#52C41A,color:#fff
    style l6 fill:#722ED1,color:#fff
    style l7 fill:#FF4D4F,color:#fff
```
