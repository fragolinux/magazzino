# Configurazione Email Alert

Questa guida spiega solo cosa devi fare per attivare gli alert email dei componenti sotto scorta.

## 1) Configura `.env`

Inserisci (o aggiorna) questi valori nel file `.env`:

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=la_tua_email@example.com
SMTP_PASS=la_tua_password_app
SMTP_FROM=la_tua_email@example.com
SMTP_FROM_NAME=Magazzino Alerts
SMTP_SECURE=tls
```

Valori tipici:

- `SMTP_SECURE=tls` con `SMTP_PORT=587`
- `SMTP_SECURE=ssl` con `SMTP_PORT=465`

Se usi Gmail con 2FA, usa una **App Password** (non la password normale).

## 2) Riavvia lo stack

- Produzione: `make down && make up`
- Sviluppo: `make devdown && make devup`

## 3) Test manuale invio email

Esegui:

```bash
make sendmail
```

Opzionale:

- `make sendmail MODE=dev`
- `make sendmail MODE=prod`

`MODE=auto` (default) usa prima `dev` se attivo, altrimenti `prod`.

## 4) Automazione (cron)

Consigliato: cron sul tuo host.

Apri il crontab:

```bash
crontab -e
```

Aggiungi, ad esempio ogni ora:

```cron
0 * * * * cd /percorso/del/repo/magazzino && /usr/bin/make sendmail MODE=auto >> /percorso/del/repo/magazzino/data/php-logs/sendmail-cron.log 2>&1
```

Varianti utili:

- Ogni 15 minuti: `*/15 * * * * ...`
- Solo produzione: usa `MODE=prod`
- Solo sviluppo: usa `MODE=dev`
