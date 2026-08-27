> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-20-backend-enhancement-design.md) | [English](2026-05-20-backend-enhancement-design.en.md) | [한국어](2026-05-20-backend-enhancement-design.ko.md) | [Русский](2026-05-20-backend-enhancement-design.ru.md) | [Deutsch](2026-05-20-backend-enhancement-design.de.md) | [Français](2026-05-20-backend-enhancement-design.fr.md) | [Español](2026-05-20-backend-enhancement-design.es.md) | [Português](2026-05-20-backend-enhancement-design.pt.md) | [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) | [العربية](2026-05-20-backend-enhancement-design.ar.md) | [বাংলা](2026-05-20-backend-enhancement-design.bn.md) | [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) | [日本語](2026-05-20-backend-enhancement-design.ja.md)

# Sous-projet A : améliorations du backend — Spécification de conception

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Périmètre

Cette itération porte sur les améliorations du backend : 15 points de fonctionnalité au total, impliquant 9 nouveaux fichiers + 4 fichiers modifiés.

---

## Liste des fichiers ajoutés/modifiés

```
app/middleware/
├── OperationLog.php          # Nouveau : enregistrement automatique des journaux d'opérations
├── Cors.php                  # Nouveau : CORS (partage de ressources entre origines)
└── RateLimit.php             # Nouveau : limitation de débit Redis
app/admin/controller/
├── ConfigController.php      # Nouveau : CRUD de configuration système
├── LogController.php         # Nouveau : consultation des journaux d'opérations
├── ProfileController.php     # Nouveau : espace personnel (avec déconnexion)
├── UploadController.php      # Nouveau : téléversement de fichiers
├── ImportController.php      # Nouveau : import d'utilisateurs Excel
└── HealthController.php      # Nouveau : vérification de santé
app/model/
├── AdminUser.php             # Modifié : ajout des traits SoftDeletes + Searchable
└── OperationLog.php          # Modifié : ajout de public $timestamps = false
app/middleware/
└── AdminAuth.php             # Modifié : vérification de la liste noire JWT
app/admin/controller/
├── DashboardController.php   # Modifié : statistiques en temps réel depuis la base
└── UserController.php        # Modifié : ajout d'actions par lots
config/
└── route.php                 # Modifié : ajout de routes + middlewares
```

---

## 1. Middlewares

### 1.1 Middleware CORS

**Fichier** : `app/middleware/Cors.php`

- Répond directement 204 aux requêtes de pré-vérification OPTIONS
- Pour les requêtes non pré-vérifiées, ajoute `Access-Control-Allow-Origin: *` aux en-têtes de réponse
- En-têtes autorisés : `Authorization, Content-Type, API-Version`
- Cache maximal : 86400 secondes

Montage : middleware global (`config/middleware.php`)

### 1.2 Middleware de limitation de débit

**Fichier** : `app/middleware/RateLimit.php`

- Stockage : fenêtre glissante Redis Sorted Set
- Défaut : 60 requêtes/minute/IP/route
- Interfaces sensibles :
  - `/api/auth/login` : 10 requêtes/minute
  - `/api/auth/register` : 5 requêtes/minute
- En cas de dépassement, retourne `429 Too Many Requests`

Montage : middleware global (`config/middleware.php`), après Cors, avant ApiVersion

### 1.3 Middleware de journalisation des opérations

**Fichier** : `app/middleware/OperationLog.php`

- Enregistre uniquement POST/PUT/DELETE
- Champs enregistrés : user_id, action, method, path, ip, input(JSON)
- Écriture asynchrone après la réponse (non bloquant)

Montage : groupe de routes `/admin`, après AdminPermission

### 1.4 Chaîne d'exécution des middlewares globaux

```
Toutes les requêtes :
  Cors → RateLimit → ApiVersion → {Middleware de route} → Controller

Requêtes /admin/* :
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Déconnexion (liste noire JWT)

**Fichier** : `app/middleware/AdminAuth.php` (modifié)

**Principe** : JWT étant sans état, la déconnexion ajoute le jeton à la liste noire Redis ; AdminAuth consulte d'abord la liste noire lors de la vérification.

**Modification d'AdminAuth** :
- Au début de `process()` : vérifier dans la collection Redis `jwt_blacklist` si le jeton courant figure sur la liste noire
- Si le jeton est sur la liste noire, retourne 401

**Route de déconnexion** (sous l'espace personnel) :

| Méthode | Route | Description |
|------|------|------|
| `POST` | `/admin/profile/logout` | Ajoute le jeton Bearer courant à la liste noire Redis, TTL = durée de validité restante du jeton |

**Logique de logout** :
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Nouveaux contrôleurs et modifications existantes

### 2.1 CRUD de configuration système (`ConfigController`)

Hérite de `BaseController`.

| Méthode | Route | Description |
|------|------|------|
| `index()` | GET `/admin/config` | Liste paginée, filtrable par `group`, pagination `page`/`limit` |
| `store()` | POST `/admin/config` | Crée une entrée de configuration, obligatoires : group, key, value |
| `update()` | PUT `/admin/config/{id}` | Met à jour value/type/description de l'entrée |
| `destroy()` | DELETE `/admin/config/{id}` | Supprime l'entrée, nécessite `confirmPassword()` |

### 2.2 Consultation des journaux d'opérations (`LogController`)

Hérite de `BaseController`.

| Méthode | Route | Description |
|------|------|------|
| `index()` | GET `/admin/log` | Liste paginée, filtres : user_id, action, path, created_at (plage) |

Aucune création/modification/suppression : les journaux sont enregistrés automatiquement par le middleware.

### 2.3 Espace personnel (`ProfileController`)

Hérite de `BaseController`. Agit sur l'utilisateur connecté (`$request->adminId`).

| Méthode | Route | Description |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Met à jour real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Modifie le mot de passe, requiert old_password, new_password, new_password_confirmation |

### 2.4 Téléversement de fichiers (`UploadController`)

Hérite de `BaseController`.

| Méthode | Route | Description |
|------|------|------|
| `upload()` | POST `/admin/upload` | Reçoit le fichier, prend en charge image/jpeg/png/gif/pdf/xlsx/docx |

- Maximum 10 Mo
- Chemin de stockage : `public/upload/{date}/{hash}.{ext}`
- Retourne : `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Données réelles du tableau de bord

**Fichier** : `app/admin/controller/DashboardController.php` (modifié)

Remplace les fausses données codées en dur par des statistiques en temps réel issues de la base :

| Indicateur | Source | Description |
|------|------|------|
| Total des utilisateurs | `AdminUser::count()` | Hors suppressions douces |
| Nouveaux du jour | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Total des rôles | `AdminRole::count()` | |
| Total des permissions | `AdminPermission::count()` | |
| Données de tendance | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Nouveaux des 7 derniers jours, par jour |
| Données de répartition | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Répartition par statut |
| Opérations récentes | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | 10 dernières entrées de journaux d'opérations |

### 2.6 Opérations par lots sur les utilisateurs

**Fichier** : `app/admin/controller/UserController.php` (modifié, nouvelles méthodes)

| Méthode | Route | Description |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Suppression par lots, corps de requête `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Activation/désactivation par lots, corps de requête `{ ids: [hashid, ...], status: 1|0 }` |

- Chaque id passe d'abord par `decodeId()` pour être converti en BIGINT
- `batchDestroy()` doit passer la vérification `confirmPassword()`

### 2.7 Import de données

**Fichier** : `app/admin/controller/ImportController.php` (nouveau)

| Méthode | Route | Description |
|------|------|------|
| `users()` | POST `/admin/import/users` | Téléverse un fichier Excel et crée les utilisateurs par lot |

Flux :
1. Reçoit le fichier `.xlsx`
2. Analyse via PhpSpreadsheet, colonnes attendues : `username, password, real_name, phone, email, status`
3. Validation + création ligne par ligne (ID généré par snowflake, mot de passe bcrypt, phone/email chiffrés par encryption)
4. Retourne le résultat : `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Vérification de santé

**Fichier** : `app/admin/controller/HealthController.php` (nouveau)

`GET /health` (sans authentification, non consigné dans les journaux d'opérations) :

Retourne l'état de connexion de chaque composant :
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

- Si un composant échoue au contrôle, la valeur du champ correspondant est la chaîne décrivant l'erreur
- La route n'utilise pas le préfixe `/admin` ; elle est enregistrée séparément au niveau global

---

## 3. Corrections des modèles

### 3.1 Horodatage d'OperationLog

**Fichier** : `app/model/OperationLog.php` (modifié)

La table `erik_operation_log` ne possède que la colonne `created_at` (pas de `updated_at`). Par défaut, Eloquent tente d'écrire `updated_at` dans `save()`, ce qui provoque une erreur SQL.

Correctif : `public $timestamps = false;` + spécification manuelle de `created_at` à l'écriture.

### 3.2 Refonte du modèle AdminUser

- Ajoute le trait `Searchable`
- Implémente `toSearchableArray()` : renvoie username, real_name
- Lorsqu'un mot-clé est détecté, `UserController::index()` utilise `AdminUser::search($kw)->get()` au lieu de MySQL LIKE

ES nécessite d'abord la création de l'index, via les commandes Scout :

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Changements de routes

Nouvelles routes dans `config/route.php` :

```php
// /admin 路由组内新增:
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

// 健康检查（全局路由，非 /admin 组内）
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// 中间件:
/admin 组中间件追加 app\middleware\OperationLog::class
```

`config/middleware.php` enregistre les middlewares globaux :

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Codes d'erreur complémentaires

| code | Signification | Déclencheur |
|------|------|---------|
| 429 | Requêtes trop fréquentes | Déclenché par RateLimit |

---

## 6. Hors du périmètre de cette itération

- Système de notification (nécessite une file de messages + une infrastructure de push côté front)
- Pages front-end Flutter (sous-projet B)
- Rafraîchissement du jeton HarmonyOS (sous-projet C)
