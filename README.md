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
| Développement local | SQLite | — |
| Production | MySQL | 8.x |

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
cp .env.example .env

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

## Switch entre SQLite et MySQL

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

### Production → MySQL

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=muse
DB_USERNAME=root
DB_PASSWORD=password
```

```bash
# Après avoir changé .env, vider le cache de config
php artisan config:clear
php artisan migrate
```

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
| `.env.example` | Modèle pour le développement local |
