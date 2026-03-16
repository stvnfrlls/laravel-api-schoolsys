#!/bin/sh
set -e

if [ "$APP_ENV" = "local" ]; then
    echo ">>> Local environment, installing with dev dependencies..."
    composer install --optimize-autoloader --no-interaction
elif [ ! -d "/var/www/vendor" ]; then
    echo ">>> vendor/ not found, installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
else
    echo ">>> vendor/ already exists, skipping composer install..."
fi

echo ">>> Setting permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo ">>> Waiting for database to be ready..."
until php artisan migrate:status > /dev/null 2>&1; do
    echo "DB not ready, retrying in 3s..."
    sleep 3
done

echo ">>> Running migrations..."
php artisan migrate --force

echo ">>> Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf