> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-20-backend-enhancement-design.md) | [English](2026-05-20-backend-enhancement-design.en.md) | [한국어](2026-05-20-backend-enhancement-design.ko.md) | [Русский](2026-05-20-backend-enhancement-design.ru.md) | [Deutsch](2026-05-20-backend-enhancement-design.de.md) | [Français](2026-05-20-backend-enhancement-design.fr.md) | [Español](2026-05-20-backend-enhancement-design.es.md) | [Português](2026-05-20-backend-enhancement-design.pt.md) | [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) | [العربية](2026-05-20-backend-enhancement-design.ar.md) | [বাংলা](2026-05-20-backend-enhancement-design.bn.md) | [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) | [日本語](2026-05-20-backend-enhancement-design.ja.md)

# Подпроект A: доработка бэкенда — дизайн-спецификация

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Область

Настоящая доработка касается бэкенда: всего 15 функциональных пунктов, 9 новых файлов + 4 изменённых файла.

---

## Список новых/изменённых файлов

```
app/middleware/
├── OperationLog.php          # новый: автоматическая запись журнала операций
├── Cors.php                  # новый: кросс-доменный доступ
└── RateLimit.php             # новый: ограничение частоты запросов через Redis
app/admin/controller/
├── ConfigController.php      # новый: CRUD системной конфигурации
├── LogController.php         # новый: просмотр журнала операций
├── ProfileController.php     # новый: личный кабинет (включая выход)
├── UploadController.php      # новый: загрузка файлов
├── ImportController.php      # новый: импорт пользователей из Excel
└── HealthController.php      # новый: проверка работоспособности
app/model/
├── AdminUser.php             # изменён: добавлены SoftDeletes + Searchable trait
└── OperationLog.php          # изменён: добавлено public $timestamps = false
app/middleware/
└── AdminAuth.php             # изменён: проверка JWT-черного списка
app/admin/controller/
├── DashboardController.php   # изменён: переход на реальную статистику из БД
└── UserController.php        # изменён: добавлены пакетные действия
config/
└── route.php                 # изменён: добавлены маршруты + промежуточное ПО
```

---

## 1. Промежуточное ПО

### 1.1 CORS-промежуточное ПО

**Файл**: `app/middleware/Cors.php`

- OPTIONS-предзапрос сразу возвращает 204
- для непредзапросов в заголовки ответа добавляется `Access-Control-Allow-Origin: *`
- разрешённые заголовки: `Authorization, Content-Type, API-Version`
- максимальное время кэширования: 86400 секунд

Подключается: глобальное промежуточное ПО (`config/middleware.php`)

### 1.2 Промежуточное ПО ограничения частоты

**Файл**: `app/middleware/RateLimit.php`

- хранение: скользящее окно на Redis Sorted Set
- по умолчанию: 60 запросов/мин/IP/маршрут
- чувствительные интерфейсы:
  - `/api/auth/login`: 10 запросов/мин
  - `/api/auth/register`: 5 запросов/мин
- при превышении возвращается `429 Too Many Requests`

Подключается: глобальное промежуточное ПО (`config/middleware.php`), после Cors, до ApiVersion

### 1.3 Промежуточное ПО журнала операций

**Файл**: `app/middleware/OperationLog.php`

- записываются только POST/PUT/DELETE
- записываемые поля: user_id, action, method, path, ip, input(JSON)
- асинхронная запись после возврата ответа (не блокирует)

Подключается: группа маршрутов `/admin`, после AdminPermission

### 1.4 Цепочка выполнения глобального промежуточного ПО

```
Все запросы:
  Cors → RateLimit → ApiVersion → {промежуточное ПО маршрута} → Controller

Запросы /admin/*:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Выход из системы (JWT-черный список)

**Файл**: `app/middleware/AdminAuth.php` (изменён)

**Принцип**: JWT сам по себе не имеет состояния; при выходе token добавляется в Redis-черный список, и AdminAuth при проверке сначала обращается к черному списку.

**Изменение AdminAuth**:
- в начале `process()`: проверка по множеству `jwt_blacklist` в Redis, находится ли текущий token в черном списке
- при попадании в черный список возвращается 401

**Маршрут выхода** (в личном кабинете):

| Метод | Маршрут | Описание |
|------|------|------|
| `POST` | `/admin/profile/logout` | добавляет текущий Bearer token в Redis-черный список, TTL=оставшийся срок жизни token |

**Логика Logout**:
```php
// разобрать оставшийся срок жизни token
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// добавить в черный список
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Новые контроллеры и доработка существующих

### 2.1 CRUD системной конфигурации (`ConfigController`)

Наследует `BaseController`.

| Метод | Маршрут | Описание |
|------|------|------|
| `index()` | GET `/admin/config` | список с пагинацией, фильтр по `group`, пагинация `page`/`limit` |
| `store()` | POST `/admin/config` | создание пункта конфигурации, обязательные: group, key, value |
| `update()` | PUT `/admin/config/{id}` | обновление value/type/description |
| `destroy()` | DELETE `/admin/config/{id}` | удаление пункта конфигурации, требуется `confirmPassword()` |

### 2.2 Просмотр журнала операций (`LogController`)

Наследует `BaseController`.

| Метод | Маршрут | Описание |
|------|------|------|
| `index()` | GET `/admin/log` | список с пагинацией, фильтры: user_id, action, path, created_at(диапазон) |

Операции добавления/изменения/удаления не предусмотрены: журнал записывается промежуточным ПО автоматически.

### 2.3 Личный кабинет (`ProfileController`)

Наследует `BaseController`. Работает с текущим авторизованным пользователем (`$request->adminId`).

| Метод | Маршрут | Описание |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | обновление real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | смена пароля, требуется old_password, new_password, new_password_confirmation |

### 2.4 Загрузка файлов (`UploadController`)

Наследует `BaseController`.

| Метод | Маршрут | Описание |
|------|------|------|
| `upload()` | POST `/admin/upload` | приём файла, поддержка image/jpeg/png/gif/pdf/xlsx/docx |

- максимум 10MB
- путь хранения: `public/upload/{date}/{hash}.{ext}`
- возврат: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Реальные данные дашборда

**Файл**: `app/admin/controller/DashboardController.php` (изменён)

Замена текущих захардкоженных фиктивных данных на реальную статистику из БД:

| Показатель | Источник | Описание |
|------|------|------|
| всего пользователей | `AdminUser::count()` | без мягко удалённых |
| новых сегодня | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| всего ролей | `AdminRole::count()` | |
| всего разрешений | `AdminPermission::count()` | |
| данные тренда | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | статистика новых за последние 7 дней |
| данные распределения | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | распределение по статусам |
| последние операции | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | последние 10 записей журнала операций |

### 2.6 Пакетные операции с пользователями

**Файл**: `app/admin/controller/UserController.php` (изменён, добавлены методы)

| Метод | Маршрут | Описание |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | пакетное удаление, тело запроса `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | пакетное включение/отключение, тело запроса `{ ids: [hashid, ...], status: 1|0 }` |

- каждый id сначала конвертируется в BIGINT через `decodeId()`
- `batchDestroy()` должен проходить проверку `confirmPassword()`

### 2.7 Импорт данных

**Файл**: `app/admin/controller/ImportController.php` (новый)

| Метод | Маршрут | Описание |
|------|------|------|
| `users()` | POST `/admin/import/users` | загрузка Excel-файла, пакетное создание пользователей |

Процесс:
1. приём `.xlsx` файла
2. разбор PhpSpreadsheet, ожидаемые колонки: `username, password, real_name, phone, email, status`
3. построчная валидация + создание (ID генерирует snowflake, пароль — bcrypt, phone/email шифруются encryption)
4. возврат результата: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Проверка работоспособности

**Файл**: `app/admin/controller/HealthController.php` (новый)

`GET /health` (без аутентификации, в журнал операций не записывается):

Возвращает статус подключения компонентов:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- при сбое проверки компонента соответствующее поле содержит строку описания ошибки
- маршрут не вешается на префикс `/admin`, регистрируется отдельно глобально

---

## 3. Исправления моделей

### 3.1 Временные метки OperationLog

**Файл**: `app/model/OperationLog.php` (изменён)

В таблице `erik_operation_log` есть только колонка `created_at` (без `updated_at`). Стандартный `save()` в Eloquent пытается записать `updated_at`, что вызывает SQL-ошибку.

Исправление: `public $timestamps = false;` + ручное указание `created_at` при записи.

### 3.2 Доработка модели AdminUser

- добавлен `Searchable` trait
- реализация `toSearchableArray()`: возвращает username, real_name
- `UserController::index()` при обнаружении ключевого слова использует `AdminUser::search($kw)->get()` вместо MySQL LIKE

Для ES нужно сначала создать индекс — через Scout-команды:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Изменения маршрутов

Новые маршруты в `config/route.php`:

```php
// внутри группы маршрутов /admin:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// проверка работоспособности (глобальный маршрут, не в группе /admin)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// промежуточное ПО:
в группе /admin добавить промежуточное ПО app\middleware\OperationLog::class
```

Регистрация глобального промежуточного ПО в `config/middleware.php`:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Дополнение кодов ошибок

| code | значение | сценарий срабатывания |
|------|------|---------|
| 429 | слишком частые запросы | срабатывание RateLimit |

---

## 6. Вне области данной версии

- система уведомлений (требует инфраструктуры очередей сообщений + пушей на фронтенде)
- страницы Flutter-фронтенда (подпроект B)
- обновление Token в HarmonyOS (подпроект C)
