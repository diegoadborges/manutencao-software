#!/bin/sh
set -e

# Initialize storage directory if empty
if [ ! "$(ls -A /var/www/storage)" ]; then
  echo "Initializing storage directory..."
  cp -R /var/www/storage-init/. /var/www/storage
  chown -R www-data:www-data /var/www/storage
fi

# Remove storage-init directory (optional)
rm -rf /var/www/storage-init

# Run Laravel migrations
echo "Running migrations..."
php artisan migrate --force

# Cache config and routes
echo "Caching config and routes..."
php artisan config:cache
php artisan route:cache

# Execute the container's main process (CMD)
exec "$@"
