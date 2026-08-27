> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](09-export-flow.md) | [English](09-export-flow.en.md) | [한국어](09-export-flow.ko.md) | [Русский](09-export-flow.ru.md) | [Deutsch](09-export-flow.de.md) | [Français](09-export-flow.fr.md) | [Español](09-export-flow.es.md) | [Português](09-export-flow.pt.md) | [हिन्दी](09-export-flow.hi.md) | [العربية](09-export-flow.ar.md) | [বাংলা](09-export-flow.bn.md) | [Bahasa Indonesia](09-export-flow.id.md) | [日本語](09-export-flow.ja.md)

# এক্সপোর্ট ব্যবসায়িক প্রক্রিয়া

## Excel এক্সপোর্ট

```mermaid
sequenceDiagram
    participant C as ক্লায়েন্ট
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as ফাইল সিস্টেম

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: কোয়েরি ফলাফল
    CTL->>CTL: সংবেদনশীল ফিল্ড ডিক্রিপ্ট
    CTL->>CTL: মাস্কিং (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet নির্মাণ
    Note right of CTL: নীল ব্যাকগ্রাউন্ড ও সাদা টেক্সটসহ হেডার<br/>ডেটা সারির পাতলা বর্ডার<br/>প্রথম সারি ফ্রিজ<br/>অটো ফিল্টার
    CTL->>FS: runtime/tmp/export_*.xlsx-এ লেখা
    CTL-->>C: ফাইল ডাউনলোড
```

## PDF এক্সপোর্ট

```mermaid
sequenceDiagram
    participant C as ক্লায়েন্ট
    participant CTL as ExportController
    participant FS as ফাইল সিস্টেম

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: হেডার: শিরোনাম+কপিরাইট+সময়<br/>কনটেন্ট: টেবিল বা কার্ড<br/>ফুটার: অপসারণযোগ্য নয় এমন কপিরাইট
    CTL->>CTL: Dompdf রেন্ডারিং (A4 ল্যান্ডস্কেপ)
    CTL->>FS: runtime/tmp/export_*.pdf-এ লেখা
    CTL-->>C: ফাইল ডাউনলোড
```
