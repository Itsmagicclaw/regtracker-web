#!/bin/bash
set -e

echo "=== RegTracker Startup ==="
echo "PORT: ${PORT:-not set}"
echo "APP_ENV: ${APP_ENV:-not set}"

echo "Clearing config cache (ensures runtime env vars are used)..."
php artisan config:clear
php artisan cache:clear

echo "Running database migrations..."
php artisan migrate --force

echo "Seeding regulatory sources..."
php artisan db:seed --force

echo "Starting Laravel server on port ${PORT:-8080}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
