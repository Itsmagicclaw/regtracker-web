#!/bin/bash
set -e

echo "Running database migrations..."
# Try normal migrate first; if tables already exist from a partial run, use fresh
php artisan migrate --force || {
    echo "Migration failed (possible schema conflict), running migrate:fresh..."
    php artisan migrate:fresh --force
}

echo "Seeding regulatory sources..."
php artisan db:seed --force

echo "Starting Laravel server..."
exec php artisan serve --host=0.0.0.0 --port=$PORT
