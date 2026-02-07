.RECIPEPREFIX := >

SHELL := /bin/sh
UNAME_S := $(shell uname -s)

.PHONY: help up down devup devdown backup backup-db backup-file restore restore-db restore-file release logs run run-safe clone dbcheck

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
> @echo "  backup-db   Backup DB (scripts/backup_db.sh)"
> @echo "  backup-file Backup file dati (data/uploads, data/config)"
> @echo "  backup      Backup completo (DB + file)"
> @echo "  restore-db   Ripristina DB da BACKUP (scripts/restore_db.sh BACKUP=...)"
> @echo "  restore-file Ripristina file dati da BACKUP"
> @echo "  restore      Ripristino completo (DB + file)"
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

backup-db: check-linux
> ./scripts/backup_db.sh "$(BACKUP)"

backup-file: check-linux
> ./scripts/backup_files.sh "$(BACKUP)"

backup: check-linux
> ./scripts/backup.sh "$(BACKUP)"

restore-db: check-linux
> ./scripts/restore_db.sh "$(BACKUP)"

restore-file: check-linux
> ./scripts/restore_files.sh "$(BACKUP)"

restore: check-linux
> ./scripts/restore.sh "$(BACKUP)"

release: check-linux
> @if [ -z "$(TAG)" ]; then \
>     echo "Manca TAG. Esempio: make release TAG=v1.4"; \
>     exit 1; \
>   fi
> git push origin main
> git tag "$(TAG)"
> git push origin "$(TAG)"
