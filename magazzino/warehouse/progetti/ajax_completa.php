<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: AJAX Completa Progetto
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID progetto mancante']);
    exit;
}

// Verifica che il progetto esista e sia in stato "confermato"
$check = $pdo->prepare("SELECT stato FROM progetti WHERE id = ?");
$check->execute([$id]);
$progetto = $check->fetch(PDO::FETCH_ASSOC);

if (!$progetto) {
    echo json_encode(['success' => false, 'error' => 'Progetto non trovato']);
    exit;
}

if ($progetto['stato'] !== 'confermato') {
    echo json_encode(['success' => false, 'error' => 'Il progetto deve essere in stato "confermato" per essere completato']);
    exit;
}

// Aggiorna lo stato a "completato"
try {
    $stmt = $pdo->prepare("UPDATE progetti SET stato = 'completato', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Progetto completato con successo']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Errore durante il completamento: ' . $e->getMessage()]);
}
