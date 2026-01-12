<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:43:45 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-11 10:15:30
*/
// 2026-01-11: Aggiunta gestione della porta MySQL

// Configurazione database
$host = 'localhost';
$db   = 'magazzino_db';      // nome del database
$user = 'root';            // username DB
$pass = '';                // password DB (lascia vuoto se XAMPP default)
$charset = 'utf8mb4';
$port = 3306;              // porta MySQL predefinita

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Errori come eccezioni
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch come array associativo
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Preparazioni sicure
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,             // Buffering query per evitare errore 2014
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    exit('Connessione al database fallita: ' . $e->getMessage());
}
?>