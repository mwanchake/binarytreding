#!/bin/sh
set -eu

port="${PORT:-10000}"
sed -i "s/10000/${port}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --database=pgsql
fi

if [ "${LARAVEL_CONFIG_CACHE:-true}" = "true" ]; then
    php artisan config:cache
fi

exec "$@"
