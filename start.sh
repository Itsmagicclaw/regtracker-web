#!/bin/bash
set -e

echo "=== RegTracker Startup ==="
echo "PORT: ${PORT:-not set}"
echo "APP_ENV: ${APP_ENV:-not set}"

# Generate APP_KEY if not set (required for sessions & encryption)
if [ -z "$APP_KEY" ]; then
  echo "APP_KEY not set — generating one..."
  php artisan key:generate --force
else
  echo "APP_KEY is set."
fi

echo "Clearing all caches (ensures runtime env vars and latest routes are used)..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "Running database migrations..."
php artisan migrate --force

echo "Seeding regulatory sources..."
php artisan db:seed --force

echo "Starting Laravel server on port ${PORT:-8080}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
