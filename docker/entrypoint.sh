#!/bin/sh
# RC Driving Academy — container entrypoint.
# Boots nginx + php-fpm on Render. Intentionally resilient: a configuration
# problem should degrade features (email, seeding) but must never prevent the
# HTTP server from starting.

cd /var/www/html || exit 1

# Writable runtime dirs (the filesystem is fresh on every boot).
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Clear any config cached at image-build time so runtime env is authoritative.
rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/events.php 2>/dev/null || true

export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"

echo "== RC Driving Academy boot =="
echo "APP_ENV=${APP_ENV} APP_DEBUG=${APP_DEBUG}"
echo "DB_CONNECTION=${DB_CONNECTION:-<unset>} DB_URL=${DB_URL:+<set>}"
echo "SESSION_DRIVER=${SESSION_DRIVER:-<unset>} CACHE_STORE=${CACHE_STORE:-<unset>}"

if [ -z "$APP_KEY" ]; then
    echo "WARNING: APP_KEY is not set; generating an ephemeral one." >&2
    echo "         Set a stable APP_KEY in the Render dashboard:" >&2
    echo "         php artisan key:generate --show" >&2
    php artisan key:generate --force 2>/dev/null || true
fi

# Render provides the web port via $PORT (default 80 for local runs).
PORT="${PORT:-80}"
if ! sed "s/__PORT__/${PORT}/" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf 2>/dev/null; then
    echo "WARNING: failed to render nginx config; falling back to port 80." >&2
    sed "s/__PORT__/80/" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
fi

# Best-effort framework bootstrapping. Failures are logged, not fatal.
php artisan package:discover --ansi 2>&1 | tail -n 3 || true
php artisan migrate --force 2>&1 | tail -n 5 || echo "WARNING: migrate failed (continuing)." >&2
php artisan db:seed --force 2>&1 | tail -n 3 || true
php artisan config:cache 2>&1 | tail -n 2 || true
php artisan view:cache 2>&1 | tail -n 2 || true

echo "== boot complete; starting php-fpm + nginx =="

# php-fpm in the background, nginx in the foreground (Render expects one process).
php-fpm -D || echo "WARNING: php-fpm failed to start." >&2
exec nginx -g "daemon off;"
