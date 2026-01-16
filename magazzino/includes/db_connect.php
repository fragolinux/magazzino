<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:43:45 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-15 18:32:37
*/
// 2026-01-11: Aggiunta gestione della porta MySQL
// 2026-01-12: Aggiunta gestione modalità errori PHP da impostazioni
// 2026-01-13: Configurazione database spostata in file separato, gestione errori da file
// 2026-01-15: Aggiunte configurazioni di sicurezza per sessioni (cookie_secure, httponly, samesite, rigenerazione)

// Carica configurazione database da file esterno
require __DIR__ . '/env_loader.php';
$dbConfig = require __DIR__ . '/../config/database.php';
$host = $dbConfig['host'];
$db = $dbConfig['db'];
$user = $dbConfig['user'];
$pass = $dbConfig['pass'];
$charset = $dbConfig['charset'];
$port = $dbConfig['port'];

$dsn = "mysql:host=$host;port=$port;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Errori come eccezioni
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch come array associativo
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Preparazioni sicure
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,             // Buffering query per evitare errore 2014
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Seleziona il database dopo la connessione (per compatibilità con GRANT specifici)
    $pdo->exec("USE `$db`");
    
    // Rigenera ID sessione periodicamente per sicurezza (solo se sessione attiva)
    if (session_status() === PHP_SESSION_ACTIVE && (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 1800)) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    // Gestione errori PHP basata su setting del database o file config
    try {
        $stmtError = $pdo->prepare("SELECT setting_value FROM setting WHERE setting_name = ? LIMIT 1");
        $stmtError->execute(['environment_mode']);
        $rowError = $stmtError->fetch(PDO::FETCH_ASSOC);
        
        if ($rowError) {
            $envMode = $rowError['setting_value'];
        } else {
            // Fallback: leggi da file config
            $settingsConfig = @include __DIR__ . '/../config/settings.php';
            $envMode = $settingsConfig['environment_mode'] ?? 'production';
        }
        
        if ($envMode === 'development') {
            // Modalità sviluppo: mostra tutti gli errori
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
        } else {
            // Modalità produzione: nascondi errori agli utenti
            ini_set('display_errors', 0);
            ini_set('display_startup_errors', 0);
            error_reporting(E_ALL); // Log comunque gli errori
        }
    } catch (Exception $e) {
        // Se la tabella setting non esiste, leggi da file o usa default produzione
        $settingsConfig = @include __DIR__ . '/../config/settings.php';
        $envMode = $settingsConfig['environment_mode'] ?? 'production';
        
        if ($envMode === 'development') {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', 0);
            ini_set('display_startup_errors', 0);
            error_reporting(E_ALL);
        }
    }
    
} catch (\PDOException $e) {
    exit('Connessione al database fallita: ' . $e->getMessage());
}
?>