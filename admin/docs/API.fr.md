> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](API.md) | [English](API.en.md) | [한국어](API.ko.md) | [Русский](API.ru.md) | [Deutsch](API.de.md) | [Français](API.fr.md) | [Español](API.es.md) | [Português](API.pt.md) | [हिन्दी](API.hi.md) | [العربية](API.ar.md) | [বাংলা](API.bn.md) | [Bahasa Indonesia](API.id.md) | [日本語](API.ja.md)

# Référence de l'API

## 1. Vue d'ensemble

Open Admin (open-admin) est construit sur webman v2 et fournit une API RESTful JSON. Toutes les interfaces du panneau d'administration nécessitent une authentification JWT et une validation des permissions RBAC ; les interfaces publiques sont routées vers des contrôleurs versionnés via l'en-tête de version API.

- **URL de base** : `http://localhost:8787`
- **Version API** : contrôlée par l'en-tête `API-Version: v1` (v1 par défaut si absent)
- **Langue** : bascule via l'en-tête `Accept-Language` ou le paramètre `?lang=zh_CN|en` (zh_CN par défaut), détection automatique par le middleware Locale

> **Vue d'ensemble des points de terminaison** : authentification (5) | tableau de bord (1) | utilisateurs (7) | rôles (4) | permissions (4) | configuration (4) | journaux (1) | espace personnel (3) | import/export (3) | upload (1) | exploitation (4 : health/metrics/docs/security.txt) | 37 points de terminaison au total
- **Authentification** : `Authorization: Bearer <token>` (JWT)
- **Format de réponse** : `{ "code": 0, "message": "success", "data": {...} }`
- **Point de terminaison de documentation** : `GET /api/docs` renvoie la spécification OpenAPI 3.0 JSON

### Exigences des requêtes

- Seules les méthodes `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` sont autorisées ; toute autre méthode HTTP (comme TRACE, CONNECT, PATCH) renvoie 405
- Toutes les requêtes `POST` / `PUT` doivent définir `Content-Type: application/json` (sauf upload de fichiers), sinon 415 est renvoyé
- La taille du corps de requête ne doit pas dépasser 10 Mo, sinon 413 est renvoyé
- Le filtre de sécurité analyse toutes les entrées de requête contre XSS, l'injection SQL, la traversée de chemin et l'injection de commandes ; en cas de détection, 403 est renvoyé
- 5 échecs de connexion consécutifs déclenchent le verrouillage du compte (15 minutes) ; pendant le verrouillage, les requêtes de connexion renvoient 429
- Un même utilisateur peut détenir au maximum 3 jetons valides simultanément ; au-delà, le jeton le plus ancien est automatiquement ajouté à la liste noire

## 2. Codes d'erreur

| code | Signification | Scénario de déclenchement |
|------|------|---------|
| 0 | Succès | |
| 400 | Erreur de paramètre de requête | Format de requête incorrect |
| 401 | Non authentifié | Jeton absent / expiré / en liste noire |
| 403 | Accès refusé / interception de sécurité | Permissions RBAC insuffisantes / déclenchement du SecurityFilter |
| 404 | Ressource introuvable | La cible de la consultation/mise à jour/suppression n'existe pas |
| 405 | Méthode de requête non autorisée | Seuls GET/POST/PUT/DELETE/OPTIONS/HEAD sont autorisés, les méthodes non standard sont directement rejetées |
| 413 | Corps de requête trop volumineux | Content-Length dépasse 10 Mo |
| 415 | Type de média non pris en charge | Le Content-Type des requêtes POST/PUT n'est ni JSON ni un upload de fichier |
| 422 | Échec de la validation des paramètres | Champ obligatoire manquant, format invalide, validation métier non conforme |
| 429 | Trop de requêtes | Déclenchement du RateLimit / verrouillage du compte (5 échecs de connexion consécutifs ⇒ verrouillage de 15 minutes) |
| 500 | Erreur interne du serveur | |

## 3. Points de terminaison publics

Tous les points de terminaison publics sont montés sous le groupe `/api` et sont distribués par le middleware `ApiVersion` aux contrôleurs versionnés correspondants selon l'en-tête `API-Version` (par exemple `app\api\v1\controller\AuthController`).

### 3.1 Health check

```
GET /health
```

- **Authentification** : aucune
- **Limitation de débit** : aucune

**Exemple de réponse** :
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

Valeurs possibles pour `database`, `redis`, `elasticsearch` : `"ok"` | `"unavailable"`. `elasticsearch` renvoie `"unavailable"` si ES est injoignable ; si l'état de santé du cluster n'est ni green ni yellow, la valeur réelle du statut est renvoyée (par exemple `"red"`).

### 3.2 Documentation API

```
GET /api/docs
```

- **Authentification** : aucune
- **Limitation de débit** : défaut global (60 requêtes/minute)
- **Réponse** : spécification OpenAPI 3.0.3 JSON, comprenant les définitions de tous les points de terminaison, paramètres et schémas

### 3.3 Génération du captcha

```
POST /api/captcha/generate
```

- **Authentification** : aucune
- **En-tête de requête** : `API-Version: v1` (obligatoire)
- **Limitation de débit** : défaut global (60 requêtes/minute)

**Corps de requête** :
```json
{
  "difficulty": "medium"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| difficulty | string | Non | `easy` / `medium` / `hard`, défaut `medium` |

**Exemple de réponse** — type clic (`type: "click"`) :
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

**Exemple de réponse** — type curseur (`type: "slider"`) :
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

**Exemple de réponse** — type rotation (`type: "rotate"`) :
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

| Champ | Type | Description |
|------|------|------|
| key | string | Identifiant du captcha, à renvoyer lors de la validation |
| type | string | Type de captcha : `click` / `slider` / `rotate` |
| image | string | Image en data URI base64 |
| extra | object | Données supplémentaires liées au type (voir ci-dessous) |

**`extra` selon le type** :

| type | Champs extra | Type | Description |
|------|-----------|------|------|
| click | targets | array | Cibles de clic, contenant `order` (ordre) `text` (texte indicatif) `x` `y` (coordonnées) |
| slider | x, y | int | Coordonnées du coin supérieur gauche de l'encoche (sur une toile de 300×200) |
| slider | puzzle_w, puzzle_h | int | Largeur/hauteur de l'image du puzzle |
| slider | puzzle | string | Image du puzzle en data URI base64 |
| rotate | angle | int | Angle de rotation correct (0-359), il faut tourner de `360-angle` pour redresser l'image |

### 3.4 Validation du captcha

```
POST /api/captcha/verify
```

- **Authentification** : aucune
- **En-tête de requête** : `API-Version: v1` (obligatoire)
- **Limitation de débit** : défaut global (60 requêtes/minute)

**Corps de requête** — type clic (`type: "click"`) :
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

**Corps de requête** — type curseur (`type: "slider"`) :
```json
{
  "key": "def456abc789",
  "type": "slider",
  "clicks": 120
}
```

**Corps de requête** — type rotation (`type: "rotate"`) :
```json
{
  "key": "ghi789abc012",
  "type": "rotate",
  "clicks": 315
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| key | string | Oui | Clé du captcha, renvoyée par generate |
| type | string | Oui | Type de captcha, doit correspondre au `type` renvoyé par generate |
| clicks | variante | Oui | Données de réponse, le format varie selon le type (voir ci-dessous) |

**`clicks` selon le type** :

| type | Type de clicks | Description | Tolérance d'erreur |
|------|------------|------|---------|
| click | `[{x:int, y:int}]` | Tableau de coordonnées de clic, dans l'ordre de `order` | Rayon de 18 px |
| slider | `int` | Décalage sur l'axe X du curseur | ±4 px |
| rotate | `int` | Angle de rotation (0-359) | ±5° |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

Après validation réussie, le backend écrit `captcha_verified:{key}` dans Redis (TTL 300 s), ce qui autorise l'endpoint de connexion.
En cas d'échec de validation, `code` vaut 422, `message` est `"验证失败，请重试"` et `data.valid` est `false`.

### 3.5 Connexion

```
POST /api/auth/login
```

- **Authentification** : aucune
- **En-tête de requête** : `API-Version: v1` (obligatoire)
- **Limitation de débit** : 10 requêtes/minute (par IP + chemin)

**Corps de requête** :
```json
{
  "username": "admin",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "captcha_key": "abc123def456"
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| username | string | Oui | min:3, max:50 | Nom d'utilisateur |
| password | string | Oui | min:6, max:32 (en clair) | Chiffré AES-256-CBC-HMAC puis encodé en Base64 (compatible texte en clair) |
| captcha_key | string | Oui | | Clé du captcha (doit d'abord être validée via `/api/captcha/verify`) |

### Protocole de chiffrement du mot de passe

Utilise le **chiffrement asymétrique RSA-2048**. La clé publique est stockée dans le code frontend (peut être exposée sans risque) ; la clé privée n'est détenue que par le serveur.

```
Processus de chiffrement (client) :
  Clé publique RSA (PEM) → chiffrement PKCS1v1.5 → encodage Base64 → transmission

Processus de déchiffrement (serveur, avec repli successif) :
  1. Déchiffrement RSA → succès et UTF-8 valide → utiliser le résultat déchiffré
  2. Déchiffrement AES-256-CBC-HMAC → succès → utiliser le résultat déchiffré (compatibilité anciens clients)
  3. Repli en texte clair → utiliser l'entrée d'origine
```

La clé publique est intégrée dans l'application frontend et n'a pas besoin d'être transmise par le réseau. La clé privée n'est stockée que dans `RSA_PRIVATE_KEY` du `.env` et ne doit pas être divulguée.

> Le chiffrement symétrique AES est une solution de compatibilité avec les anciennes versions et sera supprimé une fois tous les clients migrés vers RSA.

**Exemple de réponse** :
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

| Champ | Type | Description |
|------|------|------|
| access_token | string | Jeton d'accès JWT |
| refresh_token | string | Jeton de rafraîchissement JWT |
| expires_in | int | Durée de validité du jeton d'accès (secondes), 7200 par défaut |
| user.id | string | ID utilisateur chiffré hashid |
| user.username | string | Nom d'utilisateur |
| user.real_name | string | Nom réel |

**Erreurs possibles** :
- 422 : échec de la validation des paramètres (champ obligatoire manquant, format invalide)
- 422 : veuillez d'abord valider le captcha (captcha_key n'a pas passé `/api/captcha/verify`)
- 401 : nom d'utilisateur ou mot de passe incorrect
- 403 : compte désactivé
- 429 : compte verrouillé, réessayez dans 15 minutes (déclenché par 5 échecs de connexion consécutifs)

### 3.6 Inscription

```
POST /api/auth/register
```

- **Authentification** : aucune
- **En-tête de requête** : `API-Version: v1` (obligatoire)
- **Limitation de débit** : 5 requêtes/minute (par IP + chemin)

**Corps de requête** :
```json
{
  "username": "newuser",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "real_name": "新用户",
  "captcha_key": "abc123def456"
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| username | string | Oui | min:3, max:50 | Nom d'utilisateur (unique) |
| password | string | Oui | min:6, max:32 (en clair) | Chiffré AES-256-CBC-HMAC puis encodé en Base64 |
| real_name | string | Oui | max:50 | Nom réel |
| captcha_key | string | Oui | | Clé du captcha (doit d'abord être validée via `/api/captcha/verify`) |

**Exemple de réponse** :
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

Après inscription réussie, les jetons JWT sont directement renvoyés ; le compte est activé par défaut (status=1).

### 3.7 Rafraîchissement du jeton

```
POST /api/auth/refresh
```

- **Authentification** : aucune
- **En-tête de requête** : `API-Version: v1` (obligatoire)
- **Limitation de débit** : défaut global (60 requêtes/minute)

**Corps de requête** :
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| refresh_token | string | Oui | refresh_token obtenu à la connexion/inscription |

**Exemple de réponse** :
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

Un rafraîchissement réussi renvoie simultanément un nouveau access_token et un nouveau refresh_token ; l'ancien jeton est automatiquement invalidé. Le rafraîchissement met également à jour l'heure et l'IP de la dernière connexion de l'utilisateur.

**Erreurs possibles** :
- 422 : jeton de rafraîchissement manquant
- 401 : jeton de rafraîchissement invalide ou expiré

### 3.8 Métriques de surveillance Prometheus

```
GET /metrics
```

- **Authentification** : aucune
- **Limitation de débit** : aucune
- **Format de réponse** : format texte Prometheus (`text/plain; version=0.0.4`)

Point de terminaison public des métriques de surveillance Prometheus, à collecter par Grafana/Prometheus.

**Exemple de réponse** :
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

| Nom de la métrique | Type | Description |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Nombre total cumulé de requêtes HTTP |
| `openadmin_active_users` | gauge | Nombre d'utilisateurs actifs (connexion dans les 24 heures) |
| `openadmin_db_connection_status` | gauge | État de la connexion à la base de données, 1=normal, 0=anormal |
| `openadmin_redis_connection_status` | gauge | État de la connexion Redis, 1=normal, 0=anormal |
| `openadmin_memory_usage_bytes` | gauge | Mémoire actuellement utilisée par le processus PHP (octets) |

## 4. Tableau de bord

Toutes les interfaces du panneau d'administration sont montées sous le groupe `/admin` et passent par les trois middlewares `AdminAuth` (authentification JWT), `AdminPermission` (validation des permissions RBAC) et `OperationLog` (enregistrement des opérations).

### 4.1 Données du tableau de bord

```
GET /admin/dashboard
```

- **Authentification** : JWT + RBAC
- **Cache** : Redis 5 minutes

**Exemple de réponse** :
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

| Champ de stats | Type | Description |
|------|------|------|
| label | string | Nom de la métrique |
| value | string | Valeur de la métrique (type chaîne) |
| icon | string | Nom de l'icône Material |
| color | string | Couleur de la carte |
| trend | float? | Taux de croissance jour sur jour (pourcentage), présent uniquement pour « nombre total d'utilisateurs » |

| Champ de trends | Type | Description |
|------|------|------|
| dates | array{string} | Série de dates des 30 derniers jours |
| series | array{object} | Données de courbes de tendance, chaque élément contient name (nom), data (tableau de valeurs), color (couleur) |

## 5. Gestion des utilisateurs

Tous les `id` renvoyés par les interfaces de gestion des utilisateurs sont des chaînes chiffrées hashid. Le champ mot de passe est exclu des réponses. Le numéro de téléphone et l'e-mail sont masqués dans les interfaces de liste et renvoyés en clair dans les interfaces de détail (les champs chiffrés en base de données sont automatiquement déchiffrés par le trait Encryptable).

### 5.1 Liste des utilisateurs

```
GET /admin/user
```

- **Authentification** : JWT + RBAC

**Paramètres de requête** :

| Paramètre | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| page | int | Non | 1 | Numéro de page |
| limit | int | Non | 15 | Nombre d'éléments par page |
| keyword | string | Non | | Mot-clé de recherche, correspond au nom d'utilisateur et au nom réel |
| status | int | Non | | Filtre de statut, 0=désactivé, 1=activé |

**Exemple de réponse** :
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

| Champ | Type | Description |
|------|------|------|
| id | string | ID utilisateur chiffré hashid |
| username | string | Nom d'utilisateur |
| real_name | string | Nom réel |
| phone | string | Numéro de téléphone masqué (format `138****5678`) |
| email | string | E-mail masqué (format `a***@example.com`) |
| status | int | 1=activé, 0=désactivé |
| last_login_at | string | Heure de la dernière connexion (datetime) |
| created_at | string | Date de création (datetime) |

### 5.2 Création d'un utilisateur

```
POST /admin/user
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
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

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| username | string | Oui | min:3, max:50 | Nom d'utilisateur (unique) |
| password | string | Oui | min:6, max:32 | Mot de passe (stocké en bcrypt) |
| real_name | string | Oui | max:50 | Nom réel |
| phone | string | Non | | Numéro de téléphone (stocké chiffré avec Encryptable) |
| email | string | Non | | E-mail (stocké chiffré avec Encryptable) |
| status | int | Non | in:0,1 | Statut, défaut 1 (activé) |

**Exemple de réponse** :
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

**Erreurs possibles** :
- 422 : nom d'utilisateur déjà existant
- 422 : échec de la validation des paramètres (champ obligatoire manquant)

### 5.3 Détails d'un utilisateur

```
GET /admin/user/{id}
```

- **Authentification** : JWT + RBAC
- **Paramètre de chemin** : `{id}` est l'ID utilisateur chiffré hashid

**Exemple de réponse** :
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

Dans l'interface de détail, `phone` et `email` sont renvoyés en clair (stockés chiffrés en base de données, déchiffrés automatiquement par le cast Encryptable), sans masquage. `password` et `id_card` ne sont jamais inclus dans les réponses.

**Erreurs possibles** :
- 404 : utilisateur introuvable

### 5.4 Mise à jour d'un utilisateur

```
PUT /admin/user/{id}
```

- **Authentification** : JWT + RBAC
- **Paramètre de chemin** : `{id}` est l'ID utilisateur chiffré hashid

**Corps de requête** :
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| real_name | string | Non | Nom réel ; non transmis, conserve l'ancienne valeur |
| password | string | Non | Nouveau mot de passe ; chaîne vide ou non transmis = pas de modification |
| phone | string | Non | Numéro de téléphone |
| email | string | Non | E-mail |
| status | int | Non | 0=désactivé, 1=activé |

**Exemple de réponse** :
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

**Erreurs possibles** :
- 404 : utilisateur introuvable

### 5.5 Suppression d'un utilisateur

```
DELETE /admin/user/{id}
```

- **Authentification** : JWT + RBAC
- **Paramètre de chemin** : `{id}` est l'ID utilisateur chiffré hashid
- **Opération sensible** : confirmation du mot de passe requise

**Corps de requête** :
```json
{
  "password": "admin_password"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| password | string | Oui | Mot de passe de l'utilisateur connecté (double confirmation) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Exécute une suppression douce (Eloquent SoftDeletes) : les données sont marquées avec deleted_at sans suppression physique.

**Erreurs possibles** :
- 404 : utilisateur introuvable
- 422 : les opérations sensibles nécessitent la saisie du mot de passe pour confirmation (password vide)
- 422 : échec de la vérification du mot de passe (non concordance)

### 5.6 Suppression groupée d'utilisateurs

```
POST /admin/user/batch/destroy
```

- **Authentification** : JWT + RBAC
- **Opération sensible** : confirmation du mot de passe requise

**Corps de requête** :
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| ids | array{string} | Oui | Tableau d'ID utilisateurs chiffrés hashid |
| password | string | Oui | Mot de passe de l'utilisateur connecté (double confirmation) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Exécute une suppression douce ; `data.count` est le nombre réellement supprimé.

**Erreurs possibles** :
- 422 : veuillez sélectionner les utilisateurs à supprimer (ids vide)
- 422 : ID invalide (échec du décodage hashid)
- 422 : échec de la vérification du mot de passe

### 5.7 Activation/désactivation groupée d'utilisateurs

```
POST /admin/user/batch/status
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| ids | array{string} | Oui | Tableau d'ID utilisateurs chiffrés hashid |
| status | int | Oui | 0=désactivé, 1=activé |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

Le message varie dynamiquement selon la valeur de status : `"批量启用成功"` ou `"批量禁用成功"`.

**Erreurs possibles** :
- 422 : veuillez sélectionner des utilisateurs (ids vide)
- 422 : valeur de statut invalide (status n'est ni 0 ni 1)

## 6. Gestion des rôles

### 6.1 Liste des rôles

```
GET /admin/role
```

- **Authentification** : JWT + RBAC

**Paramètres de requête** :

| Paramètre | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| page | int | Non | 1 | Numéro de page |
| limit | int | Non | 15 | Nombre d'éléments par page |

**Exemple de réponse** :
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

| Champ | Type | Description |
|------|------|------|
| id | string | ID de rôle chiffré hashid |
| name | string | Nom du rôle |
| slug | string | Identifiant du rôle (unique, utilisé pour la validation des permissions) |
| description | string | Description du rôle |
| status | int | 1=activé, 0=désactivé |
| users_count | int | Nombre d'utilisateurs possédant ce rôle |

### 6.2 Création d'un rôle

```
POST /admin/role
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| name | string | Oui | max:50 | Nom du rôle |
| slug | string | Oui | max:50 | Identifiant du rôle |
| description | string | Non | | Description du rôle, chaîne vide par défaut |
| status | int | Non | | Statut, défaut 1 |
| permission_ids | array{int} | Non | | Tableau d'ID de permissions (ID INT bruts, pas des hashids) |

**Exemple de réponse** :
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

### 6.3 Mise à jour d'un rôle

```
PUT /admin/role/{id}
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| name | string | Non | Nom du rôle |
| description | string | Non | Description |
| status | int | Non | 0=désactivé, 1=activé |
| permission_ids | array{int} | Non | Tableau d'ID de permissions ; s'il est fourni, synchronise (remplace) les permissions du rôle |

**Exemple de réponse** :
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

### 6.4 Suppression d'un rôle

```
DELETE /admin/role/{id}
```

- **Authentification** : JWT + RBAC
- **Opération sensible** : confirmation du mot de passe requise

**Corps de requête** :
```json
{
  "password": "admin_password"
}
```

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

La suppression dissocie automatiquement le rôle de toutes ses permissions et de tous ses utilisateurs, puis supprime physiquement l'enregistrement du rôle.

## 7. Gestion des permissions

Les permissions adoptent une structure arborescente (auto-référence via parent_id) et se répartissent en trois types. L'interface de liste renvoie l'arbre complet des permissions.

### 7.1 Arbre des permissions

```
GET /admin/permission
```

- **Authentification** : JWT + RBAC

**Exemple de réponse** :
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

| Champ | Type | Description |
|------|------|------|
| id | string | Chiffré hashid |
| parent_id | string | hashid de la permission parente, « 0 » représente le nœud racine |
| name | string | Nom de la permission |
| slug | string | Identifiant de la permission (identifiant de route/bouton) |
| type | int | 1=menu, 2=bouton, 3=API |
| icon | string | Icône du menu (nom d'icône Material) |
| path | string | Chemin de routage frontend |
| sort | int | Valeur de tri (croissante) |
| children | array? | Liste des permissions enfants (récursive), champ absent si aucun nœud enfant |

### 7.2 Création d'une permission

```
POST /admin/permission
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
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

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| parent_id | int | Non | | ID de la permission parente (type INT brut), défaut 0 |
| name | string | Oui | max:50 | Nom de la permission |
| slug | string | Oui | max:100 | Identifiant de la permission |
| type | int | Oui | in:1,2,3 | 1=menu, 2=bouton, 3=API |
| icon | string | Non | | Icône du menu, vide par défaut |
| path | string | Non | | Chemin de routage frontend, vide par défaut |
| sort | int | Non | | Valeur de tri, défaut 0 |

**Exemple de réponse** :
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

### 7.3 Mise à jour d'une permission

```
PUT /admin/permission/{id}
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| name | string | Non | Nom de la permission |
| icon | string | Non | Icône |
| path | string | Non | Chemin de routage |
| sort | int | Non | Valeur de tri |

### 7.4 Suppression d'une permission

```
DELETE /admin/permission/{id}
```

- **Authentification** : JWT + RBAC
- **Opération sensible** : confirmation du mot de passe requise

**Corps de requête** :
```json
{
  "password": "admin_password"
}
```

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

La suppression supprime en cascade toutes les permissions enfants (enregistrements dont `parent_id` = ID de la permission courante), et dissocie simultanément toutes les associations avec les rôles.

## 8. Configuration système

La configuration système est unique par la combinaison `group` + `key`.

### 8.1 Liste des configurations

```
GET /admin/config
```

- **Authentification** : JWT + RBAC

**Paramètres de requête** :

| Paramètre | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| page | int | Non | 1 | Numéro de page |
| limit | int | Non | 15 | Nombre d'éléments par page |
| group | string | Non | | Filtre par groupe de configuration |

**Exemple de réponse** :
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

| Champ | Type | Description |
|------|------|------|
| id | string | hashid |
| group | string | Groupe de configuration (par exemple `system`, `email`, `storage`) |
| key | string | Clé de configuration |
| value | string | Valeur de configuration |
| type | string | Indication du type de valeur (`string`, `integer`, `boolean`, `json`, etc.) |
| description | string | Description de la configuration |

### 8.2 Création d'une configuration

```
POST /admin/config
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| group | string | Oui | max:100 | Groupe de configuration |
| key | string | Oui | max:100 | Clé de configuration (unique au sein d'un même groupe) |
| value | string | Oui | | Valeur de configuration |
| type | string | Non | | Type de valeur, défaut `string` |
| description | string | Non | | Description de la configuration, vide par défaut |

**Exemple de réponse** :
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

**Erreurs possibles** :
- 422 : élément de configuration déjà existant (même group + key)

### 8.3 Mise à jour d'une configuration

```
PUT /admin/config/{id}
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| value | string | Non | Met à jour la valeur de configuration |
| type | string | Non | Met à jour le type de valeur |
| description | string | Non | Met à jour le texte de description |

### 8.4 Suppression d'une configuration

```
DELETE /admin/config/{id}
```

- **Authentification** : JWT + RBAC
- **Opération sensible** : confirmation du mot de passe requise

**Corps de requête** :
```json
{
  "password": "admin_password"
}
```

Supprime physiquement l'enregistrement de configuration.

## 9. Journaux d'opérations

Les journaux d'opérations sont des interfaces en lecture seule, écrites automatiquement par le middleware `OperationLog` à chaque requête POST/PUT/DELETE. Les champs stockés incluent `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Liste des journaux d'opérations

```
GET /admin/log
```

- **Authentification** : JWT + RBAC

**Paramètres de requête** :

| Paramètre | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| page | int | Non | 1 | Numéro de page |
| limit | int | Non | 15 | Nombre d'éléments par page |
| user_id | int | Non | | Filtre exact par ID utilisateur (type INT brut) |
| action | string | Non | | Filtre exact par action |
| path | string | Non | | Filtre flou par chemin de requête |
| start_date | string | Non | | Date de début (format Y-m-d) |
| end_date | string | Non | | Date de fin (format Y-m-d) |

**Exemple de réponse** :
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

| Champ | Type | Description |
|------|------|------|
| id | string | hashid |
| user_name | string | Nom d'utilisateur de l'opérateur (obtenu via la relation user ; « système » pour les opérations non connectées) |
| action | string | Description de l'action effectuée |
| method | string | Méthode HTTP (POST/PUT/DELETE) |
| path | string | Chemin de la requête |
| ip | string | IP du client |
| source | string | Source de la requête |
| input | string | Chaîne JSON des paramètres de requête (sans les fichiers) |
| created_at | string | Heure de l'opération (datetime) |

## 10. Espace personnel

Les interfaces de l'espace personnel ne nécessitent que l'authentification JWT (pas de validation RBAC — le middleware `AdminPermission` doit les ajouter à sa liste blanche).

### 10.1 Mise à jour des informations personnelles

```
PUT /admin/profile
```

- **Authentification** : JWT

**Corps de requête** :
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| real_name | string | Non | Nom réel |
| phone | string | Non | Numéro de téléphone (stocké chiffré avec Encryptable) |
| email | string | Non | E-mail (stocké chiffré avec Encryptable) |

**Exemple de réponse** :
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

Dans la réponse, `phone` et `email` sont renvoyés en clair ; `password` et `id_card` sont exclus.

### 10.2 Modification du mot de passe

```
PUT /admin/profile/password
```

- **Authentification** : JWT

**Corps de requête** :
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| old_password | string | Oui | | Mot de passe actuel |
| new_password | string | Oui | min:6, max:32 | Nouveau mot de passe |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Erreurs possibles** :
- 422 : veuillez renseigner l'ancien et le nouveau mot de passe
- 422 : ancien mot de passe incorrect
- 422 : le nouveau mot de passe doit comporter 6 à 32 caractères

### 10.3 Déconnexion

```
POST /admin/profile/logout
```

- **Authentification** : JWT

**Corps de requête** : aucun (pas de requestBody, le jeton est lu dans l'en-tête Authorization)

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Logique de déconnexion : décodage du JWT pour obtenir la durée de validité restante (exp - now), puis écriture du hash md5 du jeton dans la liste noire Redis `jwt_blacklist:{md5}`, TTL = durée de validité restante. Les jetons en liste noire sont interceptés dans le middleware `AdminAuth` et renvoient 401.

Sans jeton, 401 est renvoyé. Si le jeton est expiré/invalide (l'exception levée lors du décodage), la déconnexion est tout de même considérée comme réussie.

## 11. Import et export

### 11.1 Export Excel

```
POST /admin/export/excel
```

- **Authentification** : JWT + RBAC
- **Type de réponse** : téléchargement de fichier (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Corps de requête** :
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

| Champ | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| table | string | Non | `admin_user` | Nom de la table à exporter. Pris en charge : `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | Non | | Tableau des noms de colonnes à exporter ; vide = toutes les colonnes de la table |
| conditions | object | Non | `{}` | Conditions de filtrage, paires clé-valeur ; une valeur non vide est utilisée pour WHERE |
| title | string | Non | `数据导出` | Titre de l'Excel (affiché comme nom de feuille) |

**Tables et colonnes prises en charge** :

| table | Colonnes disponibles |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Les champs sensibles `phone`, `email` et `id_card` sont automatiquement masqués lors de l'export. Limite de données : 10 000 lignes. Première ligne de l'Excel figée, filtre automatique activé.

### 11.2 Export PDF

```
POST /admin/export/pdf
```

- **Authentification** : JWT + RBAC
- **Type de réponse** : téléchargement de fichier (`application/pdf`, A4 paysage)

**Corps de requête** :
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

Ou en mode tableau :
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

| Champ | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| type | string | Non | `table` | Type d'export : `table` / `dashboard` |
| title | string | Non | `数据导出` | Titre du PDF |
| data | object | Non | `{}` | Données à exporter |

Avec `type=dashboard`, `data` doit contenir un tableau `stats` (rendu sous forme de cartes) ; avec `type=table`, `data` doit contenir les tableaux `columns` et `rows`.

Le modèle PDF contient la mention de copyright et l'horodatage d'export.

### 11.3 Import d'utilisateurs (Excel)

```
POST /admin/import/users
```

- **Authentification** : JWT + RBAC
- **Type de requête** : `multipart/form-data` (upload de fichier)

**Champs du formulaire** :

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| file | file | Oui | Formats `.xlsx` ou `.xls` |

**Colonnes exigées dans l'Excel** :

| Nom de colonne | Obligatoire | Description |
|------|------|------|
| username | Oui | Nom d'utilisateur (unique) |
| password | Oui | Mot de passe (stocké en hash bcrypt) |
| real_name | Oui | Nom réel |
| phone | Non | Numéro de téléphone |
| email | Non | E-mail |
| status | Non | Statut, défaut 1 |

La ligne 1 contient les titres de colonnes (insensibles à la casse), les données commencent à la ligne 2.

**Exemple de réponse** :
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

| Champ | Type | Description |
|------|------|------|
| total | int | Nombre total de lignes (hors ligne de titre) |
| success | int | Nombre d'imports réussis |
| failed | int | Nombre d'échecs |
| errors | array | Détails des échecs, chaque élément contient row (numéro de ligne Excel) et reason (cause de l'échec) |

## 12. Upload de fichiers

```
POST /admin/upload
```

- **Authentification** : JWT + RBAC
- **Type de requête** : `multipart/form-data`

**Champs du formulaire** :

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| file | file | Oui | Fichier à uploader |

**Types de fichiers autorisés** : `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Taille de fichier maximale** : 10 Mo

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Les fichiers sont stockés dans des répertoires datés sous `public/upload/{Y-m-d}/`, nommés `md5(uniqid) + extension d'origine`. `url` est un chemin relatif par rapport à la racine du site.

**Erreurs possibles** :
- 422 : veuillez sélectionner un fichier (aucun upload)
- 422 : type de fichier non pris en charge
- 422 : la taille du fichier ne peut pas dépasser 10 Mo
- 500 : échec de l'upload du fichier (fichier invalide)

## 13. En-têtes de réponse

Toutes les interfaces (injectés au niveau des middlewares globaux) incluent les en-têtes de réponse suivants :

| En-tête | Description |
|----|------|
| `X-RateLimit-Limit` | Limite de débit (nombre de requêtes) |
| `X-RateLimit-Remaining` | Nombre de requêtes restantes |
| `X-RateLimit-Reset` | Horodatage de réinitialisation de la fenêtre de limitation |
| `Retry-After` | Renvoyé uniquement lors du déclenchement de la limitation, secondes d'attente recommandées |
| `X-Content-Type-Options` | `nosniff` (fourni par défaut par webman, interdit le sniffing MIME) |
| `X-Frame-Options` | `DENY` (fourni par le middleware CORS / la configuration de base de webman) |

Détails de la limitation de débit :
- Limite globale par défaut : 60 requêtes/minute / IP+chemin
- Point de terminaison de connexion `/api/auth/login` : 10 requêtes/minute
- Point de terminaison d'inscription `/api/auth/register` : 5 requêtes/minute
- Algorithme de fenêtre glissante atomique Redis (Lua ZSET), éliminant la course TOCTOU
- Si Redis est indisponible, fail-open (laisse passer), sans bloquer les requêtes

## 14. Flux d'authentification

Séquence d'authentification complète :

```
1. Le client demande POST /api/captcha/generate
   (en-tête : API-Version: v1)
    ↓
   Le serveur renvoie : key + type(click|slider|rotate) + image base64 + extra(données liées au type)
   
2. L'utilisateur interagit pour résoudre le captcha (clic/glisser/tourner), le client collecte la réponse
   
3. Le client demande POST /api/captcha/verify
   (en-tête : API-Version: v1, Content-Type: application/json)
   Corps de requête : { key, type, clicks }
   - type=click:  clicks = [{x, y}, ...]        // tableau de coordonnées
   - type=slider: clicks = 120                   // décalage X
   - type=rotate: clicks = 315                   // angle de rotation
    ↓
   Serveur :
   a. Lit les données captcha:key depuis le stockage (TTL 300 s)
   b. Valide la réponse selon le type (click : distance euclidienne ≤ 18 px / slider : ± 4 px / rotate : ± 5°)
   c. Validation réussie → écrit Redis `captcha_verified:{key}` = 1 (TTL 300 s)
   d. Échec de validation → renvoie 422, compteur +1, au-delà de 3 tentatives la key est invalidée
    ↓
   Le serveur renvoie : { valid: true/false }

4. Le client demande POST /api/auth/login
   (en-tête : API-Version: v1, Content-Type: application/json)
   Corps de requête : { username, password(chiffré), captcha_key }
    ↓
   Serveur :
   a. Validation des paramètres → 422
   b. Vérifie l'existence de captcha_verified:{key} → 422
   c. Supprime captcha_verified:{key} (usage unique)
   d. Déchiffre le mot de passe : EncryptionService::decrypt(password) → texte en clair
   e. Valide les identifiants de l'utilisateur (password_verify) → 401
   f. Vérifie l'état du compte → 403/429
   g. Émet les JWT (access + refresh) → 200
   h. Met à jour last_login_at / last_login_ip
    ↓
   Le client enregistre : access_token, refresh_token, expires_in

5. Les requêtes suivantes portent le JWT
   En-tête : Authorization: Bearer <access_token>
    ↓
   Middleware AdminAuth :
   a. Extrait le jeton Bearer
   b. Vérifie la liste noire (Redis jwt_blacklist:{md5}) → 401
   c. Décode le JWT, vérifie l'expiration → 401
   d. Définit $request->adminId = champ sub
    ↓
   Middleware AdminPermission :
   a. Résout l'identifiant de permission pour la route de la ressource
   b. Interroge les rôles de l'utilisateur → permissions des rôles, effectue la correspondance
   c. Sans permission → 403
    ↓
   Le contrôleur traite la requête
    ↓
   Response + en-têtes X-RateLimit-*

6. Rafraîchissement avant expiration de l'Access Token
   Le client demande POST /api/auth/refresh
   Corps de requête : { refresh_token: "..." }
    ↓
   Le serveur décode refresh_token → émet de nouveaux access + refresh
    ↓
   Le client met à jour ses jetons locaux

7. Déconnexion
   Le client demande POST /admin/profile/logout
   En-tête : Authorization: Bearer <access_token>
    ↓
   Serveur :
   a. Décode le JWT pour obtenir le TTL restant
   b. Écrit dans la liste noire Redis : jwt_blacklist:{md5(token)} = 1, TTL = durée de validité restante
   c. Renvoie le succès
```

### Structure JWT

- **access_token** : `{ sub: <user_id>, username: "<name>" }`, TTL par défaut 7200 secondes (contrôlé par la configuration JWT `default_expire`)
- **refresh_token** : `{ sub: <user_id>, token_type: "refresh" }`, TTL par défaut 1209600 secondes (contrôlé par la configuration JWT `refresh_expire`, soit 14 jours)

### Gestion de la sécurité

- Les mots de passe sont stockés avec le hash `PASSWORD_BCRYPT`
- Le mot de passe est chiffré en couche de transport avec AES-256-CBC-HMAC (chiffrement côté client → déchiffrement côté serveur), avec repli en texte clair
- Les champs sensibles (phone, email, id_card) sont chiffrés/déchiffrés de manière transparente en base de données via `erikwang2013/encryptable`
- Les ID au niveau API sont chiffrés avec `erikwang2013/hashids` pour éviter d'exposer la séquence des ID snowflake bruts
- SecurityFilter analyse globalement les XSS, injections SQL, traversées de chemin et injections de commandes ; 5 détections par même IP en 60 secondes ⇒ liste noire temporaire de 15 minutes
- Les opérations sensibles (suppression d'utilisateur, de rôle, de permission, de configuration) exigent la double confirmation du mot de passe de l'utilisateur connecté
- Limitation des sessions concurrentes : 3 jetons valides maximum par utilisateur ; à la connexion d'un 4e appareil, le jeton le plus ancien est forcé en liste noire
- Verrouillage du compte : 5 échecs de connexion consécutifs déclenchent un verrouillage de 15 minutes, 429 est renvoyé pendant la période de verrouillage

### Architecture des middlewares

Les middlewares globaux s'appliquent à toutes les requêtes, dans l'ordre :

```
Cors (prétraitement CORS + en-têtes de réponse)
  → Locale (détection de langue Accept-Language / ?lang=zh_CN|en)
  → SecurityFilter (limitation des méthodes HTTP/taille du corps/validation Content-Type/XSS/injection SQL/traversée de chemin/injection de commandes/interception des attaques CSRF)
  → RateLimit (limitation de débit par fenêtre glissante Redis + verrouillage du compte : 5 échecs de connexion ⇒ verrouillage de 15 minutes)
  → ApiVersion (validation de la version API, groupe de routes /api)
  → AdminAuth (authentification JWT + liste noire, groupe de routes /admin)
  → AdminPermission (autorisation RBAC / cache Redis 60 s, groupe de routes /admin)
  → OperationLog (enregistrement automatique des POST/PUT/DELETE, avec détection de la source, groupe de routes /admin)
```

`/health` et `/api/docs` sont des points de terminaison publics, ne passant que par `Cors → SecurityFilter → RateLimit`.

Renforcements de sécurité :
- **Verrouillage du compte** : 5 échecs de connexion consécutifs ⇒ verrouillage automatique de 15 minutes, la connexion renvoie 429 pendant cette période
- **Limitation des sessions concurrentes** : 3 jetons valides maximum par utilisateur, au-delà le jeton le plus ancien est automatiquement ajouté à la liste noire
- **security.txt** : `GET /.well-known/security.txt` fournit les coordonnées de sécurité conformes à la norme RFC 9116
- **Configuration de sécurité Nginx** : reportez-vous à `docs/nginx-security.conf` pour un exemple complet de renforcement du proxy inverse

### Détection de la source de l'opération

Le middleware OperationLog identifie automatiquement la plate-forme du client et écrit le champ `source` du journal d'opérations :

| Plate-forme | Méthode de détection |
|------|---------|
| `ipados` | UA contient iPad |
| `macos` | UA contient Macintosh/Mac OS |
| `windows` | UA contient Windows |
| `linux` | UA contient Linux (hors Android) |
| `ios` | UA contient iPhone / iOS / CFNetwork |
| `android` | UA contient Android |
| `harmonyos` | UA contient HarmonyOS / OpenHarmony ou en-tête `X-Client-Platform` déclaré explicitement |
| `web` | Par défaut (aucune des plates-formes ci-dessus) |

> Détection en deux niveaux : en-tête de requête `X-Client-Platform` (déclaration des apps natives) → inférence automatique par User-Agent (repli). Le champ `source` de la consultation des journaux d'opérations `GET /admin/log` correspond à la source.

## 15. Déploiement et exploitation

### Docker Compose

`docker-compose.yml` à la racine du projet orchestre 5 services (Nginx, application webman, MySQL, Redis, Elasticsearch). PHP est construit via le `Dockerfile` (basé sur `php:8.3-cli`, OPcache activé).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` définit le pipeline d'intégration continue GitHub Actions :
- Vérification de syntaxe `php -l`
- Tests unitaires PHPUnit
- Analyse statique `flutter analyze`

### Sauvegarde de la base de données

Le répertoire `database/backup/` fournit des scripts de sauvegarde et de restauration :
- `backup.sh` — sauvegarde compressée mysqldump + gzip, nettoyage automatique des sauvegardes de plus de 30 jours
- `restore.sh` — restauration interactive, liste les sauvegardes existantes au choix de l'utilisateur

### Configuration de sécurité Nginx

En production, reportez-vous à `docs/nginx-security.conf` pour la configuration du renforcement de la sécurité du proxy inverse.

## 16. Interface gRPC interne (e-cat → PHP worker)

Réservé à l'appel par le gateway e-cat. Côté PHP, il s'agit d'un service workerman h2c (`tcp://0.0.0.0:8792`, processus `internal_grpc`). Le contrat est défini dans `infrastructure/tracking-gateway/proto/internal.proto` (package `internal.v1`), les messages sont encodés en protobuf et les metadata doivent contenir `x-internal-token` (valeur de `config/logistics.php` — `internal_token`, remplacée en production par la variable d'environnement `INTERNAL_TOKEN`). Un token absent/invalide renvoie `code=401 UNAUTHORIZED`.

### 16.1 Aperçu des RPC

| RPC | Requête | Réponse | Description |
|---|---|---|---|
| `Detect` | `{tracking_no}` | `{code, carrier_code, channel, confidence}` | Détection du transporteur ; non reconnu → `404 CARRIER_NOT_DETECTED` |
| `Query` | `{carrier_code, tracking_no, credential_id?}` | `{code, query_no, carrier_code, tracking_no, status, delivered_at, estimated_delivery_at, latest_description, raw_status, events[]}` | Requête de suivi ; `credential_id` optionnel (pour plusieurs identifiants, doit correspondre à `carrier_code`, sinon `400 INVALID_PARAMS`). Chaque requête est enregistrée dans `logistics_tracking_query` |
| `Carriers` | `{}` | `{code, carriers[]}` | Tous les transporteurs du registre `{carrier_code, channel}` |

### 16.2 Contrat d'erreur (champ `code` de la réponse, status gRPC toujours 0)

| `code` | `error_code` | Scénario |
|---|---|---|
| 400 | `INVALID_PARAMS` | Paramètres manquants / credential_id ne correspond pas |
| 401 | `UNAUTHORIZED` | Jeton interne incorrect |
| 404 | `CARRIER_NOT_FOUND` / `TRACKING_NOT_FOUND` / `CARRIER_NOT_DETECTED` | Transporteur non enregistré / suivi introuvable / non détecté |
| 5001 | `CARRIER_AUTH_ERROR` / `CARRIER_NETWORK_ERROR` / `CARRIER_ERROR` | Erreur d'identifiants du transporteur / erreur réseau / erreur métier (avec message structuré amont, ex. `[SF-INTERNATIONAL A1001] les paramètres obligatoires ne peuvent pas être vides`) |
| 500 | `INTERNAL_ERROR` | Exception interne |


## 17. API externe du gateway e-cat

Gateway haute fréquence en Rust (`infrastructure/tracking-gateway`), écoute sur `0.0.0.0:8080` (défaut), les requêtes portent l'en-tête `X-API-Key` (config `api_keys`). Le gateway gère l'authentification API-Key, le cache Redis (préfixe `logistics:`), le rate limiting, le circuit breaker par transporteur et le round-robin des workers ; les identifiants ne descendent jamais jusqu'au gateway.

| 端点 | 说明 |
|---|---|
| `GET /v1/health` | Vérification de santé (point public, clé non requise) |
| `GET /v1/openapi.json` | Documentation OpenAPI 3.0 (point public, clé non requise) |
| `GET /v1/carriers` | Liste des transporteurs (proxy vers le gRPC PHP `InternalService.Carriers`) |
| `POST /v1/tracking/query` | Requête de suivi (`{"carrier_code": "...", "tracking_no": "..."}`) ; sans `carrier_code`, le gateway détecte automatiquement (detect fait partie de query) |
| `GET /v1/tracking/{query_no}` | Dernier résultat de suivi par numéro de requête |
| `POST /v1/subscriptions` | Enregistrer un abonnement callback (`{"carrier_code", "callback_url", "event_type"?}`, `event_type` par défaut `tracking.update`) |


Sémantique supplémentaire côté gateway : dépassement de `rate_limit` (défaut 100/60s) → `429` ; échecs successifs d'un transporteur déclenchent le circuit breaker (défaut OPEN 60s après 5 erreurs 5xx/transport) → `503` ; worker injoignable → `502`. Gateway↔worker est du gRPC interne (h2c, `0.0.0.0:8792`), authentifié par le secret partagé `x-internal-token`, voir section 16.

### 17.1 Enveloppe de réponse et erreurs

Tous les points renvoient l'enveloppe unifiée `{"code": 0, "message": "ok", "data": ...}` ; `code != 0` = erreur, `data` absent. Le corps d'une erreur métier peut porter `error_code` / `error_message` (diagnostic du transporteur, transmis tel quel par le gateway) ; `code` correspond au statut HTTP :

| HTTP | Signification |
|---|---|
| 400 | Erreur de paramètres (`tracking_no` manquant / paramètres d'abonnement invalides) |
| 401 | Clé manquante ou invalide |
| 404 | Numéro de requête introuvable |
| 429 | Rate limit (défaut 100/60s par clé), réessayer avec backoff |
| 502 | Worker injoignable ou échec amont |
| 503 | Circuit breaker du transporteur, réessayer avec backoff |

### 17.2 Exemples d'appel (curl)

Clé de démonstration `demo-api-key`, gateway par défaut `http://127.0.0.1:8080` :

```bash
# Vérification de santé (point public, clé non requise)
curl http://127.0.0.1:8080/v1/health

# Documentation OpenAPI (point public, clé non requise)
curl http://127.0.0.1:8080/v1/openapi.json

# Liste des transporteurs
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/carriers

# Requête de suivi (transporteur facultatif)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "tracking_no": "LX123456789CN"}' \
  http://127.0.0.1:8080/v1/tracking/query

# Dernier résultat par numéro de requête
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/tracking/{query_no}

# Enregistrer un abonnement (l'adresse de callback doit être http/https public ; le gateway refuse loopback/réseaux privés)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "callback_url": "https://example.com/cb", "event_type": "tracking.update"}' \
  http://127.0.0.1:8080/v1/subscriptions
```

### 17.3 Exemples d'appel (SDK)

Cinq SDK sans dépendance dans le répertoire `sdk/` du dépôt (Python / PHP / Node.js / Go / Rust, copier-coller) :

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

Les SDK lèvent une exception (`TrackingApiError`) quand `code != 0` ou en cas d'échec réseau, avec `code` / `message` / `error_code` / `error_message` / `http_status` ; réessayer avec backoff pour le rate limit et le circuit breaker, ne pas réessayer les erreurs côté transporteur. Usage complet dans `sdk/README.md`.
