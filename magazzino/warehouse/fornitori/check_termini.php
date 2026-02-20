<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-19
 * @Description: AJAX endpoint per verificare l'esistenza di un file termini
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

// Verifica permessi admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Accesso negato']);
    exit;
}

// Imposta header JSON
header('Content-Type: application/json');

// Recupera il nome fornitore
$nome = isset($_GET['nome']) ? trim($_GET['nome']) : '';

if (empty($nome)) {
    echo json_encode(['exists' => false, 'file' => null]);
    exit;
}

// Match parziale: cerca un file termini dove il nome fornitore contiene la keyword
$nome_lower = strtolower($nome);
$termini_file = null;

// Cerca tutti i file termini_*.txt nella directory
$termini_files = glob(__DIR__ . '/termini_*.txt');
foreach ($termini_files as $file_path) {
    // Estrai la keyword dal nome file (termini_keyword.txt -> keyword)
    $filename = basename($file_path, '.txt'); // termini_keyword
    $keyword = substr($filename, 8); // rimuovi 'termini_' -> keyword
    
    // Verifica se il nome fornitore contiene la keyword
    if (strpos($nome_lower, strtolower($keyword)) !== false) {
        $termini_file = basename($file_path);
        break;
    }
}

// Restituisci il risultato
if ($termini_file) {
    echo json_encode(['exists' => true, 'file' => $termini_file]);
} else {
    echo json_encode(['exists' => false, 'file' => null]);
}
