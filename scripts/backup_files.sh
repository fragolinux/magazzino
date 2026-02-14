#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
timestamp="$(date +"%Y%m%d_%H%M%S")"
backup_dir="${1:-${BACKUP:-$root/backup/$timestamp}}"
archive="$backup_dir/files.tar.gz"

data_paths=(
  "data/uploads"
  "data/config"
)
optional_paths=(
  ".env"
  "magazzino_db.sql"
)

mkdir -p "$backup_dir"

for dir in "${data_paths[@]}"; do
  if [[ ! -d "$root/$dir" ]]; then
    mkdir -p "$root/$dir"
  fi
done

tar_items=("${data_paths[@]}")
for path in "${optional_paths[@]}"; do
  if [[ -e "$root/$path" ]]; then
    tar_items+=("$path")
  fi
done

run_tar() {
  tar -czf "$archive" -C "$root" "${tar_items[@]}"
}

dev_running="$(docker compose -f "$root/docker-compose.dev.yml" --project-directory "$root" ps -q 2>/dev/null || true)"
prod_running="$(docker compose --project-directory "$root" ps -q 2>/dev/null || true)"
if [[ -n "$dev_running" || -n "$prod_running" ]]; then
  echo "Avviso: lo stack è attivo, il backup file potrebbe non essere consistente." >&2
fi

if run_tar >/dev/null 2>&1; then
  echo "Backup file creato in $archive"
  exit 0
fi

if command -v sudo >/dev/null 2>&1; then
  echo "Permessi insufficienti, riprovo con sudo..." >&2
  sudo tar -czf "$archive" -C "$root" "${tar_items[@]}"
  echo "Backup file creato in $archive"
  exit 0
fi

echo "Backup file fallito: permessi insufficienti e sudo non disponibile." >&2
exit 1
