#!/bin/sh
set -e

if [ -f "/var/www/composer.json" ]; then
    echo ">>> Installing Composer dependencies..."
    composer install --optimize-autoloader
else
    echo ">>> No composer.json found, skipping composer install..."
fi

echo ">>> Setting permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo ">>> Running migrations..."
php artisan migrate:fresh --seed --force

echo ">>> Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf