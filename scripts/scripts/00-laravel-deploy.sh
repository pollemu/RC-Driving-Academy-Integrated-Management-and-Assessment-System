#!/usr/bin/env bash
set -e

echo "Installing Laravel dependencies..."

composer install --no-dev --optimize-autoloader \
    --working-dir=/var/www/html \
    --no-interaction

echo "Clearing Laravel configuration cache..."
php artisan config:clear

echo "Deployment setup complete."
