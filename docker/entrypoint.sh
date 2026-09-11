#!/bin/sh
set -e

echo "==> Démarrage de l'application Muse..."

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

if [ "${APP_ENV:-local}" != "production" ] && [ ! -f vendor/autoload.php ]; then
    echo "==> Installation des dépendances Composer (dev)..."
    composer install --no-interaction --prefer-dist
fi

if [ ! -L /var/www/html/public/storage ]; then
    echo "==> Création du lien storage:link..."
    php artisan storage:link
fi

if [ "${APP_ENV:-local}" = "production" ]; then
    echo "==> Optimisation Laravel (production)..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    echo "==> Mode développement — cache Laravel non figé."
    php artisan config:clear
    php artisan view:clear
fi

echo "==> Exécution des migrations..."
php artisan migrate --force

if [ "$#" -gt 0 ]; then
    echo "==> Exécution de la commande: $*"
    exec "$@"
fi

echo "==> Démarrage de nginx + PHP-FPM (supervisord)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
