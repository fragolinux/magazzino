# Linux / WSL

## Prerequisiti

- Docker + Docker Compose
- Porta 80 libera sul host (usata da Nginx; necessaria per i QR code senza porta)
- `git`, `make`, `dialog`

## Setup automatico (Ubuntu esempio)

Questo script installa tutti i prerequisiti e configura l'ambiente.
Eseguilo come utente normale (non `sudo`):

```bash
curl -fsSL https://raw.githubusercontent.com/fragolinux/magazzino/refs/heads/main/setup_wsl_ubuntu.sh -o /tmp/setup_wsl_ubuntu.sh
bash /tmp/setup_wsl_ubuntu.sh --with-zsh
```

In alternativa con `wget`:

```bash
wget -O /tmp/setup_wsl_ubuntu.sh https://raw.githubusercontent.com/fragolinux/magazzino/refs/heads/main/setup_wsl_ubuntu.sh
bash /tmp/setup_wsl_ubuntu.sh --with-zsh
```

## Avvio rapido (utente finale)

```bash
git clone https://github.com/fragolinux/magazzino.git
cd magazzino
make menu
```

In alternativa:
```bash
make up
```

## Aggiornamento rapido

```bash
make clone
cd "$(ls -d ../magazzino-* 2>/dev/null | sort -V | tail -n1)"
make run
```

## Avvio rapido (sviluppo)

```bash
make devup
```

Oppure:
```bash
./scripts/start-dev.sh
```

Facoltativo: fissa una versione specifica delle immagini (esempio `v1.1`):
```bash
MAGAZZINO_TAG=v1.1 docker compose up -d
```

## Comandi Make

Da root del repo:

```bash
make menu       # menu interattivo
make up         # avvio prod (./scripts/start.sh)
make down       # stop prod (docker compose down)
make run        # aggiorna repo + restart prod (git pull --rebase + ./scripts/start.sh)
make run-safe   # backup + aggiorna repo + restart prod (./scripts/backup.sh + ./scripts/start.sh)
make clone      # clone pulito + copia dati utente (./scripts/clone.sh)
make dbcheck    # verifica migrazioni pendenti (./scripts/dbcheck.sh)
make backup     # backup completo (DB + file) (./scripts/backup.sh)
make backup-db  # backup solo DB (./scripts/backup_db.sh)
make backup-file # backup solo file (./scripts/backup_files.sh)
make restore     # restore completo (DB + file) (./scripts/restore.sh)
make restore-db  # restore solo DB (./scripts/restore_db.sh)
make restore-file # restore solo file (./scripts/restore_files.sh)
make logs       # log stack attivo (docker compose logs -f --tail=200)
make devup      # avvio dev (./scripts/start-dev.sh)
make devdown    # stop dev (docker compose -f docker-compose.dev.yml down)
make release    # push main + tag (git push + git tag)
```

Nota: `make dbcheck` usa `scripts/dbcheck.sh` e legge `DB_ROOT_PASSWORD` da `.env`.
Non modifica la cartella `magazzino/`.
