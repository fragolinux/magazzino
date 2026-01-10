#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

"$root/scripts/ensure-dirs.sh"

if [[ ! -f "$root/.env" ]]; then
  cp "$root/.env.example" "$root/.env"
fi

docker compose --project-directory "$root" pull
docker compose --project-directory "$root" up -d
