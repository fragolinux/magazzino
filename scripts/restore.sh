#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
backup_root="$root/backup"
backup_dir="${1:-}"

if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required but not found." >&2
  exit 1
fi

if ! docker compose ps -q db >/dev/null 2>&1; then
  echo "Docker Compose project is not running. Start it with: docker compose up -d" >&2
  exit 1
fi

db_id="$(docker compose ps -q db)"
if [[ -z "$db_id" ]]; then
  echo "Database service is not running." >&2
  exit 1
fi

if ! docker compose exec -T db sh -c 'mysqladmin ping -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --silent' >/dev/null 2>&1; then
  echo "Database is not ready yet." >&2
  exit 1
fi

if [[ -z "$backup_dir" ]]; then
  backup_dir="$(ls -1dt "$backup_root"/* 2>/dev/null | head -n1 || true)"
fi

if [[ -z "$backup_dir" || ! -d "$backup_dir" ]]; then
  echo "Backup folder not found." >&2
  exit 1
fi

sql_file="$backup_dir/db.sql"
if [[ ! -f "$sql_file" ]]; then
  echo "db.sql not found in $backup_dir" >&2
  exit 1
fi

docker compose exec -T db sh -c 'mysql -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' < "$sql_file"

echo "Restore completed from $backup_dir"
