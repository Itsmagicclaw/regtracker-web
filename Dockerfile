FROM php:8.3-cli-alpine

# Install system dependencies
RUN apk add --no-cache git zip unzip curl bash sqlite-dev

# Install PHP extensions (pdo is built-in, sqlite-dev needed for pdo_sqlite)
RUN docker-php-ext-install pdo_sqlite opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8080

CMD ["bash", "start.sh"]
