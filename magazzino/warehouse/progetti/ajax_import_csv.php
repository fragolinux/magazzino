<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: AJAX Import Componenti da CSV
 */

// Buffer output per prevenire errori "headers already sent" e catturare debug
ob_start();

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

// Cattura qualsiasi output prodotto prima dell'header
$unexpected_output = ob_get_clean();
if (!empty($unexpected_output)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'error' => 'Output inatteso rilevato',
        'debug_output' => substr($unexpected_output, 0, 2000)
    ]);
    exit;
}

// Riavvia il buffer per il resto dello script
ob_start();

header('Content-Type: application/json');

// Handler per catturare errori PHP e restituirli come JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'PHP Error: ' . $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

set_exception_handler(function($e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Exception: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
});

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
    exit;
}

$progetto_id = intval($_POST['progetto_id'] ?? 0);
if (!$progetto_id) {
    echo json_encode(['success' => false, 'error' => 'ID progetto mancante']);
    exit;
}

// Verifica file
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Errore caricamento file']);
    exit;
}

$file = $_FILES['csv_file'];
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File troppo grande (max 5MB)']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    echo json_encode(['success' => false, 'error' => 'Solo file CSV sono permessi']);
    exit;
}

// Verifica che il progetto sia in bozza
$check = $pdo->prepare("SELECT stato FROM progetti WHERE id = ?");
$check->execute([$progetto_id]);
$progetto = $check->fetch(PDO::FETCH_ASSOC);

if (!$progetto || $progetto['stato'] !== 'bozza') {
    echo json_encode(['success' => false, 'error' => 'Il progetto non è modificabile']);
    exit;
}

// Leggi CSV
$lines = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$lines) {
    echo json_encode(['success' => false, 'error' => 'File vuoto o non leggibile']);
    exit;
}

$aggiunti = 0;
$aggiunti_list = [];
$non_trovati = [];
$duplicati = [];

// Determina delimitatore
$delimiter = ',';
$firstLine = $lines[0];
if (strpos($firstLine, ';') !== false && substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
    $delimiter = ';';
}

// Salta header se presente
$start = 0;
$firstCols = str_getcsv($lines[0], $delimiter, '"', "\\");
if (!is_numeric($firstCols[1] ?? '')) {
    $start = 1;
}

// Recupera lista fornitori per lookup
$fornitori_list = $pdo->query("SELECT id, nome FROM fornitori")->fetchAll(PDO::FETCH_KEY_PAIR);

$pdo->beginTransaction();

try {
    for ($i = $start; $i < count($lines); $i++) {
        $cols = str_getcsv($lines[$i], $delimiter, '"', "\\");
        if (count($cols) < 2) continue;
        
        $codice = trim($cols[0]);
        $quantita = intval($cols[1]);
        $prezzo = isset($cols[2]) && $cols[2] !== '' ? floatval(str_replace(',', '.', $cols[2])) : null;
        $fornitore = isset($cols[3]) ? trim($cols[3]) : null;
        $note = isset($cols[4]) ? trim($cols[4]) : null;
        $link_fornitore = isset($cols[5]) ? trim($cols[5]) : null;
        
        if (empty($codice) || $quantita < 1) continue;
        
        // Cerca ID fornitore dalla tabella fornitori (per progetti_componenti)
        $id_fornitore = null;
        if ($fornitore) {
            foreach ($fornitori_list as $fid => $fnome) {
                if (strcasecmp($fnome, $fornitore) === 0) {
                    $id_fornitore = $fid;
                    break;
                }
            }
        }
        
        // Cerca componente in magazzino
        $stmt = $pdo->prepare("SELECT id FROM components 
                             WHERE codice_prodotto = ? 
                                OR JSON_CONTAINS(equivalents, JSON_QUOTE(?))");
        $stmt->execute([$codice, $codice]);
        $componente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($componente) {
            // Verifica se il componente è già nel progetto
            $checkDup = $pdo->prepare("SELECT id FROM progetti_componenti WHERE ks_progetto = ? AND ks_componente = ?");
            $checkDup->execute([$progetto_id, $componente['id']]);
            
            if ($checkDup->fetch()) {
                // Componente già presente nel progetto
                $duplicati[] = [
                    'codice' => $codice,
                    'quantita' => $quantita
                ];
            } else {
                // Aggiungi al progetto
                $stmt = $pdo->prepare("INSERT INTO progetti_componenti 
                    (ks_progetto, ks_componente, quantita, prezzo, ks_fornitore, note, link_fornitore) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$progetto_id, $componente['id'], $quantita, $prezzo, $id_fornitore, $note, $link_fornitore]);
                $aggiunti++;
                $aggiunti_list[] = [
                    'codice' => $codice,
                    'quantita' => $quantita
                ];
            }
        } else {
            $non_trovati[] = [
                'codice' => $codice,
                'quantita' => $quantita,
                'prezzo' => $prezzo,
                'fornitore' => $fornitore,
                'note' => $note,
                'link_fornitore' => $link_fornitore
            ];
        }
    }
    
    $pdo->commit();
    
    // Rimuovi duplicati basati sul codice
    $non_trovati_unici = [];
    $codici_visti = [];
    foreach ($non_trovati as $item) {
        $codice = is_array($item) ? $item['codice'] : $item;
        if (!in_array($codice, $codici_visti)) {
            $non_trovati_unici[] = $item;
            $codici_visti[] = $codice;
        }
    }
    
    echo json_encode([
        'success' => true,
        'aggiunti' => $aggiunti,
        'aggiunti_list' => $aggiunti_list,
        'non_trovati' => $non_trovati_unici,
        'duplicati' => $duplicati
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
