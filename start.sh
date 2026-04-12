#!/bin/bash
set -e

echo "Running database migrations..."
php artisan migrate --force

echo "Seeding regulatory sources..."
php artisan db:seed --force

echo "Starting Laravel server..."
exec php artisan serve --host=0.0.0.0 --port=$PORT
