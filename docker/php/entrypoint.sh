#!/bin/sh
set -e

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
