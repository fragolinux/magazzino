<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-13
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-01 22:45:07
 * 
 * Configurazione Database
 * Questo file contiene le credenziali e parametri per la connessione al database MySQL
 */

// Carica configurazione percorso base (usa BASE_PATH invece di /magazzino/)
require_once __DIR__ . '/base_path.php';

return [
    'host'    => 'localhost',
    'db'      => 'magazzino_db',
    'user'    => 'magazzino_user',
    'pass'    => 'SecurePass2024!',
    'charset' => 'utf8mb4',
    'port'    => 3306
];
