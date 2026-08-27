> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](CLAUDE.md) | [English](CLAUDE.en.md) | [한국어](CLAUDE.ko.md) | [Русский](CLAUDE.ru.md) | [Deutsch](CLAUDE.de.md) | [Français](CLAUDE.fr.md) | [Español](CLAUDE.es.md) | [Português](CLAUDE.pt.md) | [हिन्दी](CLAUDE.hi.md) | [العربية](CLAUDE.ar.md) | [বাংলা](CLAUDE.bn.md) | [Bahasa Indonesia](CLAUDE.id.md) | [日本語](CLAUDE.ja.md)

# Открытая админ-панель (open-admin)

Полнофункциональная система администрирования на базе webman v2 + Flutter.

## Заявление об авторских правах

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **Неизменно, неудаляемо, необратимо.** Все новые файлы обязаны содержать указанное выше заявление об авторских правах в качестве заголовочного комментария.

## Перечень возможностей

| Домен | Функция |
|----|------|
| Аутентификация | Вход/регистрация/обновление/выход + капча + блокировка учётной записи + ограничение сессий |
| Дашборд | Реальная статистика/тренды/распределение/журнал (кэш Redis 5m) |
| Пользователи | CRUD + массовое удаление/включение и отключение + импорт Excel |
| Роли и права | CRUD + дерево прав + авторизация RBAC method.path |
| Системные настройки | CRUD пар «ключ-значение» |
| Аудит операций | Запрос журнала + автоматическое определение источников с 8 платформ |
| Файлы | Загрузка + экспорт Excel/PDF (маскирование чувствительных данных) |
| Безопасность | 18 уровней эшелонированной обороны (XSS/SQL-инъекции/CSRF/лимиты/CSP...) |
| Эксплуатация | Health check/метрики Prometheus/документация API/security.txt + Docker + CI/CD |

## Технологический стек

### Бэкенд
- PHP 8.3+, webman v2 (workerman/webman)
- База данных: MySQL 8.0+, префикс таблиц `erik_`
- Первичный ключ: BIGINT без автоинкремента, генерируется через `erikwang2013/snowflake-php`
- Шифрование/расшифровка ID на уровне API: `erikwang2013/hashids`
- Аутентификация JWT: `erikwang2013/jwt-webman`
- Шифрование/расшифровка чувствительных данных API: `erikwang2013/encryption`
- Шифрование/расшифровка чувствительных полей БД: `erikwang2013/encryptable`
- Синхронизация и поиск ES: `erikwang2013/webman-scout`
- Флаги стран: `erikwang2013/season`

### Фронтенд
- Flutter 3.x, исходники в `apps/flutter/`
- Веб-версия в стиле PC-панели управления (не мобильный App-стиль)
- Поддержка клиентской и административной части
- HarmonyOS ArkTS, исходники в `apps/harmonyos/`

## Структура проекта

```
open-admin/
├── app/
│   ├── admin/controller/       # Контроллеры админки (14)
│   │   ├── BaseController.php      # Базовый контроллер
│   │   ├── DashboardController.php # Дашборд (кэш Redis)
│   │   ├── UserController.php      # CRUD пользователей + массовые операции
│   │   ├── RoleController.php      # CRUD ролей
│   │   ├── PermissionController.php# CRUD прав
│   │   ├── ConfigController.php    # CRUD системных настроек
│   │   ├── LogController.php       # Запрос журнала операций
│   │   ├── ProfileController.php   # Личный кабинет + выход
│   │   ├── ExportController.php    # Экспорт Excel/PDF
│   │   ├── ImportController.php    # Импорт пользователей из Excel
│   │   ├── UploadController.php    # Загрузка файлов
│   │   ├── HealthController.php    # Проверка работоспособности
│   │   ├── DocsController.php      # OpenAPI-документация
│   │   └── MetricsController.php   # Метрики Prometheus
│   ├── api/v1/controller/      # Контроллеры API v1 (управление версией заголовком)
│   │   ├── CaptchaController.php
│   │   └── AuthController.php
│   ├── common/                 # Общие утилиты
│   │   ├── HashidsService.php
│   │   ├── SnowflakeService.php
│   │   └── EncryptionService.php
│   ├── common/                 # Общие определения (включая Apidoc Definitions)
│   ├── middleware/             # Промежуточное ПО (8)
│   │   ├── Cors.php            # CORS (глобально)
│   │   ├── SecurityFilter.php  # Перехват атак (глобально: XSS/SQL-инъекции/обход путей/инъекции команд/CSRF)
│   │   ├── RateLimit.php       # Redis-лимит (глобально, атомарный Lua)
│   │   ├── ApiVersion.php      # Проверка версии API
│   │   ├── AdminAuth.php       # Аутентификация JWT + чёрный список
│   │   ├── AdminPermission.php # Проверка прав RBAC (кэш Redis 60s)
│   │   └── OperationLog.php    # Автоматическая запись журнала операций (с определением источника)
│   ├── model/                  # Модели данных
│   ├── queue/                  # Задачи очередей
│   └── process/                # Процессы (Http, Monitor)
├── apps/
│   ├── flutter/                # Flutter Web-админка
│   │   └── lib/app/
│   │       ├── pages/          # 6 полных страниц
│   │       │   ├── dashboard/  # Дашборд
│   │       │   ├── login/      # Вход
│   │       │   ├── user/       # Пользователи
│   │       │   ├── role/       # Роли и права
│   │       │   ├── config/     # Системные настройки
│   │       │   ├── log/        # Журнал операций
│   │       │   └── profile/    # Личный кабинет
│   │       ├── services/       # ApiService + AuthService
│   │       ├── layouts/        # Адаптивный макет
│   │       └── theme/          # Тема Material 3
│   └── harmonyos/              # Клиент HarmonyOS
├── config/                     # Конфигурационные файлы
│   ├── route.php               # Маршруты + стратегия версий API
│   └── middleware.php           # Регистрация глобального промежуточного ПО
├── database/
│   ├── install.sql             # Полный скрипт установки (объединяет все SQL)
│   └── backup/                 # Скрипты резервного копирования БД
│       ├── backup.sh           # mysqldump+gzip, хранение 30 дней
│       └── restore.sh          # Интерактивное восстановление
├── docs/                       # Документация
│   ├── ARCHITECTURE.md         # Схемы Mermaid
│   ├── DESIGN.md               # Проектная документация
│   ├── SECURITY.md             # Дизайн архитектуры безопасности
│   ├── API.md                  # Справочник API
│   ├── nginx-security.conf     # Справочная безопасная конфигурация Nginx
│   ├── diagrams/               # Декомпозированные схемы архитектуры
│   └── superpowers/            # Спецификации и планы
│       ├── specs/              # Спецификации дизайна
│       └── plans/              # Планы реализации
├── public/                     # Точка входа
├── runtime/                    # Файлы времени выполнения
├── tests/                      # Тесты
├── vendor/                     # Зависимости Composer
├── CLAUDE.md                   # Этот файл
├── README.md                   # Документация на китайском
├── docs/translations/README.en.md                # Документация на английском
├── docs/translations/README.ko.md ... README.ja.md  # Многоязычная документация (кор/рус/нем/фр/исп/порт/хинди/араб/бенг/индон/яп)
├── .env                        # Переменные окружения (не в версионном контроле)
├── .env.example                # Шаблон переменных окружения
├── .env.docker                 # Переменные окружения для Docker
├── composer.json               # Зависимости PHP
├── Dockerfile                  # Сборка Docker
├── docker-compose.yml          # Оркестрация Docker
└── .github/
    └── workflows/
        └── ci.yml              # Пайплайн CI/CD (PHP-синтаксис + PHPUnit + Flutter analyze)
```

## Цепочка выполнения промежуточного ПО

```
全局:  Cors → Locale(Accept-Language) → SecurityFilter(方法检查→405) → RateLimit → {路由中间件}
/admin: Cors → Locale(Accept-Language) → SecurityFilter(方法检查→405) → RateLimit → AdminAuth → AdminPermission → OperationLog → Controller
/api:   Cors → Locale(Accept-Language) → SecurityFilter(方法检查→405) → RateLimit → ApiVersion → Controller
/health: Cors → Locale(Accept-Language) → SecurityFilter(方法检查→405) → RateLimit → Controller
```

## Усиление безопасности

- **Ограничение HTTP-методов**: SecurityFilter разрешает только GET/POST/PUT/DELETE/OPTIONS/HEAD, нестандартные методы возвращают 405
- **CSP-заголовки**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies инъектируются во все ответы
- **Блокировка учётной записи**: 5 неудачных входов подряд — блокировка на 15 минут
- **Ограничение одновременных сессий**: не более 3 действующих Token на пользователя; при превышении самый старый Token добавляется в чёрный список
- **security.txt**: эндпоинт `/.well-known/security.txt` по RFC 9116
- **Безопасная конфигурация Nginx**: `docs/nginx-security.conf` — справочник по усилению безопасности обратного прокси

## Стратегия версий API

Версия задаётся заголовком `API-Version` (по умолчанию `v1`) и не отражается в URL:

```bash
curl -H "API-Version: v1" http://localhost:8787/api/auth/login
```

Для новой версии достаточно создать каталог `app/api/{version}/controller/` и зарегистрировать его в middleware `ApiVersion`.

## Политика лимитов запросов

Скользящее окно Redis (атомарный Lua), по умолчанию 60 раз/мин/IP/маршрут:
- Вход: 10 раз/мин
- Регистрация: 5 раз/мин
- Заголовки ответа: `X-RateLimit-Limit/Remaining/Reset`, при превышении добавляется `Retry-After`

## Правила написания кода

### PHP
- Глобальные функции/классы без ведущего `\`, импорт через `use`
- Конфигурационные файлы обязаны содержать китайские комментарии с пояснением каждого параметра
- Все новые `.php`-файлы обязаны содержать заголовок с заявлением об авторских правах

### База данных
- Префикс таблиц: `erik_`
- Первичный ключ `id`: тип BIGINT, без автоинкремента, генерируется snowflake
- Чувствительные поля автоматически шифруются/расшифровываются trait `erikwang2013/encryptable`
- Файлы миграций в формате SQL

### Flutter
- Веб-макет в стиле PC-панели управления (сайдбар + верхняя панель + область контента)
- Управление состоянием GetX, синглтон `ApiService` (Dio + JWT-перехватчик)
- Персистентность токена через `shared_preferences`
- Адаптивные точки: мобильные (< 768px) и десктоп (>= 768px)

### HarmonyOS
- Использование нативного HTTP-клиента `@ohos.net.http`
- Бесшовное обновление токена: при 401 автоматически вызывается `/api/auth/refresh`
- При неудачном обновлении — автоматический редирект на страницу входа

## Развертывание

### Docker Compose (рекомендуется для продакшена)

`docker-compose.yml` в корне проекта оркестрирует 5 сервисов:

| Сервис | Описание |
|------|------|
| `nginx` | Обратный прокси Nginx (80/443), статические файлы |
| `app` | Приложение webman PHP 8.3, сборка из `Dockerfile` (с OPcache) |
| `mysql` | MySQL 8.0, персистентные тома |
| `redis` | Redis 7 Alpine, кэш/лимиты/Session |
| `elasticsearch` | Elasticsearch 8.x, полнотекстовый поиск |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` определяет пайплайн GitHub Actions:

- Синтаксическая проверка PHP (`php -l`)
- Модульные тесты PHPUnit
- Статический анализ Flutter (`flutter analyze`)

### Резервное копирование БД

`database/backup/backup.sh` — mysqldump + gzip, автоматическая очистка бэкапов старше 30 дней.
`database/backup/restore.sh` — интерактивное восстановление со списком доступных бэкапов.

### Мониторинг

Эндпоинт `GET /metrics` (`MetricsController`) выводит Prometheus text format с 5 метриками gauge:
- `openadmin_http_requests_total` — общее число запросов
- `openadmin_active_users` — число активных пользователей
- `openadmin_db_connection_status` — состояние соединения с БД (0/1)
- `openadmin_redis_connection_status` — состояние соединения с Redis (0/1)
- `openadmin_memory_usage_bytes` — потребление памяти
