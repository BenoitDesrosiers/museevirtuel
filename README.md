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
| Laravel Sail (dev Docker) | 1.x |
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
| Développement local | SQLite | — |
| Docker dev (Sail) | MySQL | 8.4 |
| Production (Docker) | MySQL | 8.4 |

---

## Installation — Développement local (Laravel Herd)

### Prérequis

- [Laravel Herd](https://herd.laravel.com/) installé (fournit PHP 8.4 + serveur local)
- Node.js 22+ et npm
- Composer 2

### Étapes

```bash
# 1. Cloner le dépôt
git clone <url-du-repo> muse
cd muse

# 2. Installer les dépendances PHP
composer install

# 3. Copier le fichier d'environnement
cp ..env.example ..env

# 4. Générer la clé d'application
php artisan key:generate

# 5. Créer la base de données SQLite
touch database/database.sqlite

# 6. Lancer les migrations
php artisan migrate

# 7. Installer les dépendances Node et compiler les assets
npm install
npm run dev
```

L'application est accessible sur `http://muse.test` via Herd (ou `http://localhost:8000` avec `php artisan serve`).

---

## Installation — Docker développement (Laravel Sail)

### Prérequis

- Docker Desktop

### Étapes

```bash
# 1. Cloner le dépôt
git clone <url-du-repo> muse
cd muse

# 2. Copier l'environnement et configurer pour MySQL (Sail)
cp ..env.example ..env
```

Modifier `.env` pour utiliser MySQL au lieu de SQLite :

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=muse
DB_USERNAME=sail
DB_PASSWORD=password
```

```bash
# 3. Démarrer les conteneurs
./vendor/bin/sail up -d

# 4. Générer la clé d'application
./vendor/bin/sail artisan key:generate

# 5. Lancer les migrations
./vendor/bin/sail artisan migrate

# 6. Installer les dépendances Node et compiler les assets
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

L'application est accessible sur `http://localhost`.

---

## Installation — Docker production

### Prérequis

- Docker et Docker Compose sur le serveur

### Étapes

```bash
# 1. Cloner le dépôt sur le serveur
git clone <url-du-repo> muse
cd muse

# 2. Copier le fichier d'environnement Docker
cp ..env.docker.example ..env

# 3. Remplir les valeurs obligatoires dans ..env
#    APP_KEY=   → générer avec : php -r "echo 'base64:'.base64_encode(random_bytes(32));"
#    APP_URL=   → ex: https://muse.exemple.com
#    DB_PASSWORD=  → mot de passe fort

# 4. Construire et démarrer les conteneurs
docker compose -f docker-compose.prod.yml up -d --build

# 5. Lancer les migrations (première fois seulement)
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force

# 6. Lier le stockage public
docker compose -f docker-compose.prod.yml exec app php artisan storage:link
```

L'application est accessible sur le port 80 (ou le port configuré dans `APP_PORT`).

---

## Switch entre la base de données de développement et de production

Le seul endroit à changer est le fichier **`.env`** à la racine du projet.

### Développement local → SQLite

```dotenv
DB_CONNECTION=sqlite
# DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD ne sont pas nécessaires
# Le fichier SQLite est database/database.sqlite
```

```bash
# Si le fichier n'existe pas encore
touch database/database.sqlite
php artisan migrate
```

### Développement Docker (Sail) ou production → MySQL

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql        # nom du service Docker, ou IP/hostname du serveur MySQL
DB_PORT=3306
DB_DATABASE=muse
DB_USERNAME=sail     # ou muse_user en production
DB_PASSWORD=password # mot de passe fort en production
```

```bash
# Après avoir changé ..env, vider le cache de config
php artisan config:clear
php artisan migrate
```

> **Remarque :** En production Docker, les variables de base de données sont injectées directement via `docker-compose.prod.yml` → section `environment`. Le `.env` sert de source de vérité mais les valeurs sont passées au conteneur au démarrage.

---

## Commandes utiles

```bash
# Lancer les tests
php artisan test --compact

# Vérifier et corriger le style PHP
./vendor/bin/pint

# Générer les routes Wayfinder (TypeScript)
php artisan wayfinder:generate --no-interaction

# Lancer le worker de queue
php artisan queue:work --tries=3

# Voir toutes les routes
php artisan route:list
```

---

## Structure des environnements

| Fichier | Usage |
|---------|-------|
| `.env.example` | Modèle pour développement local (SQLite) |
| `.env.docker.example` | Modèle pour production Docker (MySQL) |
| `docker-compose.yml` | Dev Docker via Laravel Sail (MySQL 8.4) |
| `docker-compose.prod.yml` | Production Docker (app + queue + MySQL) |
| `Dockerfile` | Image multi-stage : composer → node → php-fpm |
