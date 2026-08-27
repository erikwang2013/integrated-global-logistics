> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](09-export-flow.md) | [English](09-export-flow.en.md) | [한국어](09-export-flow.ko.md) | [Русский](09-export-flow.ru.md) | [Deutsch](09-export-flow.de.md) | [Français](09-export-flow.fr.md) | [Español](09-export-flow.es.md) | [Português](09-export-flow.pt.md) | [हिन्दी](09-export-flow.hi.md) | [العربية](09-export-flow.ar.md) | [বাংলা](09-export-flow.bn.md) | [Bahasa Indonesia](09-export-flow.id.md) | [日本語](09-export-flow.ja.md)

# Export-Geschäftsablauf

## Excel-Export

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Dateisystem

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Abfrageergebnis
    CTL->>CTL: Sensible Felder entschlüsseln
    CTL->>CTL: Maskieren (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet-Aufbau
    Note right of CTL: Tabellenkopf blau mit weißer Schrift<br/>Datenzeilen mit feinen Rahmen<br/>Erste Zeile fixieren<br/>Autofilter
    CTL->>FS: schreibt runtime/tmp/export_*.xlsx
    CTL-->>C: Datei-Download
```

## PDF-Export

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant FS as Dateisystem

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: Seitenkopf: Titel + Copyright + Zeit<br/>Inhalt: Tabelle oder Karte<br/>Seitenfuß: nicht entfernbares Copyright
    CTL->>CTL: Dompdf-Rendering (A4 quer)
    CTL->>FS: schreibt runtime/tmp/export_*.pdf
    CTL-->>C: Datei-Download
```
