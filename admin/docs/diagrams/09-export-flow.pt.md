> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](09-export-flow.md) | [English](09-export-flow.en.md) | [한국어](09-export-flow.ko.md) | [Русский](09-export-flow.ru.md) | [Deutsch](09-export-flow.de.md) | [Français](09-export-flow.fr.md) | [Español](09-export-flow.es.md) | [Português](09-export-flow.pt.md) | [हिन्दी](09-export-flow.hi.md) | [العربية](09-export-flow.ar.md) | [বাংলা](09-export-flow.bn.md) | [Bahasa Indonesia](09-export-flow.id.md) | [日本語](09-export-flow.ja.md)

# Fluxo de negócio de exportação

## Exportação Excel

```mermaid
sequenceDiagram
    participant C as Cliente
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Sistema de arquivos

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Resultado da consulta
    CTL->>CTL: Descriptografar campos sensíveis
    CTL->>CTL: Mascarar(maskPhone/maskEmail)
    CTL->>CTL: Construir com PhpSpreadsheet
    Note right of CTL: Cabeçalho com fundo azul e texto branco<br/>Linhas de dados com bordas finas<br/>Congelar primeira linha<br/>Filtro automático
    CTL->>FS: Gravar runtime/tmp/export_*.xlsx
    CTL-->>C: Download do arquivo
```

## Exportação PDF

```mermaid
sequenceDiagram
    participant C as Cliente
    participant CTL as ExportController
    participant FS as Sistema de arquivos

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: Cabeçalho: título + copyright + hora<br/>Conteúdo: tabela ou cartões<br/>Rodapé: copyright inamovível
    CTL->>CTL: Renderização Dompdf(A4 paisagem)
    CTL->>FS: Gravar runtime/tmp/export_*.pdf
    CTL-->>C: Download do arquivo
```
