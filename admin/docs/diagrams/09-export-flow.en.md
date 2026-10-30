> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](09-export-flow.md) | [English](09-export-flow.en.md)

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

Notes:

- Only tables on the export whitelist can be exported; `system_config` and other security-sensitive tables are excluded.
- Sensitive columns are decrypted and masked before writing, and the PDF footer carries a non-removable copyright.
- Temporary files under `runtime/tmp/` are not automatically cleaned; schedule a cleanup job on shared or long-running hosts.
