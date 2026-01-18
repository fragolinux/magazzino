#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
parent_dir="$(dirname "$root")"
repo_name="$(basename "$root")"

if ! command -v git >/dev/null 2>&1; then
  echo "git is required." >&2
  exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
  echo "rsync is required." >&2
  exit 1
fi

git -C "$root" fetch --tags
latest_tag="$(git -C "$root" tag --sort=-v:refname | head -n 1)"
if [[ -z "$latest_tag" ]]; then
  echo "No tags found; cannot determine latest version." >&2
  exit 1
fi

target_dir="${parent_dir}/${repo_name}-${latest_tag}"
if [[ -e "$target_dir" ]]; then
  echo "Target directory already exists: $target_dir" >&2
  exit 1
fi

echo "Stopping containers (dev + prod) if running..."
docker compose -f "$root/docker-compose.dev.yml" down >/dev/null 2>&1 || true
docker compose -f "$root/docker-compose.yml" down >/dev/null 2>&1 || true

echo "Cloning ${repo_name} into ${target_dir}..."
git clone --branch "$latest_tag" "$root" "$target_dir"

echo "Copying user data..."
rsync -a "$root/data/" "$target_dir/data/"
rsync -a "$root/backup/" "$target_dir/backup/"
if [[ -f "$root/.env" ]]; then
  rsync -a "$root/.env" "$target_dir/.env"
fi

echo "Done. New clone: $target_dir"
