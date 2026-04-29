FROM php:8.3-cli

# Install system dependencies + PHP extension libraries
RUN apt-get update && apt-get install -y \
    git zip unzip curl \
    libcurl4-openssl-dev \
    libxml2-dev \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
RUN docker-php-ext-install curl xml simplexml

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

# Create .env file so artisan commands can run
RUN printf "APP_NAME=RegTracker\nAPP_ENV=production\nAPP_DEBUG=true\nAPP_URL=https://regtracker-web.onrender.com\nLOG_CHANNEL=stderr\nLOG_LEVEL=debug\nCACHE_DRIVER=file\nSESSION_DRIVER=file\nDB_CONNECTION=sqlite\n" > .env

# Generate APP_KEY into .env
RUN php artisan key:generate --force

# Set permissions — 777 so any user Render runs as can write
RUN chmod -R 777 storage bootstrap/cache

EXPOSE 8080

CMD bash -c "php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"
