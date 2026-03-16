#!/bin/sh
set -e

if [ ! -f "/var/www/vendor/autoload.php" ]; then
    echo ">>> vendor/autoload.php not found, installing Composer dependencies..."
    composer install --optimize-autoloader --no-interaction
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