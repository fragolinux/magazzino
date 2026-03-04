<?php
/*
 * Override per Docker: usa variabili d'ambiente se presenti.
 */

// Mantiene compatibilita con il codice upstream che usa BASE_PATH nei redirect/cookie.
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../config/base_path.php';
}

// Configurazione database
$host = getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost';
$db   = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'magazzino_db'; // nome del database
$user = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';         // username DB
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';             // password DB (lascia vuoto se XAMPP default)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Errori come eccezioni
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch come array associativo
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Preparazioni sicure
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // In produzione potresti loggare l'errore invece di mostrarlo
    exit('Connessione al database fallita: ' . $e->getMessage());
}
?>
