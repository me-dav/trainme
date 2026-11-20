# syntax=docker/dockerfile:1

# ---- 1. Frontend assets -------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---- 2. PHP dependencies (no dev packages) ------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---- 3. Runtime: FrankenPHP, non-root ------------------------------------------
FROM dunglas/frankenphp:1-php8.3 AS runtime

RUN install-php-extensions pdo_pgsql intl zip opcache pcntl \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=:8080

WORKDIR /app

# Code is owned by root and read-only for the app user; only storage/ and
# bootstrap/cache/ are writable (same least-privilege pattern taught in the
# Linux permission module).
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY docker/entrypoint.sh /usr/local/bin/dojo-entrypoint

RUN useradd --uid 10001 --user-group --no-create-home --shell /usr/sbin/nologin app \
    && mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache /data/caddy /config/caddy \
    && chmod -R u=rwX,g=rX,o= storage bootstrap/cache \
    && chmod 0755 /usr/local/bin/dojo-entrypoint

USER app
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8080/up") === false ? 1 : 0);'

ENTRYPOINT ["dojo-entrypoint"]
CMD ["frankenphp", "php-server", "--listen", ":8080", "--root", "/app/public"]
