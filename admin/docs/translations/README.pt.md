> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](../README.md) | [English](README.en.md) | [한국어](README.ko.md) | [Русский](README.ru.md) | [Deutsch](README.de.md) | [Français](README.fr.md) | [Español](README.es.md) | [Português](README.pt.md) | [हिन्दी](README.hi.md) | [العربية](README.ar.md) | [বাংলা](README.bn.md) | [Bahasa Indonesia](README.id.md) | [日本語](README.ja.md)

> [Diagramas de arquitetura](docs/ARCHITECTURE.pt.md) | [Documento de design](docs/DESIGN.pt.md) | [Arquitetura de segurança](docs/SECURITY.pt.md) | [Referência da API](docs/API.pt.md)

# Painel de Administração Open Source (open-admin)

Sistema de administração full-stack baseado em webman v2 + Flutter.

## Lista de funcionalidades

<img src="../../../docs/diagrams/description.svg" alt="Lista de funcionalidades" width="100%">


| Domínio de negócio | Funcionalidade | Descrição |
|--------|------|------|
| 🔐 Autenticação | Login/atualização de token/logout | Captcha de clique + JWT + blacklist |
| | Bloqueio de conta | 5 falhas bloqueiam por 15 minutos |
| | Limite de sessões concorrentes | Máximo de 3 tokens válidos por usuário |
| 📊 Painel | Estatísticas em tempo real/gráfico de tendência/gráfico de distribuição/operações recentes | Cache Redis de 5 minutos |
| 👥 Gestão de usuários | CRUD + exclusão em massa/habilitação e desabilitação | Soft delete + confirmação secundária de senha |
| | Importação em massa via Excel | Validação linha a linha + relatório de erros |
| 🔒 Papéis e permissões | CRUD de papéis + árvore de permissões | Autenticação RBAC com granularidade method.path |
| ⚙ Configuração do sistema | CRUD de pares chave-valor | Gestão por grupos |
| 📋 Auditoria de operações | Consulta de logs + detecção de origem | Reconhecimento automático de 8 plataformas |
| 📁 Gestão de arquivos | Upload/exportação Excel/exportação PDF | Mascaramento automático de dados sensíveis |
| 🛡 Proteção de segurança | Defesa em profundidade com 18 camadas | XSS/Injeção SQL/Path traversal/Injeção de comandos/CSRF/Rate limit/CSP... |
| 🏥 Operações | Health check/metrics/documentação da API/security.txt | Prometheus + OpenAPI 3.0 + documentação interativa hg/apidoc |
| 🌐 Internacionalização | Alternância chinês/inglês | Cabeçalho Accept-Language / parâmetro `?lang=` |

## Stack tecnológica

| Camada | Tecnologia | Descrição |
|---|------|------|
| Framework backend | webman v2 (workerman) | Framework PHP residente de altíssimo desempenho |
| Versão do PHP | 8.3+ | |
| Banco de dados | MySQL 8.0+ | Prefixo de tabela `logistics_`, chave primária BIGINT não autoincrementável |
| Motor de busca | Elasticsearch | Sincronização e consulta via `webman-scout` |
| Frontend admin | Flutter 3.x | Versão Web com estilo de painel administrativo para PC (`apps/flutter/`) |
| Mobile | HarmonyOS ArkTS | Cliente nativo HarmonyOS (`apps/harmonyos/`), suporta celular/tablet/2em1 |

## Dependências principais

| Pacote | Uso |
|---|------|
| `erikwang2013/snowflake-php` | Geração de chaves primárias BIGINT globalmente exclusivas com o algoritmo Snowflake |
| `erikwang2013/hashids` | Criptografia/descriptografia de IDs na camada de API, ocultando os IDs reais do banco |
| `erikwang2013/jwt-webman` | Emissão e validação de tokens de autenticação JWT |
| `erikwang2013/encryption` | Criptografia/descriptografia de dados sensíveis na camada de transmissão da API |
| `erikwang2013/encryptable` | Criptografia/descriptografia automática de campos sensíveis na camada de armazenamento do banco |
| `erikwang2013/webman-scout` | Sincronização de dados com Elasticsearch e busca de texto completo |
| `erikwang2013/season` | Dados de bandeiras de países |
| `erikwang2013/poster-php` | Geração e validação de captcha de clique + geração de pôsteres |
| `phpoffice/phpspreadsheet` | Exportação Excel |
| `barryvdh/laravel-dompdf` | Exportação PDF (baseado em Dompdf) |

## Estrutura do projeto

```
open-admin/
├── app/
│   ├── admin/controller/       # Controladores do painel administrativo
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
│   │   └── BaseController.php      # Controlador base
│   ├── api/
│   │   └── v1/controller/          # Controladores da API v1 (versão controlada pelo cabeçalho API-Version)
│   │       ├── CaptchaController.php # Captcha de clique
│   │       └── AuthController.php    # Login/atualização de token
│   ├── common/                 # Classes utilitárias comuns
│   │   ├── HashidsService.php  # Codificação/decodificação de IDs
│   │   ├── SnowflakeService.php# Geração de IDs Snowflake
│   │   └── EncryptionService.php # Criptografia/descriptografia de dados + mascaramento
│   ├── middleware/             # Middlewares
│   │   ├── Cors.php            # Cross-origin
│   │   ├── SecurityFilter.php  # Bloqueio de detecção de ataques (restrição de método HTTP/XSS/Injeção SQL/Path traversal/Injeção de comandos/CSRF)
│   │   ├── RateLimit.php       # Rate limit Redis (janela deslizante + cabeçalhos de resposta)
│   │   ├── ApiVersion.php      # Validação de versão da API
│   │   ├── AdminAuth.php       # Autenticação JWT + blacklist
│   │   ├── AdminPermission.php # Validação de permissões RBAC
│   │   └── OperationLog.php    # Registro automático de logs de operação (inclui detecção de origem)
│   └── model/                  # Modelos de dados
├── apps/
│   ├── flutter/                # Painel administrativo Flutter Web (estilo PC)
│   │   └── lib/app/
│   │       ├── pages/          # 5 páginas completas (painel/usuários/papéis/configurações/logs/central)
│   │       ├── services/       # ApiService (interceptor JWT) + AuthService (persistência de token)
│   │       └── layouts/        # Layout responsivo do painel (barra lateral + barra superior + área de conteúdo)
│   └── harmonyos/              # Cliente nativo HarmonyOS (atualização silenciosa de token)
├── config/                     # Arquivos de configuração (com comentários em chinês)
│   ├── route.php               # Rotas + estratégia de versão da API
│   ├── middleware.php           # Registro de middlewares globais
│   └── ...                     # Configurações de cada componente
├── database/install.sql        # Script de instalação SQL (inclui seed de permissões)
├── public/                     # Entrada pública
├── runtime/                    # Arquivos de tempo de execução
└── vendor/                     # Dependências Composer
```

## Requisitos de ambiente

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (necessário apenas para desenvolvimento do frontend)
- Elasticsearch >= 7.x (opcional, necessário para a função de busca)

## Início rápido

### 1. Instalar dependências

```bash
composer install
```

### 2. Configurar variáveis de ambiente

Copie e modifique as variáveis de ambiente (opcional; sem configuração, os valores padrão em `config/*.php` são usados):

```bash
cp .env.example .env
```

Itens de configuração essenciais:

| Variável de ambiente | Descrição | Valor padrão |
|---------|------|--------|
| `JWT_SECRET` | Chave de assinatura JWT | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Sal do Hashids | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | Chave de criptografia da API | valor padrão de 32 bytes |
| `SNOWFLAKE_DATACENTER_ID` | ID do datacenter (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID do nó de trabalho (0-31) | `1` |
| `SCOUT_HOSTS` | Endereço do ES | `http://localhost:9200` |

**Em produção, é obrigatório alterar todas as chaves para strings aleatórias.**

### 3. Instalação em um clique

Após iniciar o serviço, acesse o assistente de instalação pelo navegador para concluir a inicialização do banco de dados e a criação do administrador:

```bash
php start.php start
```

Por padrão, escuta em `http://0.0.0.0:8787` (a porta pode ser alterada em `config/server.php`).

Abra **`http://localhost:8787/install`** no navegador e preencha o assistente:

| Etapa | Conteúdo |
|------|------|
| ① Configuração do banco de dados | Endereço do host, porta, nome do banco, usuário, senha |
| ② Configuração do administrador | Usuário e senha do administrador (padrão admin / admin888) |

Ao clicar em "Iniciar instalação", a criação das tabelas, o seed de dados de permissões e a criação da conta de administrador são concluídos automaticamente, e a configuração do banco é gravada no `.env`.

> Após a instalação, é gerado o arquivo de bloqueio `runtime/install.lock`. Para reinstalar, basta excluir este arquivo.

### 4. Login

Acesse `http://localhost:8787` e faça login com a conta de administrador definida na instalação.

### 5. Iniciar o frontend (opcional)

**Painel administrativo Flutter (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (estilo de painel administrativo para PC)
```

**Cliente HarmonyOS (celular):**

Use o DevEco Studio para abrir o diretório `apps/harmonyos/` e execute em um dispositivo real ou emulador.

### 6. Deploy com Docker Compose (recomendado para produção)

O projeto fornece uma solução completa de orquestração Docker com 5 serviços: Nginx, PHP (aplicação webman), MySQL, Redis, Elasticsearch.

```bash
# 1. Configurar variáveis de ambiente do Docker
cp .env.docker .env

# 2. Iniciar todos os serviços
docker-compose up -d

# 3. Acesse o assistente de instalação no navegador para concluir a inicialização
# http://localhost:8787/install  (preencha as informações do banco e do administrador)
# ou execute a migração SQL manualmente (dentro do contêiner app):
# docker-compose exec app mysql -h mysql -u root -p < database/install.sql

# 4. Acesso
# http://localhost:8787  (webman)
# http://localhost:8080  (proxy reverso Nginx)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, baseado em `php:8.3-cli`
- `docker-compose.yml`: orquestração de 5 serviços, isolamento de rede, persistência com volumes
- `.env.docker`: variáveis de ambiente específicas para o ambiente Docker


## Convenções de banco de dados

- **Prefixo de tabela**: `logistics_`
- **Chave primária**: todas as tabelas usam `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT desabilitado**
- **Geração de ID**: as chaves primárias são geradas pelo `SnowflakeService::generate()` na camada de aplicação, exclusivas em ambiente distribuído
- **Campos obrigatórios**: cada tabela deve conter `id`, `created_at`, `updated_at`
- **Soft delete**: tabelas que precisam de soft delete adicionam `deleted_at DATETIME DEFAULT NULL`
- **Campos sensíveis**: telefone, e-mail, CPF etc. usam o plugin `encryptable` para criptografia/descriptografia automática; o campo no banco usa `VARCHAR(500)` para armazenar o texto cifrado

## Documentação da API

A referência completa da API (formato unificado de resposta, códigos de erro, detalhes de todos os endpoints, fluxo de autenticação, política de rate limit, cadeia de middlewares) está em **[docs/API.pt.md](docs/API.pt.md)**. Pontos principais:

- **Formato unificado de resposta**: `{ "code": 0, "message": "success", "data": {...} }`, `code=0` indica sucesso
- **Códigos de erro**: `400` erro de parâmetro / `401` não autenticado / `403` sem permissão / `404` não encontrado / `422` falha de validação / `429` rate limit / `500` erro do servidor
- **Versão da API**: controlada pelo cabeçalho `API-Version: v1` (padrão v1 quando ausente), não refletida na URL
- **Autenticação**: `Authorization: Bearer <token>`; validade do access_token de 2 horas, refresh_token de 14 dias
- **Tratamento de IDs**: IDs em requisições/respostas são strings criptografadas com hashids, sem expor os IDs reais do banco

## Notas sobre o frontend

### Painel administrativo Flutter (estilo PC)

- **Layout**: barra lateral (recolhível, 64px/240px) + barra superior + área de conteúdo, responsivo com três breakpoints (celular/tablet/desktop)
- **Páginas**: login, painel, gestão de usuários, papéis e permissões, configurações do sistema, logs de operação, central do usuário
- **Gerenciamento de estado**: GetX (`ApiService` singleton + `AuthService` com persistência de token)
- **Painel**: cards de estatísticas, gráfico de linhas de tendência (fl_chart), gráfico de pizza, logs de operações recentes
- **Exportação**: exportação Excel/PDF, o PDF inclui informações de copyright não removíveis
- **Operações em massa**: exclusão em massa com múltipla seleção, habilitação/desabilitação em massa
- **Tema**: Material 3 com temas claro/escuro

### Mobile HarmonyOS

- **Páginas**: login, painel, lista/detalhes de usuários, central do usuário
- **Autenticação**: JWT Bearer + renovação silenciosa automática de token em 401, redirecionamento automático para a página de login em caso de falha na renovação
- **Armazenamento**: token gerenciado via AppStorage

## Convenções de desenvolvimento

- Referências a funções/classes globais não usam `\` antes do nome; use `use` para importação
- Todo arquivo PHP deve conter a declaração de copyright no cabeçalho
- Todos os arquivos de configuração devem conter comentários em chinês explicando cada item
- As chaves primárias do banco devem ser geradas pelo snowflake na camada de aplicação; autoincremento é proibido
- Todos os IDs em parâmetros e respostas na camada de API devem passar por criptografia/descriptografia hashids
- O middleware AdminPermission usa cache Redis para permissões de usuário (TTL=60s), eliminando o gargalo de consultas N+1

## Deploy

### Docker Compose (recomendado)

O diretório raiz do projeto fornece `docker-compose.yml`, que orquestra 5 serviços:

| Serviço | Imagem | Porta |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | build local com `Dockerfile` | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

A imagem PHP é construída via `Dockerfile`, imagem base `php:8.3-cli`, com OPcache habilitado.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

Pipeline de integração contínua com GitHub Actions: `.github/workflows/ci.yml`

- Verificação de sintaxe PHP (`php -l`)
- Testes unitários PHPUnit
- Análise estática Flutter (`flutter analyze`)

### Backup do banco de dados

Diretório `database/backup/`:

- `backup.sh` — backup com mysqldump + gzip, limpeza automática de backups anteriores a 30 dias
- `restore.sh` — restauração interativa, lista os backups disponíveis para escolha

### Configuração de segurança do Nginx

Para produção, consulte `docs/nginx-security.conf` para reforçar a segurança do proxy reverso.

## Open source não é fácil, agradecemos o apoio

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](./docs/weixinpay.png "WeChat") | ![Alipay](./docs/alipay.png "Alipay") |

### Doação por transferência internacional (remessa transfronteiriça)

**Informações do beneficiário**

- Nome do beneficiário: WANG KEXUN
- Número da conta: 881015918251

**Banco beneficiário**

- ZA Bank SWIFT Code: AABLHKHHXXX
- Nome do banco: ZA Bank Limited
- Código do banco: 387
- Endereço do banco: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Banco correspondente para remessas internacionais (se necessário)**

> Estas são as informações do banco correspondente (banco intermediário) para remessas transfronteiriças, não do banco beneficiário. Consulte o seu banco de origem para saber se é necessário fornecer as informações do banco correspondente.

- **Para remessas em HKD, CNY e USD**, o banco correspondente é o Citibank:
  - Nome do banco: Citibank N.A. Hong Kong
  - SWIFT Code: CITIHKHXXXX
  - Código do banco: 006
  - Nome da agência: Hong Kong Branch
  - Código da agência: 391
  - Endereço do banco: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **Para outras moedas**, o banco correspondente é o BNY Mellon:
  - Nome do banco: THE BANK OF NEW YORK MELLON
  - SWIFT Code: IRVTUS3NXXX
  - Endereço do banco: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
