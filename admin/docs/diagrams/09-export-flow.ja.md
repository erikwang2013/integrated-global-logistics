> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](09-export-flow.md) | [English](09-export-flow.en.md) | [한국어](09-export-flow.ko.md) | [Русский](09-export-flow.ru.md) | [Deutsch](09-export-flow.de.md) | [Français](09-export-flow.fr.md) | [Español](09-export-flow.es.md) | [Português](09-export-flow.pt.md) | [हिन्दी](09-export-flow.hi.md) | [العربية](09-export-flow.ar.md) | [বাংলা](09-export-flow.bn.md) | [Bahasa Indonesia](09-export-flow.id.md) | [日本語](09-export-flow.ja.md)

# エクスポート業務フロー

## Excel エクスポート

```mermaid
sequenceDiagram
    participant C as クライアント
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as ファイルシステム

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: クエリ結果
    CTL->>CTL: 機密フィールドを復号
    CTL->>CTL: マスキング(maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheetで構築
    Note right of CTL: ヘッダーは青地に白文字<br/>データ行は細枠線<br/>先頭行を固定<br/>自動フィルター
    CTL->>FS: runtime/tmp/export_*.xlsx に書き込み
    CTL-->>C: ファイルダウンロード
```

## PDF エクスポート

```mermaid
sequenceDiagram
    participant C as クライアント
    participant CTL as ExportController
    participant FS as ファイルシステム

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: ページヘッダー:タイトル+著作権+時刻<br/>本文:テーブルまたはカード<br/>ページフッター:削除不可の著作権
    CTL->>CTL: Dompdfでレンダリング(A4横向き)
    CTL->>FS: runtime/tmp/export_*.pdf に書き込み
    CTL-->>C: ファイルダウンロード
```
