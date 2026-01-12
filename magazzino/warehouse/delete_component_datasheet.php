<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-11 14:02:38 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-11 14:03:06
*/

// Eimina il datasheet PDF di un componente

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

if (!isset($_POST['component_id']) || !is_numeric($_POST['component_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID componente non valido']);
    exit;
}

$component_id = intval($_POST['component_id']);

try {
    // Recupera il nome del file dal database
    $stmt = $pdo->prepare("SELECT datasheet_file FROM components WHERE id = ?");
    $stmt->execute([$component_id]);
    $component = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$component) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Componente non trovato']);
        exit;
    }
    
    // Elimina il file fisico se esiste
    if ($component['datasheet_file']) {
        $datasheet_dir = realpath(__DIR__ . '/..') . '/datasheet';
        $file_path = $datasheet_dir . DIRECTORY_SEPARATOR . $component['datasheet_file'];
        
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }
    
    // Aggiorna il database rimuovendo il riferimento al file
    $stmt = $pdo->prepare("UPDATE components SET datasheet_file = NULL WHERE id = ?");
    $stmt->execute([$component_id]);
    
    echo json_encode(['success' => true, 'message' => 'Datasheet eliminato con successo']);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore database: ' . $e->getMessage()]);
}
