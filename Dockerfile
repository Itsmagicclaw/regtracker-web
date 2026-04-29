FROM php:8.3-cli

# Install system dependencies (Debian-based, sqlite3 already available)
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

# Set permissions
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8080

CMD ["bash", "start.sh"]
