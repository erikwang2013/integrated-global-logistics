# Схема архитектуры и диаграммы бизнес-логики

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](ARCHITECTURE.md) | [English](ARCHITECTURE.en.md) | [한국어](ARCHITECTURE.ko.md) | [Русский](ARCHITECTURE.ru.md) | [Deutsch](ARCHITECTURE.de.md) | [Français](ARCHITECTURE.fr.md) | [Español](ARCHITECTURE.es.md) | [Português](ARCHITECTURE.pt.md) | [हिन्दी](ARCHITECTURE.hi.md) | [العربية](ARCHITECTURE.ar.md) | [বাংলা](ARCHITECTURE.bn.md) | [Bahasa Indonesia](ARCHITECTURE.id.md) | [日本語](ARCHITECTURE.ja.md)

> Диаграммы Mermaid ниже автоматически отображаются в GitHub / GitLab / VS Code. В других средах используйте [Mermaid Live Editor](https://mermaid.live/).

---

## 1. Топология системы

```mermaid
flowchart TB
    subgraph "Клиентский уровень"
        A1["Flutter Web<br/>PC-админка<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>Клиент для телефона/планшета"]
    end

    subgraph "Шлюз/пограничный уровень (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>Обратный прокси + HTTPS + Gzip<br/>Статические файлы"]
    end

    subgraph "Прикладной уровень (webman v2)"
        C0["Middleware ApiVersion<br/>Проверка заголовка API-Version"]
        C1["Middleware AdminAuth<br/>Проверка JWT"]
        C2["Middleware AdminPermission<br/>Проверка прав RBAC"]
        C3["Контроллеры админки<br/>Dashboard / User / Role / Permission"]
        C4["Публичные контроллеры v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Уровень хранения"
        D1[("MySQL 8.0<br/>Основное хранилище<br/>Префикс таблиц erik_")]
        D2[("Elasticsearch<br/>Полнотекстовый поиск<br/>Префикс индексов erik_")]
        D3[("Redis<br/>Session / кэш<br/>Хранилище капчи")]
    end

    subgraph "Внешние системы"
        E1["DevEco Studio<br/>Сборка HarmonyOS"]
        E2["Flutter SDK<br/>Сборка Web"]
    end

    A1 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    A2 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    B1 --> C0
    C0 --> C1
    C1 --> C2
    C2 --> C3
    C0 --> C4
    C3 --> C5
    C4 --> C5
    C3 --> D1
    C4 --> D1
    C3 --> D2
    C4 --> D2
    C1 --> D3

    style A1 fill:#1677FF,color:#fff
    style A2 fill:#1677FF,color:#fff
    style B1 fill:#722ED1,color:#fff
    style C0 fill:#EB2F96,color:#fff
    style C1 fill:#FA8C16,color:#fff
    style C2 fill:#FA8C16,color:#fff
    style C3 fill:#52C41A,color:#fff
    style C4 fill:#52C41A,color:#fff
    style C5 fill:#52C41A,color:#fff
    style D1 fill:#1890FF,color:#fff
    style D2 fill:#1890FF,color:#fff
    style D3 fill:#1890FF,color:#fff
```

---

## 2. Многослойная архитектура бэкенда

```mermaid
flowchart TD
    subgraph "Слой маршрутизации Route Layer"
        R1["config/route.php<br/>Сопоставление URL → Controller"]
    end

    subgraph "Слой промежуточного ПО Middleware Layer"
        M_RL["RateLimit<br/>Redis-лимит со скользящим окном<br/>Заголовки ответа X-RateLimit"]
        M_SF["SecurityFilter<br/>Перехват атак<br/>XSS/SQL-инъекции/обход путей/CSRF"]
        M0["ApiVersion<br/>Проверка версии API<br/>Инъекция apiVersion"]
        M1["AdminAuth<br/>Проверка JWT Token<br/>Инъекция adminId"]
        M2["AdminPermission<br/>Авторизация RBAC<br/>Сопоставление method.path<br/>Кэш прав в Redis на 60s"]
    end

    subgraph "Слой контроллеров Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + поиск + пагинация"]
        CT3["RoleController<br/>CRUD + синхронизация прав"]
        CT4["PermissionController<br/>CRUD + построение дерева"]
        CT5["DashboardController<br/>статистика/тренды/распределение"]
        CT6["ExportController<br/>Экспорт Excel/PDF"]
        CT7["CaptchaController<br/>Генерация/проверка капчи"]
        CT8["AuthController<br/>Вход/регистрация/обновление"]
    end

    subgraph "Слой сервисов Service Layer"
        S1["HashidsService<br/>Кодирование/декодирование ID"]
        S2["SnowflakeService<br/>Генерация глобально уникальных ID"]
        S3["EncryptionService<br/>Шифрование + маскирование"]
    end

    subgraph "Слой моделей Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Слой драйверов Driver Layer"
        D1["MySQL PDO"]
        D2["Elasticsearch HTTP"]
        D3["Redis"]
    end

    R1 --> M_SF --> M_RL --> M0
    M0 --> M1
    M1 --> M2
    M2 --> CT2 & CT3 & CT4 & CT5 & CT6
    M0 --> CT7 & CT8
    CT1 -.->|extends| CT2 & CT3 & CT4 & CT5 & CT6
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> S1 & S2 & S3
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> MD1 & MD2 & MD3 & MD4 & MD5
    MD1 & MD2 & MD3 & MD4 & MD5 --> D1
    MD1 --> D2
    CT7 --> D3

    style R1 fill:#722ED1,color:#fff
    style M_SF fill:#FF4D4F,color:#fff
    style M_RL fill:#EB2F96,color:#fff
    style M0 fill:#EB2F96,color:#fff
    style M1 fill:#FA8C16,color:#fff
    style M2 fill:#FA8C16,color:#fff
    style CT1 fill:#1677FF,color:#fff
```

---

## 3. Жизненный цикл запроса

```mermaid
sequenceDiagram
    participant C as Клиент
    participant N as Nginx
    participant MW_LOC as Locale
    participant MW_SF as SecurityFilter
    participant MW_RL as RateLimit
    participant MW0 as ApiVersion
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL
    participant OPLOG as OperationLog

    C->>N: HTTPS-запрос<br/>Header: API-Version: v1, Accept-Language: zh_CN
    N->>MW_LOC: Передача

    MW_LOC->>MW_LOC: locale = zh_CN (Accept-Language / ?lang=)
    MW_LOC->>MW_SF: Пропуск

    alt нестандартный HTTP-метод (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else метод допустим (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Проверка белого списка методов пройдена
    end

    alt сработало обнаружение атаки
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Пропуск

    alt сработал лимит запросов
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: Пропуск

    alt неподдерживаемая версия
        MW0-->>C: 400 неподдерживаемая версия API
    else версия действительна
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Token отсутствует или недействителен
        MW1-->>C: 401 Unauthorized
    else Token действителен
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt нет прав
        MW2-->>C: 403 Forbidden
    else есть права
        MW2->>CTL: Вход в контроллер
    end

    CTL->>CTL: Валидация параметров (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt чувствительная операция (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt неверный пароль
            CTL-->>C: 422 ошибка проверки пароля
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast автоматически расшифровывает
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: Формирование JSON-ответа
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Запись журнала операций (POST/PUT/DELETE)
```

---

## 4. Процесс аутентификации и капчи

```mermaid
sequenceDiagram
    participant U as Пользователь
    participant CL as Клиент
    participant SV as Сервер
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Шаг 1: получение капчи ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Генерация фонового изображения 300×200
    CAP->>CAP: Случайное размещение N китайских целей
    CAP->>CAP: Генерация key, сохранение targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Шаг 2: клики пользователя ===
    CL->>CL: Отрисовка изображения капчи
    CL->>CL: Подсказка «нажмите по порядку: дерево → птица → цветок»
    U->>CL: Последовательные клики по позициям текста на изображении
    CL->>CL: Сбор clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Шаг 3: вход ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt ошибка капчи
        CAP-->>SV: false
        SV-->>CL: 422 ошибка капчи
    else капча верна
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt неверные учётные данные
            SV-->>CL: 401 неверное имя пользователя или пароль
        else учётные данные верны
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Последующие запросы ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. Модель прав RBAC

```mermaid
flowchart LR
    subgraph "Пользователь User"
        U1["admin<br/>(супер-админ)"]
        U2["editor<br/>(редактор)"]
        U3["viewer<br/>(только чтение)"]
    end

    subgraph "Роль Role"
        R1["super_admin<br/>Идентификатор прав: *"]
        R2["editor<br/>Идентификатор прав: get.*, post.*"]
        R3["viewer<br/>Идентификатор прав: get.*"]
    end

    subgraph "Право Permission (дерево)"
        P1["dashboard<br/>type=1 меню"]
        P2["user<br/>type=1 меню"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 кнопка"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (все права)"| P1 & P2 & P3 & P4 & P5 & P6
    R2 --> P1 & P2 & P3 & P4
    R3 --> P1 & P3

    P2 --> P3 & P4 & P5
    P1 --> P6

    style U1 fill:#1677FF,color:#fff
    style R1 fill:#FA8C16,color:#fff
    style P1 fill:#52C41A,color:#fff
```

```mermaid
flowchart TD
    subgraph "Типы прав"
        T1["type=1 меню<br/>Управляет отображением/скрытием сайдбара"]
        T2["type=2 кнопка<br/>Управляет кнопками действий на странице"]
        T3["type=3 API<br/>Управляет доступом к интерфейсам"]
    end

    subgraph "Формат идентификатора прав"
        F1["{method}.{path}<br/>пример: get.admin/user<br/>пример: post.admin/user<br/>пример: delete.admin/role"]
    end

    subgraph "Процесс проверки"
        J1["Извлечение Token → adminId"]
        J2["Поиск ролей пользователя"]
        J3["Сбор всех slug прав"]
        J4["Формирование method.path"]
        J5{"Совпадение?"}
        J6["Пропуск"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"да / slug=*"| J6
        J5 -->|нет| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Полный жизненный цикл ID

```mermaid
flowchart LR
    subgraph "1. Генерация"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>пример: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Хранение"
        S1["Таблицы MySQL erik_*<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Чувствительные поля<br/>encryptable cast<br/>Шифрование AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Передача"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["строка hashid<br/>пример: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Обратное декодирование"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Слои шифрования данных

```mermaid
flowchart TB
    subgraph "Транспортное шифрование (encryption)"
        E1["Клиент отправляет чувствительные данные"]
        E2["Шифрование AES-256-CBC"]
        E3["Шифротекст в API-передаче"]
        E4["Сервер расшифровывает и обрабатывает"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Шифрование на уровне хранения (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Запись: автоматическое шифрование"]
        D3["MySQL VARCHAR(500)<br/>Хранение шифротекста"]
        D4["Чтение: автоматическая расшифровка"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Маскирование на уровне отображения (mask)"
        M1["phone: 138****1234"]
        M2["email: a***@example.com"]
        M3["id_card: ********"]
        D4 --> M1 & M2 & M3
    end

    E4 --> D1

    style E2 fill:#1677FF,color:#fff
    style D2 fill:#FA8C16,color:#fff
    style M1 fill:#52C41A,color:#fff
```

---

## 8. ER-связи базы данных

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "зашифровано"
        VARCHAR phone "зашифровано"
        VARCHAR id_card "зашифровано"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "мягкое удаление"
    }

    erik_admin_role {
        BIGINT id PK "Snowflake"
        VARCHAR name
        VARCHAR slug UK
        VARCHAR description
        TINYINT status
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Snowflake"
        BIGINT parent_id FK "самоссылка"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1 меню 2 кнопка 3 API"
        VARCHAR icon
        VARCHAR path
        INT sort
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK
        BIGINT role_id PK_FK
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK
        BIGINT permission_id PK_FK
    }

    erik_operation_log {
        BIGINT id PK "Snowflake"
        BIGINT user_id FK
        VARCHAR action
        VARCHAR method
        VARCHAR path
        VARCHAR ip
        VARCHAR source "источник"
        TEXT input "маскировка"
        DATETIME created_at
    }

    erik_system_config {
        BIGINT id PK "Snowflake"
        VARCHAR group
        VARCHAR key
        TEXT value
        VARCHAR type
        VARCHAR description
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user ||--o{ erik_admin_user_role : "user_id"
    erik_admin_role ||--o{ erik_admin_user_role : "role_id"
    erik_admin_role ||--o{ erik_admin_role_permission : "role_id"
    erik_admin_permission ||--o{ erik_admin_role_permission : "permission_id"
    erik_admin_user ||--o{ erik_operation_log : "user_id"
    erik_admin_permission ||--o{ erik_admin_permission : "parent_id"
```

---

## 9. Бизнес-процесс экспорта

```mermaid
sequenceDiagram
    participant C as Клиент
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Файловая система

    Note over C,FS: === Экспорт Excel ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Данные
    CTL->>CTL: Расшифровка чувствительных полей
    CTL->>CTL: Маскирование (maskPhone/maskEmail)
    CTL->>CTL: Построение через PhpSpreadsheet<br/>шапка — синий фон, белый текст<br/>тонкие рамки у строк данных<br/>закрепление первой строки<br/>автофильтр
    CTL->>FS: Запись runtime/tmp/export_*.xlsx
    CTL-->>C: Скачивание файла

    Note over C,FS: === Экспорт PDF ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>шапка: заголовок + копирайт + время<br/>содержимое: таблица или карточки<br/>подвал: неудаляемый копирайт
    CTL->>CTL: Рендер Dompdf, A4 альбомная
    CTL->>FS: Запись runtime/tmp/export_*.pdf
    CTL-->>C: Скачивание файла
```

---

## 10. Дерево компонентов Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Форма входа<br/>имя пользователя/пароль/капча"]
    LF --> CAPTCHA["Компонент капчи по клику<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Circle-метка клика"]

    DB --> SIDEBAR["Сайдбар NavigationDrawer<br/>сворачивается 64px / 240px<br/>дашборд/пользователи/роли/настройки/журнал"]
    DB --> HEADER["Верхняя панель 56px<br/>кнопка сворачивания + меню пользователя<br/>AlertDialog выхода"]
    DB --> CONTENT["Область контента"]
    CONTENT --> DASH["DashboardPage<br/>карточки статистики GridView<br/>линейный график трендов LineChart<br/>круговая диаграмма PieChart<br/>последние операции ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. Маршрутизация страниц HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>Запуск"]
    EA -->|"нет Token"| LP["LoginPage<br/>Страница входа"]
    EA -->|"есть Token"| DP["DashboardPage<br/>Дашборд"]

    LP -->|"успешный вход<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>Список пользователей"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Личный кабинет"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>Детали/создание/редактирование"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"выход<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Обзор эшелонированной защиты

```mermaid
flowchart TB
    subgraph "Уровень 1: человек-машина"
        L1["Капча по клику<br/>Click Captcha<br/>обязательна при входе/регистрации"]
    end

    subgraph "Уровень 2: подтверждение операций"
        L2["Повторное подтверждение паролем<br/>confirmPassword()<br/>обязательно для DELETE"]
    end

    subgraph "Уровень 3: безопасность передачи"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Уровень 4: идентификация"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "Уровень 5: авторизация"
        L5["RBAC<br/>точность method.path<br/>супер-админ *"]
    end

    subgraph "Уровень 6: защита данных"
        L6["ID в API: шифрование Hashids<br/>тело запроса: шифрование Encryption<br/>хранение: шифрование Encryptable<br/>экспорт: маскирование + копирайт"]
    end

    subgraph "Уровень 7: аудит и трассировка"
        L7["OperationLog<br/>запись всех операций<br/>пользователь/IP/время/источник/параметры"]
    end

    L1 --> L2 --> L3 --> L4 --> L5 --> L6 --> L7

    style L1 fill:#1677FF,color:#fff
    style L2 fill:#1677FF,color:#fff
    style L3 fill:#FA8C16,color:#fff
    style L4 fill:#FA8C16,color:#fff
    style L5 fill:#52C41A,color:#fff
    style L6 fill:#722ED1,color:#fff
    style L7 fill:#FF4D4F,color:#fff
```

---

## 13. Топология развертывания

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Веб-сервер"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["Статические файлы<br/>Flutter Web build/"]
    end

    subgraph "Сервер приложений (горизонтальное масштабирование)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "Уровень данных"
        MYSQL["MySQL 8.0<br/>мастер-реплика<br/>префикс erik_"]
        ES["Elasticsearch 8.x<br/>кластер из 3 узлов<br/>префикс erik_"]
        REDIS["Redis 7.x<br/>режим Sentinel<br/>poster:captcha:*"]
    end

    subgraph "Мониторинг"
        MON["Grafana<br/>+ Prometheus"]
    end

    DNS --> NGX
    NGX --> STA
    NGX --> WM1 & WM2 & WM3
    WM1 & WM2 & WM3 --> MYSQL
    WM1 & WM2 & WM3 --> ES
    WM1 & WM2 & WM3 --> REDIS
    WM1 & WM2 & WM3 --> MON

    style NGX fill:#722ED1,color:#fff
    style WM1 fill:#1677FF,color:#fff
    style WM2 fill:#1677FF,color:#fff
    style WM3 fill:#1677FF,color:#fff
    style MYSQL fill:#1890FF,color:#fff
    style ES fill:#1890FF,color:#fff
    style REDIS fill:#1890FF,color:#fff
```
