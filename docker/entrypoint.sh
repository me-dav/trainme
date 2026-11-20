#!/bin/sh
# Prepares the Laravel app, then hands PID 1 to the server process (exec).
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY belum diset. Buat dengan: php artisan key:generate --show" >&2
    exit 1
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
