> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](SECURITY.md) | [English](SECURITY.en.md) | [한국어](SECURITY.ko.md) | [Русский](SECURITY.ru.md) | [Deutsch](SECURITY.de.md) | [Français](SECURITY.fr.md) | [Español](SECURITY.es.md) | [Português](SECURITY.pt.md) | [हिन्दी](SECURITY.hi.md) | [العربية](SECURITY.ar.md) | [বাংলা](SECURITY.bn.md) | [Bahasa Indonesia](SECURITY.id.md) | [日本語](SECURITY.ja.md)

# Documento de design da arquitetura de segurança

## 1. Panorama da defesa em profundidade

O sistema adota um modelo de defesa em profundidade com 7 camadas, filtrando requisições maliciosas de fora para dentro, garantindo que, mesmo que qualquer camada individual falhe, as camadas seguintes continuem servindo de proteção.

Toda a cadeia de middlewares é executada na seguinte ordem (ver `config/middleware.php`):

```
Requisição → Cors → Locale(Accept-Language) → SecurityMiddleware (erikwang2013/security-php, 31 detectores) → RateLimit → [Middlewares do grupo de rotas: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Camada | Middleware/mecanismo | Objetivo de proteção |
|----|--------|---------|
| 1 | SecurityMiddleware (erikwang2013/security-php) | 31 tipos de detecção de ataque + validação de método HTTP + limite de tamanho do corpo da requisição + validação de Content-Type + CSRF + blacklist de IP por escalada de ataque |
| 2 | Cors | Segurança de cross-origin + injeção de cabeçalhos de segurança na resposta |
| 3 | RateLimit | Rate limit com janela deslizante do Redis, evita força bruta |
| 4 | AdminAuth | Autenticação JWT + logout com blacklist |
| 5 | AdminPermission | Autorização com granularidade RBAC method.path |
| 6 | OperationLog | Auditoria de operações + rastreamento de origem |
| 7 | Criptografia de dados | Ofuscação de IDs com Hashids + criptografia de banco com Encryptable + criptografia de transmissão com EncryptionService |

As três camadas do frontend (Flutter) têm validação de entrada independente; o backend não confia no frontend, e cada camada se defende de forma independente.

---

## 2. Motor de detecção de ataques

## 2. Motor de detecção de ataques (erikwang2013/security-php)

A detecção de ataques foi migrada do SecurityMiddleware proprietário para o pacote de segurança dedicado `erikwang2013/security-php` v1.1+, que fornece **31 detectores**, cobrindo 5 grandes categorias de ataque.

### 2.1 Classificação dos detectores

**Ataques de injeção (11 tipos):** XSS, injeção SQL, injeção de comandos, injeção NoSQL, injeção LDAP, injeção XPath, JNDI/Log4Shell, inclusão de arquivos no servidor (SSI), injeção GraphQL, injeção de templates SSTI

**Ataques de protocolo e de requisição (9 tipos):** SSRF, XXE, injeção de cabeçalhos de resposta HTTP, ataque de cabeçalho Host, Request Smuggling, Open Redirect, bypass de CORS, sequestro de WebSocket, DNS Rebinding

**Validação da camada de protocolo HTTP (6 tipos):** validação de método HTTP (405), limite de tamanho do corpo da requisição (413), validação de Content-Type (415), verificação de Origin para CSRF, blacklist de IP por escalada de ataque, detecção de vazamento de dados sensíveis

**Ataques de dados e serialização (5 tipos):** desserialização PHP, injeção de fórmula CSV, injeção de cabeçalho de e-mail, ataques JWT (análise estruturada), JS Prototype Pollution

**Ataques de arquivos e caminhos (2 tipos):** path traversal, upload malicioso de arquivos

### 2.2 Modos de tratamento

Cada detector suporta independentemente dois modos:
- `block` — bloqueia ao detectar o ataque, retornando o código de status configurado
- `log` — apenas registra no log sem bloquear (`header_injection`, `ssti`, `nosql_injection` usam o modo log por padrão para evitar falsos positivos)

### 2.3 Blacklist de IP por escalada de ataque

Um mesmo IP que aciona 5 detecções de ataque em 60 segundos → banimento automático por 15 minutos. O backend de armazenamento pode ser Redis (distribuído), File (JSON em uma única máquina) ou Cache (arquivo independente de alto desempenho); a configuração atual usa armazenamento Redis.

### 2.4 Logs de segurança

Local do arquivo: `runtime/logs/security.log` (rotação automática, 10MB/arquivo)

---

## 4. Cabeçalhos de segurança da resposta

Todos os cabeçalhos são injetados no middleware `Cors`, adicionados a cada resposta via `$response->withHeaders()`.

| Cabeçalho | Valor | Função |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Permite cross-origin de qualquer origem (cenário de painel administrativo em rede interna) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Conjunto de métodos permitidos |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | Cabeçalhos personalizados permitidos |
| Access-Control-Max-Age | `86400` | Cache da requisição de preflight por 24 horas |
| X-Content-Type-Options | `nosniff` | Proíbe MIME sniffing do navegador |
| X-Frame-Options | `DENY` | Proíbe incorporação em qualquer iframe, evita clickjacking |
| X-XSS-Protection | `1; mode=block` | Habilita o filtro XSS embutido do navegador e bloqueia a renderização da página |
| Referrer-Policy | `strict-origin-when-cross-origin` | Mesma origem envia a URL completa; cross-origin envia apenas o domínio |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Desabilita as APIs de câmera/microfone/geolocalização em todo o site |

Requisições de preflight OPTIONS retornam diretamente 204 vazio, sem entrar na cadeia de middlewares subsequente.

### 4.2 Content-Security-Policy (CSP)

Injetada junto com os outros cabeçalhos de segurança no middleware Cors, fornecendo defesa em profundidade e restringindo as origens de recursos que o navegador pode carregar e executar.

| Cabeçalho | Valor | Função |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Restringe a origem de recursos como scripts/estilos/imagens/conexões/frames/formulários |
| X-Permitted-Cross-Domain-Policies | `none` | Proíbe o carregamento de arquivos de política entre domínios por Adobe Flash/PDF |

Pontos-chave da política CSP:
- `default-src 'self'`: por padrão, apenas recursos de mesma origem
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: permite scripts de mesma origem + scripts inline (necessário para Flutter Web) + eval (necessário para depuração do Flutter Web)
- `frame-ancestors 'none'`: proíbe incorporação em iframe por qualquer página, dupla garantia com X-Frame-Options: DENY
- `base-uri 'self'`: restringe a tag `<base>` a apontar apenas para a mesma origem
- `form-action 'self'`: restringe o envio de formulários apenas para a mesma origem

---

## 5. Estratégia de rate limit

### Algoritmo

Janela deslizante com Redis Sorted Set + script Lua atômico; operações principais:

```lua
-- 1. Limpa registros antigos fora da janela
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. Verifica a contagem da janela atual
local count = redis.call('ZCARD', KEYS[1])
-- 3. Se exceder o limite retorna {0, count}; se não, faz ZADD e retorna {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- sufixo aleatório evita sobrescrita no mesmo milissegundo
redis.call('EXPIRE', KEYS[1], window + 10)
```

O script Lua é executado em thread única no lado do servidor Redis, sendo **naturalmente atômico**, eliminando a condição de corrida TOCTOU (Time-of-check to Time-of-use).

### Configuração do rate limit

| Rota | Limite | Janela | Cenário |
|------|------|------|------|
| Padrão (todas as rotas) | 60 requisições/minuto | 60s | API geral |
| `/api/auth/login` | 10 requisições/minuto | 60s | Login (evita força bruta) |
| `/api/auth/register` | 5 requisições/minuto | 60s | Registro (evita registro em massa) |

### Cabeçalhos de resposta

Quando o rate limit é acionado, retorna HTTP 429 com corpo JSON:
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

Todas as respostas (incluindo as normais) carregam os seguintes cabeçalhos:

| Cabeçalho | Descrição |
|----|------|
| X-RateLimit-Limit | Número máximo de requisições permitidas na janela atual |
| X-RateLimit-Remaining | Número de requisições restantes na janela atual |
| X-RateLimit-Reset | Timestamp Unix de reinício da janela |
| Retry-After | Presente apenas quando o rate limit é acionado; segundos sugeridos de espera |

### Estratégia de degradação

Quando o Redis apresenta anomalia (timeout de conexão, indisponível, etc.), **fail-open**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis down, libera todas as requisições
}
```

É preferível perder temporariamente a proteção de rate limit a bloquear requisições de negócio normais.

### 5.4 Mecanismo de bloqueio de conta

Além do rate limit, o endpoint de login conta com um mecanismo adicional de **bloqueio de conta**, evitando força bruta direcionada a usuários específicos.

**Fluxo de bloqueio**:

```
Falha de login → Redis INCR account_lockout:{userId} TTL=900s
5 falhas consecutivas → Redis SETEX account_locked:{userId} 900 1
            → retorna 429 "账号已被锁定，请15分钟后再试"
            → limpa o contador DEL account_lockout:{userId}
```

**Comportamento durante o bloqueio**:

Durante o bloqueio, todas as requisições de login retornam 429 diretamente, sem validação de senha, bloqueando completamente as tentativas de força bruta.

**Constantes de configuração**:

| Constante | Valor | Significado |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Número máximo de falhas consecutivas |
| LOCKOUT_DURATION | 900 | Duração do bloqueio (segundos), ou seja, 15 minutos |

Observação: o bloqueio de conta é baseado em `userId`, não em IP; portanto, o atacante não consegue contornar o bloqueio trocando de IP. Combinado com o rate limit por IP (10/minuto), forma uma proteção dupla:
- Nível de IP: rate limit de 10/minuto impede força bruta distribuída
- Nível de conta: bloqueio após 5 falhas impede força bruta direcionada

---

## 6. Autenticação e autorização

### 6.1 Autenticação JWT

Implementada pelo middleware AdminAuth, montado nos grupos de rotas que exigem autenticação.

**Configuração de parâmetros** (`config/plugin/erikwang2013/jwt/jwt`, injetada via `.env`):

| Parâmetro | Valor | Descrição |
|------|-----|------|
| Algoritmo | HS256 | Assinatura simétrica HMAC-SHA256 |
| Chave | `JWT_SECRET` | Injetada por variável de ambiente; em produção, precisa ser trocada |
| TTL do access_token | 7200s (2h) | `JWT_TTL` |
| TTL do refresh_token | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Emissor | `open-admin` | `JWT_ISSUER` |
| Audiência | `open-admin` | `JWT_AUDIENCE` |

**Extração do token**: extraído do cabeçalho `Authorization: Bearer <token>`; remove o prefixo `Bearer ` para obter o JWT original.

**Fluxo de autenticação**:
1. Token vazio → 401 direto `{"code": 401, "message": "未登录"}`
2. Verifica a blacklist do Redis `jwt_blacklist:{md5(token)}` → encontrado → 401 `Token已失效，请重新登录`
3. Decodificação do JWT → falha (expirado/assinatura inválida) → 401 `Token已过期或无效`
4. Sucesso → injeta `$request->adminId` e `$request->adminUsername`

**Mecanismo de blacklist**: quando o usuário faz logout, o `md5(token)` é gravado no Redis com TTL igual à validade restante do JWT. Em caso de falha do Redis, a verificação da blacklist é ignorada (fail-open); nesse caso, o token com logout ainda pode ser usado por um curto período, mas a validade curta do próprio JWT (2h) serve como proteção de fallback.

### 6.2 Limite de sessões concorrentes

Para evitar o uso indevido do token em vários dispositivos após vazamento, o sistema limita o número de tokens válidos que um mesmo usuário pode manter simultaneamente.

**Lógica de limitação**:

```
Login bem-sucedido → emissão de novo token
         → consulta a quantidade de tokens válidos do usuário atual: Redis SCARD user_tokens:{userId}
         → se a quantidade >= 3 (MAX_CONCURRENT_SESSIONS):
            → ordena por horário de criação, remove o token mais antigo:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → adiciona o novo token ao conjunto: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Constantes de configuração**:

| Constante | Valor | Significado |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Número máximo de tokens concorrentes por usuário |

**Cenário de deslogado à força**: quando o usuário faz login em um 4º dispositivo, o token do 1º dispositivo é adicionado à blacklist à força e as requisições subsequentes retornam 401 "Token已失效，请重新登录".

No logout, o token atual é removido do conjunto. Quando o token expira naturalmente, a chave Redis expira automaticamente e os membros do conjunto diminuem.

### 6.3 Modelo de permissões RBAC

Implementado pelo middleware AdminPermission.

**Modelo de dados**: associação em três camadas User -> Role -> Permission

- `logistics_admin_user` (tabela de usuários)
- `logistics_admin_user_role` (tabela de associação usuário-papel)
- `logistics_admin_role` (tabela de papéis)
- `logistics_admin_role_permission` (tabela de associação papel-permissão)
- `logistics_admin_permission` (tabela de permissões)

**Tipos de permissão**:
| type | Significado | Exemplo |
|------|------|------|
| 1 | Permissão de menu | Controla a visibilidade da navegação lateral |
| 2 | Permissão de botão | Controla os botões de ação na página (novo/editar/excluir) |
| 3 | Permissão de API | Controla as chamadas aos endpoints do backend |

Formato do identificador de permissão de API: `{method}.{path}`

Por exemplo:
- `post.admin/user` — criar usuário
- `put.admin/user` — editar usuário
- `delete.admin/user` — excluir usuário
- `get.admin/user` — visualizar lista de usuários

**Fluxo de autorização**:
1. `$request->adminId` vazio → libera (rota sem autenticação prévia configurada)
2. Obtém usuário → papéis (pula papéis desabilitados com `status=0`) → lista de permissões
3. Super administrador (`slug = '*'`) → libera diretamente
4. Constrói `strtolower(method) . '.' . trim(path, '/')` → compara com a lista de permissões
5. Sem correspondência → 403 `{"code": 403, "message": "无权限访问"}`

**Confirmação secundária**: o BaseController fornece o método `confirmPassword()`. Operações sensíveis (excluir usuário, exportar dados, etc.) exigem adicionalmente a senha atual na camada de Controller, evitando operações não autorizadas após sequestro de sessão.

---

## 7. Logs de auditoria

### 7.1 Logs de operação

O middleware OperationLog registra automaticamente os logs de operação para requisições POST / PUT / DELETE. Requisições GET não são registradas.

**Campos registrados**:

| Campo | Origem | Descrição |
|------|------|------|
| id | SnowflakeService::generate() | ID globalmente exclusivo |
| user_id | `$request->adminId` | ID do operador, 0 se não logado |
| action | `$request->method()` | Equivalente ao method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Caminho da requisição |
| ip | `$request->getRealIp()` | IP real do cliente |
| source | detectSource() | Plataforma de origem do cliente |
| input | corpo da requisição (JSON mascarado) | Dados enviados pela operação |
| created_at | `date('Y-m-d H:i:s')` | Horário da operação |

**Filtro de campos sensíveis**: percorre recursivamente o corpo da requisição; os valores dos seguintes campos são substituídos por `***`:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Detecção de origem** (`detectSource()`): por prioridade:

1. Primeiro lê o cabeçalho personalizado `X-Client-Platform` (declarado explicitamente por clientes nativos)
2. Degrada para inferência pela string User-Agent (ordem de detecção do método `detectSource()`):

| Plataforma | Palavras-chave do UA |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | valor padrão de fallback |

**Tolerância a falhas**: anomalias na gravação do log não bloqueiam requisições de negócio (`catch (\Throwable)` engole silenciosamente).

### 7.2 Logs de segurança

**Local do arquivo**: `runtime/logs/security.log`

**Conteúdo registrado**:
- Logs de bloqueio de ataques: categoria do ataque, IP, caminho, campo, origem, trecho do payload (primeiros 200 caracteres)
- Notificações de banimento de IP: IP banido, número de acionamentos

A permissão de gravação do log é `FILE_APPEND | LOCK_EX`, garantindo gravação segura em concorrência.

---

## 8. Proteção de dados

O sistema adota uma estratégia de proteção de dados em três camadas, correspondendo às três fases do fluxo de dados.

### 8.1 Camada de transmissão — EncryptionService

O `EncryptionService` usa o pacote `erikwang2013/encryption` para criptografar/descriptografar campos sensíveis nas requisições/respostas da API.

**Detalhes técnicos**:
- Algoritmo: `aes-256-cbc-hmac` (com assinatura HMAC integrada contra adulteração)
- Chave: variável de ambiente `ENCRYPTION_KEY`, alinhada automaticamente a 32 bytes
- Uso: transmissão de campos como telefone e CPF entre o cliente e a API

**Métodos utilitários de mascaramento**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (nome de usuário com mais de 2 caracteres) ou `a**@example.com`

### 8.2 Camada de armazenamento — Cast Encryptable

O modelo `AdminUser` usa o cast Eloquent `Erikwang2013\Encryptable\Encryptable`, para os seguintes campos:

- `email` → cast para Encryptable, criptografia/descriptografia automática
- `phone` → cast para Encryptable, criptografia/descriptografia automática
- `id_card` → cast para Encryptable, criptografia/descriptografia automática

Ao gravar no banco, o campo é criptografado automaticamente em texto cifrado; ao ler, é descriptografado automaticamente para texto puro. O tipo da coluna no banco é `VARCHAR(500)`, e o texto cifrado é armazenado em base64.

**Sistema de chaves**: independente da criptografia da camada de transmissão (`ENCRYPTION_KEY`), usa `ENCRYPTABLE_KEY`; o vazamento de uma chave não compromete a outra camada.

Rotação de chaves: a variável de ambiente `ENCRYPTION_PREVIOUS_KEYS` suporta uma lista de chaves históricas (separadas por vírgula); ao ler dados antigos, tenta descriptografar com as chaves históricas e, ao gravar, re-criptografa com a chave atual.

### 8.3 Camada de exibição — Ofuscação de IDs e mascaramento

**Ofuscação de IDs com Hashids**: o `HashidsService` usa o pacote `erikwang2013/hashids`.

- IDs BIGINT do banco retornados pela API externa são codificados como strings hash (ex.: `xK3mN9qR2pL7wV8b`)
- O cliente envia a string hash nas requisições e o backend decodifica automaticamente para o ID original
- O sal `HASHIDS_SALT` é injetado por variável de ambiente; sais diferentes produzem resultados de codificação/decodificação completamente diferentes
- Comprimento mínimo do hash de 16 caracteres, usando conjunto de caracteres alfanuméricos de 62 dígitos
- O BaseController fornece os métodos convenientes `encodeId()`, `decodeId()` e `encodeIds()`

**Mascaramento na exportação**: na exportação Excel/PDF (ExportController), os campos sensíveis são mascarados de forma unificada:
- Telefone: `138****1234`
- E-mail: `a***@example.com`
- CPF: coberto completamente como `********`

---

## 9. Gerenciamento de chaves

Todas as chaves são injetadas por variáveis de ambiente no `.env`; os arquivos de configuração usam `getenv()` para leitura e têm valores padrão de fallback embutidos (seguros apenas em ambiente de desenvolvimento).

| Variável de ambiente | Uso | Pacote | Requisito de produção |
|----------|------|-----|---------|
| JWT_SECRET | Chave de assinatura JWT | erikwang2013/jwt-webman | String aleatória com 64+ caracteres |
| JWT_ALGORITHM | Algoritmo de assinatura JWT | mesmo acima | Manter HS256 |
| HASHIDS_SALT | Sal de codificação de IDs | erikwang2013/hashids | String aleatória |
| SNOWFLAKE_DATACENTER_ID | ID do datacenter (0-31) | erikwang2013/snowflake-php | Manter o padrão em um único datacenter |
| ENCRYPTION_KEY | Chave de criptografia da camada de transmissão da API | erikwang2013/encryption | String aleatória de 32 bytes |
| ENCRYPTABLE_KEY | Chave de criptografia da camada de armazenamento do banco | erikwang2013/encryptable | String aleatória de 32 bytes, diferente da chave de transmissão |

**Requisitos de segurança**:
- O arquivo `.env` já está no `.gitignore`; é proibido enviá-lo ao repositório
- `.env.example` é um arquivo de modelo público e não contém chaves reais
- Em produção, **é obrigatório** trocar todas as chaves padrão por strings aleatórias
- Recomenda-se usar `openssl rand -base64 32` para gerar as chaves

### Isolamento do armazenamento de chaves

| Camada | Chave de configuração | Variável de ambiente da chave |
|----|--------|-------------|
| Criptografia de transmissão | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Criptografia de armazenamento | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| Ofuscação de IDs | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| Assinatura JWT | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

O sistema fornece um endpoint de informações de contato de segurança em `/.well-known/security.txt`, em conformidade com o padrão RFC 9116, facilitando que pesquisadores de segurança encontrem rapidamente o canal de reporte ao descobrir vulnerabilidades.

**Forma de acesso**:

```
GET /.well-known/security.txt
```

**Conteúdo da resposta**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Descrição dos campos**:

| Campo | Descrição |
|------|------|
| Contact | Contato para reporte de vulnerabilidades de segurança |
| Expires | Data de expiração do arquivo, precisa ser atualizada periodicamente |
| Preferred-Languages | Idiomas preferidos de comunicação |
| Canonical | URL canônica deste arquivo |
| Policy | Link para a política de segurança / política de divulgação de vulnerabilidades |

Este endpoint não é limitado por middlewares como rate limit ou autenticação; qualquer pessoa pode acessá-lo diretamente.

---

## 11. Configuração de segurança do Nginx

O projeto fornece `docs/nginx-security.conf` como configuração de referência para o reforço de segurança do Nginx como proxy reverso em produção.

**Medidas de segurança incluídas**:

| Item de configuração | Função |
|--------|------|
| `server_tokens off` | Oculta o número da versão do Nginx |
| `client_max_body_size 10m` | Limita o tamanho do corpo da requisição, em cooperação com o SecurityMiddleware (erikwang2013/security-php) |
| `limit_req_zone` | Limitação de frequência de requisições no nível do Nginx |
| `limit_conn_zone` | Limitação do número de conexões concorrentes |
| cabeçalhos de segurança `add_header` | Adiciona cabeçalhos de segurança como X-XSS-Protection no nível do Nginx |
| `if ($request_method)` | Rejeita métodos HTTP não padronizados no nível do Nginx |
| Configuração SSL/TLS | Configuração moderna de TLS 1.2/1.3, desabilitando conjuntos de criptografia fracos |
| Ocultar cabeçalhos do backend | `proxy_hide_header` remove cabeçalhos sensíveis como a versão do webman |

**Como usar**: mescle as configurações de `docs/nginx-security.conf` no seu bloco server do Nginx e ajuste conforme o domínio real e os caminhos dos certificados.

---

## 12. Modelo de ameaças

### 12.1 Ameaças protegidas

| Tipo de ameaça | Vetor de ataque | Camadas de defesa |
|----------|---------|---------|
| Abuso de método HTTP | Ataque XST TRACE/TRACK, tunelamento por proxy CONNECT, sondagem de métodos WebDAV | Whitelist de métodos 405 do detector http_method do SecurityMiddleware |
| Força bruta direcionada | Tentativas repetidas de senha contra um usuário específico | Bloqueio de conta (5 falhas bloqueiam por 15 minutos) + RateLimit (login 10/min) + Captcha |
| Força bruta | Tentativas distribuídas de nome de usuário/senha a partir de vários IPs | RateLimit (login 10/min) + Captcha |
| XSS (script entre sites) | `<script>`, onerror, javascript: | SecurityMiddleware (erikwang2013/security-php) (5 padrões) + cabeçalho de resposta X-XSS-Protection + CSP |
| Injeção SQL | UNION SELECT, OR 1=1, bypass por comentários | SecurityMiddleware (erikwang2013/security-php) (6 padrões) + consultas parametrizadas do Eloquent ORM |
| CSRF (falsificação de requisição entre sites) | Sites maliciosos enviam requisições em nome do usuário | Validação de Origin/Referer do SecurityMiddleware (erikwang2013/security-php) |
| Path traversal | `../../etc/passwd` | Padrão de path traversal do SecurityMiddleware (erikwang2013/security-php) + whitelist de extensões do UploadController |
| Injeção de comandos | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityMiddleware (erikwang2013/security-php) (4 padrões) |
| Sequestro de sessão | Roubo do token JWT | Validade curta do JWT (2h) + logout com blacklist + confirmação secundária de senha em operações sensíveis |
| Enumeração de IDs | Percorrer IDs numéricos para estimar o volume de dados | Ofuscação com Hashids em strings aleatórias |
| Vazamento de dados | Extração do banco / man-in-the-middle / vazamento de logs | Criptografia/mascaramento em três camadas + filtro de campos sensíveis do OperationLog |
| Ataque DoS | Corpo de requisição gigantesco / requisições de alta frequência | Limite de 10MB no corpo + RateLimit 60/min + blacklist de IP |
| Escalação de privilégios | Usuário de baixo privilégio acessa endpoints administrativos | Autorização com granularidade RBAC method.path |
| Ataque de upload de arquivos | shell.php.png com dupla extensão | Detecção de arquivos maliciosos do SecurityMiddleware (erikwang2013/security-php) |

### 12.2 Limitações conhecidas

| Limitação | Escopo de impacto | Medidas de mitigação |
|------|---------|---------|
| A proteção CSRF só é eficaz para navegadores | Clientes não navegador (curl, Postman, apps mobile) podem ignorar a verificação de Origin/Referer | Clientes não navegador naturalmente não sofrem ataques CSRF; usa autenticação JWT em vez de Cookies |
| Quando o Redis está indisponível, rate limit e blacklist degradam para fail-open | Atacantes podem contornar rate limit e bloqueio de alta frequência | Monitorar a disponibilidade do Redis com alertas; a blacklist de IP suporta três backends (file/redis/cache) com degradação |
| Sem motor WAF independente | Detecção baseada em correspondência de regex, não um motor de regras WAF dedicado | Em produção, recomenda-se Nginx ModSecurity ou Cloudflare WAF na frente |
| JWT sem estado não pode ser revogado ativamente | Token não pode ser revogado ativamente pelo servidor antes da expiração (exceto pela blacklist) | Blacklist + TTL curto de 2h reduz a janela de risco |
| Endpoints administrativos sem rate limit especial | Endpoints administrativos compartilham o limite padrão de 60/min com as APIs comuns | A frequência de operações administrativas é naturalmente baixa; por enquanto não há necessidade de diferenciar |
| Limite de retrocesso PCRE | O pacote tem limite interno de retrocesso de 1.000.000 com recuperação via finally; entradas extremamente complexas ainda apresentam risco de desempenho | Limite de tamanho do corpo da requisição (10MB) como fallback |
## 13. Segurança do portal do cliente e pagamentos (M7–M10)
Verificação de assinatura de webhooks Stripe / PayPal (HMAC-SHA256 / verify-webhook-signature); chaves de pagamento criptografadas via `Encryptable` em `logistics_system_config`; USDT TRC20 verificado automaticamente via Tronscan, BEP20 / ERC20 manual; X-API-Key definido pelo cliente (≥16 caracteres) armazenado como sha256. Detecção de ataques na gateway (M11): `ecat-security` SecurityBodyLayer integrado à gateway, cargas de ataque bloqueadas na camada da gateway. **13.5 Segurança CDN (M12)**: plano gratuito Cloudflare — HTTPS completo + WAF de dupla camada (regras gerenciadas na borda + detecção de camada de aplicação da gateway); origem Tunnel sem exposição pública; callbacks via subdomínio somente DNS direto para não perder pedidos em falha de CDN; limite de taxa por X-API-Key, independente de IPs de borda CDN; endpoints autenticados sempre no-store contra mistura de cache entre usuários. **13.6 Gerenciamento de chaves de provedores CDN (M13)**: perfis de provedores CDN (access_key / access_secret / lista de domínios) na tabela `logistics_cdn_provider`, campos secretos criptografados via `Encryptable`, manutenção via CRUD `/admin/cdn/provider`.
