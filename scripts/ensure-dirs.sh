#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

dirs=(
  "$root/data/uploads/datasheet"
  "$root/data/uploads/images"
  "$root/data/db"
  "$root/data/nginx-logs"
  "$root/data/php-logs"
  "$root/data/config"
)

upload_dirs=(
  "$root/data/uploads/datasheet"
  "$root/data/uploads/images"
)

can_write_as_uid_33() {
  local dir="$1"
  local uid gid mode owner_perms group_perms other_perms

  uid=$(stat -c %u "$dir" 2>/dev/null || echo "")
  gid=$(stat -c %g "$dir" 2>/dev/null || echo "")
  mode=$(stat -c %a "$dir" 2>/dev/null || echo "")

  if [[ -z "$uid" || -z "$gid" || -z "$mode" ]]; then
    return 1
  fi

  owner_perms=$(( (10#${mode} / 100) % 10 ))
  group_perms=$(( (10#${mode} / 10) % 10 ))
  other_perms=$(( 10#${mode} % 10 ))

  if [[ "$uid" -eq 33 && $((owner_perms & 2)) -ne 0 ]]; then
    return 0
  fi
  if [[ "$gid" -eq 33 && $((group_perms & 2)) -ne 0 ]]; then
    return 0
  fi
  if [[ $((other_perms & 2)) -ne 0 ]]; then
    return 0
  fi

  return 1
}

for dir in "${dirs[@]}"; do
  mkdir -p "$dir"
  gitkeep="$dir/.gitkeep"
  if [[ ! -f "$gitkeep" && -w "$dir" ]]; then
    : > "$gitkeep"
  fi
done

config_dir="$root/data/config"
if [[ -d "$config_dir" ]]; then
  if [[ ! -f "$config_dir/base_path.php" && -f "$root/magazzino/config/base_path.php" ]]; then
    cp "$root/magazzino/config/base_path.php" "$config_dir/base_path.php"
  fi
  if [[ ! -f "$config_dir/settings.php" && -f "$root/magazzino/config/settings.php.example" ]]; then
    cp "$root/magazzino/config/settings.php.example" "$config_dir/settings.php"
  fi
  if [[ ! -f "$config_dir/database.php" && -f "$root/overrides/database.php" ]]; then
    cp "$root/overrides/database.php" "$config_dir/database.php"
  fi
fi

needs_fix=0
for dir in "${upload_dirs[@]}"; do
  if ! can_write_as_uid_33 "$dir"; then
    needs_fix=1
    break
  fi
done

if [[ "$needs_fix" -eq 1 ]]; then
  if command -v sudo >/dev/null 2>&1; then
    echo "Adjusting permissions for data/uploads (requires sudo)..." >&2
    sudo chown -R 33:33 "${upload_dirs[@]}"
    sudo find "${upload_dirs[@]}" -type d -exec chmod 775 {} +
  else
    echo "Warning: data/uploads not writable by container user; run chmod/chown manually." >&2
  fi
fi
