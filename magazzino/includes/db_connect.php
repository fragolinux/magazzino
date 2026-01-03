<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:43:45 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-20 18:34:55
*/

// Configurazione database
$host = 'localhost';
$db   = 'magazzino_db';      // nome del database
$user = 'root';            // username DB
$pass = '';                // password DB (lascia vuoto se XAMPP default)
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
