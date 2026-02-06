# TODO

## Migliorie Backup

- Estendere `scripts/backup.sh` per includere anche i file dati (probabilmente `data/uploads/`).
- Decidere se includere `data/nginx-logs` e `data/php-logs`.
- Escludere `data/db` dal backup file per evitare duplicazione con il dump SQL.
- Valutare aggiornare `scripts/restore.sh` per ripristinare anche i file oltre al DB.
- Aggiungere opzioni per ripristinare solo DB, solo file o entrambi.
- Permettere il restore in cartella corrente o in un percorso custom.

## Menu Dialog

- Aggiungere supporto a `dialog` per un menu con tutte le opzioni del Makefile.
- Includere un sotto-menu per sfogliare i backup esistenti.
- Offrire restore: solo DB, solo file o completo.
- Consentire scelta destinazione restore: cartella corrente o path custom.
