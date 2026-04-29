#!/bin/bash
set -e

echo "=== RegTracker Startup ==="

if [ -z "$APP_KEY" ]; then
  php artisan key:generate --force
fi

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "=== Starting server on port ${PORT:-8080} ==="
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
