<?php
/**
 * @Author: gabriele.riva 
 * @Date: 2026-03-03
*/
/**
 * Configurazione SMTP per l'invio delle email
 * ATTENZIONE: LEGGERE LE ISTRUZIONI SOTTO PRIMA DI USARE
 */

// Se stai usando Gmail, ricorda di usare una "Password per le app" se hai l'autenticazione a 2 fattori
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // 587 per TLS, 465 per SSL - Se usi GMAIL, lascia 587
define('SMTP_USER', 'tua_email'); // La tua email completa (es. esempio:@gmail.com)
define('SMTP_PASS', 'tua_password_app'); // La password per le app generata da Google (16 caratteri senza spazi)
define('SMTP_FROM', 'tua_email'); // L'indirizzo email da cui inviare le notifiche (di solito la stessa di SMTP_USER)
define('SMTP_FROM_NAME', 'Magazzino Alerts'); // Il nome che apparirà come mittente nelle email
define('SMTP_SECURE', 'tls'); // 'tls' o 'ssl' - Se usi GMAIL, lascia 'tls' e porta 587

/*
ISTRUZIONI PER L'USO DELLA CONFIGURAZIONE SMTP GMAIL:
1. Abilita l'autenticazione a 2 fattori sul tuo account Google se non l'hai già fatto.
2. Crea una "Password per le app" specifica per questo progetto:
   - Vai su https://myaccount.google.com/apppasswords
   - poi dai un nome (es. "Magazzino Alerts") e genera la password.
3. Sostituisci 'tua_password_app' con la password generata (16 caratteri "senza spazi").
4. Assicurati che il server su cui è ospitato il progetto possa connettersi a smtp.gmail.com sulla porta 587 (o 465 se usi SSL).
5. Testa l'invio delle email eseguendo lo script cron/send_low_stock_emails.php e verifica che le email arrivino correttamente agli amministratori configurati.

XAMPP
Estensione OpenSSL: Assicurati che nel tuo file php.ini di XAMPP la riga extension=openssl sia attiva (senza il punto e virgola davanti), altrimenti la connessione criptata verso Google fallirà.

ESEGUIRE LO SCRIPT IN AUTOMATICO:
- utilizzare l' Utilità di pianificazione (Task Scheduler)
- Si crea un task che esegue `php.exe C:\xampp\htdocs\magazzino\cron\send_low_stock_emails.php` ogni X ore/giorni.

ISTRUZIONI per l'Utilità di pianificazione su Windows:
1. Crea l'Attività
  Apri l'Utilità di pianificazione.
    Nel pannello a destra, clicca su Crea attività di base... (o Create Basic Task).
  Dai un nome al task (es. Invio Email Magazzino) e clicca su Avanti.
  Scegli la frequenza (es. Ogni giorno) e clicca su Avanti. Imposta l'orario di inizio.
2. Configura l'Azione (Il passaggio critico)
  Arrivato alla scheda Azione, seleziona Avvio programma e compila i campi in questo modo:
  Programma o script:
  C:\xampp\php\php.exe
  (Assicurati che il percorso porti effettivamente alla cartella php di XAMPP).
  Aggiungi argomenti (opzionale):
  C:\xampp\htdocs\magazzino\cron\send_low_stock_emails.php
  Percorso iniziale (opzionale):
  C:\xampp\htdocs\magazzino\cron\
  (Questo è fondamentale: permette a PHP di risolvere correttamente eventuali "include" o "require" relativi dentro lo script).
3. Impostazioni di esecuzione
  Prima di finire, apri le proprietà del task appena creato:
  Nella scheda Generale, seleziona Esegui indipendentemente dalla connessione degli utenti se vuoi che le email partano anche se non hai fatto il login (richiederà la tua password di Windows).
  Seleziona Esegui con i privilegi massimi per evitare blocchi di sistema.
*/