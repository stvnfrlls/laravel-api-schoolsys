FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    curl \
    zip \
    unzip \
    git \
    libpq-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy Nginx config
COPY docker/nginx/default.conf /etc/nginx/sites-available/default

# Copy Supervisor config
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script
COPY docker/php/entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r//' /entrypoint.sh \
    && chmod +x /entrypoint.sh

WORKDIR /var/www

COPY src/ .

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]