> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](09-export-flow.md) | [English](09-export-flow.en.md) | [한국어](09-export-flow.ko.md) | [Русский](09-export-flow.ru.md) | [Deutsch](09-export-flow.de.md) | [Français](09-export-flow.fr.md) | [Español](09-export-flow.es.md) | [Português](09-export-flow.pt.md) | [हिन्दी](09-export-flow.hi.md) | [العربية](09-export-flow.ar.md) | [বাংলা](09-export-flow.bn.md) | [Bahasa Indonesia](09-export-flow.id.md) | [日本語](09-export-flow.ja.md)

# Бизнес-процесс экспорта

## Экспорт в Excel

```mermaid
sequenceDiagram
    participant C as Клиент
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Файловая система

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: результаты запроса
    CTL->>CTL: дешифрование чувствительных полей
    CTL->>CTL: маскирование (maskPhone/maskEmail)
    CTL->>CTL: построение PhpSpreadsheet
    Note right of CTL: заголовок — синий фон, белый текст<br/>строки данных — тонкие рамки<br/>закреплённая первая строка<br/>автофильтр
    CTL->>FS: запись в runtime/tmp/export_*.xlsx
    CTL-->>C: скачивание файла
```

## Экспорт в PDF

```mermaid
sequenceDiagram
    participant C as Клиент
    participant CTL as ExportController
    participant FS as Файловая система

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: верхний колонтитул: заголовок+копирайт+время<br/>содержимое: таблица или карточки<br/>нижний колонтитул: неудаляемый копирайт
    CTL->>CTL: рендеринг Dompdf (альбомная A4)
    CTL->>FS: запись в runtime/tmp/export_*.pdf
    CTL-->>C: скачивание файла
```
