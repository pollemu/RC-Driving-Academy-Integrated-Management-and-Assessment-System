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

# Use a .env if one is present; otherwise fall back to the committed template.
# On Render, configuration comes from environment variables (which take
# precedence over .env), so this mainly provides sane defaults.
if [ ! -f .env ]; then
    cp .env.example .env 2>/dev/null || touch .env
fi

# Never advertise debug info on a public host unless explicitly overridden.
export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"

# Render gives the web port via $PORT (defaults to 80 for local docker runs).
PORT="${PORT:-80}"
sed "s/__PORT__/${PORT}/" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Generate an application key only if none was supplied. Set APP_KEY as a
# Render environment variable so it stays stable across deploys/restarts.
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force || true
fi

php artisan package:discover --ansi || true

# Apply schema and seed demo data. Both are idempotent, so this is safe on
# every boot (new migrations apply; seeding is skipped once data exists).
php artisan migrate --force
php artisan db:seed --force || true

# Cache config/views for speed. Routes are not cached because the app defines
# closure routes (which cannot be serialised).
php artisan config:cache || true
php artisan view:cache || true

# php-fpm background, nginx foreground (Render expects one process).
php-fpm -D
exec nginx -g "daemon off;"
