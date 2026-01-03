# Magazzino Componenti Elettronici (Docker)

Questo repository contiene una app PHP 8.0 servita da Nginx + PHP-FPM con database MariaDB.
Tutti i dati persistenti stanno in `data/` e i backup in `backup/`.

## Crediti e riferimenti

Questo progetto si basa sul lavoro originale di RG4Tech (Gabriele Riva).
Grazie per lo sviluppo e per aver condiviso il progetto con la community.

- Autore: RG4Tech (Gabriele Riva)
- Sito web del progetto: https://rg4tech.altervista.org/forum/thread-463-post-576.html
- Video di presentazione: https://www.youtube.com/watch?v=vZVBEfRnHZI

## TL;DR (avvio rapido)

Windows (PowerShell):
```powershell
git clone https://github.com/fragolinux/magazzino.git
cd magazzino
.\scripts\start.ps1
```

Linux/WSL (bash):
```bash
git clone https://github.com/fragolinux/magazzino.git
cd magazzino
./scripts/start.sh
```

Apri `http://localhost:8080` e accedi con `RG4Tech / 12345678`.
Per cambiare password o altre impostazioni avanzate, leggi il resto del README.

## Prerequisiti

- Docker + Docker Compose (Windows, Linux o WSL)

## Struttura

- `magazzino/` sorgenti PHP dell'app
- `magazzino_db.sql` dump iniziale del database
- `docker/` setup Nginx + PHP
- `docker-compose.yml` avvio utente finale (immagini prebuild)
- `docker-compose.dev.yml` avvio sviluppo (sorgenti locali)
- `data/` dati persistenti (DB + log)
- `.env` configurazione locale
- `backup/` cartella backup
- `overrides/` override per l'uso in Docker

## Avvio rapido (utente finale)

1) Copia `.env.example` in `.env` (include credenziali DB di default).
   Facoltativo: personalizza utente/password DB.

2) Avvia i container (consigliato tramite script):
Windows (PowerShell):
```powershell
.\scripts\start.ps1
```

Linux/WSL (bash):
```bash
./scripts/start.sh
```

App disponibile su `http://localhost:8080`.

Login di default:
- Username: `RG4Tech`
- Password: `12345678`

## Avvio rapido (sviluppo)

Per lavorare sui sorgenti locali (hot reload), usa il compose di sviluppo:

Windows (PowerShell):
```powershell
docker compose -f docker-compose.dev.yml up --build
```

Linux/WSL (bash):
```bash
docker compose -f docker-compose.dev.yml up --build
```

Oppure usa gli script di avvio:
Windows (PowerShell):
```powershell
.\scripts\start-dev.ps1
```

Linux/WSL (bash):
```bash
./scripts/start-dev.sh
```

Facoltativo: fissa una versione specifica delle immagini (esempio `v1.1`):
Windows (PowerShell):
```powershell
$env:MAGAZZINO_TAG="v1.1"
docker compose up -d
```

Linux/WSL (bash):
```bash
MAGAZZINO_TAG=v1.1 docker compose up -d
```

## Note WSL

- Per prestazioni migliori, tieni il repo dentro il filesystem WSL e lancia `docker compose` da WSL.
- Assicurati che Docker Desktop abbia l'integrazione WSL attiva.

## Inizializzazione database

- Al primo avvio (cartella `data/db` vuota), MariaDB importa `magazzino_db.sql`.
- Nome/utente/password DB vengono creati da `.env`.

Per reimportare da zero (DISTRUGGE i dati):
Windows (PowerShell):
```powershell
Remove-Item -Recurse -Force .\data\db
docker compose up -d
```

Linux/WSL (bash):
```bash
rm -rf ./data/db
docker compose up -d
```

## Credenziali DB: creare e recuperare

Setup consigliato: imposta le credenziali in `.env` prima del primo `up`.
Login app di default: `RG4Tech / 12345678`.

Esempio `.env`:
```
DB_NAME=magazzino_db
DB_USER=magazzino_app
DB_PASS=change_me
DB_ROOT_PASSWORD=change_me_root
```

Recupero credenziali:
- Controlla `.env` sul host.
- Oppure dentro il container DB:
Windows (PowerShell):
```powershell
docker compose exec db printenv MARIADB_USER MARIADB_PASSWORD MARIADB_DATABASE
```

Linux/WSL (bash):
```bash
docker compose exec db printenv MARIADB_USER MARIADB_PASSWORD MARIADB_DATABASE
```

## Override DB per Docker

Il file originale `magazzino/includes/db_connect.php` resta quello dell'autore.
Per Docker usiamo l'override `overrides/db_connect.php`, montato dal compose.
Così puoi aggiornare `magazzino/` sovrascrivendo tutto senza perdere la modifica.

## Cartella dati locale

Tutti i dati persistenti stanno in `data/`:

- `data/db/` file MariaDB
- `data/nginx-logs/` log Nginx
- `data/php-logs/` log PHP-FPM

Backup = copia `data/` + `.env` + `magazzino_db.sql` oppure usa gli script sotto.

## Backup (export DB)

I backup finiscono in `backup/<timestamp>/` e contengono:

- `db.sql` (dump completo)

Esegui backup:

Windows:
```powershell
.\scripts\backup.ps1
```

Linux/WSL (bash):
```bash
./scripts/backup.sh
```

## Restore (DB)

Ripristina da un backup specifico:

Windows:
```powershell
.\scripts\restore.ps1 -BackupPath .\backup\20250101_120000
```

Linux/WSL (bash):
```bash
./scripts/restore.sh ./backup/20250101_120000
```

Se non specifichi il path, gli script usano l'ultimo backup disponibile.

## Note

- In Docker l'app usa `DB_HOST/DB_NAME/DB_USER/DB_PASS` da ambiente tramite l'override.
- Host DB di default in Docker: `db` (già impostato nel `docker-compose.yml`).
