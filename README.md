# Muse

Application web Laravel/Vue pour la gestion de cours, projets de recherche et musées virtuels.

---

## Versions des technologies

### Backend

| Technologie | Version |
|-------------|---------|
| PHP | 8.4 |
| Laravel | 13.x |
| Laravel Fortify | 1.30+ |
| Inertia.js (serveur) | 3.x |
| Laravel Wayfinder | 0.1.x |
| Pest | 4.4+ |
| Laravel Pint | 1.24+ |

### Frontend

| Technologie | Version |
|-------------|---------|
| Node.js | 22 |
| Vue | 3.5+ |
| Vite | 7.x |
| TypeScript | 5.2+ |
| Tailwind CSS | 4.x |
| Inertia.js (client) | 3.x |
| reka-ui | 2.6+ |
| Tiptap | 3.x |

### Base de données

| Environnement | Moteur | Version |
|---------------|--------|---------|
| Développement / production | MySQL | 8.4 |

---

## Installation — Podman Compose (recommandé)

### Prérequis

- [Podman](https://podman.io/) avec support Compose (`podman compose`)
- Ou Docker Compose v2

### Démarrage développement

```bash
# 1. Cloner le dépôt
git clone <url-du-repo> muse
cd muse

# 2. Copier l'environnement conteneurisé
cp .env.docker.example .env

# 3. Générer la clé (sur l'hôte ou dans le conteneur après premier build)
php artisan key:generate
# ou : podman compose --profile dev run --rm app php artisan key:generate

# 4. Démarrer MySQL, l'app, la queue et Vite
podman compose --profile dev up --build
```

- Application : http://localhost:8080
- Vite (HMR) : http://localhost:5173
- MySQL (depuis l'hôte) : `127.0.0.1:3306`

### Démarrage production

```bash
cp .env.docker.example .env
# Remplir APP_KEY, APP_URL, DB_PASSWORD (mot de passe fort)

podman compose --profile prod up --build -d
```

### Commandes utiles

```bash
# Migrations
podman compose --profile dev exec app php artisan migrate

# Tests dans le conteneur
podman compose --profile dev exec app php artisan test --compact

# Logs
podman compose --profile dev logs -f app

# Arrêter
podman compose --profile dev down
```

> **SELinux (Linux)** : si les permissions de volume échouent, ajouter `:Z` aux montages dans `compose.yaml` (ex. `.:/var/www/html:Z`).

---

## Installation — Laravel Herd (sans conteneur)

### Prérequis

- [Laravel Herd](https://herd.laravel.com/) (PHP 8.4)
- MySQL 8.x local
- Node.js 22+ et npm
- Composer 2

### Étapes

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configurer .env avec MySQL local (DB_HOST=127.0.0.1)
php artisan migrate
npm install
npm run dev
```

L'application est accessible sur `http://muse.test` via Herd.

---

## Commandes utiles

```bash
# Tests (MySQL requis — CI ou conteneur mysql sur :3306)
php artisan test --compact

# Style PHP
./vendor/bin/pint

# Routes Wayfinder
php artisan wayfinder:generate --no-interaction

# Worker de queue
php artisan queue:work --tries=3
```

---

## Structure des environnements

| Fichier | Usage |
|---------|-------|
| `.env.example` | Modèle Herd / MySQL local |
| `.env.docker.example` | Modèle Podman Compose (DB_HOST=mysql) |
| [`compose.yaml`](compose.yaml) | Stack dev (profil `dev`) et prod (profil `prod`) |
| [`Dockerfile`](Dockerfile) | Image multi-stage : composer → node → PHP/nginx |
