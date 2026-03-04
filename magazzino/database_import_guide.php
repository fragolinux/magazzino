<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-02-03 19:54:00 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-03 19:54:00
*/

/**
 * Pagina con istruzioni dettagliate per importare il database
 */

require_once __DIR__ . '/includes/auth_check.php';

// solo admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Accesso negato: permessi insufficienti.";
    exit;
}

?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="fas fa-database me-2"></i>Guida all'Importazione Database</h2>
    <a href="<?= BASE_PATH ?>settings.php?tab=database" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i> Torna alle Impostazioni
    </a>
  </div>

  <div class="row">
    <div class="col-lg-8">
      <!-- Introduzione -->
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h4 class="card-title"><i class="fas fa-info-circle text-primary me-2"></i>Informazioni Importanti</h4>
          <p class="card-text">
            Questa guida ti aiuterà a importare il database del magazzino in modo sicuro e corretto.
            Prima di procedere, assicurati di avere:
          </p>
          <ul class="card-text">
            <li><strong>File SQL di backup</strong> (es. <code>magazzino_db_backup_2026-02-03.sql</code>)</li>
            <li><strong>Credenziali database</strong> (host, username, password, nome database)</li>
            <li><strong>Accesso a phpMyAdmin</strong> o <strong>MySQL Command Line</strong></li>
          </ul>
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Attenzione:</strong> L'importazione sovrascriverà tutti i dati esistenti nel database di
            destinazione.
            Se hai dati importanti, fai prima un backup del database corrente.
          </div>
        </div>
      </div>

      <!-- Metodo 1: phpMyAdmin -->
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Metodo 1: Importazione tramite phpMyAdmin</h5>
        </div>
        <div class="card-body">
          <h6><i class="fas fa-list-ol me-2 text-primary"></i>Passaggi:</h6>
          <ol>
            <li><strong>Accedi a phpMyAdmin</strong>
              <ul>
                <li>Apri il browser e vai a <code>http://localhost/phpmyadmin</code></li>
                <li>Effettua il login con le tue credenziali MySQL</li>
              </ul>
            </li>
            <li><strong>Seleziona il database</strong>
              <ul>
                <li>Nel menu di sinistra, clicca sul nome del database (es. <code>magazzino_db</code>)</li>
                <li>Se il database non esiste, crealo prima cliccando su "Nuovo" nella colonna di sinistra</li>
              </ul>
            </li>
            <li><strong>Importa il file SQL</strong>
              <ul>
                <li>Clicca sulla scheda <strong>"Importa"</strong> nel menu superiore</li>
                <li>Clicca su <strong>"Scegli file"</strong> e seleziona il tuo file SQL di backup</li>
                <li>Assicurati che il formato sia impostato su <strong>"SQL"</strong></li>
                <li>Per file molto grandi, potresti dover aumentare il limite di upload in phpMyAdmin</li>
              </ul>
            </li>
            <li><strong>Avvia l'importazione</strong>
              <ul>
                <li>Clicca su <strong>"Esegui"</strong> in basso a destra</li>
                <li>Attendi il completamento dell'operazione</li>
                <li>phpMyAdmin mostrerà un messaggio di successo con il numero di query eseguite</li>
              </ul>
            </li>
          </ol>

          <h6 class="mt-4"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Problemi Comuni:</h6>
          <ul>
            <li><strong>File troppo grande:</strong> Se il file supera il limite di upload (di solito 2MB-50MB),
              modifica il file <code>php.ini</code> aumentando <code>upload_max_filesize</code> e
              <code>post_max_size</code></li>
            <li><strong>Timeout:</strong> Per database molto grandi, potrebbe essere necessario aumentare
              <code>max_execution_time</code></li>
            <li><strong>Permessi:</strong> Assicurati che l'utente MySQL abbia i permessi per creare tabelle e inserire
              dati</li>
          </ul>
        </div>
      </div>

      <!-- Metodo 2: Command Line -->
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white">
          <h5 class="mb-0"><i class="fas fa-terminal me-2"></i>Metodo 2: Importazione tramite Command Line</h5>
        </div>
        <div class="card-body">
          <h6><i class="fas fa-list-ol me-2 text-success"></i>Passaggi:</h6>
          <ol>
            <li><strong>Apri il terminale</strong>
              <ul>
                <li><strong>Windows:</strong> Apri il Prompt dei comandi o PowerShell</li>
                <li><strong>Linux/Mac:</strong> Apri il terminale</li>
              </ul>
            </li>
            <li><strong>Naviga alla cartella del file SQL</strong>
              <ul>
                <li>Usa il comando <code>cd</code> per spostarti nella cartella contenente il file SQL</li>
                <li>es. <code>cd C:\Users\Utente\Downloads</code></li>
              </ul>
            </li>
            <li><strong>Importa il database</strong>
              <ul>
                <li>Usa il seguente comando MySQL:</li>
                <li><code>mysql -u [username] -p [database_name] < [file_sql]</code></li>
                <li><strong>Esempio:</strong>
                  <code>mysql -u root -p magazzino_db < magazzino_db_backup_2026-02-03.sql</code></li>
              </ul>
            </li>
            <li><strong>Inserisci la password</strong>
              <ul>
                <li>Il sistema chiederà la password MySQL</li>
                <li>Digita la password e premi Invio</li>
                <li>Non vedrai la password mentre la digiti</li>
              </ul>
            </li>
            <li><strong>Attendi il completamento</strong>
              <ul>
                <li>L'importazione potrebbe richiedere alcuni minuti per database grandi</li>
                <li>Quando termina, tornerai al prompt del terminale</li>
              </ul>
            </li>
          </ol>

          <h6 class="mt-4"><i class="fas fa-desktop me-2 text-info"></i>Utilizzo con XAMPP:</h6>
          <p>Se stai usando XAMPP, segui questi passaggi specifici:</p>
          <ul>
            <li><strong>1. Avvia MySQL da XAMPP Control Panel</strong></li>
            <li><strong>2. Apri il terminale di XAMPP:</strong>
              <ul>
                <li>Windows: Clicca su "Shell" nel pannello di controllo XAMPP</li>
                <li>Oppure apri il prompt dei comandi e naviga a <code>C:\xampp\mysql\bin\</code></li>
              </ul>
            </li>
            <li><strong>3. Esegui il comando:</strong>
              <ul>
                <li><code>mysql -u root -p magazzino_db < C:\percorso\al\tuo\file\magazzino_db_backup.sql</code></li>
                <li>Nota: usa il percorso completo del file SQL</li>
              </ul>
            </li>
            <li><strong>4. Verifica l'importazione:</strong>
              <ul>
                <li>Accedi a phpMyAdmin per verificare che tutte le tabelle siano state create correttamente</li>
                <li>Oppure esegui: <code>mysql -u root -p -e "USE magazzino_db; SHOW TABLES;"</code></li>
              </ul>
            </li>
          </ul>

          <h6 class="mt-4"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Soluzioni ai Problemi Comuni:
          </h6>
          <ul>
            <li><strong>Comando 'mysql' non riconosciuto:</strong>
              <ul>
                <li>Aggiungi il percorso di MySQL al PATH del sistema</li>
                <li>Windows: <code>C:\xampp\mysql\bin\</code></li>
                <li>Linux/Mac: <code>/usr/local/mysql/bin/</code> o <code>/usr/bin/mysql</code></li>
              </ul>
            </li>
            <li><strong>Accesso negato:</strong>
              <ul>
                <li>Verifica username e password</li>
                <li>Controlla che l'utente abbia i permessi necessari</li>
              </ul>
            </li>
            <li><strong>Database non esiste:</strong>
              <ul>
                <li>Crea prima il database: <code>mysql -u root -p -e "CREATE DATABASE magazzino_db;"</code></li>
              </ul>
            </li>
            <li><strong>File non trovato:</strong>
              <ul>
                <li>Verifica il percorso del file SQL</li>
                <li>Usa il percorso assoluto invece del relativo</li>
              </ul>
            </li>
          </ul>
        </div>
      </div>

      <!-- Confronto Metodi -->
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-info text-white">
          <h5 class="mb-0"><i class="fas fa-balance-scale me-2"></i>Confronto tra i Metodi</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead class="table-light">
                <tr>
                  <th>Caratteristica</th>
                  <th>phpMyAdmin</th>
                  <th>Command Line</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong>Facilità d'uso</strong></td>
                  <td><i class="fas fa-check text-success"></i> Interfaccia grafica intuitiva</td>
                  <td><i class="fas fa-times text-warning"></i> Richiede conoscenza comandi</td>
                </tr>
                <tr>
                  <td><strong>Velocità</strong></td>
                  <td><i class="fas fa-times text-warning"></i> Limiti di upload e timeout</td>
                  <td><i class="fas fa-check text-success"></i> Più veloce per grandi database</td>
                </tr>
                <tr>
                  <td><strong>File grandi</strong></td>
                  <td><i class="fas fa-times text-danger"></i> Limiti tipici 2MB-50MB</td>
                  <td><i class="fas fa-check text-success"></i> Nessun limite pratico</td>
                </tr>
                <tr>
                  <td><strong>Feedback</strong></td>
                  <td><i class="fas fa-check text-success"></i> Messaggi visivi e progresso</td>
                  <td><i class="fas fa-times text-warning"></i> Output testuale solo</td>
                </tr>
                <tr>
                  <td><strong>Accesso</strong></td>
                  <td><i class="fas fa-check text-success"></i> Accessibile via browser</td>
                  <td><i class="fas fa-times text-warning"></i> Richiede accesso server</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Verifica Importazione -->
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white">
          <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Verifica Importazione</h5>
        </div>
        <div class="card-body">
          <h6><i class="fas fa-list-ol me-2 text-secondary"></i>Controlli da Eseguire:</h6>
          <ol>
            <li><strong>Numero tabelle:</strong> Dovrebbero esserci 15-20 tabelle principali</li>
            <li><strong>Tabelle principali:</strong> Verifica la presenza di:
              <ul>
                <li><code>component</code> - Componenti elettronici</li>
                <li><code>category</code> - Categorie</li>
                <li><code>location</code> - Posizioni</li>
                <li><code>setting</code> - Impostazioni</li>
                <li><code>user</code> - Utenti</li>
              </ul>
            </li>
            <li><strong>Dati nelle tabelle:</strong> Controlla che le tabelle non siano vuote</li>
            <li><strong>Relazioni:</strong> Verifica che le chiavi esterne siano corrette</li>
          </ol>

          <h6 class="mt-4"><i class="fas fa-terminal me-2 text-info"></i>Comandi di Verifica:</h6>
          <div class="bg-light p-3 rounded">
            <code>mysql -u root -p -e "USE magazzino_db; SHOW TABLES;"</code><br>
            <code>mysql -u root -p -e "USE magazzino_db; SELECT COUNT(*) FROM component;"</code><br>
            <code>mysql -u root -p -e "USE magazzino_db; SELECT * FROM setting LIMIT 5;"</code>
          </div>
        </div>
      </div>
    </div>

    <!-- Colonna laterale con riepilogo -->
    <div class="col-lg-4">
      <div class="card shadow-sm sticky-top" style="top: 20px;">
        <div class="card-header bg-dark text-white">
          <h6 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Riepilogo Rapido</h6>
        </div>
        <div class="card-body">
          <h6><i class="fas fa-cog me-2 text-primary"></i>phpMyAdmin</h6>
          <ul class="small">
            <li>Accesso via browser</li>
            <li>Interfaccia grafica</li>
            <li>Limiti file piccoli</li>
            <li>Ideale per principianti</li>
          </ul>

          <hr>

          <h6><i class="fas fa-terminal me-2 text-success"></i>Command Line</h6>
          <ul class="small">
            <li>Comandi MySQL</li>
            <li>Nessun limite file</li>
            <li>Più veloce</li>
            <li>Ideale per esperti</li>
          </ul>

          <hr>

          <h6><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Attenzione</h6>
          <ul class="small">
            <li>Sovrascrive dati esistenti</li>
            <li>Fai backup prima</li>
            <li>Verifica permessi</li>
            <li>Controlla connessione</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>