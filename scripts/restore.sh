#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
backup_dir="${1:-${BACKUP:-}}"

"$root/scripts/restore_db.sh" --check >/dev/null 2>&1 || {
  echo "Restore completo richiede DB attivo. Avvia dev o prod e riprova." >&2
  exit 1
}

"$root/scripts/restore_db.sh" "$backup_dir"
"$root/scripts/restore_files.sh" "$backup_dir"

echo "Restore completo eseguito."
