#!/bin/sh
set -e

uploads_dir="/var/www/html"
datasheet_dir="${uploads_dir}/datasheet"
images_dir="${uploads_dir}/images"

mkdir -p "$datasheet_dir" "$images_dir"
chown -R www-data:www-data "$datasheet_dir" "$images_dir" 2>/dev/null || true
chmod -R 775 "$datasheet_dir" "$images_dir" 2>/dev/null || true

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
