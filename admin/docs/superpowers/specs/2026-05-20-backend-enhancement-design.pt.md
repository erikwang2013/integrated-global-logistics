> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-20-backend-enhancement-design.md) | [English](2026-05-20-backend-enhancement-design.en.md) | [한국어](2026-05-20-backend-enhancement-design.ko.md) | [Русский](2026-05-20-backend-enhancement-design.ru.md) | [Deutsch](2026-05-20-backend-enhancement-design.de.md) | [Français](2026-05-20-backend-enhancement-design.fr.md) | [Español](2026-05-20-backend-enhancement-design.es.md) | [Português](2026-05-20-backend-enhancement-design.pt.md) | [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) | [العربية](2026-05-20-backend-enhancement-design.ar.md) | [বাংলা](2026-05-20-backend-enhancement-design.bn.md) | [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) | [日本語](2026-05-20-backend-enhancement-design.ja.md)

# Subprojeto A: Aprimoramento do backend — Especificação de design

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Escopo

Este é o aprimoramento do backend, com 15 funcionalidades no total, envolvendo 9 novos arquivos + 4 arquivos modificados.

---

## Lista de arquivos novos/modificados

```
app/middleware/
├── OperationLog.php          # Novo: registro automático de log de operações
├── Cors.php                  # Novo: cross-origin
└── RateLimit.php             # Novo: limite de taxa Redis
app/admin/controller/
├── ConfigController.php      # Novo: CRUD de configuração do sistema
├── LogController.php         # Novo: consulta de logs de operação
├── ProfileController.php     # Novo: centro pessoal (inclui logout)
├── UploadController.php      # Novo: upload de arquivos
├── ImportController.php      # Novo: importação de usuários via Excel
└── HealthController.php      # Novo: verificação de saúde
app/model/
├── AdminUser.php             # Modificado: adiciona SoftDeletes + trait Searchable
└── OperationLog.php          # Modificado: adiciona public $timestamps = false
app/middleware/
└── AdminAuth.php             # Modificado: verificação de blacklist JWT
app/admin/controller/
├── DashboardController.php   # Modificado: estatísticas em tempo real via banco de dados
└── UserController.php        # Modificado: adiciona ações em lote
config/
└── route.php                 # Modificado: adiciona rotas + middleware
```

---

## 1. Middleware

### 1.1 Middleware CORS

**Arquivo**: `app/middleware/Cors.php`

- Requisições de preflight OPTIONS retornam diretamente 204
- Requisições não-preflight acrescentam `Access-Control-Allow-Origin: *` ao cabeçalho da resposta
- Cabeçalhos permitidos: `Authorization, Content-Type, API-Version`
- Cache máximo: 86400 segundos

Montagem: middleware global (`config/middleware.php`)

### 1.2 Middleware de limite de taxa

**Arquivo**: `app/middleware/RateLimit.php`

- Armazenamento: janela deslizante Redis Sorted Set
- Padrão: 60 vezes/minuto/IP/rota
- Interfaces sensíveis:
  - `/api/auth/login`: 10 vezes/minuto
  - `/api/auth/register`: 5 vezes/minuto
- Exceder o limite retorna `429 Too Many Requests`

Montagem: middleware global (`config/middleware.php`), depois de Cors e antes de ApiVersion

### 1.3 Middleware de log de operação

**Arquivo**: `app/middleware/OperationLog.php`

- Registra somente POST/PUT/DELETE
- Campos registrados: user_id, action, method, path, ip, input(JSON)
- Gravação assíncrona após a resposta ser retornada (sem bloqueio)

Montagem: grupo de rotas `/admin`, depois de AdminPermission

### 1.4 Cadeia de execução do middleware global

```
Todas as requisições:
  Cors → RateLimit → ApiVersion → {Middleware da rota} → Controller

Requisições /admin/*:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Logout (blacklist JWT)

**Arquivo**: `app/middleware/AdminAuth.php` (modificado)

**Princípio**: JWT é sem estado por natureza; no logout, o token é adicionado à blacklist no Redis, e o AdminAuth verifica a blacklist primeiro.

**Alteração no AdminAuth**:
- No início de `process()`: verificar na coleção Redis `jwt_blacklist` se o token atual está na blacklist
- Se estiver na blacklist, retornar 401

**Rota de logout** (sob o centro pessoal):

| Método | Rota | Descrição |
|------|------|------|
| `POST` | `/admin/profile/logout` | Adiciona o token Bearer atual à blacklist do Redis, TTL = validade restante do token |

**Lógica do Logout**:
```php
// Analisar a validade restante do token
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// Adicionar à blacklist
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Novos controladores e alterações existentes

### 2.1 CRUD de configuração do sistema (`ConfigController`)

Herda de `BaseController`.

| Método | Rota | Descrição |
|------|------|------|
| `index()` | GET `/admin/config` | Lista paginada, filtrável por `group`, paginação com `page`/`limit` |
| `store()` | POST `/admin/config` | Cria item de configuração, obrigatórios: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Atualiza value/type/description do item de configuração |
| `destroy()` | DELETE `/admin/config/{id}` | Exclui item de configuração, exige `confirmPassword()` |

### 2.2 Consulta de logs de operação (`LogController`)

Herda de `BaseController`.

| Método | Rota | Descrição |
|------|------|------|
| `index()` | GET `/admin/log` | Lista paginada, filtros: user_id, action, path, created_at(intervalo) |

Não há criar/alterar/excluir; os logs são registrados automaticamente pelo middleware.

### 2.3 Centro pessoal (`ProfileController`)

Herda de `BaseController`. Opera o usuário atualmente logado (`$request->adminId`).

| Método | Rota | Descrição |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Atualiza real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Altera a senha, exige old_password, new_password, new_password_confirmation |

### 2.4 Upload de arquivos (`UploadController`)

Herda de `BaseController`.

| Método | Rota | Descrição |
|------|------|------|
| `upload()` | POST `/admin/upload` | Recebe arquivo, suporta image/jpeg/png/gif/pdf/xlsx/docx |

- Máximo de 10MB
- Caminho de armazenamento: `public/upload/{date}/{hash}.{ext}`
- Retorno: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Dados reais do dashboard

**Arquivo**: `app/admin/controller/DashboardController.php` (modificado)

Substituir os dados falsos atualmente codificados por estatísticas em tempo real do banco:

| Indicador | Origem | Descrição |
|------|------|------|
| Total de usuários | `AdminUser::count()` | Sem soft delete |
| Novos hoje | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Total de roles | `AdminRole::count()` | |
| Total de permissões | `AdminPermission::count()` | |
| Dados de tendência | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Novos usuários dos últimos 7 dias, por dia |
| Dados de distribuição | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Distribuição por status |
| Operações recentes | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | Últimos 10 logs de operação |

### 2.6 Operações em lote de usuários

**Arquivo**: `app/admin/controller/UserController.php` (modificado, novos métodos)

| Método | Rota | Descrição |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Exclusão em lote, corpo `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Habilitar/desabilitar em lote, corpo `{ ids: [hashid, ...], status: 1|0 }` |

- Cada id passa primeiro por `decodeId()` para virar BIGINT
- `batchDestroy()` deve passar pela validação de `confirmPassword()`

### 2.7 Importação de dados

**Arquivo**: `app/admin/controller/ImportController.php` (novo)

| Método | Rota | Descrição |
|------|------|------|
| `users()` | POST `/admin/import/users` | Envia arquivo Excel, cria usuários em lote |

Fluxo:
1. Recebe o arquivo `.xlsx`
2. Parse com PhpSpreadsheet, colunas esperadas: `username, password, real_name, phone, email, status`
3. Validação linha a linha + criação (ID gerado por snowflake, senha bcrypt, phone/email criptografados com encryption)
4. Retorna o resultado: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Verificação de saúde

**Arquivo**: `app/admin/controller/HealthController.php` (novo)

`GET /health` (sem autenticação, não contabilizado no log de operações):

Retorna o status de conexão de cada componente:
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

- Quando a detecção de um componente falha, o valor do campo correspondente é a string de descrição do erro
- A rota não usa o prefixo `/admin`, é registrada separadamente no escopo global

---

## 3. Correções de modelos

### 3.1 Timestamps do OperationLog

**Arquivo**: `app/model/OperationLog.php` (modificado)

A tabela `erik_operation_log` tem apenas a coluna `created_at` (sem `updated_at`). O `save()` padrão do Eloquent tenta gravar `updated_at`, causando erro de SQL.

Correção: `public $timestamps = false;` + especificar manualmente `created_at` na gravação.

### 3.2 Alteração do modelo AdminUser

- Adicionar trait `Searchable`
- Implementar `toSearchableArray()`: retorna username, real_name
- `UserController::index()` usa `AdminUser::search($kw)->get()` quando detecta palavra-chave, em vez de LIKE do MySQL

O ES precisa primeiro criar o índice, o que pode ser feito com comandos Scout:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Alterações de rotas

Novas rotas no `config/route.php`:

```php
// Adicionado dentro do grupo de rotas /admin:
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

// Verificação de saúde (rota global, fora do grupo /admin)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// Middleware:
app\middleware\OperationLog::class adicionado ao grupo de middleware /admin
```

Registro dos middlewares globais no `config/middleware.php`:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Códigos de erro complementares

| code | Significado | Cenário de acionamento |
|------|------|---------|
| 429 | Requisições em excesso | Acionado por RateLimit |

---

## 6. Fora do escopo desta versão

- Sistema de notificações (requer fila de mensagens + infraestrutura de push no frontend)
- Páginas frontend Flutter (subprojeto B)
- Refresh de Token no HarmonyOS (subprojeto C)
