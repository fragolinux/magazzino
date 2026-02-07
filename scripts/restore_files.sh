#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
backup_root="$root/backup"
backup_dir="${1:-${BACKUP:-}}"
archive_name="files.tar.gz"

dev_running="$(docker compose -f "$root/docker-compose.dev.yml" --project-directory "$root" ps -q 2>/dev/null || true)"
prod_running="$(docker compose --project-directory "$root" ps -q 2>/dev/null || true)"

stop_dev=0
stop_prod=0
if [[ -n "$dev_running" ]]; then
  stop_dev=1
fi
if [[ -n "$prod_running" ]]; then
  stop_prod=1
fi

if [[ "$stop_dev" -eq 1 || "$stop_prod" -eq 1 ]]; then
  echo "Stack attivo rilevato: fermo i servizi per un restore file sicuro..." >&2
  if [[ "$stop_dev" -eq 1 ]]; then
    docker compose -f "$root/docker-compose.dev.yml" --project-directory "$root" down
  fi
  if [[ "$stop_prod" -eq 1 ]]; then
    docker compose --project-directory "$root" down
  fi
fi

if [[ -z "$backup_dir" ]]; then
  backup_dir="$(ls -1dt "$backup_root"/* 2>/dev/null | head -n1 || true)"
fi

if [[ -z "$backup_dir" || ! -d "$backup_dir" ]]; then
  echo "Cartella backup non trovata." >&2
  exit 1
fi

archive="$backup_dir/$archive_name"
if [[ ! -f "$archive" ]]; then
  echo "$archive_name non trovato in $backup_dir" >&2
  exit 1
fi

run_extract() {
  tar -xzf "$archive" -C "$root"
}

if ! run_extract >/dev/null 2>&1; then
  if command -v sudo >/dev/null 2>&1; then
    echo "Permessi insufficienti, riprovo con sudo..." >&2
    sudo tar -xzf "$archive" -C "$root"
  else
    echo "Restore file fallito: permessi insufficienti e sudo non disponibile." >&2
    exit 1
  fi
fi

if command -v sudo >/dev/null 2>&1; then
  sudo chown -R 33:33 "$root/data/uploads" 2>/dev/null || true
  sudo find "$root/data/uploads" -type d -exec chmod 775 {} + 2>/dev/null || true
  sudo find "$root/data/uploads" -type f -exec chmod 664 {} + 2>/dev/null || true
  sudo chown -R 33:33 "$root/data/config" 2>/dev/null || true
  sudo find "$root/data/config" -type d -exec chmod 775 {} + 2>/dev/null || true
  sudo find "$root/data/config" -type f -exec chmod 664 {} + 2>/dev/null || true
else
  echo "Nota: sudo non disponibile; permessi su data/uploads e data/config non aggiornati." >&2
fi

if [[ "$stop_dev" -eq 1 || "$stop_prod" -eq 1 ]]; then
  echo "Ripristino lo stato precedente dei servizi..." >&2
  if [[ "$stop_dev" -eq 1 ]]; then
    docker compose -f "$root/docker-compose.dev.yml" --project-directory "$root" up -d
  fi
  if [[ "$stop_prod" -eq 1 ]]; then
    docker compose --project-directory "$root" up -d
  fi
fi

echo "Restore file completato da $backup_dir"
