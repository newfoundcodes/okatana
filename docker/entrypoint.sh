#!/bin/sh
set -eu

cd /var/www/html
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache /data
chown -R www-data:www-data storage bootstrap/cache /data

if [ ! -e public/storage ]; then
  gosu www-data php artisan storage:link >/dev/null 2>&1 || true
fi

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ -n "${DB_DATABASE:-}" ] && [ "${DB_DATABASE}" != ":memory:" ]; then
  mkdir -p "$(dirname "$DB_DATABASE")"
  touch "$DB_DATABASE"

  chown www-data:www-data "$DB_DATABASE"
fi

if [ "${OKATANA_AUTO_MIGRATE:-false}" = "true" ]; then
  gosu www-data php artisan migrate --force
fi

if [ "${1:-}" = "php" ]; then
  exec gosu www-data "$@"
fi

exec "$@"
