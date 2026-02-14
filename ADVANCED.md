# Avanzato

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

## Inizializzazione database

- Al primo avvio (cartella `data/db` vuota), MariaDB importa `magazzino_db.sql`.
- Ad ogni avvio, il container PHP esegue migrazioni idempotenti per allineare lo schema alle ultime versioni.
- Nome/utente/password DB vengono creati da `.env`.
- Le migrazioni vengono eseguite automaticamente con l'utente root del DB (usando `DB_ROOT_PASSWORD`),
  mentre l'app continua a usare `DB_USER/DB_PASS`. Non sono richieste azioni manuali da parte dell'utente.

Per reimportare da zero (DISTRUGGE i dati):

Windows (PowerShell):
```powershell
Remove-Item -Recurse -Force .\data\db
docker compose up -d
```

Linux/WSL (bash):
```bash
rm -rf ./data/db
make up
```

## Credenziali DB: creare e recuperare

Setup consigliato: imposta le credenziali in `.env` prima del primo `up`.
Se al primo `make run` vedi warning del tipo "The DB_* variable is not set", significa che manca `.env`:
copia `.env.example` in `.env` e personalizza i valori.
Login app di default: `RG4Tech / 12345678`.

Esempio `.env`:
```
DB_NAME=magazzino_db
DB_USER=magazzino_app
DB_PASS=change_me
DB_ROOT_PASSWORD=change_me_root
HTTP_PORT=80
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

## Override in Docker

I file sotto `magazzino/` devono restare identici al rilascio autore.
Gli override locali vanno usati solo dove strettamente necessario.

Attualmente:
- `overrides/database.php` (config DB da env + `BASE_PATH`)
- `overrides/update_index.php` (pagina update custom per ambiente Docker)

Non vengono più sovrascritti in runtime `includes/db_connect.php`, `includes/auth_check.php` o `settings.php`,
così le novità upstream restano effettive dopo ogni upgrade.

## Cartella dati locale

Tutti i dati persistenti stanno in `data/`:

- `data/db/` file MariaDB
- `data/nginx-logs/` log Nginx
- `data/php-logs/` log PHP-FPM
- `data/uploads/` datasheet PDF e immagini componenti (persistenti e condivisi tra Nginx/PHP)

Backup = copia `data/` + `.env` + `magazzino_db.sql` oppure usa gli script di backup.

### Verifica automatica cartelle (volumi)

Gli script di avvio (`scripts/start.*` e `scripts/start-dev.*`) eseguono una verifica preliminare:
creano le cartelle dei volumi se mancanti e aggiungono un `.gitkeep` per mantenerle tracciate
anche su installazioni precedenti al commit che le ha introdotte.

Script dedicati (eseguibili anche manualmente):
- `scripts/ensure-dirs.sh`
- `scripts/ensure-dirs.ps1`

## Avvio rapido (sviluppo, Linux/WSL)

Per lavorare sui sorgenti locali (hot reload), usa il compose di sviluppo:

```bash
make devup
```

Oppure:
```bash
./scripts/start-dev.sh
```

Facoltativo: fissa una versione specifica delle immagini (esempio `v1.1`):
```bash
MAGAZZINO_TAG=v1.1 make up
```

## Startup clean ambiente dev (simula repo appena clonato)

Obiettivo: ripartire da zero mantenendo solo la cartella `backup/`.

Metodo consigliato (automatico):

```bash
make cleanstart
```

Il target chiede conferma esplicita (`YES`), crea backup preventivo (DB + file, incluso `.env` se presente),
ferma gli stack, pulisce `data/`, rimuove `.env` e l'immagine locale `magazzino-php:latest`.

### 1) Backup preventivo (file + database)

Usa i target Make (raccomandato):

```bash
make backup
```

In alternativa puoi usare:

```bash
make menu
```

### 2) Spegni stack

```bash
make devdown
```

### 3) Rimuovi configurazione locale

```bash
rm -f .env
```

### 4) Pulisci i dati generati runtime

Attenzione: mantiene la struttura cartelle, elimina contenuti runtime.

```bash
sudo rm -rf data/db/* data/config/* data/nginx-logs/* data/php-logs/* data/uploads/datasheet/* data/uploads/images/*
sudo chown -R "$(id -u)":"$(id -g)" data
mkdir -p data/db data/config data/nginx-logs data/php-logs data/uploads data/uploads/datasheet data/uploads/images data/uploads/images/components data/uploads/images/components/thumbs
touch data/.gitkeep data/db/.gitkeep data/config/.gitkeep data/nginx-logs/.gitkeep data/php-logs/.gitkeep data/uploads/.gitkeep data/uploads/datasheet/.gitkeep data/uploads/images/.gitkeep data/uploads/images/components/.gitkeep data/uploads/images/components/thumbs/.gitkeep
```

### 5) Rimuovi immagine locale buildata in dev

```bash
docker image rm magazzino-php:latest || true
```

### 6) Ricrea `.env` e riavvia

```bash
make devup
```

Nota: `make devup` crea automaticamente `.env` da `.env.example` se non esiste.
Se devi personalizzare `DB_*` o `HTTP_PORT`, modifica `.env` dopo la creazione.

### 7) Verifica rapida

```bash
docker compose -f docker-compose.dev.yml ps
curl -I http://localhost/magazzino/login.php
```
