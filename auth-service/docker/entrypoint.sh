#!/bin/sh
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

php artisan key:generate --force --no-interaction >/dev/null 2>&1 || true
php artisan migrate --force --no-interaction
php artisan db:seed --class=AuthCodeSeeder --force --no-interaction || true

chown -R www-data:www-data storage bootstrap/cache || true

exec php-fpm
