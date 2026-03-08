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

Se hai appena cambiato il file `.env`, riavvia:

- `make down && make up`

Se non hai cambiato `.env`, puoi passare direttamente al test.

## 3) Test manuale invio email

Prerequisiti:

- almeno un utente `admin` con email valorizzata
- almeno un componente sotto scorta (`quantity < quantity_min`)

Esegui:

```bash
make sendmail
```

## 4) Automazione (cron)

Consigliato: cron sul tuo host.

Apri il crontab:

```bash
crontab -e
```

Aggiungi, ad esempio ogni ora:

```cron
0 * * * * cd /percorso/del/repo/magazzino && mkdir -p backup && /bin/echo "[$(/usr/bin/date -Iseconds)] sendmail run" >> backup/sendmail-cron.log && /usr/bin/make sendmail >> backup/sendmail-cron.log 2>&1
```

Varianti utili:

- Ogni 15 minuti: `*/15 * * * * ...`
