> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](DESIGN.md) | [English](DESIGN.en.md) | [한국어](DESIGN.ko.md) | [Русский](DESIGN.ru.md) | [Deutsch](DESIGN.de.md) | [Français](DESIGN.fr.md) | [Español](DESIGN.es.md) | [Português](DESIGN.pt.md) | [हिन्दी](DESIGN.hi.md) | [العربية](DESIGN.ar.md) | [বাংলা](DESIGN.bn.md) | [Bahasa Indonesia](DESIGN.id.md) | [日本語](DESIGN.ja.md)

# Painel de Administração Open Source — Documento de design

> Para os diagramas Mermaid detalhados, consulte [ARCHITECTURE.pt.md](ARCHITECTURE.pt.md) (renderizados automaticamente no GitHub/GitLab/VS Code).

## 1. Arquitetura do sistema

> **Lista de funcionalidades**: autenticação(login/register/refresh/logout + bloqueio de conta + limite de sessões) | painel(cache Redis) | usuários CRUD+em massa+importação | papéis e permissões(RBAC) | configuração do sistema | auditoria de operações(origem de 8 plataformas) | arquivos(upload+exportação+mascaramento) | segurança(defesa em 18 camadas) | operações(health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Arquitetura do backend

### 2.1 Design em camadas

| Camada | Diretório | Responsabilidade |
|---|------|------|
| Rotas | `config/route.php` | Mapeamento de URL para controllers, vínculo de middlewares, rotas versionadas |
| Middlewares | `app/middleware/` | Bloqueio de ataques (SecurityFilter), rate limit (RateLimit), autenticação (JWT), autorização (RBAC), versão da API (ApiVersion) |
| Controllers | 14: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs (painel) + Captcha/Auth (API v1) | Validação de parâmetros da requisição, chamada de lógica de negócio, formatação de resposta |
| Serviços de negócio | `app/service/` | Lógica de negócio reutilizável (reservado) |
| Modelos de dados | `app/model/` | Mapeamento ORM, relacionamentos, criptografia/descriptografia de campos |
| Utilitários comuns | `app/common/` | Serviços Hashids, Snowflake, Encryption |

### 2.2 Ciclo de vida da requisição

```
Requisição do cliente
  │
  ▼
Servidor HTTP webman (workerman)
  │
  ▼
Correspondência de rotas
  │
  ▼
Cadeia de middlewares:
  Locale ──────────────► Detecção de idioma via Accept-Language / ?lang=
  │
  ▼
  SecurityFilter ──────► Verificação de método HTTP → 405 (apenas GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     Bloqueio de ataques XSS/Injeção SQL/Path traversal/Injeção de comandos/CSRF (403)
  ▼
  RateLimit ───────────► Rate limit com janela deslizante do Redis
  │ (falha retorna 429 + cabeçalho Retry-After)
  ▼
  ApiVersion ─────────► Validação do cabeçalho API-Version, injeta $request->apiVersion
  │ (falha retorna 400)
  ▼
  AdminAuth ──────────► Validação JWT, injeta $request->adminId
  │ (falha retorna 401)
  ▼
  AdminPermission ────► Verificação de permissões RBAC (cache Redis 60s)
  │ (falha retorna 403)
  ▼
  OperationLog ───────► Registro de log de operação (POST/PUT/DELETE), detecção automática de origem
  │
  ▼
Controller::method()
  │
  ├─► Validação de parâmetros (validator)
  ├─► Confirmação de operação sensível (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Operações com Model (criptografia/descriptografia automática via encryptable)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 Ciclo de vida do ID

```
Geração (Snowflake) → Armazenamento (MySQL BIGINT) → Transmissão (codificação Hashids) → Externo (string hash)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Sistema de criptografia de dados

```
Camada de transmissão (encryption)     — AES-256-CBC, chave independente
Camada de armazenamento (encryptable)  — AES-128-ECB, chave independente, processado automaticamente pelos Model $casts
Camada de exibição (mask)              — telefone: 138****1234, e-mail: a***@example.com
```

## 3. Design do banco de dados

### 3.1 Relações ER

```
logistics_admin_user ──┬── logistics_admin_user_role ──┬── logistics_admin_role
  (usuários)       │    (associação usuário-papel) │     (papéis)
                  │                          │
                  │                    logistics_admin_role_permission
                  │                     (associação papel-permissão)
                  │                          │
                  │                          ▼
                  │                    logistics_admin_permission
                  │                      (permissões/menus)
                  │
                  ▼
           logistics_operation_log
             (logs de operação)

logistics_system_config (configuração do sistema) — tabela independente
```

### 3.2 Estrutura das tabelas principais

| Nome da tabela | Nº de campos | Descrição |
|------|-------|------|
| `logistics_admin_user` | 14 | Usuários administrativos, phone/email/id_card armazenados criptografados, suporta soft delete |
| `logistics_admin_role` | 7 | Papéis, slug exclusivo |
| `logistics_admin_permission` | 10 | Árvore de permissões (parent_id autorreferenciado), type: 1=menu 2=botão 3=API |
| `logistics_admin_user_role` | 2 | Tabela intermediária muitos-para-muitos usuário-papel |
| `logistics_admin_role_permission` | 2 | Tabela intermediária muitos-para-muitos papel-permissão |
| `logistics_system_config` | 8 | Configuração de pares chave-valor, group+key exclusivos em conjunto |
| `logistics_operation_log` | 9 | Logs de auditoria de operações (inclui source de origem) |

### 3.3 Convenções de chave primária

- Tipo: `BIGINT UNSIGNED NOT NULL`
- Característica: **não autoincrementável**, gerada pelo algoritmo Snowflake na camada de aplicação
- Vantagens: globalmente exclusiva, amigável a ambientes distribuídos, incremento de tendência favorável a índices, não expõe o volume de negócios
- Configuração: datacenter_id(0-31) + worker_id(0-31), suporta 1024 nós concorrentes

## 4. Design da API

### 4.1 Convenções de URL

```
Endpoints públicos:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

Painel:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

Rotas de recursos:
  GET    /admin/user          → lista
  POST   /admin/user          → criar
  GET    /admin/user/{hashid} → detalhes
  PUT    /admin/user/{hashid} → atualizar
  DELETE /admin/user/{hashid} → excluir (exige confirmação de senha)

Configuração do sistema:  /admin/config[/{hashid}]
Logs de operação:  /admin/log
Central do usuário:  /admin/profile[/password|/logout]
Importação:     /admin/import/users
Upload:     /admin/upload
Em massa:     /admin/user/batch/{destroy|status}
Documentação:     /api/docs     (OpenAPI 3.0)
Saúde:     /health
```

### 4.2 Estratégia de versão da API

A versão da API é controlada pelo cabeçalho da requisição e **não aparece no caminho da URL**:

```http
API-Version: v1
```

| Mecanismo | Descrição |
|------|------|
| Versão padrão | Sem o cabeçalho `API-Version`, o padrão é `v1` |
| Validação | O middleware `ApiVersion` valida; versões não suportadas retornam 400 |
| Rotas | A função auxiliar `v()` resolve dinamicamente a classe do controller conforme a versão |
| Diretório | Controllers organizados por versão: `app/api/{version}/controller/` |

Exemplo de extensão — adicionar a API v2:
1. Crie `app/api/v2/controller/AuthController.php`
2. Adicione `'v2'` à constante `SUPPORTED` do middleware `ApiVersion`
3. As definições de rotas não precisam ser alteradas

```bash
# Usar v1
curl -H "API-Version: v1" /api/auth/login

# Usar v2
curl -H "API-Version: v2" /api/auth/login

# Sem o cabeçalho, padrão v1
curl /api/auth/login
```

### 4.3 Estratégia de rate limit

Baseada no algoritmo de janela deslizante com Redis Sorted Set, executada com script Lua atômico:

| Endpoint | Limite |
|------|------|
| Padrão | 60 requisições/minuto/IP/rota |
| POST /api/auth/login | 10 requisições/minuto |
| POST /api/auth/register | 5 requisições/minuto |

Ao exceder o limite, retorna 429; os cabeçalhos de resposta incluem X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 Resposta unificada

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Significado | Cenário de acionamento |
|------|------|---------|
| 0 | Sucesso | Resposta normal |
| 400 | Erro de parâmetro | Formato da requisição incorreto |
| 401 | Não autenticado | Token ausente/expirado/inválido |
| 403 | Sem permissão | Os papéis do usuário não contêm a permissão necessária |
| 404 | Não encontrado | Recurso não localizado |
| 422 | Falha de validação | Parâmetros do formulário fora das regras / falha na confirmação de senha |
| 500 | Erro do servidor | Exceção inesperada |

### 4.5 Fluxo de autenticação (com captcha de clique)

```
Cliente                               Servidor
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② o usuário clica nas posições    │
  │     dos textos na imagem           │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 Modelo de permissões (RBAC)

```
  Usuário ──┬── Papel ──┬── Permissão
  User     Role      Permission
                 │
                 ├── type=1: menu (controla a visibilidade da barra lateral)
                 ├── type=2: botão (controla as operações na página)
                 └── type=3: API  (controla o acesso aos endpoints)

  Formato do identificador de permissão: {method}.{path}
  Ex.: get.admin/user  post.admin/user  delete.admin/user
  Identificador do super administrador: * (pula todas as verificações de permissão)
```

### 4.7 Confirmação secundária de operações sensíveis

Operações sensíveis como exclusão de usuários, papéis e permissões exigem que a senha do usuário atual seja enviada no corpo da requisição para reverificação de identidade:

```
Cliente                            Servidor
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → senha incorreta retorna 422
  │                                │ → senha correta continua a execução
  │◄── 200 { code: 0 }           │
```

O frontend exibe um diálogo de confirmação antes de disparar a operação de exclusão e envia a requisição após coletar a senha do usuário.

## 5. Design do frontend

### 5.1 Painel administrativo Flutter Web

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

Recursos: barra lateral recolhível, tema duplo Material 3, tabela de dados de alta densidade, diálogos pop-up, interação por hover do mouse

### 5.2 Mobile HarmonyOS

Roteamento de páginas:

| Página | Rota | Descrição |
|------|------|------|
| LoginPage | `pages/LoginPage` | Nome de usuário e senha + login com captcha de clique |
| DashboardPage | `pages/DashboardPage` | Cards de estatísticas + operações recentes |
| UserListPage | `pages/UserListPage` | Lista de usuários, busca + pull-to-refresh + carregar ao rolar |
| UserDetailPage | `pages/UserDetailPage` | Novo/editar/visualizar/excluir (confirmação com AlertDialog) |
| ProfilePage | `pages/ProfilePage` | Central do usuário, logout (confirmação com AlertDialog) |

Fluxo de dados: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Design de segurança

### 6.1 Defesa em profundidade

| Camada | Medida |
|------|------|
| Restrição de métodos | Whitelist de métodos HTTP do SecurityFilter, apenas GET/POST/PUT/DELETE/OPTIONS/HEAD; métodos não padronizados retornam 405 |
| Bloqueio de ataques | Middleware SecurityFilter, detecção e bloqueio de XSS/Injeção SQL/Path traversal/Injeção de comandos/CSRF |
| Verificação humano-máquina | Captcha de clique (Click Captcha), validação obrigatória no login/registro |
| Bloqueio de conta | 5 falhas consecutivas de login bloqueiam a conta por 15 minutos; durante o bloqueio retorna 429 |
| Limite de sessões | Máximo de 3 tokens concorrentes por usuário; o token mais antigo vai para a blacklist automaticamente quando o limite é excedido |
| Rate limit | Middleware RateLimit, janela deslizante Redis, atômico com Lua |
| CSP | Cabeçalho Content-Security-Policy restringe a origem dos recursos, evita XSS e injeção de dados |
| Confirmação de operação | Operações sensíveis como exclusão exigem confirmação secundária da senha do usuário atual |
| Transmissão | HTTPS + JWT Bearer Token |
| ID da interface | Criptografia Hashids, sem possibilidade de reverter para o ID real externamente |
| Corpo da requisição | Criptografia de campos sensíveis com AES-256-CBC |
| Banco de dados | Chave primária BIGINT (não expõe o valor de autoincremento) |
| Banco de dados | Criptografia de campos sensíveis com AES-128-ECB |
| Autenticação | JWT HS256, expiração de 2h + refresh token |
| Autorização | RBAC, controle de permissões com granularidade method.path |
| Auditoria | OperationLog registra todas as operações (inclui detecção automática da origem `source`) |

### 6.2 Gerenciamento de chaves

```
JWT_SECRET          → injetado por variável de ambiente, string aleatória de 64 caracteres
HASHIDS_SALT        → sal exclusivo; em caso de vazamento, é necessário trocar globalmente
ENCRYPTION_KEY      → chave de criptografia da transmissão da API, 32 bytes
ENCRYPTABLE_KEY     → chave de criptografia do armazenamento do banco, independente da chave de transmissão
SCOUT_HOSTS         → endereço do ES, implantado na rede interna
```

### 6.3 Proteção de dados sensíveis

| Cenário | Campo | Medida |
|------|------|------|
| Exibição em lista | phone | Mascaramento: 138****1234 |
| Exibição em lista | email | Mascaramento: a***@example.com |
| Visualização de detalhes | phone/email | Exige endpoint com descriptografia |
| Exportação Excel | phone/email | Exportação com mascaramento |
| Exportação PDF | todos os campos | Mascaramento + marca d'água de copyright não removível |
| Armazenamento | phone/email/id_card | encryptable criptografa em texto cifrado |

## 7. Design de exportação

### 7.1 Exportação Excel

```
Requisição: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() consulta os dados (limit 10000)
  → mascaramento de campos sensíveis
  → PhpSpreadsheet constrói (cabeçalho azul com texto branco + congela a primeira linha + filtro automático)
  → grava em runtime/tmp/ → resposta de download
```

### 7.2 Exportação PDF

```
Requisição: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + CSS inline + copyright no cabeçalho + copyright não removível no rodapé
  → Dompdf renderiza A4 paisagem
  → grava em runtime/tmp/ → resposta de download
```

## 8. Arquitetura de deploy

### 8.1 Topologia recomendada

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                     arquivos estáticos: build/ do Flutter Web
```

### 8.2 Docker Compose (recomendado para produção)

O `docker-compose.yml` na raiz do projeto orquestra todos os serviços da topologia acima:

| Serviço | Imagem/build | Porta | Descrição |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Proxy reverso + arquivos estáticos + Gzip |
| `app` | build local com `Dockerfile` | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Banco de dados principal, persistência com volume |
| `redis` | redis:7-alpine | 6379 | Cache / rate limit / captcha |
| `elasticsearch` | elasticsearch:8.x | 9200 | Busca de texto completo |

Antes de iniciar, substitua chaves como `JWT_SECRET`, `HASHIDS_SALT` e `ENCRYPTION_KEY` no `docker-compose.yml` por strings aleatórias.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

A integração contínua com GitHub Actions é definida em `.github/workflows/ci.yml`:
- Verificação de sintaxe PHP (`php -l`)
- Testes unitários PHPUnit
- Análise estática Flutter (`flutter analyze`)

### 8.4 Backup do banco de dados

`database/backup/backup.sh` — backup com mysqldump + gzip, limpeza automática de backups anteriores a 30 dias.
`database/backup/restore.sh` — seleção interativa e restauração de backups.

### 8.5 Monitoramento

O endpoint `GET /metrics` (`MetricsController`) expõe 5 métricas gauge em Prometheus text format: total de requisições HTTP, usuários ativos, status de conexão do banco/Redis e uso de memória.

### 8.6 Requisitos de ambiente

| Componente | Versão mínima | Configuração recomendada |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ com OPcache habilitado |
| MySQL | 8.0+ | 8.0+ com replicação mestre-escravo |
| Elasticsearch | 7.x | 8.x com cluster de 3 nós |
| Redis | 6.x | 7.x em modo sentinela |
| Nginx | 1.20+ | Proxy reverso + gzip + SSL |
| Flutter SDK | 3.41+ | Última versão estável |
| HarmonyOS | API 12 | DevEco Studio 5.x |
