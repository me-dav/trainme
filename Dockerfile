# syntax=docker/dockerfile:1

# ---- 1. PHP dependencies (no dev packages) ------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---- 2. Frontend assets -------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---- 3. Runtime: FrankenPHP, non-root ------------------------------------------
FROM php:8.3-fpm-alpine AS app
RUN apk add --no-cache postgresql-dev libzip-dev icu-dev libpng-dev libjpeg-turbo-dev freetype-dev \
      && docker-php-ext-configure gd --with-freetype --with-jpeg \
      && docker-php-ext-install pdo_pgsql zip intl gd bcmath opcache

# Install ekstensi Redis via PECL
RUN { echo "upload_max_filesize=21M"; echo "post_max_size=22M"; echo "memory_limit=256M"; \
      echo "opcache.enable=1"; echo "opcache.validate_timestamps=0"; } > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html
COPY --from=vendor /app ./
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
      && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
      && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# --- nginx (hanya file statis dari public/) ---
FROM nginx:alpine AS nginx
COPY --from=app /var/www/html/public /var/www/html/public
