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

version_suffix="${latest_tag#v}"
target_dir="${parent_dir}/${repo_name}-${version_suffix}"
if [[ -e "$target_dir" ]]; then
  echo "Target directory already exists: $target_dir" >&2
  exit 1
fi

echo "Stopping containers (dev + prod) if running..."
docker compose -f "$root/docker-compose.dev.yml" down >/dev/null 2>&1 || true
docker compose -f "$root/docker-compose.yml" down >/dev/null 2>&1 || true

echo "Cloning ${repo_name} into ${target_dir}..."
git clone "$root" "$target_dir"
git -C "$target_dir" checkout -b "release-${version_suffix}" "$latest_tag"

echo "Copying user data..."
rsync -a --exclude 'db/***' "$root/data/" "$target_dir/data/"

if ! rsync -a --numeric-ids "$root/data/db/" "$target_dir/data/db/" 2>/dev/null; then
  if command -v sudo >/dev/null 2>&1; then
    echo "Using sudo to copy data/db (requires password)..." >&2
    sudo rsync -a --numeric-ids "$root/data/db/" "$target_dir/data/db/"
  else
    echo "Cannot copy data/db without sudo; run this script with sudo or copy data/db manually." >&2
    exit 1
  fi
fi

rsync -a "$root/backup/" "$target_dir/backup/"
if [[ -f "$root/.env" ]]; then
  rsync -a "$root/.env" "$target_dir/.env"
fi

echo "Done. New clone: $target_dir"
