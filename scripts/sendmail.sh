#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
mode="${1:-auto}"
script_path="/var/www/html/cron/send_low_stock_emails.php"

dev_compose=(
  docker compose
  --project-directory "$root"
  -f "$root/docker-compose.dev.yml"
)
prod_compose=(
  docker compose
  --project-directory "$root"
)

dev_running="$("${dev_compose[@]}" ps --status running -q php 2>/dev/null || true)"
prod_running="$("${prod_compose[@]}" ps --status running -q php 2>/dev/null || true)"

run_dev() {
  "${dev_compose[@]}" exec -T php php "$script_path"
}

run_prod() {
  "${prod_compose[@]}" exec -T php php "$script_path"
}

case "$mode" in
  auto|"")
    if [[ -n "$dev_running" ]]; then
      run_dev
      exit 0
    fi
    if [[ -n "$prod_running" ]]; then
      run_prod
      exit 0
    fi
    echo "No running PHP container found. Start stack with 'make devup' or 'make up'." >&2
    exit 1
    ;;
  dev)
    if [[ -z "$dev_running" ]]; then
      echo "Dev stack is not running. Start it with 'make devup'." >&2
      exit 1
    fi
    run_dev
    ;;
  prod)
    if [[ -z "$prod_running" ]]; then
      echo "Prod stack is not running. Start it with 'make up'." >&2
      exit 1
    fi
    run_prod
    ;;
  *)
    echo "Invalid MODE='$mode'. Allowed values: auto, dev, prod." >&2
    exit 1
    ;;
esac
