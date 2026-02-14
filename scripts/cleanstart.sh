#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
timestamp="$(date +"%Y%m%d_%H%M%S")"
backup_dir="${1:-${BACKUP:-$root/backup/$timestamp}}"
temp_env_created=0

echo "Questo comando fara':"
echo "- backup DB e file in: $backup_dir"
echo "- stop stack dev/prod"
echo "- rimozione .env"
echo "- pulizia contenuti runtime in data/"
echo "- rimozione immagine locale magazzino-php:latest"
echo
printf "Confermi cleanstart? Digita YES per continuare: "
read -r confirm
if [[ "$confirm" != "YES" ]]; then
  echo "Annullato."
  exit 0
fi

echo "Backup preventivo..."
# Evita warning compose con variabili mancanti se .env non esiste.
if [[ ! -f "$root/.env" && -f "$root/.env.example" ]]; then
  cp "$root/.env.example" "$root/.env"
  temp_env_created=1
fi

if ! "$root/scripts/backup_db.sh" "$backup_dir"; then
  echo "Backup DB non disponibile (servizio DB non attivo/non pronto)." >&2
  printf "Continuare cleanstart SENZA backup DB? Digita CONTINUE per proseguire: "
  read -r force_continue
  if [[ "$force_continue" != "CONTINUE" ]]; then
    if [[ "$temp_env_created" -eq 1 ]]; then
      rm -f "$root/.env"
    fi
    echo "Annullato."
    exit 1
  fi
fi
"$root/scripts/backup_files.sh" "$backup_dir"
echo "Backup completato in $backup_dir"

echo "Stop stack (dev/prod)..."
docker compose -f "$root/docker-compose.dev.yml" --project-directory "$root" down || true
docker compose --project-directory "$root" down || true

echo "Rimozione .env..."
rm -f "$root/.env"

echo "Pulizia data/..."
if command -v sudo >/dev/null 2>&1; then
  sudo rm -rf \
    "$root/data/db/"* \
    "$root/data/config/"* \
    "$root/data/nginx-logs/"* \
    "$root/data/php-logs/"* \
    "$root/data/uploads/datasheet/"* \
    "$root/data/uploads/images/"*
else
  rm -rf \
    "$root/data/db/"* \
    "$root/data/config/"* \
    "$root/data/nginx-logs/"* \
    "$root/data/php-logs/"* \
    "$root/data/uploads/datasheet/"* \
    "$root/data/uploads/images/"*
fi

if command -v sudo >/dev/null 2>&1; then
  sudo mkdir -p \
    "$root/data/db" \
    "$root/data/config" \
    "$root/data/nginx-logs" \
    "$root/data/php-logs" \
    "$root/data/uploads" \
    "$root/data/uploads/datasheet" \
    "$root/data/uploads/images" \
    "$root/data/uploads/images/components" \
    "$root/data/uploads/images/components/thumbs"
  sudo chown -R "$(id -u):$(id -g)" "$root/data"
else
  mkdir -p \
    "$root/data/db" \
    "$root/data/config" \
    "$root/data/nginx-logs" \
    "$root/data/php-logs" \
    "$root/data/uploads" \
    "$root/data/uploads/datasheet" \
    "$root/data/uploads/images" \
    "$root/data/uploads/images/components" \
    "$root/data/uploads/images/components/thumbs"
fi

touch \
  "$root/data/.gitkeep" \
  "$root/data/db/.gitkeep" \
  "$root/data/config/.gitkeep" \
  "$root/data/nginx-logs/.gitkeep" \
  "$root/data/php-logs/.gitkeep" \
  "$root/data/uploads/.gitkeep" \
  "$root/data/uploads/datasheet/.gitkeep" \
  "$root/data/uploads/images/.gitkeep" \
  "$root/data/uploads/images/components/.gitkeep" \
  "$root/data/uploads/images/components/thumbs/.gitkeep"

echo "Rimozione immagine locale dev..."
docker image rm magazzino-php:latest >/dev/null 2>&1 || true

echo
echo "Cleanstart completato."
echo "Backup: $backup_dir"
echo "Prossimo passo: make devup (se .env manca, viene creato automaticamente da .env.example)"
