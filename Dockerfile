FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git zip unzip curl \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create required Laravel storage directories
RUN mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache

# Generate APP_KEY and cache config at build time
RUN php artisan key:generate --force
RUN php artisan config:clear
RUN php artisan route:clear
RUN php artisan view:clear

# Set permissions
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8080

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
