> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](09-export-flow.md) | [English](09-export-flow.en.md) | [한국어](09-export-flow.ko.md) | [Русский](09-export-flow.ru.md) | [Deutsch](09-export-flow.de.md) | [Français](09-export-flow.fr.md) | [Español](09-export-flow.es.md) | [Português](09-export-flow.pt.md) | [हिन्दी](09-export-flow.hi.md) | [العربية](09-export-flow.ar.md) | [বাংলা](09-export-flow.bn.md) | [Bahasa Indonesia](09-export-flow.id.md) | [日本語](09-export-flow.ja.md)

# Alur Bisnis Ekspor

## Ekspor Excel

```mermaid
sequenceDiagram
    participant C as Klien
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Sistem File

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Hasil kueri
    CTL->>CTL: Dekripsi kolom sensitif
    CTL->>CTL: Masking (maskPhone/maskEmail)
    CTL->>CTL: Membangun PhpSpreadsheet
    Note right of CTL: Teks putih di latar biru pada header<br/>Border tipis pada baris data<br/>Baris pertama dibekukan<br/>Filter otomatis
    CTL->>FS: Menulis ke runtime/tmp/export_*.xlsx
    CTL-->>C: Unduhan file
```

## Ekspor PDF

```mermaid
sequenceDiagram
    participant C as Klien
    participant CTL as ExportController
    participant FS as Sistem File

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: Header: judul + hak cipta + waktu<br/>Isi: tabel atau kartu<br/>Footer: hak cipta yang tidak dapat dihapus
    CTL->>CTL: Render Dompdf (A4 lanskap)
    CTL->>FS: Menulis ke runtime/tmp/export_*.pdf
    CTL-->>C: Unduhan file
```
