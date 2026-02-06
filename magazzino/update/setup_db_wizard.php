<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-02-03 11:24:47 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-03 11:38:35
*/

/*
 * Setup Wizard per Configurazione Database
 * 
 * Questo script viene invocato quando la connessione al database fallisce.
 * Permette di configurare i parametri di connessione in modo sicuro.
 *
 * SICUREZZA: Richiede un token di sicurezza generato sul filesystem.
 * Questo prova che l'utente ha accesso ai file del server.
 */

session_start();

define('BASE_PATH', __DIR__ . '/../');
$configFile = BASE_PATH . 'config/database.php';
$tokenFile = BASE_PATH . 'config/setup_token.txt';

// Genera un nuovo token se non esiste o se è scaduto (opzionale, semplifichiamo per ora)
if (!file_exists($tokenFile)) {
    $token = bin2hex(random_bytes(16));
    file_put_contents($tokenFile, $token);
}

$message = '';
$error = '';

// Controlla se il token è già stato verificato in precedenza (tramite sessione)
$tokenVerified = isset($_SESSION['token_verified']) && $_SESSION['token_verified'] === true;

// Leggi i dati attuali da config/database.php (se esiste)
$currentConfig = [
    'host' => 'localhost',
    'db' => '',
    'user' => '',
    'pass' => '',
    'port' => 3306
];
if (file_exists($configFile)) {
    try {
        $currentConfig = require $configFile;
    } catch (Exception $e) {
        // File corrotto o non leggibile, usa i defaults
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Se il token è già verificato in sessione, salta la verifica e procedi con i dati DB
    if (isset($_SESSION['token_verified']) && $_SESSION['token_verified'] === true) {
        // Procedi direttamente con i dati del database
        if (isset($_POST['host']) && isset($_POST['db_name'])) {
            $host = $_POST['host'] ?? 'localhost';
            $db_name = $_POST['db_name'] ?? '';
            $user = $_POST['user'] ?? '';
            $pass = $_POST['password'] ?? '';
            $port = $_POST['port'] ?? 3306;
            
            try {
                // Prima prova a connetterti SENZA specificare il database
                $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                ];
                $pdo = new PDO($dsn, $user, $pass, $options);
                
                // Verifica se il database esiste
                $stmt = $pdo->query("SHOW DATABASES LIKE '$db_name'");
                $dbExists = $stmt->fetch();
                
                if (!$dbExists) {
                    // Database non esiste, crealo
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                }
                
                // Ora seleziona il database
                $pdo->exec("USE `$db_name`");
                
                // Connessione riuscita! Scrivi il file di configurazione
                $configContent = "<?php\n\n" . 
                                 "require_once __DIR__ . '/base_path.php';\n\n" .
                                 "return [\n" .
                                 "    'host'    => '$host',\n" .
                                 "    'db'      => '$db_name',\n" .
                                 "    'user'    => '$user',\n" .
                                 "    'pass'    => '$pass',\n" .
                                 "    'charset' => 'utf8mb4',\n" .
                                 "    'port'    => $port\n" .
                                 "];\n";
                
                if (file_put_contents($configFile, $configContent)) {
                    // Ora esegui tutte le migrazioni disponibili
                    require_once __DIR__ . '/migration_manager.php';
                    $migrationManager = new MigrationManager($pdo);
                    
                    try {
                        $migrationResult = $migrationManager->runPendingMigrations();
                        $migrationsApplied = count($migrationResult['applied']);
                        
                        // Costruisci messaggio dettagliato con tutti i risultati
                        $message = "<h5>✓ Configurazione salvata con successo!</h5>";
                        $message .= "<p>Database " . ($dbExists ? "esistente collegato" : "creato") . ". ";
                        $message .= "Applicate $migrationsApplied migrazione/i.</p>";
                        
                        // Mostra dettagli migrazioni
                        if (!empty($migrationResult['applied'])) {
                            $message .= "<h6 class='mt-3'>Dettagli Migrazioni:</h6>";
                            $message .= "<div style='max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 0.85em;'>";
                            
                            foreach ($migrationResult['applied'] as $migration) {
                                $version = htmlspecialchars($migration['version']);
                                $status = $migration['status'] === 'success' ? '<span class="text-success">✓</span>' : '<span class="text-danger">✗</span>';
                                
                                $message .= "<strong>{$status} Versione {$version}</strong><br>";
                                
                                if (!empty($migration['stats']['details'])) {
                                    foreach ($migration['stats']['details'] as $detail) {
                                        $message .= htmlspecialchars($detail) . "<br>";
                                    }
                                }
                                
                                $message .= "<br>";
                            }
                            
                            $message .= "</div>";
                        }
                        
                        $message .= "<div class='mt-3'><a href='../index.php' class='btn btn-primary'>Vai alla Dashboard</a></div>";
                    } catch (Exception $e) {
                        $message = "Configurazione salvata, database " . ($dbExists ? "collegato" : "creato") . ", ma errore durante le migrazioni: " . htmlspecialchars($e->getMessage());
                    }
                    
                    // Rimuovi il token e pulisci la sessione
                    @unlink($tokenFile);
                    unset($_SESSION['token_verified']);
                    // NON fare redirect automatico, mostra il report
                } else {
                    $error = "Impossibile scrivere il file di configurazione. Controlla i permessi di scrittura.";
                }
                
            } catch (PDOException $e) {
                $error = "Connessione fallita: " . $e->getMessage();
            }
        }
    } else {
        // Token non ancora verificato, controlla il token
        $inputToken = trim($_POST['security_token'] ?? '');
        $storedToken = trim(file_get_contents($tokenFile) ?: '');
        
        // Verifica Token
        if (empty($inputToken) || $inputToken !== $storedToken) {
            $error = "Token di sicurezza non valido. Controlla il file config/setup_token.txt";
            $_SESSION['token_verified'] = false;
        } else {
            // Token verificato! Salva in sessione e ricarica la pagina
            $_SESSION['token_verified'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurazione Database - Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .setup-card { max-width: 600px; margin: 50px auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card setup-card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Configurazione Database</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php elseif ($tokenVerified): ?>
                    <!-- Token verificato, mostra form dati database -->
                    <form method="POST" action="">
                        <div class="alert alert-info">
                            <strong>Token verificato!</strong> Aggiorna i parametri di connessione se necessario.
                        </div>
                        
                        <h5 class="mb-3">Parametri Connessione Database</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Host</label>
                            <input type="text" name="host" class="form-control" value="<?php echo htmlspecialchars($currentConfig['host']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nome Database</label>
                            <input type="text" name="db_name" class="form-control" value="<?php echo htmlspecialchars($currentConfig['db']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Utente Database</label>
                            <input type="text" name="user" class="form-control" value="<?php echo htmlspecialchars($currentConfig['user']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password Database</label>
                            <input type="text" name="password" class="form-control" value="<?php echo htmlspecialchars($currentConfig['pass']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Porta</label>
                            <input type="number" name="port" class="form-control" value="<?php echo htmlspecialchars($currentConfig['port']); ?>">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Salva e Connetti</button>
                    </form>
                <?php else: ?>
                    <!-- Token non verificato, mostra form token -->
                    <div class="alert alert-warning">
                        <strong>Attenzione:</strong> La connessione al database è fallita.
                        Per procedere, devi inserire il <strong>Token di Sicurezza</strong> che è stato generato nel file:
                        <code><?php echo realpath($tokenFile); ?></code>
                    </div>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Token di Sicurezza</label>
                            <input type="text" name="security_token" class="form-control" required placeholder="Incolla il token qui">
                            <div class="form-text">Copia il contenuto del file setup_token.txt generato sul server.</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Verifica Token</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
