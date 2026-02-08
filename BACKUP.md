# Backup e Restore

## Backup (DB + file)

I backup finiscono in `backup/<timestamp>/` e contengono:

- `db.sql` (dump completo)
- `files.tar.gz` (archivio di `data/uploads` + `data/config`)

Esegui backup:

Windows:
```powershell
.\scripts\backup.ps1
```
Nota: `backup.ps1` esegue solo il backup del DB (non dei file).
Su Windows puro non è il setup consigliato e la gestione file non verrà ampliata.

Linux/WSL (bash):
```bash
make backup
```

Backup separati (Linux/WSL):
```bash
make backup-db    # solo DB
make backup-file  # solo file (uploads + config)
```

## Restore (DB + file)

Ripristina da un backup specifico:

Windows:
```powershell
.\scripts\restore.ps1 -BackupPath .\backup\20250101_120000
```

Linux/WSL (bash):
```bash
make restore BACKUP=backup/20250101_120000
```

Se non specifichi il path, gli script usano l'ultimo backup disponibile.

Restore separati (Linux/WSL):
```bash
make restore-db
make restore-file
```

Nota operativa:
- Backup file: se i servizi sono attivi, viene mostrato un avviso ma il backup prosegue.
- Restore file: se i servizi sono attivi, vengono fermati e riavviati automaticamente.

## Note

- In Docker l'app usa `DB_HOST/DB_NAME/DB_USER/DB_PASS` da ambiente tramite l'override.
- Host DB di default in Docker: `db` (già impostato nel `docker-compose.yml`).
