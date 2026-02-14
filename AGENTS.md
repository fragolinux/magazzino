# Repository Guidelines

## Project Structure & Module Organization

- `magazzino/` holds the PHP application.
- `magazzino/admin/` contains admin-only user management screens.
- `magazzino/warehouse/` contains inventory workflows (locations, compartments, components).
- `magazzino/includes/` holds shared PHP includes such as auth and database connection.
- `magazzino/assets/` stores static assets (CSS, JS, webfonts, images).
- `magazzino/config/` is reserved for configuration (currently empty).
- `magazzino_db.sql` (repo root) provides the database schema/data seed.

## Build, Test, and Development Commands

- `php -S localhost:8000 -t magazzino` starts a local PHP dev server from the repo root.
- Import the database dump into MySQL (example): `mysql -u root -p magazzino_db < magazzino_db.sql`.
- Configure connection settings in `magazzino/includes/db_connect.php` (host, db, user, pass).

## Coding Style & Naming Conventions

- Use 4-space indentation, no tabs.
- Match existing file naming: lower_snake_case for PHP files (for example, `add_component.php`).
- Prefer simple, explicit PHP and prepared statements via PDO.
- Keep shared layout in `magazzino/includes/header.php` and `magazzino/includes/footer.php`.

## Testing Guidelines

- No automated test framework is present.
- Validate changes manually by walking through key pages (login, dashboard, components, compartments).
- If you add tests, document how to run them here.

## Commit & Pull Request Guidelines

- Git history is not available in this workspace, so no enforced commit convention is known.
- Use short, imperative commit subjects (for example, "Fix component search query").
- PRs should include a clear description, steps to test, and UI screenshots for visible changes.
- Link related issues or tickets when applicable.

## Security & Configuration Tips

- Do not commit real credentials; keep production secrets outside the repo.
- The login flow uses SHA-256 hashes; keep any new auth logic consistent with existing behavior.

## Regole Locali (Persistenti)

- Non eseguire mai `git push`. I rilasci li fa l’utente con `make release`.
- Non modificare mai file sotto `magazzino/` con cambi locali. L’albero upstream deve restare l’esatto contenuto del rilascio dell’autore.
- Qualsiasi fix locale va messo in `overrides/`, `docker/`, `scripts/` o in altri percorsi non-upstream.
- Non chiedere conferme su queste regole; vanno sempre applicate.
- I messaggi di commit devono essere in inglese.

## Regole Operative Docker/Override (Persistenti)

- In documentazione e istruzioni operative, preferire sempre i target `make` rispetto a script diretti o comandi `docker compose` quando il target equivalente esiste (salvo casi tecnici particolari da spiegare).
- Gli override devono essere minimali: sovrascrivere solo i file realmente necessari al runtime Docker.
- Evitare override di file upstream sensibili (`includes/db_connect.php`, `includes/auth_check.php`, `settings.php`) salvo richiesta esplicita dell'utente.
- Mantenere il comportamento attuale dei compose: override attivi solo per `config/database.php` e `update/index.php`.
- Le migrazioni DB devono usare i file SQL dell'autore in `update/migrations/`, ordinate con `version_compare` (semver-like), in modo idempotente.
- Le migrazioni devono usare credenziali admin dedicate (`DB_MIGRATION_USER`/`DB_MIGRATION_PASS`, tipicamente root) senza elevare i privilegi di `DB_USER`.
- In caso di utente non-admin, filtrare automaticamente statement privilegiati (`CREATE USER`, `GRANT`, `REVOKE`, `FLUSH PRIVILEGES`) invece di modificare i file upstream.
- Nginx deve mostrare una pagina di attesa "umana" durante bootstrap (errori 502/503/504) con refresh automatico ogni 5 secondi.
- Per lint PHP usare preferibilmente il PHP nel container (`docker compose exec -T php php -l ...`) invece di richiedere PHP installato sull'host.
- Se l'utente dichiara che sta ripulendo/azzerando l'ambiente, non avviare `make up/devup` o `docker compose up` senza conferma esplicita.
