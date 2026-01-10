#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

dirs=(
  "$root/data/uploads/datasheet"
  "$root/data/uploads/images"
  "$root/data/db"
  "$root/data/nginx-logs"
  "$root/data/php-logs"
)

for dir in "${dirs[@]}"; do
  mkdir -p "$dir"
  gitkeep="$dir/.gitkeep"
  if [[ ! -f "$gitkeep" ]]; then
    : > "$gitkeep"
  fi
done
