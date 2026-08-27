> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](SECURITY.md) | [English](SECURITY.en.md) | [한국어](SECURITY.ko.md) | [Русский](SECURITY.ru.md) | [Deutsch](SECURITY.de.md) | [Français](SECURITY.fr.md) | [Español](SECURITY.es.md) | [Português](SECURITY.pt.md) | [हिन्दी](SECURITY.hi.md) | [العربية](SECURITY.ar.md) | [বাংলা](SECURITY.bn.md) | [Bahasa Indonesia](SECURITY.id.md) | [日本語](SECURITY.ja.md)

# Document de conception de l'architecture de sécurité

## 1. Panorama de la défense en profondeur

Le système adopte un modèle de défense en profondeur à 7 couches, filtrant les requêtes malveillantes de l'extérieur vers l'intérieur, afin qu'une défaillance de n'importe quelle couche soit toujours couverte par les lignes de défense suivantes.

Toute la chaîne de middlewares s'exécute dans l'ordre suivant (voir `config/middleware.php`) :

```
请求 → Cors → Locale(Accept-Language) → SecurityMiddleware (erikwang2013/security-php, 31种检测器) → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Couche | Middleware/mécanisme | Objectif de protection |
|----|--------|---------|
| 1 | SecurityMiddleware (erikwang2013/security-php) | 31 détecteurs d'attaques + validation des méthodes HTTP + limitation de la taille du corps de requête + validation Content-Type + CSRF + liste noire d'escalade d'IP attaquantes |
| 2 | Cors | Sécurité CORS + injection des en-têtes de sécurité de réponse |
| 3 | RateLimit | Limitation de débit par fenêtre glissante Redis, contre le brute force |
| 4 | AdminAuth | Authentification JWT + liste noire de déconnexion |
| 5 | AdminPermission | Autorisation RBAC à la granularité method.path |
| 6 | OperationLog | Audit des opérations + traçage de la source |
| 7 | Chiffrement des données | Confusion des ID Hashids + chiffrement DB Encryptable + chiffrement de transmission EncryptionService |

Le frontend à trois couches (Flutter) dispose de ses propres validations d'entrée ; le backend ne fait confiance à rien, chaque couche se défend indépendamment.

---

## 2. Moteur de détection des attaques

## 2. Moteur de détection des attaques (erikwang2013/security-php)

La détection des attaques a été migrée du SecurityMiddleware maison (erikwang2013/security-php) vers le paquet de sécurité dédié `erikwang2013/security-php` v1.1+, fournissant **31 détecteurs** couvrant 5 grandes catégories d'attaques.

### 2.1 Classification des détecteurs

**Attaques par injection (11 types) :** XSS, injection SQL, injection de commandes, injection NoSQL, injection LDAP, injection XPath, JNDI/Log4Shell, inclusion côté serveur SSI, injection GraphQL, injection de modèles SSTI

**Attaques par protocole et par requête (9 types) :** SSRF, XXE, injection d'en-têtes de réponse HTTP, attaque d'en-tête Host, Request Smuggling, Open Redirect, contournement CORS, détournement WebSocket, DNS Rebinding

**Validations de la couche protocole HTTP (6 types) :** validation des méthodes HTTP (405), limitation de la taille du corps de requête (413), validation Content-Type (415), vérification de l'origine CSRF, liste noire d'escalade d'IP attaquantes, détection de fuite de données sensibles

**Attaques sur les données et la sérialisation (5 types) :** désérialisation PHP, injection de formules CSV, injection d'en-têtes de courriel, attaques JWT (analyse structurée), pollution du prototype JS

**Attaques sur les fichiers et les chemins (2 types) :** traversée de chemin, upload de fichiers malveillants

### 2.2 Modes de traitement

Chaque détecteur prend indépendamment en charge deux modes :
- `block` — intercepter dès la détection d'une attaque, renvoyer le code de statut configuré
- `log` — journaliser uniquement sans intercepter (`header_injection`, `ssti`, `nosql_injection` sont en mode log par défaut pour éviter les faux positifs)

### 2.3 Liste noire d'escalade d'IP attaquantes

Une même IP déclenchant 5 détections d'attaque en 60 secondes → bannissement automatique de 15 minutes. Le backend de stockage peut être Redis (distribué), File (JSON mono-machine) ou Cache (fichier indépendant haute concurrence) ; la configuration actuelle utilise le stockage Redis.

### 2.4 Journal de sécurité

Emplacement du fichier : `runtime/logs/security.log` (rotation automatique, 10 Mo/fichier)

---

## 4. En-têtes de sécurité de réponse

Tous les en-têtes sont injectés dans le middleware `Cors` et ajoutés à chaque réponse via `$response->withHeaders()`.

| En-tête | Valeur | Rôle |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Autorise le CORS depuis toute origine (scénario de panneau d'administration en intranet) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Ensemble des méthodes autorisées |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | En-têtes personnalisés autorisés |
| Access-Control-Max-Age | `86400` | Cache des requêtes de pré-vérification pendant 24 heures |
| X-Content-Type-Options | `nosniff` | Interdit le sniffing MIME par le navigateur |
| X-Frame-Options | `DENY` | Interdit tout embarquement iframe, contre le clickjacking |
| X-XSS-Protection | `1; mode=block` | Active le filtre XSS intégré du navigateur et bloque le rendu de la page |
| Referrer-Policy | `strict-origin-when-cross-origin` | URL complète en même origine, seul le domaine en cross-origin |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Désactive les API caméra/micro/localisation sur tout le site |

Les requêtes de pré-vérification OPTIONS renvoient directement une réponse vide 204, sans entrer dans la suite de la chaîne de middlewares.

### 4.2 Content-Security-Policy (CSP)

Injectée avec les autres en-têtes de sécurité dans le middleware Cors, elle offre une défense en profondeur en limitant les sources de ressources que le navigateur peut charger et exécuter.

| En-tête | Valeur | Rôle |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Limite les sources des scripts/styles/images/connexions/cadres/formulaires et autres ressources |
| X-Permitted-Cross-Domain-Policies | `none` | Interdit le chargement de fichiers de politiques cross-domain Adobe Flash/PDF etc. |

Points essentiels de la politique CSP :
- `default-src 'self'` : seules les ressources de même origine sont autorisées par défaut
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'` : scripts de même origine + scripts en ligne (indispensables à Flutter Web) + eval (indispensables au débogage Flutter Web)
- `frame-ancestors 'none'` : interdit tout embarquement iframe dans une page, double protection avec X-Frame-Options: DENY
- `base-uri 'self'` : limite la balise `<base>` à la même origine
- `form-action 'self'` : limite la soumission des formulaires à la même origine

---

## 5. Stratégie de limitation de débit

### Algorithme

Fenêtre glissante Redis Sorted Set + script Lua atomique, opérations clés :

```lua
-- 1. 清理窗口外的旧记录
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. 检查当前窗口计数
local count = redis.call('ZCARD', KEYS[1])
-- 3. 超限则返回 {0, count}，未超限则 ZADD 并返回 {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- 随机后缀避免同毫秒覆盖
redis.call('EXPIRE', KEYS[1], window + 10)
```

Le script Lua s'exécute en monothread côté serveur Redis, **naturellement atomique**, éliminant la course TOCTOU (Time-of-check to Time-of-use).

### Configuration de la limitation de débit

| Route | Limite | Fenêtre | Scénario |
|------|------|------|------|
| Défaut (toutes les routes) | 60 requêtes/minute | 60 s | API générale |
| `/api/auth/login` | 10 requêtes/minute | 60 s | Connexion (contre le brute force) |
| `/api/auth/register` | 5 requêtes/minute | 60 s | Inscription (contre l'inscription de masse) |

### En-têtes de réponse

Lors du déclenchement de la limitation, un HTTP 429 avec un corps JSON est renvoyé :
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

Toutes les réponses (y compris normales) portent les en-têtes suivants :

| En-tête | Description |
|----|------|
| X-RateLimit-Limit | Nombre maximal de requêtes autorisées dans la fenêtre courante |
| X-RateLimit-Remaining | Nombre de requêtes restantes dans la fenêtre courante |
| X-RateLimit-Reset | Horodatage Unix de réinitialisation de la fenêtre |
| Retry-After | Présent uniquement lors de la limitation, secondes d'attente recommandées |

### Stratégie de repli

En cas d'anomalie Redis (timeout de connexion, indisponibilité, etc.), **fail-open** :

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis down, 放行所有请求
}
```

Mieux vaut perdre brièvement la protection de limitation de débit que bloquer les requêtes métier normales.

### 5.4 Mécanisme de verrouillage du compte

L'interface de connexion, en plus de la limitation de débit, ajoute un mécanisme de **verrouillage du compte** pour empêcher le brute force ciblé sur un utilisateur spécifique.

**Flux de verrouillage** :

```
登录失败 → Redis INCR account_lockout:{userId} TTL=900s
连续 5 次失败 → Redis SETEX account_locked:{userId} 900 1
            → 返回 429 "账号已被锁定，请15分钟后再试"
            → 清除计数器 DEL account_lockout:{userId}
```

**Comportement pendant le verrouillage** :

Pendant le verrouillage, toutes les requêtes de connexion renvoient directement 429 sans vérification du mot de passe, bloquant complètement les tentatives de brute force.

**Constantes de configuration** :

| Constante | Valeur | Signification |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Nombre maximal d'échecs consécutifs |
| LOCKOUT_DURATION | 900 | Durée du verrouillage (secondes), soit 15 minutes |

Remarque : le verrouillage du compte est basé sur `userId` et non sur l'IP ; changer d'IP ne permet donc pas de contourner le verrouillage. Combiné à la limitation de débit IP (10/minute), il forme une double protection :
- Au niveau IP : la limitation de débit à 10 requêtes/minute bloque le brute force distribué
- Au niveau du compte : le verrouillage après 5 échecs bloque le brute force ciblé

---

## 6. Authentification et autorisation

### 6.1 Authentification JWT

Implémentée par le middleware AdminAuth, montée sur les groupes de routes nécessitant une authentification.

**Configuration des paramètres** (`config/plugin/erikwang2013/jwt/jwt`, injectée via `.env`) :

| Paramètre | Valeur | Description |
|------|-----|------|
| Algorithme | HS256 | Signature symétrique HMAC-SHA256 |
| Clé | `JWT_SECRET` | Injectée par variable d'environnement, à remplacer en production |
| TTL access_token | 7200 s (2 h) | `JWT_TTL` |
| TTL refresh_token | 1209600 s (14 j) | `JWT_REFRESH_TTL` |
| Émetteur | `open-admin` | `JWT_ISSUER` |
| Audience | `open-admin` | `JWT_AUDIENCE` |

**Extraction du jeton** : extrait de l'en-tête `Authorization: Bearer <token>`, le préfixe `Bearer ` est retiré pour obtenir le JWT brut.

**Flux d'authentification** :
1. Jeton vide → 401 direct `{"code": 401, "message": "未登录"}`
2. Vérification de la liste noire Redis `jwt_blacklist:{md5(token)}` → correspondance → 401 `Token已失效，请重新登录`
3. Décode JWT → échec (expiré/signature non conforme) → 401 `Token已过期或无效`
4. Succès → injection de `$request->adminId` et `$request->adminUsername`

**Mécanisme de liste noire** : à la déconnexion, `md5(token)` est écrit dans Redis, TTL = durée de validité restante du JWT. En cas de panne Redis, la vérification de la liste noire est ignorée (fail-open) ; le jeton déconnecté peut alors être utilisé temporairement, mais la courte validité du JWT lui-même (2 h) sert de protection de secours.

### 6.2 Limitation des sessions concurrentes

Pour empêcher l'abus multi-appareils après une fuite de jeton, le système limite le nombre de jetons valides détenus simultanément par un même utilisateur.

**Logique de limitation** :

```
登录成功 → 签发新 Token
         → 查询当前用户有效 Token 数量: Redis SCARD user_tokens:{userId}
         → 若数量 >= 3（MAX_CONCURRENT_SESSIONS）:
            → 按创建时间升序排列，移除最旧的 Token:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → 将新 Token 加入集合: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Constantes de configuration** :

| Constante | Valeur | Signification |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Nombre maximal de jetons concurrents par utilisateur |

**Scénario d'expulsion** : lorsque l'utilisateur se connecte sur un 4e appareil, le jeton du 1er appareil est forcé en liste noire ; les requêtes suivantes renvoient 401 « Token已失效，请重新登录 ».

À la déconnexion, le jeton courant est retiré de l'ensemble. À l'expiration naturelle du jeton, la clé Redis s'invalide automatiquement et les membres de l'ensemble diminuent en conséquence.

### 6.3 Modèle de permissions RBAC

Implémenté par le middleware AdminPermission.

**Modèle de données** : association à trois niveaux User -> Role -> Permission

- `logistics_admin_user` (table des utilisateurs)
- `logistics_admin_user_role` (table de liaison utilisateurs-rôles)
- `logistics_admin_role` (table des rôles)
- `logistics_admin_role_permission` (table de liaison rôles-permissions)
- `logistics_admin_permission` (table des permissions)

**Types de permissions** :
| type | Signification | Exemple |
|------|------|------|
| 1 | Permission de menu | Contrôle la visibilité de la navigation de gauche |
| 2 | Permission de bouton | Contrôle les boutons d'action de la page (ajouter/modifier/supprimer) |
| 3 | Permission API | Contrôle l'appel des interfaces backend |

Format de l'identifiant de permission API : `{method}.{path}`

Par exemple :
- `post.admin/user` — créer un utilisateur
- `put.admin/user` — modifier un utilisateur
- `delete.admin/user` — supprimer un utilisateur
- `get.admin/user` — consulter la liste des utilisateurs

**Flux d'autorisation** :
1. `$request->adminId` vide → laisser passer (la route n'a pas d'authentification en préalable)
2. Obtenir l'utilisateur → les rôles (en sautant les rôles désactivés avec `status=0`) → la liste des permissions
3. Super administrateur (`slug = '*'`) → laisser passer directement
4. Construire `strtolower(method) . '.' . trim(path, '/')` → comparer avec la liste des permissions
5. Échec de correspondance → 403 `{"code": 403, "message": "无权限访问"}`

**Double confirmation** : BaseController fournit la méthode `confirmPassword()`. Les opérations sensibles (suppression d'utilisateur, export de données, etc.) exigent en plus la saisie du mot de passe courant au niveau Controller, pour empêcher les opérations non autorisées après un détournement de session.

---

## 7. Journaux d'audit

### 7.1 Journaux d'opérations

Le middleware OperationLog enregistre automatiquement les journaux d'opérations pour les requêtes POST / PUT / DELETE. Les requêtes GET ne sont pas enregistrées.

**Champs enregistrés** :

| Champ | Source | Description |
|------|------|------|
| id | SnowflakeService::generate() | ID globalement unique |
| user_id | `$request->adminId` | ID de l'opérateur, 0 si non connecté |
| action | `$request->method()` | Équivalent à method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Chemin de la requête |
| ip | `$request->getRealIp()` | IP réelle du client |
| source | detectSource() | Plate-forme source du client |
| input | Corps de la requête (JSON masqué) | Données soumises par l'opération |
| created_at | `date('Y-m-d H:i:s')` | Heure de l'opération |

**Filtrage des champs sensibles** : parcours récursif du corps de requête, la valeur des champs suivants est remplacée par `***` :

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Détection de la source** (`detectSource()`) : par ordre de priorité :

1. Lire d'abord l'en-tête personnalisé `X-Client-Platform` (déclaration explicite des clients natifs)
2. Repli sur l'inférence par la chaîne User-Agent (ordre de détection de la méthode `detectSource()`) :

| Plate-forme | Mot-clé UA |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Valeur par défaut de repli |

**Tolérance aux pannes** : une anomalie d'écriture du journal ne bloque pas la requête métier (`catch (\Throwable)` avalé silencieusement).

### 7.2 Journal de sécurité

**Emplacement du fichier** : `runtime/logs/security.log`

**Contenu enregistré** :
- Journaux d'interception d'attaques : catégorie d'attaque, IP, chemin, champ, source, extrait de payload (200 premiers caractères)
- Notifications de bannissement IP : IP bannie, nombre de déclenchements

Les permissions du journal sont `FILE_APPEND | LOCK_EX`, garantissant une écriture concurrente sécurisée.

---

## 8. Protection des données

Le système adopte une stratégie de protection des données en trois couches, correspondant aux trois phases du flux de données.

### 8.1 Couche de transport — EncryptionService

`EncryptionService` utilise le paquet `erikwang2013/encryption` pour chiffrer/déchiffrer les champs sensibles des requêtes/réponses API.

**Détails techniques** :
- Algorithme : `aes-256-cbc-hmac` (avec signature HMAC intégrée contre la falsification)
- Clé : variable d'environnement `ENCRYPTION_KEY`, alignée automatiquement sur 32 octets
- Usage : transmission entre le client et l'API des champs tels que numéro de téléphone, numéro de carte d'identité

**Méthodes utilitaires de masquage** :
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (nom d'utilisateur de plus de 2 caractères) ou `a**@example.com`

### 8.2 Couche de stockage — Cast Encryptable

Le modèle `AdminUser` utilise le cast Eloquent `Erikwang2013\Encryptable\Encryptable`, champs correspondants :

- `email` → cast Encryptable, chiffrement/déchiffrement automatique
- `phone` → cast Encryptable, chiffrement/déchiffrement automatique
- `id_card` → cast Encryptable, chiffrement/déchiffrement automatique

À l'écriture en base de données, les données sont automatiquement chiffrées en texte chiffré ; à la lecture, déchiffrées automatiquement en clair. La colonne de stockage est de type `VARCHAR(500)`, le texte chiffré étant stocké en base64.

**Système de clés** : indépendant du chiffrement de la couche de transport (`ENCRYPTION_KEY`), la couche de stockage utilise `ENCRYPTABLE_KEY` ; la fuite d'une clé ne compromet pas l'autre couche.

Rotation des clés : la variable d'environnement `ENCRYPTION_PREVIOUS_KEYS` prend en charge une liste de clés historiques (séparées par des virgules) ; à la lecture d'anciennes données, les clés historiques sont essayées pour déchiffrer ; à la réécriture, la clé courante est utilisée pour rechiffrer.

### 8.3 Couche d'affichage — Confusion des ID et masquage

**Confusion des ID Hashids** : `HashidsService` utilise le paquet `erikwang2013/hashids`.

- Les ID BIGINT de la base de données renvoyés par l'API externe sont encodés en chaînes hash (par exemple `xK3mN9qR2pL7wV8b`)
- Le client transmet la chaîne hash dans ses requêtes, le backend décode automatiquement vers l'ID d'origine
- Le sel `HASHIDS_SALT` est injecté par variable d'environnement ; un sel différent donne des résultats d'encodage/décodage complètement différents
- Longueur minimale du hash : 16 caractères, jeu de caractères alphanumériques de 62 symboles
- BaseController fournit les méthodes pratiques `encodeId()`, `decodeId()`, `encodeIds()`

**Masquage à l'export** : lors des exports Excel/PDF (ExportController), les champs sensibles sont uniformément masqués :
- Numéro de téléphone : `138****1234`
- E-mail : `a***@example.com`
- Carte d'identité : entièrement couverte par `********`

---

## 9. Gestion des clés

Toutes les clés sont injectées via les variables d'environnement `.env` ; les fichiers de configuration les lisent avec `getenv()` et intègrent des valeurs de repli par défaut (sûres uniquement en développement).

| Variable d'environnement | Usage | Paquet | Exigence de production |
|----------|------|-----|---------|
| JWT_SECRET | Clé de signature JWT | erikwang2013/jwt-webman | Chaîne aléatoire de 64+ caractères |
| JWT_ALGORITHM | Algorithme de signature JWT | Idem | Conserver HS256 |
| HASHIDS_SALT | Sel d'encodage des ID | erikwang2013/hashids | Chaîne aléatoire |
| SNOWFLAKE_DATACENTER_ID | ID du centre de données (0-31) | erikwang2013/snowflake-php | Conserver la valeur par défaut en mono-salle |
| ENCRYPTION_KEY | Clé de chiffrement de la couche de transport API | erikwang2013/encryption | Chaîne aléatoire de 32 octets |
| ENCRYPTABLE_KEY | Clé de chiffrement de la couche de stockage DB | erikwang2013/encryptable | Chaîne aléatoire de 32 octets, différente de la clé de transport |

**Exigences de sécurité** :
- Le fichier `.env` est ajouté au `.gitignore`, sa soumission au dépôt est strictement interdite
- `.env.example` est un fichier modèle public, ne contenant aucune clé réelle
- En production, **toutes** les clés par défaut doivent être remplacées par des chaînes aléatoires
- Utilisation recommandée de `openssl rand -base64 32` pour générer les clés

### Isolation du stockage des clés

| Couche | Clé de configuration | Variable d'environnement de la clé |
|----|--------|-------------|
| Chiffrement de transport | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Chiffrement de stockage | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| Confusion des ID | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| Signature JWT | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

Le système fournit un point de terminaison de coordonnées de sécurité conforme à la norme RFC 9116 à `/.well-known/security.txt`, permettant aux chercheurs en sécurité de trouver rapidement un canal de signalement en cas de découverte de vulnérabilité.

**Mode d'accès** :

```
GET /.well-known/security.txt
```

**Contenu de la réponse** :

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Description des champs** :

| Champ | Description |
|------|------|
| Contact | Coordonnées de signalement des vulnérabilités de sécurité |
| Expires | Date d'expiration du fichier, à mettre à jour régulièrement |
| Preferred-Languages | Langues de communication préférées |
| Canonical | URL canonique de ce fichier |
| Policy | Lien vers la politique de sécurité / de divulgation des vulnérabilités |

Ce point de terminaison n'est soumis à aucune restriction de débit ni à aucune authentification ; tout le monde peut y accéder directement.

---

## 11. Configuration de sécurité Nginx

Le projet fournit `docs/nginx-security.conf` comme configuration de référence pour le renforcement de la sécurité du proxy inverse Nginx en production.

**Mesures de sécurité incluses** :

| Élément de configuration | Rôle |
|--------|------|
| `server_tokens off` | Masque le numéro de version Nginx |
| `client_max_body_size 10m` | Limite la taille du corps de requête, en coordination avec SecurityMiddleware (erikwang2013/security-php) |
| `limit_req_zone` | Limitation de la fréquence des requêtes au niveau Nginx |
| `limit_conn_zone` | Limitation du nombre de connexions concurrentes |
| En-têtes de sécurité `add_header` | Ajoute X-XSS-Protection et d'autres en-têtes de sécurité au niveau Nginx |
| `if ($request_method)` | Rejette les méthodes HTTP non standard au niveau Nginx |
| Configuration SSL/TLS | Configuration moderne TLS 1.2/1.3, désactivation des suites de chiffrement faibles |
| Masquage des en-têtes backend | `proxy_hide_header` supprime les en-têtes sensibles tels que la version webman |

**Mode d'utilisation** : fusionnez la configuration de `docs/nginx-security.conf` dans votre bloc server Nginx, en ajustant selon votre domaine et le chemin des certificats.

---

## 12. Modèle de menaces

### 12.1 Menaces couvertes

| Type de menace | Vecteur d'attaque | Niveau de défense |
|----------|---------|---------|
| Abus de méthodes HTTP | Attaques XST TRACE/TRACK, proxy tunnel CONNECT, sondage de méthodes WebDAV | Liste blanche de méthodes 405 du détecteur http_method de SecurityMiddleware |
| Brute force ciblé | Tentatives répétées de mot de passe sur un utilisateur spécifique | Verrouillage du compte (5 échecs ⇒ verrouillage de 15 min) + RateLimit (connexion 10/min) + Captcha |
| Brute force | Tentatives répétées de nom d'utilisateur/mot de passe depuis des IP distribuées | RateLimit (connexion 10/min) + Captcha |
| XSS (scripting cross-site) | `<script>`, onerror, javascript: | SecurityMiddleware (erikwang2013/security-php) (5 modes) + en-tête de réponse X-XSS-Protection + CSP |
| Injection SQL | UNION SELECT, OR 1=1, contournement par commentaires | SecurityMiddleware (erikwang2013/security-php) (6 modes) + requêtes paramétrées Eloquent ORM |
| CSRF (falsification de requête cross-site) | Sites malveillants émettant des requêtes | Validation Origin/Referer par SecurityMiddleware (erikwang2013/security-php) |
| Traversée de chemin | `../../etc/passwd` | Mode traversée de chemin de SecurityMiddleware (erikwang2013/security-php) + liste blanche d'extensions d'UploadController |
| Injection de commandes | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityMiddleware (erikwang2013/security-php) (4 modes) |
| Détournement de session | Vol du jeton JWT | JWT à courte validité (2 h) + liste noire de déconnexion + double confirmation du mot de passe pour les opérations sensibles |
| Énumération des ID | Parcourir les ID numériques pour deviner le volume de données | Confusion Hashids en chaînes aléatoires |
| Fuite de données | Exfiltration de la base / homme du milieu / fuite de journaux | Chiffrement/masquage en trois couches + filtrage des champs sensibles d'OperationLog |
| Attaques DoS | Corps de requête surdimensionnés / requêtes haute fréquence | Limite du corps de requête 10 Mo + RateLimit 60/min + liste noire IP |
| Élévation de privilèges | Des utilisateurs à faibles privilèges accèdent aux interfaces d'administration | Autorisation RBAC à la granularité method.path |
| Attaque d'upload de fichiers | shell.php.png à double extension | Détection de fichiers malveillants par SecurityMiddleware (erikwang2013/security-php) |

### 12.2 Limites connues

| Limite | Périmètre d'impact | Mesure d'atténuation |
|------|---------|---------|
| La protection CSRF n'est efficace que pour les navigateurs | Les clients non navigateur (curl, Postman, apps mobiles) peuvent contourner la vérification Origin/Referer | Les clients non navigateur ne sont naturellement pas exposés au CSRF ; l'authentification JWT remplace les cookies |
| En cas d'indisponibilité Redis, la limitation de débit et la liste noire se replient en fail-open | Les attaquants peuvent contourner la limitation de débit et l'interception haute fréquence | Surveiller la disponibilité Redis avec alertes ; la liste noire IP prend en charge les trois backends file/redis/cache avec repli |
| Pas de moteur WAF indépendant | Détection basée sur des correspondances regex, non un moteur de règles WAF dédié | En production, placer en amont Nginx ModSecurity ou Cloudflare WAF |
| Le JWT sans état ne peut pas être révoqué activement | Avant expiration, le jeton ne peut pas être révoqué côté serveur (sauf liste noire) | La liste noire + un TTL court de 2 h réduisent la fenêtre de risque |
| Aucune limitation de débit spécifique pour les points de terminaison d'administration | Les interfaces d'administration partagent la limite par défaut de 60/min avec les interfaces classiques | La fréquence des opérations d'administration est naturellement faible, aucune distinction nécessaire pour l'instant |
| Limite de backtracking PCRE | Le paquet intègre une limite de backtracking de 1 000 000 avec restauration finally ; les entrées extrêmement complexes présentent encore un risque de performance | La limite de taille du corps de requête (10 Mo) sert de filet de sécurité |
