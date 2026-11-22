#!/bin/sh
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
  echo "ERROR: falta APP_KEY en el archivo .env (ver DESPLIEGUE.md, paso 3)." >&2
  exit 1
fi

# Espera a que MySQL acepte conexiones
intentos=0
until php -r 'new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306).";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' 2>/dev/null; do
  intentos=$((intentos + 1))
  if [ "$intentos" -gt 60 ]; then echo "ERROR: MySQL no responde." >&2; exit 1; fi
  sleep 2
done

mkdir -p storage/app/private storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan package:discover --ansi || true
php artisan migrate --force
php artisan config:cache

exec "$@"
