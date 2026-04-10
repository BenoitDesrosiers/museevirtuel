#!/bin/sh
set -e

echo "==> Démarrage de l'application Muse..."

# Correction des permissions sur storage/ et bootstrap/cache/
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Créer le lien symbolique storage -> public/storage
if [ ! -L /var/www/html/public/storage ]; then
    echo "==> Création du lien storage:link..."
    php artisan storage:link
fi

# Optimisations Laravel (caches de config, routes, vues)
echo "==> Optimisation Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations automatiques
echo "==> Exécution des migrations..."
php artisan migrate --force

echo "==> Démarrage de PHP-FPM..."
exec php-fpm
