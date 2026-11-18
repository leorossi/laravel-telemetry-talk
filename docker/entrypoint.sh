#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Bind-mounted source can arrive with unexpected permissions.
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

# vendor/ normally comes from the image via the `vendor` named volume; rebuild
# it if the volume was created empty for some reason.
if [ ! -f vendor/autoload.php ]; then
    echo "vendor/autoload.php is missing - running composer install" >&2
    composer install --no-interaction --prefer-dist --no-progress
fi

exec "$@"
