> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](../README.md) | [English](README.en.md) | [한국어](README.ko.md) | [Русский](README.ru.md) | [Deutsch](README.de.md) | [Français](README.fr.md) | [Español](README.es.md) | [Português](README.pt.md) | [हिन्दी](README.hi.md) | [العربية](README.ar.md) | [বাংলা](README.bn.md) | [Bahasa Indonesia](README.id.md) | [日本語](README.ja.md)

# Open Admin (open-admin)

Système de panneau d'administration full-stack basé sur webman v2 + Flutter.

> [Diagrammes d'architecture](docs/ARCHITECTURE.fr.md) | [Document de conception](docs/DESIGN.fr.md) | [Architecture de sécurité](docs/SECURITY.fr.md) | [Référence API](docs/API.fr.md)

## Fonctionnalités

| Domaine métier | Fonctionnalité | Description |
|--------|------|------|
| 🔐 Authentification | Connexion / rafraîchissement du jeton / déconnexion | Captcha à clic + JWT + liste noire |
| | Verrouillage du compte | 5 échecs ⇒ verrouillage de 15 minutes |
| | Limitation des sessions concurrentes | 3 jetons valides maximum par utilisateur |
| 📊 Tableau de bord | Statistiques en temps réel / graphique de tendances / graphique de répartition / opérations récentes | Cache Redis 5 minutes |
| 👥 Gestion des utilisateurs | CRUD + suppression groupée / activation-désactivation | Suppression douce + confirmation du mot de passe |
| | Import Excel groupé | Validation ligne par ligne + rapport d'erreurs |
| 🔒 Rôles et permissions | CRUD des rôles + arbre des permissions | Autorisation RBAC au niveau method.path |
| ⚙ Configuration système | CRUD de paires clé-valeur | Gestion par groupes |
| 📋 Audit des opérations | Consultation des journaux + détection de la source | Reconnaissance automatique de 8 plates-formes |
| 📁 Gestion des fichiers | Upload / export Excel / export PDF | Masquage automatique des données sensibles |
| 🛡 Protection de sécurité | 18 couches de défense en profondeur | XSS / injection SQL / traversée de chemin / injection de commandes / CSRF / limitation de débit / CSP... |
| 🏥 Exploitation | Health check / metrics / documentation API / security.txt | Prometheus + OpenAPI 3.0 + documentation interactive hg/apidoc |
| 🌐 Internationalisation | Bascule chinois / anglais | En-tête `Accept-Language` / paramètre `?lang=` |

## Pile technique

| Couche | Technologie | Description |
|---|------|------|
| Framework backend | webman v2 (workerman) | Framework PHP résident ultra-performant |
| Version PHP | 8.3+ | |
| Base de données | MySQL 8.0+ | Préfixe de table `erik_`, clé primaire BIGINT non auto-incrémentée |
| Moteur de recherche | Elasticsearch | Synchronisation et requêtes via `webman-scout` |
| Frontend d'administration | Flutter 3.x | Le Web est conçu comme un panneau d'administration PC (`apps/flutter/`) |
| Mobile | HarmonyOS ArkTS | Client natif HarmonyOS (`apps/harmonyos/`), prend en charge téléphone / tablette / 2-en-1 |

## Dépendances principales

| Paquet | Usage |
|---|------|
| `erikwang2013/snowflake-php` | Génération d'une clé primaire BIGINT globalement unique par l'algorithme Snowflake |
| `erikwang2013/hashids` | Chiffrement/déchiffrement des ID au niveau API, masque les ID réels de la base de données |
| `erikwang2013/jwt-webman` | Émission et validation des jetons d'authentification JWT |
| `erikwang2013/encryption` | Chiffrement/déchiffrement des données sensibles en couche de transport d'interface |
| `erikwang2013/encryptable` | Chiffrement/déchiffrement automatique des champs sensibles en couche de stockage de base de données |
| `erikwang2013/webman-scout` | Synchronisation des données Elasticsearch et recherche plein texte |
| `erikwang2013/season` | Données de drapeaux nationaux |
| `erikwang2013/poster-php` | Génération et validation du captcha à clic + génération d'affiches |
| `phpoffice/phpspreadsheet` | Export Excel |
| `barryvdh/laravel-dompdf` | Export PDF (basé sur Dompdf) |

## Structure du projet

```
open-admin/
├── app/
│   ├── admin/controller/       # Contrôleurs du panneau d'administration
│   │   ├── DashboardController.php # Tableau de bord (cache Redis)
│   │   ├── UserController.php      # CRUD utilisateurs + opérations groupées
│   │   ├── RoleController.php      # CRUD rôles
│   │   ├── PermissionController.php# CRUD permissions
│   │   ├── ConfigController.php    # CRUD configuration système
│   │   ├── LogController.php       # Consultation des journaux d'opérations
│   │   ├── ProfileController.php   # Espace personnel + déconnexion
│   │   ├── ExportController.php    # Export Excel/PDF
│   │   ├── ImportController.php    # Import d'utilisateurs Excel
│   │   ├── UploadController.php    # Upload de fichiers
│   │   ├── HealthController.php    # Health check
│   │   ├── DocsController.php      # Documentation OpenAPI
│   │   └── BaseController.php      # Contrôleur de base
│   ├── api/
│   │   └── v1/controller/          # Contrôleurs API v1 (version contrôlée par l'en-tête API-Version)
│   │       ├── CaptchaController.php # Captcha à clic
│   │       └── AuthController.php    # Connexion / rafraîchissement du jeton
│   ├── common/                 # Classes d'outils communes
│   │   ├── HashidsService.php  # Encodage/décodage des ID
│   │   ├── SnowflakeService.php# Génération d'ID Snowflake
│   │   └── EncryptionService.php # Chiffrement/déchiffrement des données + masquage
│   ├── middleware/             # Middleware
│   │   ├── Cors.php            # CORS
│   │   ├── SecurityFilter.php  # Blocage de la détection d'attaques (limitation des méthodes HTTP/XSS/injection SQL/traversée de chemin/injection de commandes/CSRF)
│   │   ├── RateLimit.php       # Limitation de débit Redis (fenêtre glissante + en-têtes de réponse)
│   │   ├── ApiVersion.php      # Validation de la version API
│   │   ├── AdminAuth.php       # Authentification JWT + liste noire
│   │   ├── AdminPermission.php # Validation des permissions RBAC
│   │   └── OperationLog.php    # Enregistrement automatique des journaux d'opérations (avec détection de la source)
│   └── model/                  # Modèles de données
├── apps/
│   ├── flutter/                # Panneau d'administration Web Flutter (style PC)
│   │   └── lib/app/
│   │       ├── pages/          # 5 pages complètes (tableau de bord/utilisateurs/rôles/config/journaux/espace personnel)
│   │       ├── services/       # ApiService (intercepteur JWT) + AuthService (persistance du jeton)
│   │       └── layouts/        # Mise en page responsive du panneau d'administration (barre latérale + barre supérieure + zone de contenu)
│   └── harmonyos/              # Client natif HarmonyOS (rafraîchissement transparent du jeton)
├── config/                     # Fichiers de configuration (avec commentaires en chinois)
│   ├── route.php               # Routage + stratégie de version API
│   ├── middleware.php           # Enregistrement des middlewares globaux
│   └── ...                     # Configurations des composants
├── database/install.sql        # Script d'installation SQL (avec données de permissions initiales)
├── public/                     # Point d'entrée public
├── runtime/                    # Fichiers d'exécution
└── vendor/                     # Dépendances Composer
```

## Prérequis

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (uniquement pour le développement frontend)
- Elasticsearch >= 7.x (optionnel, requis pour la recherche)

## Démarrage rapide

### 1. Installer les dépendances

```bash
composer install
```

### 2. Configurer les variables d'environnement

Copiez puis modifiez les variables d'environnement (optionnel ; sans configuration, les valeurs par défaut de `config/*.php` sont utilisées) :

```bash
cp .env.example .env
```

Éléments de configuration clés :

| Variable d'environnement | Description | Valeur par défaut |
|---------|------|--------|
| `JWT_SECRET` | Clé de signature JWT | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Sel Hashids | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | Clé de chiffrement API | Valeur par défaut de 32 octets |
| `SNOWFLAKE_DATACENTER_ID` | ID du centre de données (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID du nœud de travail (0-31) | `1` |
| `SCOUT_HOSTS` | Adresse ES | `http://localhost:9200` |

**En production, remplacez impérativement toutes les clés par des chaînes aléatoires.**

### 3. Installation en une commande

Après le démarrage du service, ouvrez l'assistant d'installation dans le navigateur pour initialiser la base de données et créer l'administrateur :

```bash
php start.php start
```

Écoute par défaut sur `http://0.0.0.0:8787` (le port peut être modifié dans `config/server.php`).

Ouvrez **`http://localhost:8787/install`** dans le navigateur et renseignez les informations demandées par l'assistant :

| Étape | Contenu |
|------|------|
| ① Configuration de la base de données | Hôte, port, nom de la base, utilisateur, mot de passe |
| ② Paramètres administrateur | Nom d'utilisateur et mot de passe de l'administrateur (par défaut admin / admin888) |

Cliquez sur « Commencer l'installation » pour créer automatiquement les tables, insérer les données de permissions et créer le compte administrateur, puis écrire la configuration de la base de données dans `.env`.

> Après l'installation, le fichier de verrouillage `runtime/install.lock` est généré. Supprimez ce fichier pour réinstaller.

### 4. Connexion

Accédez à `http://localhost:8787` et connectez-vous avec le compte administrateur défini lors de l'installation.

### 5. Démarrer le frontend (optionnel)

**Panneau d'administration Flutter (Web) :**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (style panneau d'administration PC)
```

**Client HarmonyOS (mobile) :**

Ouvrez le répertoire `apps/harmonyos/` avec DevEco Studio, puis exécutez sur un appareil réel ou un simulateur.

### 6. Déploiement Docker Compose en une commande (recommandé en production)

Le projet fournit une solution d'orchestration Docker complète avec 5 services : Nginx, PHP (application webman), MySQL, Redis, Elasticsearch.

```bash
# 1. Configurer les variables d'environnement Docker
cp .env.docker .env

# 2. Démarrer tous les services
docker-compose up -d

# 3. Ouvrir l'assistant d'installation dans le navigateur pour l'initialisation
# http://localhost:8787/install  (renseigner la base de données et l'administrateur)
# ou exécuter manuellement la migration SQL (dans le conteneur app) :
# docker-compose exec app mysql -h mysql -u root -p < database/install.sql

# 4. Accès
# http://localhost:8787  (webman)
# http://localhost:8080  (proxy inverse Nginx)
```

- `Dockerfile` : PHP 8.3 + OPcache + Composer, basé sur `php:8.3-cli`
- `docker-compose.yml` : orchestration de 5 services, isolation réseau, persistance des volumes de données
- `.env.docker` : variables d'environnement dédiées à Docker


## Normes de base de données

- **Préfixe de table** : `erik_`
- **Clé primaire** : la clé primaire de toutes les tables est `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT interdit**
- **Génération des ID** : les ID de clé primaire sont générés au niveau application par `SnowflakeService::generate()`, uniques en environnement distribué
- **Champs obligatoires** : chaque table doit contenir `id`, `created_at`, `updated_at`
- **Suppression douce** : les tables nécessitant une suppression douce ajoutent `deleted_at DATETIME DEFAULT NULL`
- **Champs sensibles** : numéro de téléphone, e-mail, numéro de carte d'identité, etc., chiffrés/déchiffrés automatiquement via le plug-in `encryptable`, stockés en `VARCHAR(500)` sous forme de texte chiffré

## Documentation API

La référence API complète (format de réponse unifié, codes d'erreur, détails de tous les points de terminaison, flux d'authentification, stratégie de limitation de débit, chaîne de middlewares) est disponible dans **[docs/API.md](docs/API.fr.md)**, points essentiels :

- **Format de réponse unifié** : `{ "code": 0, "message": "success", "data": {...} }`, `code=0` signifie succès
- **Codes d'erreur** : `400` erreur de paramètre / `401` non connecté / `403` accès refusé / `404` introuvable / `422` échec de validation / `429` limitation de débit / `500` erreur serveur
- **Version API** : contrôlée par l'en-tête `API-Version: v1` (v1 par défaut si absent), non visible dans l'URL
- **Authentification** : `Authorization: Bearer <token>` ; access_token valide 2 heures, refresh_token 14 jours
- **Traitement des ID** : les ID des requêtes/réponses sont des chaînes chiffrées hashids, les ID réels de la base de données ne sont jamais exposés

## Notes sur le frontend

### Panneau d'administration Flutter (style PC)

- **Mise en page** : barre latérale (repliable 64px/240px) + barre supérieure + zone de contenu, trois points de rupture responsives (mobile/tablette/desktop)
- **Pages** : connexion, tableau de bord, gestion des utilisateurs, rôles et permissions, configuration système, journaux d'opérations, espace personnel
- **Gestion d'état** : GetX (singleton `ApiService` + persistance du jeton `AuthService`)
- **Tableau de bord** : cartes de statistiques, graphique de tendances en courbes (fl_chart), graphique circulaire, journaux d'opérations récents
- **Export** : export Excel/PDF, le PDF contient une mention de copyright inamovible
- **Opérations groupées** : suppression groupée multi-sélection, activation/désactivation groupée
- **Thème** : Material 3 double thème clair/sombre

### Mobile HarmonyOS

- **Pages** : connexion, tableau de bord, liste/détail des utilisateurs, espace personnel
- **Authentification** : JWT Bearer + rafraîchissement transparent du jeton à la réception d'un 401, redirection automatique vers la page de connexion en cas d'échec du rafraîchissement
- **Stockage** : le jeton est géré via AppStorage

## Normes de développement

- Les références aux fonctions/classes globales n'utilisent pas de `\` de tête, `use` est systématiquement utilisé
- Tous les fichiers PHP doivent commencer par la mention de copyright
- Tous les fichiers de configuration doivent contenir des commentaires en chinois
- Les clés primaires de la base de données doivent être générées au niveau application par snowflake, l'auto-incrémentation est interdite
- Tous les ID des paramètres et réponses au niveau API doivent passer par le chiffrement/déchiffrement hashids
- Le middleware AdminPermission met en cache les permissions utilisateur dans Redis (TTL=60s), éliminant le goulot d'étranglement des requêtes N+1

## Déploiement

### Docker Compose (recommandé)

`docker-compose.yml` à la racine du projet orchestre 5 services :

| Service | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | build local `Dockerfile` | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

L'image PHP est construite via le `Dockerfile`, image de base `php:8.3-cli`, avec OPcache activé.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

Pipeline d'intégration continue GitHub Actions : `.github/workflows/ci.yml`

- Vérification de la syntaxe PHP (`php -l`)
- Tests unitaires PHPUnit
- Analyse statique Flutter (`flutter analyze`)

### Sauvegarde de la base de données

Répertoire `database/backup/` :

- `backup.sh` — sauvegarde mysqldump + gzip, nettoyage automatique des sauvegardes de plus de 30 jours
- `restore.sh` — restauration interactive, liste les sauvegardes disponibles au choix

### Configuration de sécurité Nginx

En production, reportez-vous à `docs/nginx-security.conf` pour le renforcement de la sécurité du proxy inverse.

## Le logiciel open source, c'est du travail — merci pour votre soutien

| WeChat | Alipay |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

### Dons par virement international (transfert transfrontalier)

**Informations sur le bénéficiaire**

- Nom du bénéficiaire : WANG KEXUN
- Numéro de compte du bénéficiaire : 881015918251

**Banque du bénéficiaire**

- ZA Bank SWIFT Code : AABLHKHHXXX
- Nom de la banque : ZA Bank Limited
- Code banque : 387
- Adresse de la banque : Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Banque correspondante pour le virement transfrontalier (si nécessaire)**

> Il s'agit des informations de la banque correspondante (banque intermédiaire) pour le virement transfrontalier, et non de celles de la banque du bénéficiaire. Renseignez-vous auprès de votre banque émettrice pour savoir si les informations de la banque correspondante sont requises.

- **Pour les versements en dollars de Hong Kong, en yuans renminbi et en dollars américains**, la banque correspondante est Citibank :
  - Nom de la banque : Citibank N.A. Hong Kong
  - SWIFT Code : CITIHKHXXXX
  - Code banque : 006
  - Nom de la succursale : Hong Kong Branch
  - Code succursale : 391
  - Adresse de la banque : Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **Pour les versements dans d'autres devises**, la banque correspondante est BNY Mellon :
  - Nom de la banque : THE BANK OF NEW YORK MELLON
  - SWIFT Code : IRVTUS3NXXX
  - Adresse de la banque : THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
