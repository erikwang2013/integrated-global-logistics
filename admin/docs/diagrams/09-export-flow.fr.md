> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](09-export-flow.md) | [English](09-export-flow.en.md) | [한국어](09-export-flow.ko.md) | [Русский](09-export-flow.ru.md) | [Deutsch](09-export-flow.de.md) | [Français](09-export-flow.fr.md) | [Español](09-export-flow.es.md) | [Português](09-export-flow.pt.md) | [हिन्दी](09-export-flow.hi.md) | [العربية](09-export-flow.ar.md) | [বাংলা](09-export-flow.bn.md) | [Bahasa Indonesia](09-export-flow.id.md) | [日本語](09-export-flow.ja.md)

# Processus métier d'exportation

## Export Excel

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Système de fichiers

    C->>CTL: POST /admin/export/excel
    Note right of C: {table,columns,conditions,title}
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Résultats de la requête
    CTL->>CTL: Déchiffre les champs sensibles
    CTL->>CTL: Masque (maskPhone/maskEmail)
    CTL->>CTL: Construction PhpSpreadsheet
    Note right of CTL: En-tête bleu à texte blanc<br/>Bordures fines sur les lignes de données<br/>Première ligne figée<br/>Filtre automatique
    CTL->>FS: Écrit dans runtime/tmp/export_*.xlsx
    CTL-->>C: Téléchargement du fichier
```

## Export PDF

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant FS as Système de fichiers

    C->>CTL: POST /admin/export/pdf
    Note right of C: {type,title,data}
    CTL->>CTL: buildPdfHtml()
    Note right of CTL: En-tête : titre + copyright + heure<br/>Contenu : tableau ou carte<br/>Pied de page : copyright non amovible
    CTL->>CTL: Rendu Dompdf (A4 paysage)
    CTL->>FS: Écrit dans runtime/tmp/export_*.pdf
    CTL-->>C: Téléchargement du fichier
```
