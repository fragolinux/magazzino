.RECIPEPREFIX := >

SHELL := /bin/sh
UNAME_S := $(shell uname -s)

.PHONY: help up down devup devdown backup restore release logs run

help:
> @echo "Usage: make <target>"
> @echo ""
> @echo "Targets:"
> @echo "  up       Start production stack (scripts/start.sh)"
> @echo "  down     Stop production stack (docker compose down)"
> @echo "  devup    Start dev stack (scripts/start-dev.sh)"
> @echo "  devdown  Stop dev stack (docker compose -f docker-compose.dev.yml down)"
> @echo "  run      Update repo and restart production stack"
> @echo "  backup   Run DB backup (scripts/backup.sh)"
> @echo "  restore  Restore DB from BACKUP path (scripts/restore.sh BACKUP=...)"
> @echo "  release  Push main and create/push tag (TAG=...)"
> @echo "  logs     Tail logs for the active stack (dev or prod)"
> @echo ""
> @echo "Examples:"
> @echo "  make up"
> @echo "  make devup"
> @echo "  make restore BACKUP=backup/20250101_120000"
> @echo "  make release TAG=v1.4"

check-linux:
> @if [ "$(UNAME_S)" != "Linux" ]; then \
>     echo "This Makefile is intended for Linux only."; \
>     exit 1; \
>   fi

up: check-linux
> ./scripts/start.sh

down: check-linux
> docker compose down

devup: check-linux
> ./scripts/start-dev.sh

devdown: check-linux
> docker compose -f docker-compose.dev.yml down

run: check-linux
> git pull --rebase
> docker compose down
> ./scripts/start.sh

logs: check-linux
> if docker compose -f docker-compose.dev.yml ps >/dev/null 2>&1; then \
>   docker compose -f docker-compose.dev.yml logs -f --tail=200; \
> else \
>   docker compose logs -f --tail=200; \
> fi

backup: check-linux
> ./scripts/backup.sh

restore: check-linux
> if [ -z "$(BACKUP)" ]; then \
>     echo "Missing BACKUP path. Example: make restore BACKUP=backup/20250101_120000"; \
>     exit 1; \
>   fi
> ./scripts/restore.sh "$(BACKUP)"

release: check-linux
> @if [ -z "$(TAG)" ]; then \
>     echo "Missing TAG. Example: make release TAG=v1.4"; \
>     exit 1; \
>   fi
> git push origin main
> git tag "$(TAG)"
> git push origin "$(TAG)"
