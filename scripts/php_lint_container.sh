#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [ "$#" -eq 0 ]; then
  echo "Usage: $0 <php-file> [<php-file> ...]" >&2
  exit 1
fi

files=()
for file in "$@"; do
  case "$file" in
    /*)
      if [[ "$file" != "$root/"* ]]; then
        echo "File outside repo not allowed: $file" >&2
        exit 1
      fi
      repo_file="${file#$root/}"
      ;;
    *)
      repo_file="$file"
      ;;
  esac

  if [ ! -f "$root/$repo_file" ]; then
    echo "Missing file: $repo_file" >&2
    exit 1
  fi

  case "$repo_file" in
    magazzino/*)
      files+=("/var/www/html/${repo_file#magazzino/}")
      ;;
    data/config/*)
      files+=("/var/www/html/config/${repo_file#data/config/}")
      ;;
    *)
      echo "Unsupported path for container lint: $repo_file" >&2
      exit 1
      ;;
  esac
done

docker compose -f "$root/docker-compose.dev.yml" --project-directory "$root" \
  run --rm --no-deps --entrypoint sh php -lc '
    set -eu
    for file in "$@"; do
      php -l "$file"
    done
  ' sh "${files[@]}"
