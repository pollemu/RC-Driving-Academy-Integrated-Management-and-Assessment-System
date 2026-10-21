#!/usr/bin/env bash
set -e

cd /var/www/html

echo "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "Preparing Laravel..."
php artisan config:clear

echo "Deployment setup complete."
