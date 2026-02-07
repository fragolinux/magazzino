#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
backup_root="$root/backup"
mode_arg="${1:-}"
if [[ "$mode_arg" == "--check" ]]; then
  check_only=1
  backup_dir=""
else
  check_only=0
  backup_dir="${1:-${BACKUP:-}}"
fi

dev_db_id="$(docker compose -f "$root/docker-compose.dev.yml" --project-directory "$root" ps -q db 2>/dev/null || true)"
prod_db_id="$(docker compose --project-directory "$root" ps -q db 2>/dev/null || true)"

if [[ -n "$dev_db_id" && -n "$prod_db_id" ]]; then
  if [[ "$dev_db_id" != "$prod_db_id" ]]; then
    echo "Trovati DB attivi sia in dev che in prod. Scegli quale stack usare e riprova." >&2
    exit 1
  fi
fi

if [[ -n "$dev_db_id" ]]; then
  compose_cmd=(docker compose -f "$root/docker-compose.dev.yml" --project-directory "$root")
else
  compose_cmd=(docker compose --project-directory "$root")
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "docker richiesto ma non trovato." >&2
  exit 1
fi

if ! "${compose_cmd[@]}" ps -q db >/dev/null 2>&1; then
  echo "Stack Docker non in esecuzione. Avvia dev o prod e riprova." >&2
  exit 1
fi

db_id="$("${compose_cmd[@]}" ps -q db)"
if [[ -z "$db_id" ]]; then
  echo "Servizio database non attivo." >&2
  exit 1
fi

if ! "${compose_cmd[@]}" exec -T db sh -c 'mysqladmin ping -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --silent' >/dev/null 2>&1; then
  echo "Database non pronto." >&2
  exit 1
fi

if [[ "$check_only" -eq 1 ]]; then
  exit 0
fi

if [[ -z "$backup_dir" ]]; then
  backup_dir="$(ls -1dt "$backup_root"/* 2>/dev/null | head -n1 || true)"
fi

if [[ -z "$backup_dir" || ! -d "$backup_dir" ]]; then
  echo "Cartella backup non trovata." >&2
  exit 1
fi

sql_file="$backup_dir/db.sql"
if [[ ! -f "$sql_file" ]]; then
  echo "db.sql non trovato in $backup_dir" >&2
  exit 1
fi

"${compose_cmd[@]}" exec -T db sh -c 'mysql -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' < "$sql_file"

echo "Restore DB completato da $backup_dir"
