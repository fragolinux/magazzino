#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
timestamp="$(date +"%Y%m%d_%H%M%S")"
backup_dir="$root/backup/$timestamp"
compose_cmd=(docker compose --project-directory "$root")

if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required but not found." >&2
  exit 1
fi

if ! "${compose_cmd[@]}" ps -q db >/dev/null 2>&1; then
  echo "Docker Compose project is not running. Start it with: docker compose up -d" >&2
  exit 1
fi

db_id="$("${compose_cmd[@]}" ps -q db)"
if [[ -z "$db_id" ]]; then
  echo "Database service is not running." >&2
  exit 1
fi

if ! "${compose_cmd[@]}" exec -T db sh -c 'mysqladmin ping -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --silent' >/dev/null 2>&1; then
  echo "Database is not ready yet." >&2
  exit 1
fi

mkdir -p "$backup_dir"

"${compose_cmd[@]}" exec -T db sh -c 'mysqldump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' > "$backup_dir/db.sql"

echo "Backup created at $backup_dir"
