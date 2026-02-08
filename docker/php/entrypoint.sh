#!/bin/sh
set -e

if [ -n "${TZ:-}" ]; then
    echo "date.timezone=${TZ}" > /usr/local/etc/php/conf.d/99-timezone.ini
fi

uploads_dir="/var/www/html"
datasheet_dir="${uploads_dir}/datasheet"
images_dir="${uploads_dir}/images"
config_dir="/var/www/html/config"

mkdir -p "$datasheet_dir" "$images_dir"
chown -R www-data:www-data "$datasheet_dir" "$images_dir" 2>/dev/null || true
find "$datasheet_dir" "$images_dir" -type d -exec chmod 775 {} + 2>/dev/null || true

if [ -d "$config_dir" ]; then
    chown -R www-data:www-data "$config_dir" 2>/dev/null || true
    find "$config_dir" -type d -exec chmod 775 {} + 2>/dev/null || true
    find "$config_dir" -type f -exec chmod 664 {} + 2>/dev/null || true
fi

if [ -f "/var/www/html/composer.json" ] && [ ! -f "/var/www/html/vendor/autoload.php" ]; then
    if command -v composer >/dev/null 2>&1; then
        echo "Installing PHP dependencies (composer)..." >&2
        export COMPOSER_ALLOW_SUPERUSER=1
        composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader || \
            echo "Composer install failed; barcode features may not work." >&2
    else
        echo "Composer not available; barcode features may not work." >&2
    fi
fi

tries=30
while ! php /usr/local/bin/db_migrate.php; do
    tries=$((tries - 1))
    if [ "$tries" -le 0 ]; then
        echo "DB migration failed after retries; continuing startup." >&2
        break
    fi
    echo "Waiting for DB... ($tries retries left)" >&2
    sleep 2
done

exec docker-php-entrypoint "$@"
