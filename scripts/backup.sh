#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
timestamp="$(date +"%Y%m%d_%H%M%S")"
backup_dir="${BACKUP:-$root/backup/$timestamp}"

"$root/scripts/backup_db.sh" "$backup_dir"
"$root/scripts/backup_files.sh" "$backup_dir"

echo "Backup completo creato in $backup_dir"
