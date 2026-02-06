.RECIPEPREFIX := >

SHELL := /bin/sh
UNAME_S := $(shell uname -s)

.PHONY: help up down devup devdown backup restore release logs run run-safe clone dbcheck

help:
> @echo "Uso: make <target>"
> @echo ""
> @echo "Target:"
> @echo "  up       Avvia stack produzione (scripts/start.sh)"
> @echo "  down     Ferma stack produzione (docker compose down)"
> @echo "  devup    Avvia stack sviluppo (scripts/start-dev.sh)"
> @echo "  devdown  Ferma stack sviluppo (docker compose -f docker-compose.dev.yml down)"
> @echo "  run      Aggiorna repo e riavvia stack produzione"
> @echo "  run-safe Backup + aggiorna repo + riavvia stack produzione"
> @echo "  clone    Clona ultimo tag in nuova cartella e copia dati utente"
> @echo "  backup   Backup DB (scripts/backup.sh)"
> @echo "  restore  Ripristina DB da BACKUP (scripts/restore.sh BACKUP=...)"
> @echo "  release  Push main e crea/pusha tag (TAG=...)"
> @echo "  logs     Log dello stack attivo (dev o prod)"
> @echo "  dbcheck  Verifica migrazioni pendenti (dev o prod)"
> @echo ""
> @echo "Esempi:"
> @echo "  make up"
> @echo "  make devup"
> @echo "  make restore BACKUP=backup/20250101_120000"
> @echo "  make release TAG=v1.4"

check-linux:
> @if [ "$(UNAME_S)" != "Linux" ]; then \
>     echo "Questo Makefile è pensato solo per Linux."; \
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
> docker compose -f docker-compose.dev.yml down
> docker compose down
> ./scripts/start.sh

run-safe: check-linux
> ./scripts/backup.sh
> git pull --rebase
> docker compose -f docker-compose.dev.yml down
> docker compose down
> ./scripts/start.sh

clone: check-linux
> ./scripts/clone.sh

logs: check-linux
> if docker compose -f docker-compose.dev.yml ps >/dev/null 2>&1; then \
>   docker compose -f docker-compose.dev.yml logs -f --tail=200; \
> else \
>   docker compose logs -f --tail=200; \
> fi

dbcheck: check-linux
> ./scripts/dbcheck.sh

backup: check-linux
> ./scripts/backup.sh

restore: check-linux
> if [ -z "$(BACKUP)" ]; then \
>     echo "Manca il path BACKUP. Esempio: make restore BACKUP=backup/20250101_120000"; \
>     exit 1; \
>   fi
> ./scripts/restore.sh "$(BACKUP)"

release: check-linux
> @if [ -z "$(TAG)" ]; then \
>     echo "Manca TAG. Esempio: make release TAG=v1.4"; \
>     exit 1; \
>   fi
> git push origin main
> git tag "$(TAG)"
> git push origin "$(TAG)"
