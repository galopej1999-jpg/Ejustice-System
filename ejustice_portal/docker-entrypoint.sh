#!/usr/bin/env sh
# Entrypoint to ensure permissions for storage and run php-fpm

set -e

# Ensure storage directories exist and have correct permissions
mkdir -p /var/www/html/storage/documents
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

# If a DOC_ENC_KEY is provided via env, ensure it's available (no-op here)

# Execute the given command (default to php-fpm)
if [ "$#" -eq 0 ]; then
  exec php-fpm
else
  exec "$@"
fi
