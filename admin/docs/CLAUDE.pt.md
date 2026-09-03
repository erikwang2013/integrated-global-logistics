> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](CLAUDE.md) | [English](CLAUDE.en.md) | [한국어](CLAUDE.ko.md) | [Русский](CLAUDE.ru.md) | [Deutsch](CLAUDE.de.md) | [Français](CLAUDE.fr.md) | [Español](CLAUDE.es.md) | [Português](CLAUDE.pt.md) | [हिन्दी](CLAUDE.hi.md) | [العربية](CLAUDE.ar.md) | [বাংলা](CLAUDE.bn.md) | [Bahasa Indonesia](CLAUDE.id.md) | [日本語](CLAUDE.ja.md)

# Painel de Administração Open Source (open-admin)

Sistema de administração full-stack baseado em webman v2 + Flutter.

## Declaração de copyright

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **Imutável, inamovível, irreversível.** Todos os arquivos novos devem incluir a declaração de copyright acima como comentário no cabeçalho.

## Lista de funcionalidades

| Domínio | Funcionalidade |
|----|------|
| Autenticação | Login/registro/atualização de token/logout + captcha + bloqueio de conta + limite de sessões |
| Painel | Estatísticas em tempo real/tendências/distribuição/logs (cache Redis de 5m) |
| Usuários | CRUD + exclusão em massa/habilitação e desabilitação + importação Excel |
| Papéis e permissões | CRUD + árvore de permissões + autorização RBAC method.path |
| Configuração do sistema | CRUD de pares chave-valor |
| Auditoria de operações | Consulta de logs + detecção automática de origem de 8 plataformas |
| Arquivos | Upload + exportação Excel/PDF (mascaramento de dados sensíveis) |
| Segurança | Defesa em profundidade com 18 camadas (XSS/Injeção SQL/CSRF/Rate limit/CSP...) |
| Operações | Health check/métricas Prometheus/documentação da API/security.txt + Docker + CI/CD |

## Stack tecnológica

### Backend
- PHP 8.3+, webman v2 (workerman/webman)
- Banco de dados: MySQL 8.0+, prefixo de tabela `logistics_`
- Chave primária: BIGINT não autoincrementável, gerada por `erikwang2013/snowflake-php`
- Criptografia/descriptografia de IDs na camada de API: `erikwang2013/hashids`
- Autenticação JWT: `erikwang2013/jwt-webman`
- Criptografia/descriptografia de dados sensíveis na API: `erikwang2013/encryption`
- Criptografia/descriptografia de campos sensíveis no banco: `erikwang2013/encryptable`
- Sincronização e consulta ES: `erikwang2013/webman-scout`
- Bandeiras de países: `erikwang2013/season`

### Frontend
- Flutter 3.x, diretório de código-fonte `apps/flutter/`
- Versão Web com design de painel administrativo para PC (não estilo de app mobile)
- Suporta cliente e painel administrativo
- HarmonyOS ArkTS, diretório de código-fonte `apps/harmonyos/`

## Estrutura do projeto

```
open-admin/
├── app/
│   ├── admin/controller/       # Controladores do painel administrativo (14)
│   │   ├── BaseController.php      # Controlador base
│   │   ├── DashboardController.php # Painel (cache Redis)
│   │   ├── UserController.php      # CRUD de usuários + operações em massa
│   │   ├── RoleController.php      # CRUD de papéis
│   │   ├── PermissionController.php# CRUD de permissões
│   │   ├── ConfigController.php    # CRUD de configurações do sistema
│   │   ├── LogController.php       # Consulta de logs de operação
│   │   ├── ProfileController.php   # Central do usuário + logout
│   │   ├── ExportController.php    # Exportação Excel/PDF
│   │   ├── ImportController.php    # Importação de usuários via Excel
│   │   ├── UploadController.php    # Upload de arquivos
│   │   ├── HealthController.php    # Health check
│   │   ├── DocsController.php      # Documentação OpenAPI
│   │   └── MetricsController.php   # Métricas Prometheus
│   ├── api/v1/controller/      # Controladores da API v1 (controle por cabeçalho de versão)
│   │   ├── CaptchaController.php
│   │   └── AuthController.php
│   ├── common/                 # Classes utilitárias comuns
│   │   ├── HashidsService.php
│   │   ├── SnowflakeService.php
│   │   └── EncryptionService.php
│   ├── common/                 # Definições comuns (inclui Apidoc Definitions)
│   ├── middleware/             # Middlewares (8)
│   │   ├── Cors.php            # Cross-origin (global)
│   │   ├── SecurityFilter.php  # Bloqueio de ataques (global: XSS/Injeção SQL/Path traversal/Injeção de comandos/CSRF)
│   │   ├── RateLimit.php       # Rate limit Redis (global, atômico com Lua)
│   │   ├── AdminAuth.php       # Autenticação JWT + blacklist
│   │   ├── AdminPermission.php # Verificação de permissões RBAC (cache Redis 60s)
│   │   └── OperationLog.php    # Registro automático de logs de operação (inclui detecção de origem)
│   ├── model/                  # Modelos de dados
│   ├── queue/                  # Tarefas de fila
│   └── process/                # Processos (Http, Monitor)
├── apps/
│   ├── flutter/                # Painel administrativo Flutter Web
│   │   └── lib/app/
│   │       ├── pages/          # 6 páginas completas
│   │       │   ├── dashboard/  # Painel
│   │       │   ├── login/      # Login
│   │       │   ├── user/       # Gestão de usuários
│   │       │   ├── role/       # Papéis e permissões
│   │       │   ├── config/     # Configuração do sistema
│   │       │   ├── log/        # Logs de operação
│   │       │   └── profile/    # Central do usuário
│   │       ├── services/       # ApiService + AuthService
│   │       ├── layouts/        # Layout responsivo
│   │       └── theme/          # Tema Material 3
│   └── harmonyos/              # Cliente HarmonyOS
├── config/                     # Arquivos de configuração
│   ├── route.php               # Rotas + estratégia de versão da API
│   └── middleware.php           # Registro de middlewares globais
├── database/
│   ├── install.sql             # Script de instalação completa (todos os SQLs mesclados)
│   └── backup/                 # Scripts de backup do banco
│       ├── backup.sh           # mysqldump+gzip, retenção de 30 dias
│       └── restore.sh          # Restauração interativa
├── docs/                       # Documentação
│   ├── ARCHITECTURE.md         # Diagramas de arquitetura Mermaid
│   ├── DESIGN.md               # Documento de design
│   ├── SECURITY.md             # Design da arquitetura de segurança
│   ├── API.md                  # Documento de referência da API
│   ├── nginx-security.conf     # Configuração de segurança de referência do Nginx
│   ├── diagrams/               # Diagramas de arquitetura decompostos
│   └── superpowers/            # Especificações e planos
│       ├── specs/              # Especificações de design
│       └── plans/              # Planos de implementação
├── public/                     # Entrada pública
├── runtime/                    # Arquivos de tempo de execução
├── tests/                      # Testes
├── vendor/                     # Dependências Composer
├── CLAUDE.md                   # Este arquivo
├── README.md                   # Documentação em chinês
├── docs/translations/README.en.md                # Documentação em inglês
├── docs/translations/README.ko.md ... README.ja.md  # Documentações multilíngues (coreano/russo/alemão/francês/espanhol/português/híndi/árabe/bengali/indonésio/japonês)
├── .env                        # Variáveis de ambiente (não versionadas)
├── .env.example                # Modelo de variáveis de ambiente
├── .env.docker                 # Variáveis de ambiente do Docker
├── composer.json               # Dependências PHP
├── Dockerfile                  # Build Docker
├── docker-compose.yml          # Orquestração Docker
└── .github/
    └── workflows/
        └── ci.yml              # Pipeline CI/CD (sintaxe PHP+PHPUnit+Flutter analyze)
```

## Cadeia de execução de middlewares

```
Global:  Cors → Locale(Accept-Language) → SecurityFilter(verificação de método→405) → RateLimit → {middlewares de rota}
/admin: Cors → Locale(Accept-Language) → SecurityFilter(verificação de método→405) → RateLimit → AdminAuth → AdminPermission → OperationLog → Controller
/api:   Cors → Locale(Accept-Language) → SecurityFilter(verificação de método→405) → RateLimit → Controller
/health: Cors → Locale(Accept-Language) → SecurityFilter(verificação de método→405) → RateLimit → Controller
```

## Reforços de segurança

- **Restrição de métodos HTTP**: o SecurityFilter permite apenas GET/POST/PUT/DELETE/OPTIONS/HEAD; métodos não padronizados retornam 405
- **Cabeçalho CSP**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies injetados em todas as respostas
- **Bloqueio de conta**: 5 falhas consecutivas de login bloqueiam a conta por 15 minutos
- **Limite de sessões concorrentes**: no máximo 3 tokens válidos por usuário; o token mais antigo vai para a blacklist quando o limite é excedido
- **security.txt**: endpoint `/.well-known/security.txt` RFC 9116
- **Configuração de segurança do Nginx**: referência de reforço de segurança do proxy reverso em `docs/nginx-security.conf`

## Estratégia de versão da API

A versão é controlada pelo cabeçalho `API-Version` (padrão `v1`), não refletida na URL:

```bash
curl http://localhost:8787/api/v1/auth/login
```

Para adicionar uma nova versão, basta criar o diretório `app/api/{version}/controller/` e registrá-lo no middleware `ApiVersion`.

## Estratégia de rate limit

Janela deslizante Redis (atômico com Lua), padrão de 60 requisições/minuto/IP/rota:
- Login: 10 requisições/minuto
- Registro: 5 requisições/minuto
- Cabeçalhos de resposta: `X-RateLimit-Limit/Remaining/Reset`; quando o limite é excedido, adiciona `Retry-After`

## Convenções de código

### PHP
- Referências a funções/classes globais sem `\` antes do nome; use `use` para importação
- Arquivos de configuração devem conter comentários em chinês explicando o significado de cada item
- Todos os arquivos `.php` novos devem ter a declaração de copyright no cabeçalho

### Banco de dados
- Prefixo de tabela: `logistics_`
- Chave primária `id`: tipo BIGINT, não autoincrementável, gerada por snowflake
- Campos sensíveis usam a trait `erikwang2013/encryptable` para criptografia/descriptografia automática
- Arquivos de migração usam formato SQL

### Flutter
- Layout Web com estilo de painel administrativo para PC (barra lateral + barra superior + área de conteúdo)
- Gerenciamento de estado com GetX, singleton `ApiService` (Dio + interceptor JWT)
- Persistência de token com `shared_preferences`
- Breakpoints responsivos: mobile (< 768px) e desktop (>= 768px)

### HarmonyOS
- Usa o cliente HTTP nativo `@ohos.net.http`
- Renovação silenciosa de token: em 401, chama automaticamente `/api/v1/auth/refresh`
- Falha na renovação redireciona automaticamente para a página de login

## Deploy

### Docker Compose (recomendado para produção)

O `docker-compose.yml` na raiz do projeto orquestra 5 serviços:

| Serviço | Descrição |
|------|------|
| `nginx` | Proxy reverso Nginx (80/443), serviço de arquivos estáticos |
| `app` | Aplicação webman PHP 8.3, build via `Dockerfile` (com OPcache) |
| `mysql` | MySQL 8.0, persistência com volume de dados |
| `redis` | Redis 7 Alpine, cache/rate limit/Session |
| `elasticsearch` | Elasticsearch 8.x, busca de texto completo |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` define o pipeline do GitHub Actions:

- Verificação de sintaxe PHP (`php -l`)
- Testes unitários PHPUnit
- Análise estática Flutter (`flutter analyze`)

### Backup do banco de dados

`database/backup/backup.sh` — mysqldump + gzip, limpeza automática de backups anteriores a 30 dias.
`database/backup/restore.sh` — restauração interativa, lista os backups disponíveis para escolha.

### Monitoramento

O endpoint `GET /metrics` (`MetricsController`) emite Prometheus text format, com 5 métricas gauge:
- `openadmin_http_requests_total` — total de requisições
- `openadmin_active_users` — número de usuários ativos
- `openadmin_db_connection_status` — status da conexão com o banco (0/1)
- `openadmin_redis_connection_status` — status da conexão Redis (0/1)
- `openadmin_memory_usage_bytes` — uso de memória
