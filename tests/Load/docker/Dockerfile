# PHP-FPM 8.3 proche de la production (PostgreSQL, opcache). Construit depuis la racine du projet.
FROM php:8.3-fpm-alpine

RUN apk add --no-cache libpq-dev icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql pgsql intl zip mbstring opcache bcmath pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

RUN { echo 'opcache.enable=1'; echo 'opcache.memory_consumption=192'; echo 'opcache.validate_timestamps=0'; echo 'opcache.jit=off'; } > /usr/local/etc/php/conf.d/opcache.ini
# Nombre de processus PHP-FPM : à faire varier pendant les essais (voir docs/dimensionnement-bd.md).
RUN { echo '[www]'; echo 'pm = static'; echo 'pm.max_children = ${PHP_FPM_CHILDREN:-16}'; } > /usr/local/etc/php-fpm.d/zz-pool.conf
