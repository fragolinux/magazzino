<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-02-03 11:25:20 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2026-02-03 11:25:20
*/

/*
 * Inizializzazione Database
 * 
 * Questo script viene eseguito automaticamente quando il database esiste ma è vuoto.
 * Esegue tutte le migrazioni disponibili per popolare il database.
 */

// Non richiede autenticazione perché viene eseguito prima della creazione delle tabelle users
require_once __DIR__ . '/../config/base_path.php';

// Carica la configurazione database
$dbConfig = require __DIR__ . '/../config/database.php';
$host = $dbConfig['host'];
$db = $dbConfig['db'];
$user = $dbConfig['user'];
$pass = $dbConfig['pass'];
$charset = $dbConfig['charset'];
$port = $dbConfig['port'];

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Errore connessione database: ' . $e->getMessage());
}

// Carica il Migration Manager
require_once __DIR__ . '/migration_manager.php';
$migrationManager = new MigrationManager($pdo);

?><!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inizializzazione Database - Magazzino</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding: 50px 0; }
        .init-card { max-width: 800px; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .migration-log { background: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card init-card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-database"></i> Inizializzazione Database</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Database rilevato!</strong> Il database esiste ma è vuoto. Esecuzione delle migrazioni in corso...
                </div>

                <?php
                try {
                    $result = $migrationManager->runPendingMigrations();
                    
                    echo '<div class="alert alert-success">';
                    echo '<h5>✓ Inizializzazione completata con successo!</h5>';
                    echo '<p>' . htmlspecialchars($result['message']) . '</p>';
                    echo '<p><strong>Versione corrente:</strong> ' . htmlspecialchars($result['currentVersion']) . '</p>';
                    echo '</div>';
                    
                    if (!empty($result['applied'])) {
                        echo '<h5>Dettagli Migrazioni:</h5>';
                        echo '<div class="migration-log">';
                        
                        foreach ($result['applied'] as $migration) {
                            $version = htmlspecialchars($migration['version']);
                            $status = $migration['status'] === 'success' ? '<span class="text-success">✓</span>' : '<span class="text-danger">✗</span>';
                            
                            echo "<strong>{$status} Versione {$version}</strong><br>";
                            
                            if (!empty($migration['stats']['details'])) {
                                foreach ($migration['stats']['details'] as $detail) {
                                    echo htmlspecialchars($detail) . "<br>";
                                }
                            }
                            
                            echo "<br>";
                        }
                        
                        echo '</div>';
                    }
                    
                    echo '<div class="mt-4">';
                    echo '<a href="../index.php" class="btn btn-primary btn-lg">Vai alla Dashboard</a>';
                    echo '</div>';
                    
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger">';
                    echo '<h5>✗ Errore durante l\'inizializzazione</h5>';
                    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '</div>';
                    
                    echo '<div class="mt-4">';
                    echo '<a href="setup_db_wizard.php" class="btn btn-warning">Torna alla Configurazione</a>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>