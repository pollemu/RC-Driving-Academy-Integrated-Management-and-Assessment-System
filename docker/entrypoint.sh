#!/bin/sh
set -e

cd /var/www/html

# Writable runtime dirs (the filesystem is fresh on every boot).
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

# Clear any config cached at image-build time so runtime env is authoritative.
rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/events.php

# On Render, all configuration comes from environment variables. Do not invent
# a .env here: if config is missing we want the failure to be loud, not silently
# masked by .env.example defaults (which point at sqlite/file drivers).

# Never advertise debug info on a public host unless explicitly overridden.
export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"

if [ -z "$APP_KEY" ]; then
    echo "FATAL: APP_KEY is not set. Set it in the Render dashboard to a value" >&2
    echo "       from: php artisan key:generate --show" >&2
    exit 1
fi

# Render gives the web port via $PORT (defaults to 80 for local docker runs).
PORT="${PORT:-80}"
sed "s/__PORT__/${PORT}/" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Print effective configuration (no secrets) so misconfiguration is obvious in logs.
echo "== RC Driving Academy boot =="
echo "APP_ENV=$APP_ENV APP_DEBUG=$APP_DEBUG"
echo "DB_CONNECTION=${DB_CONNECTION:-<unset>} DB_URL=${DB_URL:+<set>}"
echo "SESSION_DRIVER=${SESSION_DRIVER:-<unset>} CACHE_STORE=${CACHE_STORE:-<unset>}"

php artisan package:discover --ansi || true

# Apply schema and seed demo data. Both are idempotent, so this is safe on
# every boot (new migrations apply; seeding is skipped once data exists).
php artisan migrate --force
php artisan db:seed --force || true

# Cache config/views for speed. Routes are not cached because the app defines
# closure routes (which cannot be serialised).
php artisan config:cache || true
php artisan view:cache || true

echo "== boot complete =="

# php-fpm background, nginx foreground (Render expects one process).
php-fpm -D
exec nginx -g "daemon off;"
