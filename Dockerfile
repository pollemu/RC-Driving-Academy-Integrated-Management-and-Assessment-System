FROM php:8.4-fpm-alpine

# System packages + PHP extensions Laravel needs (pgsql for Render Postgres).
# opcache ships with the official image; just enable it.
RUN apk add --no-cache \
        nginx curl \
        libpq-dev oniguruma-dev icu-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql pgsql mbstring intl zip bcmath \
    && docker-php-ext-enable opcache \
    && rm -rf /var/cache/apk/*

RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Serve PHP-FPM over a Unix socket (not TCP :9000) so only the nginx HTTP port
# is exposed; a second bound port confuses Render's port detection.
RUN sed -i 's#^listen = .*#listen = /run/php-fpm.sock#' /usr/local/etc/php-fpm.d/*.conf 2>/dev/null || true
RUN printf '[www]\nlisten = /run/php-fpm.sock\nlisten.owner = www-data\nlisten.group = www-data\nlisten.mode = 0660\nclear_env = no\n' \
        > /usr/local/etc/php-fpm.d/zz-listen.conf

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first so the layer is cached across app changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Application code.
COPY . .

RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/nginx.conf.template
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
