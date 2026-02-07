#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [ -f "$root/.env" ]; then
  # shellcheck disable=SC1090
  source "$root/.env"
fi

db_name="${DB_NAME:-}"
db_root_password="${DB_ROOT_PASSWORD:-}"

if [ -z "$db_name" ] || [ -z "$db_root_password" ]; then
  echo "Missing DB_NAME or DB_ROOT_PASSWORD in .env" >&2
  exit 1
fi

dev_db_id="$(docker compose -f "$root/docker-compose.dev.yml" --project-directory "$root" ps -q db 2>/dev/null || true)"
prod_db_id="$(docker compose --project-directory "$root" ps -q db 2>/dev/null || true)"

if [[ -z "$dev_db_id" && -z "$prod_db_id" ]]; then
  echo "DB non attivo. Avvia dev o prod e riprova." >&2
  exit 1
fi

if [[ -n "$dev_db_id" && -n "$prod_db_id" && "$dev_db_id" != "$prod_db_id" ]]; then
  echo "Trovati DB attivi sia in dev che in prod. Scegli quale stack usare e riprova." >&2
  exit 1
fi

if [[ -n "$dev_db_id" ]]; then
  compose_args=(-f "$root/docker-compose.dev.yml" --project-directory "$root")
else
  compose_args=(--project-directory "$root")
fi

migrations_dir="$root/magazzino/update/migrations"
if [ ! -d "$migrations_dir" ]; then
  echo "Missing migrations dir: $migrations_dir" >&2
  exit 1
fi

latest_version="$(
  ls -1 "$migrations_dir"/*.sql 2>/dev/null \
    | sed -E 's#.*/##; s/\.sql$//' \
    | sort -V \
    | tail -n1
)"

current_version="$(
  docker compose "${compose_args[@]}" exec -T db mysql -uroot -p"${db_root_password}" -N -B -e \
    "USE ${db_name}; \
     SELECT version FROM db_version ORDER BY id DESC LIMIT 1;" 2>/dev/null \
    || true
)"

if [ -z "$current_version" ]; then
  echo "Impossibile leggere la versione DB (db_version). Controlla credenziali/permessi." >&2
  exit 1
fi

echo "DB version: ${current_version}"
echo "Latest migration: ${latest_version:-n/a}"

versions="$(
  ls -1 "$migrations_dir"/*.sql 2>/dev/null \
    | sed -E 's#.*/##; s/\.sql$//' \
    | sort -V \
    || true
)"

pending=""
while IFS= read -r v; do
  if [ -z "$v" ]; then
    continue
  fi
  first="$(printf '%s\n' "$current_version" "$v" | sort -V | head -n1)"
  if [ "$first" = "$current_version" ] && [ "$v" != "$current_version" ]; then
    pending+="${v}"$'\n'
  fi
done <<< "$versions"

if [ -z "$pending" ]; then
  echo "Pending migrations: none"
  exit 0
fi

echo "Pending migrations:"
echo "$pending" | awk '{print "- " $1 " (" $1 ".sql)"}'
