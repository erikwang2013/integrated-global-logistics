> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](API.md) | [English](API.en.md) | [한국어](API.ko.md) | [Русский](API.ru.md) | [Deutsch](API.de.md) | [Français](API.fr.md) | [Español](API.es.md) | [Português](API.pt.md) | [हिन्दी](API.hi.md) | [العربية](API.ar.md) | [বাংলা](API.bn.md) | [Bahasa Indonesia](API.id.md) | [日本語](API.ja.md)

# Documento de referência da API

## 1. Visão geral

O Painel de Administração Open Source (open-admin) é construído sobre webman v2 e fornece uma API JSON RESTful. Todos os endpoints do painel administrativo exigem autenticação JWT e verificação de permissões RBAC; os endpoints públicos são roteados para controladores versionados por meio do cabeçalho de versão da API.

- **URL base**: `http://localhost:8787`
- **Versão da API**: controlada pelo cabeçalho `API-Version: v1` (padrão v1 quando ausente)
- **Idioma**: alterna via cabeçalho `Accept-Language` ou parâmetro `?lang=zh_CN|en` (padrão zh_CN), detectado automaticamente pelo middleware Locale

> **Visão geral dos endpoints**: autenticação(5) | painel(1) | usuários(7) | papéis(4) | permissões(4) | configuração(4) | logs(1) | central do usuário(3) | importação/exportação(3) | upload(1) | operações(4: health/metrics/docs/security.txt) | total de 37 endpoints
- **Autenticação**: `Authorization: Bearer <token>` (JWT)
- **Formato de resposta**: `{ "code": 0, "message": "success", "data": {...} }`
- **Endpoint de documentação**: `GET /api/docs` retorna a especificação JSON OpenAPI 3.0

### Requisitos de requisição

- Apenas os métodos `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` são permitidos; o uso de outros métodos HTTP (como TRACE, CONNECT, PATCH) retorna 405
- Todas as requisições `POST` / `PUT` devem definir `Content-Type: application/json` (exceto uploads de arquivos), caso contrário retorna 415
- O corpo da requisição não pode exceder 10MB, caso contrário retorna 413
- O filtro de segurança varre todas as entradas das requisições contra XSS, injeção SQL, path traversal e injeção de comandos; quando detectado, retorna 403
- 5 falhas consecutivas de login acionam o bloqueio da conta (15 minutos); durante o bloqueio, requisições de login retornam 429
- Um mesmo usuário pode manter no máximo 3 tokens válidos simultaneamente; o token mais antigo é adicionado à blacklist automaticamente quando o limite é excedido

## 2. Códigos de erro

| code | Significado | Cenário de acionamento |
|------|------|---------|
| 0 | Sucesso | |
| 400 | Erro de parâmetro da requisição | Formato da requisição incorreto |
| 401 | Não autenticado | Token ausente / expirado / na blacklist |
| 403 | Sem permissão / bloqueio de segurança | Permissões RBAC insuficientes / detecção do SecurityFilter |
| 404 | Recurso não encontrado | O alvo da consulta/atualização/exclusão não existe |
| 405 | Método de requisição não permitido | Apenas GET/POST/PUT/DELETE/OPTIONS/HEAD são permitidos; métodos não padronizados são rejeitados diretamente |
| 413 | Corpo da requisição muito grande | Content-Length excede 10MB |
| 415 | Tipo de mídia não suportado | Requisição POST/PUT com Content-Type não JSON e que não seja upload de arquivo |
| 422 | Falha na validação de parâmetros | Campo obrigatório ausente, formato incorreto, validação de negócio reprovada |
| 429 | Requisições frequentes demais | RateLimit acionado / bloqueio de conta (5 falhas consecutivas de login bloqueiam por 15 minutos) |
| 500 | Erro interno do servidor | |

## 3. Endpoints públicos

Todos os endpoints públicos estão no grupo `/api` e são distribuídos pelo middleware `ApiVersion` para os controladores versionados correspondentes com base no cabeçalho `API-Version` (por exemplo, `app\api\v1\controller\AuthController`).

### 3.1 Health check

```
GET /health
```

- **Autenticação**: não necessária
- **Rate limit**: não há

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

Valores de `database`, `redis` e `elasticsearch`: `"ok"` | `"unavailable"`. `elasticsearch` retorna `"unavailable"` quando o ES está inacessível; se o status de saúde do cluster não for green/yellow, retorna o valor real de status (por exemplo, `"red"`).

### 3.2 Documentação da API

```
GET /api/docs
```

- **Autenticação**: não necessária
- **Rate limit**: padrão global (60 requisições/minuto)
- **Resposta**: especificação JSON OpenAPI 3.0.3, contendo definições de todos os endpoints, parâmetros e Schemas

### 3.3 Gerar captcha

```
POST /api/captcha/generate
```

- **Autenticação**: não necessária
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: padrão global (60 requisições/minuto)

**Corpo da requisição**:
```json
{
  "difficulty": "medium"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| difficulty | string | não | `easy` / `medium` / `hard`, padrão `medium` |

**Exemplo de resposta** — tipo clique (`type: "click"`):
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "type": "click",
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "extra": {
      "targets": [
        { "order": 1, "text": "A", "x": 120, "y": 85 },
        { "order": 2, "text": "B", "x": 310, "y": 42 }
      ]
    }
  }
}
```

**Exemplo de resposta** — tipo deslizante (`type: "slider"`):
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "def456abc789",
    "type": "slider",
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "extra": {
      "x": 120,
      "y": 60,
      "puzzle_w": 50,
      "puzzle_h": 50,
      "puzzle": "data:image/png;base64,iVBORw0KGgo..."
    }
  }
}
```

**Exemplo de resposta** — tipo rotação (`type: "rotate"`):
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "ghi789abc012",
    "type": "rotate",
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "extra": {
      "angle": 45
    }
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| key | string | Identificador do captcha, reenviado na validação |
| type | string | Tipo do captcha: `click` / `slider` / `rotate` |
| image | string | Imagem data URI base64 |
| extra | object | Dados adicionais relacionados ao tipo (ver abaixo) |

**`extra` por tipo**:

| type | campos de extra | Tipo | Descrição |
|------|-----------|------|------|
| click | targets | array | Alvos de clique, contendo `order`(ordem) `text`(texto da dica) `x` `y`(coordenadas) |
| slider | x, y | int | Coordenadas do canto superior esquerdo do recorte (com base em canvas 300×200) |
| slider | puzzle_w, puzzle_h | int | Largura e altura da peça do quebra-cabeça |
| slider | puzzle | string | Peça do quebra-cabeça em data URI base64 |
| rotate | angle | int | Ângulo correto de rotação (0-359), é necessário rotacionar `360-angle` para endireitar a imagem |

### 3.4 Validar captcha

```
POST /api/captcha/verify
```

- **Autenticação**: não necessária
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: padrão global (60 requisições/minuto)

**Corpo da requisição** — tipo clique (`type: "click"`):
```json
{
  "key": "abc123def456",
  "type": "click",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

**Corpo da requisição** — tipo deslizante (`type: "slider"`):
```json
{
  "key": "def456abc789",
  "type": "slider",
  "clicks": 120
}
```

**Corpo da requisição** — tipo rotação (`type: "rotate"`):
```json
{
  "key": "ghi789abc012",
  "type": "rotate",
  "clicks": 315
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| key | string | sim | Chave do captcha, retornada por generate |
| type | string | sim | Tipo do captcha, deve ser igual ao `type` retornado por generate |
| clicks | variante | sim | Dados da resposta, o formato varia conforme o type (ver abaixo) |

**`clicks` por tipo**:

| type | tipo de clicks | Descrição | Tolerância de erro |
|------|------------|------|---------|
| click | `[{x:int, y:int}]` | Array de coordenadas de clique, na ordem de order | raio de 18px |
| slider | `int` | Deslocamento do eixo X do controle deslizante | ±4px |
| rotate | `int` | Ângulo de rotação (0-359) | ±5° |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

Após a validação bem-sucedida, o backend grava `captcha_verified:{key}` no Redis (TTL 300s), e o endpoint de login libera o acesso com base nisso.
Em caso de falha na validação, o `code` é 422, o `message` é `"验证失败，请重试"` e `data.valid` é `false`.

### 3.5 Login

```
POST /api/auth/login
```

- **Autenticação**: não necessária
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: 10 requisições/minuto (por IP + caminho)

**Corpo da requisição**:
```json
{
  "username": "admin",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "captcha_key": "abc123def456"
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| username | string | sim | min:3, max:50 | Nome de usuário |
| password | string | sim | min:6, max:32 (texto puro) | Criptografado com AES-256-CBC-HMAC e codificado em Base64 (compatível com texto puro) |
| captcha_key | string | sim | | Chave do captcha (é necessário passar pela validação de `/api/captcha/verify` primeiro) |

### Protocolo de criptografia de senha

Usa **criptografia assimétrica RSA-2048**; a chave pública fica no código do frontend (pode ser exposta com segurança), e a chave privada é mantida somente no servidor.

```
Fluxo de criptografia (cliente):
  Chave pública RSA (PEM) → criptografia PKCS1v1.5 → codificação Base64 → transmissão

Fluxo de descriptografia (servidor, com fallback em etapas):
  1. Descriptografia com chave privada RSA → sucesso e UTF-8 válido → usar o resultado descriptografado
  2. Descriptografia AES-256-CBC-HMAC → sucesso → usar o resultado descriptografado (compatibilidade com clientes antigos)
  3. Fallback para texto puro → usar a entrada original diretamente
```

A chave pública está embutida no aplicativo frontend e não precisa ser transmitida pela rede. A chave privada é armazenada apenas em `RSA_PRIVATE_KEY` no `.env` e não pode vazar.

> A criptografia simétrica AES é uma solução de compatibilidade com versões antigas; será removida quando todos os clientes migrarem para RSA.

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| access_token | string | Token de acesso JWT |
| refresh_token | string | Token de atualização JWT |
| expires_in | int | Validade do token de acesso (segundos), padrão 7200 |
| user.id | string | ID do usuário criptografado com hashid |
| user.username | string | Nome de usuário |
| user.real_name | string | Nome real |

**Erros possíveis**:
- 422: falha na validação de parâmetros (campo obrigatório ausente, formato incorreto)
- 422: conclua primeiro a validação do captcha (captcha_key não passou em `/api/captcha/verify`)
- 401: nome de usuário ou senha incorretos
- 403: conta desabilitada
- 429: conta bloqueada, tente novamente em 15 minutos (acionado por 5 falhas consecutivas de login)

### 3.6 Registro

```
POST /api/auth/register
```

- **Autenticação**: não necessária
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: 5 requisições/minuto (por IP + caminho)

**Corpo da requisição**:
```json
{
  "username": "newuser",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "real_name": "新用户",
  "captcha_key": "abc123def456"
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| username | string | sim | min:3, max:50 | Nome de usuário (exclusivo) |
| password | string | sim | min:6, max:32 (texto puro) | Criptografado com AES-256-CBC-HMAC e codificado em Base64 |
| real_name | string | sim | max:50 | Nome real |
| captcha_key | string | sim | | Chave do captcha (é necessário passar pela validação de `/api/captcha/verify` primeiro) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

Após o registro bem-sucedido, o token JWT é retornado diretamente e o status do usuário fica habilitado por padrão (status=1).

### 3.7 Atualizar token

```
POST /api/auth/refresh
```

- **Autenticação**: não necessária
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: padrão global (60 requisições/minuto)

**Corpo da requisição**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| refresh_token | string | sim | refresh_token obtido no login/registro |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

Uma atualização bem-sucedida retorna um novo access_token e refresh_token, e o token antigo é invalidado automaticamente. A atualização também atualiza o horário do último login e o IP do usuário.

**Erros possíveis**:
- 422: token de atualização ausente
- 401: token de atualização inválido ou expirado

### 3.8 Métricas de monitoramento Prometheus

```
GET /metrics
```

- **Autenticação**: não necessária
- **Rate limit**: não há
- **Formato de resposta**: Prometheus text format (`text/plain; version=0.0.4`)

Endpoint público de métricas Prometheus para coleta por Grafana/Prometheus.

**Exemplo de resposta**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| Nome da métrica | Tipo | Descrição |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Total acumulado de requisições HTTP |
| `openadmin_active_users` | gauge | Número de usuários ativos atuais (login nas últimas 24 horas) |
| `openadmin_db_connection_status` | gauge | Status da conexão com o banco de dados, 1=normal, 0=anomalia |
| `openadmin_redis_connection_status` | gauge | Status da conexão Redis, 1=normal, 0=anomalia |
| `openadmin_memory_usage_bytes` | gauge | Uso de memória atual do processo PHP (bytes) |

## 4. Painel

Todos os endpoints do painel administrativo estão no grupo `/admin` e passam por três middlewares: `AdminAuth` (autenticação JWT), `AdminPermission` (verificação de permissões RBAC) e `OperationLog` (registro de operações).

### 4.1 Dados do painel

```
GET /admin/dashboard
```

- **Autenticação**: JWT + RBAC
- **Cache**: Redis por 5 minutos

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| Campo de stats | Tipo | Descrição |
|------|------|------|
| label | string | Nome da métrica |
| value | string | Valor da métrica (tipo string) |
| icon | string | Nome do ícone Material |
| color | string | Cor do card |
| trend | float? | Taxa de crescimento diário (porcentagem), presente apenas em "user total" |

| Campo de trends | Tipo | Descrição |
|------|------|------|
| dates | array{string} | Sequência de datas dos últimos 30 dias |
| series | array{object} | Dados das linhas de tendência, cada linha contém name (nome), data (array de valores), color (cor) |

## 5. Gestão de usuários

Todos os `id` retornados pelos endpoints de gestão de usuários são strings criptografadas com hashid. Os campos de senha são excluídos das respostas. Telefone e e-mail são mascarados nas respostas de listagem e retornados em texto puro na resposta de detalhes (os campos criptografados no banco são descriptografados automaticamente pela trait Encryptable).

### 5.1 Lista de usuários

```
GET /admin/user
```

- **Autenticação**: JWT + RBAC

**Parâmetros de consulta**:

| Parâmetro | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| page | int | não | 1 | Número da página |
| limit | int | não | 15 | Quantidade por página |
| keyword | string | não | | Palavra-chave de busca, corresponde ao nome de usuário e nome real |
| status | int | não | | Filtro de status, 0=desabilitado, 1=habilitado |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | ID do usuário criptografado com hashid |
| username | string | Nome de usuário |
| real_name | string | Nome real |
| phone | string | Telefone mascarado (formato `138****5678`) |
| email | string | E-mail mascarado (formato `a***@example.com`) |
| status | int | 1=habilitado, 0=desabilitado |
| last_login_at | string | Horário do último login (datetime) |
| created_at | string | Horário de criação (datetime) |

### 5.2 Criar usuário

```
POST /admin/user
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| username | string | sim | min:3, max:50 | Nome de usuário (exclusivo) |
| password | string | sim | min:6, max:32 | Senha (armazenada com bcrypt) |
| real_name | string | sim | max:50 | Nome real |
| phone | string | não | | Telefone (armazenamento criptografado com Encryptable) |
| email | string | não | | E-mail (armazenamento criptografado com Encryptable) |
| status | int | não | in:0,1 | Status, padrão 1 (habilitado) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**Erros possíveis**:
- 422: nome de usuário já existe
- 422: falha na validação de parâmetros (campo obrigatório ausente)

### 5.3 Detalhes do usuário

```
GET /admin/user/{id}
```

- **Autenticação**: JWT + RBAC
- **Parâmetro de caminho**: `{id}` é o ID do usuário criptografado com hashid

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

No endpoint de detalhes, `phone` e `email` são retornados em texto puro (armazenados criptografados no banco, descriptografados automaticamente pelo cast Encryptable), sem mascaramento. `password` e `id_card` nunca aparecem na resposta.

**Erros possíveis**:
- 404: usuário não existe

### 5.4 Atualizar usuário

```
PUT /admin/user/{id}
```

- **Autenticação**: JWT + RBAC
- **Parâmetro de caminho**: `{id}` é o ID do usuário criptografado com hashid

**Corpo da requisição**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| real_name | string | não | Nome real; se não for enviado, mantém o valor original |
| password | string | não | Nova senha; string vazia ou ausente significa não alterar |
| phone | string | não | Telefone |
| email | string | não | E-mail |
| status | int | não | 0=desabilitado, 1=habilitado |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**Erros possíveis**:
- 404: usuário não existe

### 5.5 Excluir usuário

```
DELETE /admin/user/{id}
```

- **Autenticação**: JWT + RBAC
- **Parâmetro de caminho**: `{id}` é o ID do usuário criptografado com hashid
- **Operação sensível**: requer confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "password": "admin_password"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| password | string | sim | Senha do usuário atualmente logado (confirmação secundária) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Executa soft delete (Eloquent SoftDeletes): os dados são marcados com `deleted_at` sem exclusão física.

**Erros possíveis**:
- 404: usuário não existe
- 422: operações sensíveis exigem confirmação de senha (password vazio)
- 422: falha na verificação da senha (senha incorreta)

### 5.6 Exclusão em massa de usuários

```
POST /admin/user/batch/destroy
```

- **Autenticação**: JWT + RBAC
- **Operação sensível**: requer confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| ids | array{string} | sim | Array de IDs de usuário criptografados com hashid |
| password | string | sim | Senha do usuário atualmente logado (confirmação secundária) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Executa soft delete; `data.count` é o número real de exclusões.

**Erros possíveis**:
- 422: selecione os usuários a excluir (ids vazio)
- 422: ID inválido (falha na decodificação do hashid)
- 422: falha na verificação da senha

### 5.7 Habilitação/desabilitação em massa de usuários

```
POST /admin/user/batch/status
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| ids | array{string} | sim | Array de IDs de usuário criptografados com hashid |
| status | int | sim | 0=desabilitado, 1=habilitado |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

O message muda dinamicamente conforme o valor de status: `"批量启用成功"` ou `"批量禁用成功"`.

**Erros possíveis**:
- 422: selecione usuários (ids vazio)
- 422: valor de status inválido (status não é 0 nem 1)

## 6. Gestão de papéis

### 6.1 Lista de papéis

```
GET /admin/role
```

- **Autenticação**: JWT + RBAC

**Parâmetros de consulta**:

| Parâmetro | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| page | int | não | 1 | Número da página |
| limit | int | não | 15 | Quantidade por página |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | ID do papel criptografado com hashid |
| name | string | Nome do papel |
| slug | string | Identificador do papel (exclusivo, usado na verificação de permissões) |
| description | string | Descrição do papel |
| status | int | 1=habilitado, 0=desabilitado |
| users_count | int | Número de usuários com este papel |

### 6.2 Criar papel

```
POST /admin/role
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| name | string | sim | max:50 | Nome do papel |
| slug | string | sim | max:50 | Identificador do papel |
| description | string | não | | Descrição do papel, padrão string vazia |
| status | int | não | | Status, padrão 1 |
| permission_ids | array{int} | não | | Array de IDs de permissão (IDs INT originais, não hashids) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 Atualizar papel

```
PUT /admin/role/{id}
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| name | string | não | Nome do papel |
| description | string | não | Descrição |
| status | int | não | 0=desabilitado, 1=habilitado |
| permission_ids | array{int} | não | Array de IDs de permissão; se enviado, sincroniza (substitui) as permissões do papel |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 Excluir papel

```
DELETE /admin/role/{id}
```

- **Autenticação**: JWT + RBAC
- **Operação sensível**: requer confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "password": "admin_password"
}
```

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Ao excluir, as associações entre o papel e todas as permissões e usuários são removidas automaticamente e, em seguida, o registro do papel é excluído fisicamente.

## 7. Gestão de permissões

As permissões usam uma estrutura em árvore (parent_id autorreferenciado), divididas em três tipos. O endpoint de listagem retorna a árvore de permissões completa.

### 7.1 Árvore de permissões

```
GET /admin/permission
```

- **Autenticação**: JWT + RBAC

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | Criptografado com hashid |
| parent_id | string | Hashid da permissão pai, `"0"` indica o nó raiz |
| name | string | Nome da permissão |
| slug | string | Identificador da permissão (identificador de rota/botão) |
| type | int | 1=menu, 2=botão, 3=API |
| icon | string | Ícone do menu (nome do ícone Material) |
| path | string | Caminho da rota do frontend |
| sort | int | Valor de ordenação (ascendente) |
| children | array? | Lista de permissões filhas (recursiva); campo ausente quando não há nós filhos |

### 7.2 Criar permissão

```
POST /admin/permission
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| parent_id | int | não | | ID da permissão pai (tipo INT original), padrão 0 |
| name | string | sim | max:50 | Nome da permissão |
| slug | string | sim | max:100 | Identificador da permissão |
| type | int | sim | in:1,2,3 | 1=menu, 2=botão, 3=API |
| icon | string | não | | Ícone do menu, padrão vazio |
| path | string | não | | Caminho da rota do frontend, padrão vazio |
| sort | int | não | | Valor de ordenação, padrão 0 |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 Atualizar permissão

```
PUT /admin/permission/{id}
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| name | string | não | Nome da permissão |
| icon | string | não | Ícone |
| path | string | não | Caminho da rota |
| sort | int | não | Valor de ordenação |

### 7.4 Excluir permissão

```
DELETE /admin/permission/{id}
```

- **Autenticação**: JWT + RBAC
- **Operação sensível**: requer confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "password": "admin_password"
}
```

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Ao excluir, todas as permissões filhas são excluídas em cascata (registros com `parent_id` igual ao ID da permissão atual) e as associações com todos os papéis são removidas.

## 8. Configuração do sistema

As configurações do sistema são únicas pela combinação de `group` + `key`.

### 8.1 Lista de configurações

```
GET /admin/config
```

- **Autenticação**: JWT + RBAC

**Parâmetros de consulta**:

| Parâmetro | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| page | int | não | 1 | Número da página |
| limit | int | não | 15 | Quantidade por página |
| group | string | não | | Filtrar por grupo de configuração |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | hashid |
| group | string | Grupo de configuração (por exemplo, `system`, `email`, `storage`) |
| key | string | Chave da configuração |
| value | string | Valor da configuração |
| type | string | Dica de tipo do valor (`string`, `integer`, `boolean`, `json`, etc.) |
| description | string | Descrição da configuração |

### 8.2 Criar configuração

```
POST /admin/config
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| group | string | sim | max:100 | Grupo de configuração |
| key | string | sim | max:100 | Chave da configuração (exclusiva dentro do mesmo grupo) |
| value | string | sim | | Valor da configuração |
| type | string | não | | Tipo do valor, padrão `string` |
| description | string | não | | Descrição da configuração, padrão vazio |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**Erros possíveis**:
- 422: o item de configuração já existe (mesmo group + key)

### 8.3 Atualizar configuração

```
PUT /admin/config/{id}
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| value | string | não | Atualizar o valor da configuração |
| type | string | não | Atualizar o tipo do valor |
| description | string | não | Atualizar o texto da descrição |

### 8.4 Excluir configuração

```
DELETE /admin/config/{id}
```

- **Autenticação**: JWT + RBAC
- **Operação sensível**: requer confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "password": "admin_password"
}
```

Exclui fisicamente o registro da configuração.

## 9. Logs de operação

Os logs de operação são um endpoint somente leitura, gravados automaticamente pelo middleware `OperationLog` em cada requisição POST/PUT/DELETE; os campos armazenados incluem `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Lista de logs de operação

```
GET /admin/log
```

- **Autenticação**: JWT + RBAC

**Parâmetros de consulta**:

| Parâmetro | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| page | int | não | 1 | Número da página |
| limit | int | não | 15 | Quantidade por página |
| user_id | int | não | | Filtro exato por ID de usuário (tipo INT original) |
| action | string | não | | Filtro exato por ação |
| path | string | não | | Filtro difuso por caminho de requisição |
| start_date | string | não | | Data de início (formato Y-m-d) |
| end_date | string | não | | Data de término (formato Y-m-d) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | hashid |
| user_name | string | Nome de usuário da operação (obtido via associação de user; mostra "系统" para operações sem login) |
| action | string | Descrição da ação |
| method | string | Método HTTP (POST/PUT/DELETE) |
| path | string | Caminho da requisição |
| ip | string | IP do cliente |
| source | string | Origem da requisição |
| input | string | String JSON dos parâmetros da requisição (sem arquivos) |
| created_at | string | Horário da operação (datetime) |

## 10. Central do usuário

Os endpoints da central do usuário exigem apenas autenticação JWT (sem verificação de permissões RBAC — o middleware `AdminPermission` deve colocá-los na whitelist).

### 10.1 Atualizar informações pessoais

```
PUT /admin/profile
```

- **Autenticação**: JWT

**Corpo da requisição**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| real_name | string | não | Nome real |
| phone | string | não | Telefone (armazenamento criptografado com Encryptable) |
| email | string | não | E-mail (armazenamento criptografado com Encryptable) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

Na resposta, `phone` e `email` são retornados em texto puro; `password` e `id_card` foram removidos.

### 10.2 Alterar senha

```
PUT /admin/profile/password
```

- **Autenticação**: JWT

**Corpo da requisição**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Campo | Tipo | Obrigatório | Regras de validação | Descrição |
|------|------|------|---------|------|
| old_password | string | sim | | Senha atual |
| new_password | string | sim | min:6, max:32 | Nova senha |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Erros possíveis**:
- 422: informe a senha antiga e a nova
- 422: senha antiga incorreta
- 422: a nova senha deve ter de 6 a 32 caracteres

### 10.3 Logout

```
POST /admin/profile/logout
```

- **Autenticação**: JWT

**Corpo da requisição**: nenhum (sem requestBody; o token é lido do cabeçalho Authorization)

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Lógica de logout: decodifica o JWT para obter a validade restante (exp - now), grava o hash md5 do token na blacklist do Redis `jwt_blacklist:{md5}` com TTL = validade restante. Tokens na blacklist são bloqueados no middleware `AdminAuth`, retornando 401.

Sem token, retorna 401. Token expirado/inválido (exceção na decodificação) ainda é tratado como logout bem-sucedido.

## 11. Importação e exportação

### 11.1 Exportar Excel

```
POST /admin/export/excel
```

- **Autenticação**: JWT + RBAC
- **Tipo de resposta**: download de arquivo (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Corpo da requisição**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| Campo | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| table | string | não | `admin_user` | Nome da tabela a exportar. Suporta: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | não | | Array de nomes de colunas a exportar; vazio exporta todas as colunas da tabela |
| conditions | object | não | `{}` | Condições de filtro, pares chave-valor; valores não vazios são usados no WHERE |
| title | string | não | `数据导出` | Título do Excel (exibido como nome da planilha) |

**Tabelas e colunas suportadas**:

| table | Colunas disponíveis |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Campos sensíveis `phone`, `email` e `id_card` são mascarados automaticamente na exportação. Limite de 10000 linhas de dados. O Excel congela a primeira linha e ativa o filtro automático.

### 11.2 Exportar PDF

```
POST /admin/export/pdf
```

- **Autenticação**: JWT + RBAC
- **Tipo de resposta**: download de arquivo (`application/pdf`, A4 paisagem)

**Corpo da requisição**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

Ou modo tabela:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| Campo | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| type | string | não | `table` | Tipo de exportação: `table` / `dashboard` |
| title | string | não | `数据导出` | Título do PDF |
| data | object | não | `{}` | Dados a exportar |

Com `type=dashboard`, `data` deve conter um array `stats` (renderizado em forma de cards); com `type=table`, `data` deve conter os arrays `columns` e `rows`.

O template do PDF inclui informações de copyright e o timestamp da exportação.

### 11.3 Importar usuários (Excel)

```
POST /admin/import/users
```

- **Autenticação**: JWT + RBAC
- **Tipo de requisição**: `multipart/form-data` (upload de arquivo)

**Campos do formulário**:

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| file | file | sim | Formato `.xlsx` ou `.xls` |

**Requisitos das colunas do Excel**:

| Nome da coluna | Obrigatório | Descrição |
|------|------|------|
| username | sim | Nome de usuário (exclusivo) |
| password | sim | Senha (armazenada com hash bcrypt) |
| real_name | sim | Nome real |
| phone | não | Telefone |
| email | não | E-mail |
| status | não | Status, padrão 1 |

A linha 1 é o cabeçalho das colunas (sem diferenciação de maiúsculas/minúsculas); os dados começam na linha 2.

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| total | int | Número total de linhas (sem a linha de cabeçalho) |
| success | int | Número de importações bem-sucedidas |
| failed | int | Número de falhas |
| errors | array | Detalhes das falhas; cada item contém row (número da linha no Excel) e reason (motivo da falha) |

## 12. Upload de arquivos

```
POST /admin/upload
```

- **Autenticação**: JWT + RBAC
- **Tipo de requisição**: `multipart/form-data`

**Campos do formulário**:

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| file | file | sim | Arquivo a enviar |

**Tipos de arquivo permitidos**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Tamanho máximo do arquivo**: 10MB

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Os arquivos são armazenados em diretórios por data em `public/upload/{Y-m-d}/`; o nome do arquivo é `md5(uniqid) + extensão original`. `url` é um caminho relativo à raiz do site.

**Erros possíveis**:
- 422: selecione um arquivo (não enviado)
- 422: tipo de arquivo não suportado
- 422: o tamanho do arquivo não pode exceder 10MB
- 500: falha no upload do arquivo (arquivo inválido)

## 13. Cabeçalhos de resposta

Todas as APIs (injetadas na camada de middleware global) incluem os seguintes cabeçalhos de resposta:

| Cabeçalho | Descrição |
|----|------|
| `X-RateLimit-Limit` | Limite máximo do rate limit (quantidade) |
| `X-RateLimit-Remaining` | Requisições restantes |
| `X-RateLimit-Reset` | Timestamp de reinício da janela do rate limit |
| `Retry-After` | Retornado apenas quando o rate limit é acionado; segundos sugeridos de espera |
| `X-Content-Type-Options` | `nosniff` (padrão do webman, proíbe MIME sniffing) |
| `X-Frame-Options` | `DENY` (fornecido pelo middleware CORS/configuração base do webman) |

Detalhes do rate limit:
- Limite global padrão: 60 requisições/minuto / IP+caminho
- Endpoint de login `/api/auth/login`: 10 requisições/minuto
- Endpoint de registro `/api/auth/register`: 5 requisições/minuto
- Usa algoritmo de janela deslizante atômica do Redis (Lua ZSET), evitando corrida TOCTOU
- Quando o Redis está indisponível, fail open (libera), sem bloquear requisições

## 14. Fluxo de autenticação

Sequência completa de autenticação:

```
1. O cliente solicita POST /api/captcha/generate
   (cabeçalho: API-Version: v1)
    ↓
   O servidor retorna: key + type(click|slider|rotate) + imagem base64 + extra(dados relacionados ao tipo)
   
2. O usuário interage e conclui a operação do captcha (clique/arraste/rotação), e o cliente coleta as respostas
   
3. O cliente solicita POST /api/captcha/verify
   (cabeçalho: API-Version: v1, Content-Type: application/json)
   corpo da requisição: { key, type, clicks }
   - type=click:  clicks = [{x, y}, ...]        // array de coordenadas
   - type=slider: clicks = 120                   // deslocamento no eixo X
   - type=rotate: clicks = 315                   // ângulo de rotação
    ↓
   Servidor:
   a. Lê os dados captcha:key do armazenamento (TTL 300s)
   b. Valida a resposta conforme o type (click: distância euclidiana ≤18px / slider: ±4px / rotate: ±5°)
   c. Validação aprovada → grava Redis `captcha_verified:{key}` = 1 (TTL 300s)
   d. Validação reprovada → retorna 422, contador +1, key invalidada após 3 tentativas
    ↓
   O servidor retorna: { valid: true/false }

4. O cliente solicita POST /api/auth/login
   (cabeçalho: API-Version: v1, Content-Type: application/json)
   corpo da requisição: { username, password(criptografada), captcha_key }
    ↓
   Servidor:
   a. Validação de parâmetros → 422
   b. Verifica se captcha_verified:{key} existe → 422
   c. Remove captcha_verified:{key} (uso único)
   d. Descriptografa a senha: EncryptionService::decrypt(password) → texto puro
   e. Valida as credenciais do usuário (password_verify) → 401
   f. Verifica o status da conta → 403/429
   g. Emite JWT (access + refresh) → 200
   h. Atualiza last_login_at / last_login_ip
    ↓
   O cliente salva: access_token, refresh_token, expires_in

5. Requisições subsequentes carregam o JWT
   cabeçalho: Authorization: Bearer <access_token>
    ↓
   Middleware AdminAuth:
   a. Extrai o Bearer token
   b. Verifica a blacklist (Redis jwt_blacklist:{md5}) → 401
   c. Decodifica o JWT, valida a expiração → 401
   d. Define $request->adminId = campo sub
    ↓
   Middleware AdminPermission:
   a. Resolve o identificador de permissão para a rota do recurso
   b. Consulta os papéis do usuário → permissões dos papéis e faz a correspondência
   c. Sem permissão → 403
    ↓
   O Controller processa a requisição
    ↓
   Response + cabeçalhos X-RateLimit-*

6. Renovação antes da expiração do Access Token
   O cliente solicita POST /api/auth/refresh
   corpo da requisição: { refresh_token: "..." }
    ↓
   O servidor decodifica o refresh_token → emite novo access + refresh
    ↓
   O cliente atualiza os tokens locais

7. Logout
   O cliente solicita POST /admin/profile/logout
   cabeçalho: Authorization: Bearer <access_token>
    ↓
   Servidor:
   a. Decodifica o JWT para obter o TTL restante
   b. Grava na blacklist do Redis: jwt_blacklist:{md5(token)} = 1, TTL = validade restante
   c. Retorna sucesso
```

### Estrutura do JWT

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, TTL padrão de 7200 segundos (controlado pela configuração JWT `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, TTL padrão de 1209600 segundos (controlado pela configuração JWT `refresh_expire`, ou seja, 14 dias)

### Gerenciamento de segurança

- Senhas armazenadas com hash `PASSWORD_BCRYPT`
- Criptografia AES-256-CBC-HMAC na camada de transmissão de senhas (criptografia no cliente → descriptografia no servidor), compatível com fallback para texto puro
- Campos sensíveis (phone, email, id_card) usam `erikwang2013/encryptable` para criptografia/descriptografia transparente na camada de banco
- IDs na camada de API usam `erikwang2013/hashids` para transmissão criptografada, evitando expor a sequência original de IDs snowflake
- O SecurityFilter faz varredura global de XSS, injeção SQL, path traversal e injeção de comandos; mesmo IP com 5 detecções/60s entra na blacklist temporária por 15 minutos
- Operações sensíveis (excluir usuário, papel, permissão, configuração) exigem confirmação secundária da senha do usuário atualmente logado
- Limite de sessões concorrentes: no máximo 3 tokens válidos por usuário; ao fazer login em um 4º dispositivo, o token mais antigo é adicionado à blacklist à força
- Bloqueio de conta: 5 falhas consecutivas de login acionam bloqueio de 15 minutos; durante o bloqueio, retorna 429

### Arquitetura de middlewares

Os middlewares globais atuam em todas as requisições, em ordem:

```
Cors (pré-processamento de cross-origin + cabeçalhos de resposta)
  → Locale (detecção de idioma via Accept-Language / ?lang=zh_CN|en)
  → SecurityFilter (restrição de método HTTP/tamanho do corpo/validação de Content-Type/XSS/Injeção SQL/Path traversal/Injeção de comandos/bloqueio de ataques CSRF)
  → RateLimit (rate limit com janela deslizante do Redis + bloqueio de conta: 5 falhas de login bloqueiam por 15 minutos)
  → ApiVersion (validação de versão da API, grupo de rotas /api)
  → AdminAuth (autenticação JWT + blacklist, grupo de rotas /admin)
  → AdminPermission (autorização RBAC / cache Redis de 60s, grupo de rotas /admin)
  → OperationLog (registro automático de POST/PUT/DELETE, inclui detecção de origem, grupo de rotas /admin)
```

`/health` e `/api/docs` são endpoints públicos e passam apenas por `Cors → SecurityFilter → RateLimit`.

Reforços de segurança:
- **Bloqueio de conta**: 5 falhas consecutivas de login bloqueiam a conta automaticamente por 15 minutos; durante o bloqueio, o login retorna 429
- **Limite de sessões concorrentes**: no máximo 3 tokens válidos por usuário; o token mais antigo é adicionado à blacklist automaticamente quando o limite é excedido
- **security.txt**: `GET /.well-known/security.txt` fornece informações de contato de segurança no padrão RFC 9116
- **Configuração de segurança do Nginx**: consulte `docs/nginx-security.conf` para um exemplo completo de reforço de segurança do proxy reverso

### Detecção de origem das operações

O middleware OperationLog identifica automaticamente a plataforma do cliente e grava no campo `source` do log de operação:

| Plataforma | Método de detecção |
|------|---------|
| `ipados` | UA contém iPad |
| `macos` | UA contém Macintosh/Mac OS |
| `windows` | UA contém Windows |
| `linux` | UA contém Linux (não Android) |
| `ios` | UA contém iPhone / iOS / CFNetwork |
| `android` | UA contém Android |
| `harmonyos` | UA contém HarmonyOS / OpenHarmony ou cabeçalho `X-Client-Platform` declarado explicitamente |
| `web` | padrão (nenhuma das plataformas acima) |

> Detecção em dois níveis: cabeçalho `X-Client-Platform` (declarado por apps nativos) → inferência automática pelo User-Agent (fallback). O campo `source` da consulta de logs de operação `GET /admin/log` indica a origem.

## 15. Deploy e operações

### Docker Compose

O diretório raiz do projeto fornece `docker-compose.yml`, que orquestra 5 serviços (Nginx, app webman, MySQL, Redis, Elasticsearch). O PHP é construído via `Dockerfile` (baseado em `php:8.3-cli`, com OPcache habilitado).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` define o pipeline de integração contínua com GitHub Actions:
- verificação de sintaxe `php -l`
- testes unitários PHPUnit
- análise estática `flutter analyze`

### Backup do banco de dados

O diretório `database/backup/` fornece scripts de backup e restauração:
- `backup.sh` — backup compactado com mysqldump + gzip, limpeza automática de backups anteriores a 30 dias
- `restore.sh` — restauração interativa, lista os backups existentes para o usuário escolher

### Configuração de segurança do Nginx

Para ambientes de produção, consulte `docs/nginx-security.conf` para configurar o reforço de segurança do proxy reverso.

## 16. Interface gRPC interna (e-cat → PHP worker)

Somente para chamada pelo gateway e-cat. No lado PHP é um serviço workerman h2c (`tcp://0.0.0.0:8792`, processo `internal_grpc`). O contrato é definido em `infrastructure/tracking-gateway/proto/internal.proto` (package `internal.v1`), as mensagens são codificadas em protobuf e os metadata devem conter `x-internal-token` (valor de `config/logistics.php` — `internal_token`, substituído em produção pela variável de ambiente `INTERNAL_TOKEN`). Token ausente/incorreto retorna `code=401 UNAUTHORIZED`.

### 16.1 Visão geral dos RPC

| RPC | Requisição | Resposta | Descrição |
|---|---|---|---|
| `Detect` | `{tracking_no}` | `{code, carrier_code, channel, confidence}` | Detecção de transportadora; não reconhecida → `404 CARRIER_NOT_DETECTED` |
| `Query` | `{carrier_code, tracking_no, credential_id?}` | `{code, query_no, carrier_code, tracking_no, status, delivered_at, estimated_delivery_at, latest_description, raw_status, events[]}` | Consulta de rastreio; `credential_id` opcional (cenário multi-credenciais, deve corresponder a `carrier_code`, senão `400 INVALID_PARAMS`). Cada consulta é gravada em `logistics_tracking_query` |
| `Carriers` | `{}` | `{code, carriers[]}` | Todas as transportadoras do registro `{carrier_code, channel}` |

### 16.2 Contrato de erros (campo `code` na resposta, status gRPC sempre 0)

| `code` | `error_code` | Cenário |
|---|---|---|
| 400 | `INVALID_PARAMS` | Parâmetros ausentes / credential_id não corresponde |
| 401 | `UNAUTHORIZED` | Token interno incorreto |
| 404 | `CARRIER_NOT_FOUND` / `TRACKING_NOT_FOUND` / `CARRIER_NOT_DETECTED` | Transportadora não registrada / rastreio não encontrado / não detectada |
| 5001 | `CARRIER_AUTH_ERROR` / `CARRIER_NETWORK_ERROR` / `CARRIER_ERROR` | Erro de credenciais da transportadora / erro de rede / erro de negócio (inclui mensagem estruturada do provedor, ex. `[SF-INTERNATIONAL A1001] parâmetros obrigatórios não podem estar vazios`) |
| 500 | `INTERNAL_ERROR` | Exceção interna |


## 17. API externa do gateway de consulta e-cat

Gateway de alta frequência em Rust (`infrastructure/tracking-gateway`), escuta em `0.0.0.0:8080` (padrão), requisições carregam o cabeçalho `X-API-Key` (config `api_keys`). O gateway cuida da autenticação API-Key, cache Redis (prefixo `logistics:`), rate limiting, circuit breaker por transportadora e encaminhamento round-robin aos workers; credenciais nunca descem até o gateway.

| 端点 | 说明 |
|---|---|
| `GET /v1/health` | Verificação de saúde (ponto público, sem chave) |
| `GET /v1/openapi.json` | Documentação OpenAPI 3.0 (ponto público, sem chave) |
| `GET /v1/carriers` | Lista de transportadoras (encaminha ao gRPC PHP `InternalService.Carriers`) |
| `POST /v1/tracking/query` | Consulta de rastreio (`{"carrier_code": "...", "tracking_no": "..."}`); sem `carrier_code` o gateway detecta automaticamente (detect é feito dentro de query) |
| `GET /v1/tracking/{query_no}` | Último resultado de rastreio por número de consulta |
| `POST /v1/subscriptions` | Registrar assinatura de callback (`{"carrier_code", "callback_url", "event_type"?}`, `event_type` padrão `tracking.update`) |


Semântica adicional do gateway: exceder `rate_limit` (padrão 100/60s) retorna `429`; falhas consecutivas da transportadora disparam o circuit breaker (padrão OPEN 60s após 5 erros 5xx/transporte) → `503`; worker inacessível → `502`. Gateway↔worker é gRPC interno (h2c, `0.0.0.0:8792`), autenticado pelo segredo compartilhado `x-internal-token`, ver seção 16.

### 17.1 Envelope de resposta e erros

Todos os pontos retornam o envelope unificado `{"code": 0, "message": "ok", "data": ...}`; `code != 0` é erro e `data` é omitido. O corpo de um erro de negócio pode conter `error_code` / `error_message` (diagnóstico da transportadora, repassado pelo gateway); `code` corresponde ao status HTTP:

| HTTP | Significado |
|---|---|
| 400 | Erro de parâmetros (`tracking_no` ausente / parâmetros de assinatura inválidos) |
| 401 | Chave ausente ou inválida |
| 404 | Número de consulta não existe |
| 429 | Rate limit (padrão 100/60s por chave), tentar novamente com backoff |
| 502 | Worker inacessível ou falha do provedor |
| 503 | Circuit breaker da transportadora, tentar novamente com backoff |

### 17.2 Exemplos de chamada (curl)

Chave demo `demo-api-key`, gateway padrão `http://127.0.0.1:8080`:

```bash
# Verificação de saúde (ponto público, sem chave)
curl http://127.0.0.1:8080/v1/health

# Documentação OpenAPI (ponto público, sem chave)
curl http://127.0.0.1:8080/v1/openapi.json

# Lista de transportadoras
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/carriers

# Consulta de rastreio (transportadora opcional)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "tracking_no": "LX123456789CN"}' \
  http://127.0.0.1:8080/v1/tracking/query

# Último resultado por número de consulta
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/tracking/{query_no}

# Registrar assinatura (o endereço de callback deve ser http/https público; o gateway recusa loopback/redes privadas)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "callback_url": "https://example.com/cb", "event_type": "tracking.update"}' \
  http://127.0.0.1:8080/v1/subscriptions
```

### 17.3 Exemplos de chamada (SDK)

Cinco SDKs sem dependências no diretório `sdk/` do repositório (Python / PHP / Node.js / Go / Rust, copiar e usar):

```python
from tracking_client import TrackingClient
client = TrackingClient("demo-api-key", "http://127.0.0.1:8080")
result = client.query_tracking("LX123456789CN")
print(result["status"], result["events"][0]["description"])
```

```php
require "TrackingClient.php";
$client = new TrackingClient("demo-api-key", "http://127.0.0.1:8080");
$carriers = $client->list_carriers();
$sub = $client->subscribe("china-post", "https://example.com/cb");
```

```js
const { TrackingClient } = require("./tracking-client.js");
const client = new TrackingClient("demo-api-key", "http://127.0.0.1:8080");
const detail = await client.getTracking("query_no");
console.log(detail.status);
```

```go
import "trackingclient"
client := trackingclient.NewClient("demo-api-key", "http://127.0.0.1:8080")
raw, err := client.QueryTracking("LX123456789CN", "china-post")
if err != nil { panic(err) }
fmt.Println(string(raw))
```

```rust
use trackingclient::tracking_client::TrackingClient;
let client = TrackingClient::new("demo-api-key", "http://127.0.0.1:8080")?;
let detail = client.get_tracking("query_no")?;
println!("{}", detail);
```

Os SDKs lançam exceção (`TrackingApiError`) quando `code != 0` no envelope ou em falha de rede, com `code` / `message` / `error_code` / `error_message` / `http_status`; em rate limit e circuit breaker tente novamente com backoff, erros do lado da transportadora não devem ser repetidos. Uso completo em `sdk/README.md`.
