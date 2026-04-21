#!/bin/sh
set -eu

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

php artisan migrate --force
php artisan db:seed --class=AdminSeeder --force
php artisan config:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
