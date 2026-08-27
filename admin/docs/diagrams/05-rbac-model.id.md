> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](05-rbac-model.md) | [English](05-rbac-model.en.md) | [한국어](05-rbac-model.ko.md) | [Русский](05-rbac-model.ru.md) | [Deutsch](05-rbac-model.de.md) | [Français](05-rbac-model.fr.md) | [Español](05-rbac-model.es.md) | [Português](05-rbac-model.pt.md) | [हिन्दी](05-rbac-model.hi.md) | [العربية](05-rbac-model.ar.md) | [বাংলা](05-rbac-model.bn.md) | [Bahasa Indonesia](05-rbac-model.id.md) | [日本語](05-rbac-model.ja.md)

# Model Izin RBAC

## Hubungan Pengguna-Role-Izin

```mermaid
flowchart LR
    subgraph users["Pengguna"]
        u1["admin (Super Admin)"]
        u2["editor (Editor)"]
        u3["viewer (Hanya baca)"]
    end

    subgraph roles["Role"]
        r1["super_admin<br/>Identifikasi izin: *"]
        r2["editor<br/>Identifikasi izin: get.* post.*"]
        r3["viewer<br/>Identifikasi izin: get.*"]
    end

    subgraph permissions["Izin (pohon)"]
        p1["dashboard (menu)"]
        p2["user (menu)"]
        p3["get.admin/user (API)"]
        p4["post.admin/user (API)"]
        p5["delete.admin/user (API)"]
        p6["export.excel (tombol)"]
    end

    u1 --> r1
    u2 --> r2
    u3 --> r3
    r1 --> p1 & p2 & p3 & p4 & p5 & p6
    r2 --> p1 & p2 & p3 & p4
    r3 --> p1 & p3
    p2 --> p3 & p4 & p5
    p1 --> p6

    style u1 fill:#1677FF,color:#fff
    style r1 fill:#FA8C16,color:#fff
    style p1 fill:#52C41A,color:#fff
```

## Alur Penentuan Izin

```mermaid
flowchart TD
    start["Permintaan tiba"] --> extract["Ekstrak Token→adminId"]
    extract --> findRoles["Cari role pengguna"]
    findRoles --> collectSlug["Kumpulkan semua permission.slug"]
    collectSlug --> buildKey["Bangun method.path"]
    buildKey --> check{"slug==* atau<br/>slug cocok?"}
    check -->|"Ya"| allow["200 Izinkan"]
    check -->|"Tidak"| deny["403 Forbidden"]

    style allow fill:#52C41A,color:#fff
    style deny fill:#FF4D4F,color:#fff
```

## Jenis Izin

```mermaid
flowchart LR
    t1["type=1 Menu<br/>Mengontrol tampilan sidebar"]
    t2["type=2 Tombol<br/>Mengontrol tombol aksi"]
    t3["type=3 API<br/>Mengontrol akses antarmuka"]

    style t1 fill:#1677FF,color:#fff
    style t2 fill:#FA8C16,color:#fff
    style t3 fill:#52C41A,color:#fff
```
