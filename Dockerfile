# =============================================================================
# Stage 1 — composer-builder : dépendances PHP + génération Wayfinder
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

COPY . .

# Bootstrap Laravel sans connexion MySQL réelle (Wayfinder lit les routes uniquement)
RUN printf "APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\nAPP_ENV=production\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_DATABASE=muse\nDB_USERNAME=muse\nDB_PASSWORD=secret\n" > .env \
    && php artisan wayfinder:generate --no-interaction


# =============================================================================
# Stage 2 — node-builder : assets front-end (Vite + Tailwind)
# =============================================================================
FROM node:22-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci --ignore-scripts

COPY resources/ resources/
COPY public/ public/
COPY vite.config.ts tsconfig.json ./

COPY --from=composer-builder /app/resources/js/actions resources/js/actions
COPY --from=composer-builder /app/resources/js/routes resources/js/routes
COPY --from=composer-builder /app/resources/js/wayfinder resources/js/wayfinder

RUN npm run build


# =============================================================================
# Stage 3 — runtime : PHP-FPM + nginx + supervisord
# =============================================================================
FROM php:8.4-fpm-alpine AS runtime

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

LABEL maintainer="Muse Application"

RUN apk add --no-cache \
        ffmpeg \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        mysql-client \
        nginx \
        oniguruma-dev \
        supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        xml \
        zip \
    && rm -rf /var/cache/apk/*

RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.save_comments=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

RUN { \
    echo 'post_max_size=100M'; \
    echo 'upload_max_filesize=100M'; \
    echo 'variables_order=EGPCS'; \
} > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

COPY . .
COPY --from=composer-builder /app/vendor ./vendor
COPY --from=node-builder /app/public/build ./public/build

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint"]
