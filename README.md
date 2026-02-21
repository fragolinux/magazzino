# Magazzino Componenti Elettronici (Docker)

Questo repository contiene una app PHP 8.0 servita da Nginx + PHP-FPM con database MariaDB.
Tutti i dati persistenti stanno in `data/` e i backup in `backup/`.

## Nota importante (Linux/WSL consigliato)

La migliore esperienza si ottiene su **Linux o WSL**. Le aggiunte recenti (menu `dialog`, comandi `make`,
script `*.sh` per backup/restore avanzati) funzionano solo in ambienti Unix-like.
Su Windows “puro” restano validi gli script PowerShell, ma il menu interattivo e i target Make, che
semplificano molto l'uso e non richiedono conoscenze avanzate di Docker e Linux, non sono disponibili.

## Documentazione

- **Linux/WSL:** vedi `LINUX.md`
- **Windows/WSL:** vedi `WINDOWS.md`
- **Avanzato (DB, override, struttura, dati):** vedi `ADVANCED.md`
- **Backup e restore:** vedi `BACKUP.md`

## Prerequisiti (Linux/WSL)

- Docker + Docker Compose
- Porta 80 libera sul host (usata da Nginx; necessaria per i QR code senza porta)
- `git`, `make`, `dialog`

## Crediti e riferimenti

Questo progetto si basa sul lavoro originale di RG4Tech (Gabriele Riva).
Grazie per lo sviluppo e per aver condiviso il progetto con la community.

- Autore: RG4Tech (Gabriele Riva)
- Sito web del progetto: https://rg4tech.altervista.org/forum/thread-463-post-576.html
- Playlist Video del progetto: https://www.youtube.com/playlist?list=PLNZXUv5jKUn4FmaiDwSzNop_WA8vuwMkV

## Video guide al funzionamento

In questi 3 asciinema viene mostrato il processo completo in Docker:

1. Setup automatico completo dell'ambiente (in questo esempio su WSL/Windows, replicabile su Ubuntu): in poco piu di un minuto Docker e prerequisiti risultano installati e configurati.
[![asciicast](https://asciinema.org/a/gTlH40GGNnSmieOA.svg)](https://asciinema.org/a/gTlH40GGNnSmieOA)

2. Clone del repository GitHub e primo avvio dell'applicativo (gli eventuali warning mostrati sono attesi al primo avvio senza configurazione utente/password).
[![asciicast](https://asciinema.org/a/SxrlaMpUJfvNu3xo.svg)](https://asciinema.org/a/SxrlaMpUJfvNu3xo)

3. Panoramica del menu e avvio di alcune funzioni; in alternativa puoi usare i comandi `make` documentati nel repository.
[![asciicast](https://asciinema.org/a/Q5OJ3L6amxBhTqRc.svg)](https://asciinema.org/a/Q5OJ3L6amxBhTqRc)

## TL;DR (avvio rapido, Linux/WSL)

```bash
git clone https://github.com/fragolinux/magazzino.git
cd magazzino
make menu
```

In alternativa:
```bash
make up
```

Per Windows, vedi `WINDOWS.md`.

## TL;DR (aggiornamento rapido, Linux)

```bash
make clone
cd "$(ls -d ../magazzino-* 2>/dev/null | sort -V | tail -n1)"
make run
```

Apri `http://localhost` e accedi con `RG4Tech / 12345678`.
Per password e impostazioni avanzate, vedi `ADVANCED.md`.

## Avvio rapido (utente finale, Linux/WSL)

1) Copia `.env.example` in `.env` (include credenziali DB di default).
   Facoltativo: personalizza utente/password DB.

2) Avvia i container:
```bash
make menu
```

In alternativa:
```bash
make up
```

Nota: `start.sh` esegue un `docker compose pull` prima dell'avvio per aggiornare le immagini.

App disponibile su `http://localhost`.

Login di default:
- Username: `RG4Tech`
- Password: `12345678`

## Comandi Make (Linux)

Da root del repo (Linux):

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

## Screenshot Menu e Comandi

1. `make` (help e target disponibili)  
   ![make help](images/1make.png)
2. Menu principale  
   ![menu principale](images/2mainmenu.png)
3. Menu backup  
   ![menu backup](images/3backupmenu.png)
4. Menu restore  
   ![menu restore](images/4restoremenu.png)
5. Sottomenu restore (selezione backup)  
   ![sottomenu restore](images/5restoresubmenu.png)
6. `make dbcheck`  
   ![make dbcheck](images/6dbcheck.png)
7. `make up`  
   ![make up](images/7makeup.png)
8. `make down`  
   ![make down](images/8makedown.png)
9. `make clone`  
   ![make clone](images/9makeclone.png)
10. `make restore`  
    ![make restore](images/10makerestore.png)
