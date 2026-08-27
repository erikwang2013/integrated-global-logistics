> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](09-export-flow.md) | [English](09-export-flow.en.md) | [한국어](09-export-flow.ko.md) | [Русский](09-export-flow.ru.md) | [Deutsch](09-export-flow.de.md) | [Français](09-export-flow.fr.md) | [Español](09-export-flow.es.md) | [Português](09-export-flow.pt.md) | [हिन्दी](09-export-flow.hi.md) | [العربية](09-export-flow.ar.md) | [বাংলা](09-export-flow.bn.md) | [Bahasa Indonesia](09-export-flow.id.md) | [日本語](09-export-flow.ja.md)

# Export Business Flow

## Excel Export

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as File System

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Query Results
    CTL->>CTL: Decrypt Sensitive Fields
    CTL->>CTL: Mask (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet Build
    Note right of CTL: Blue header with white text<br/>Thin borders on data rows<br/>Frozen first row<br/>Auto filter
    CTL->>FS: Write runtime/tmp/export_*.xlsx
    CTL-->>C: File Download
```

## PDF Export

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant FS as File System

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: Page header: title + copyright + time<br/>Content: table or cards<br/>Footer: non-removable copyright
    CTL->>CTL: Dompdf Rendering (A4 Landscape)
    CTL->>FS: Write runtime/tmp/export_*.pdf
    CTL-->>C: File Download
```
