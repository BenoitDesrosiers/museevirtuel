# =============================================================================
# Stage 1 — composer-builder : installe les dépendances PHP + génère Wayfinder
# (doit être avant node-builder car node-builder en a besoin)
# =============================================================================
FROM composer:2 AS composer-builder

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

# Copier tout le code source (nécessaire pour php artisan wayfinder:generate)
COPY . .

# .env minimal pour bootstrapper Laravel sans base de données
RUN printf "APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\nAPP_ENV=production\nDB_CONNECTION=sqlite\nDB_DATABASE=/tmp/dummy.db\n" > .env \
    && touch /tmp/dummy.db \
    && php artisan wayfinder:generate --no-interaction


# =============================================================================
# Stage 2 — node-builder : compile les assets front-end (Vite + Tailwind)
# =============================================================================
FROM node:22-alpine AS node-builder

WORKDIR /app

# Copier uniquement ce qui est nécessaire pour npm install
COPY package.json package-lock.json ./

RUN npm ci --ignore-scripts

# Copier les sources front-end et la config de build
COPY resources/ resources/
COPY public/ public/
COPY vite.config.ts tsconfig.json ./

# Copier les fichiers Wayfinder générés (ignorés par git, générés en Stage 1)
COPY --from=composer-builder /app/resources/js/actions resources/js/actions
COPY --from=composer-builder /app/resources/js/routes resources/js/routes
COPY --from=composer-builder /app/resources/js/wayfinder resources/js/wayfinder

RUN npm run build


# =============================================================================
# Stage 3 — runtime : image de production finale
# =============================================================================
FROM php:8.4-fpm-alpine AS runtime

LABEL maintainer="Muse Application"

# Extensions PHP nécessaires
RUN apk add --no-cache \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        libxml2-dev \
        icu-dev \
        oniguruma-dev \
        mysql-client \
        nginx \
        supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        zip \
        xml \
        mbstring \
        bcmath \
        intl \
        opcache \
    && rm -rf /var/cache/apk/*

# Configuration OPcache pour la production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.save_comments=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

# Configuration PHP (upload et variables)
RUN { \
    echo 'post_max_size=100M'; \
    echo 'upload_max_filesize=100M'; \
    echo 'variables_order=EGPCS'; \
} > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

# Copier le code source de l'application
COPY . .

# Copier les dépendances depuis les stages précédents
COPY --from=composer-builder /app/vendor ./vendor
COPY --from=node-builder /app/public/build ./public/build

# Copier les fichiers de configuration Docker
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.prod.conf /etc/supervisor/conf.d/supervisord.conf

RUN chmod +x /usr/local/bin/entrypoint

# Permissions Laravel
RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint"]
