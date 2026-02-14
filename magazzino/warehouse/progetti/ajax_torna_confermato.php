<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-09
 * @Description: AJAX Torna a Confermato (da Completato)
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

// Verifica che il progetto esista e sia in stato "completato"
$check = $pdo->prepare("SELECT stato FROM progetti WHERE id = ?");
$check->execute([$id]);
$progetto = $check->fetch(PDO::FETCH_ASSOC);

if (!$progetto) {
    echo json_encode(['success' => false, 'error' => 'Progetto non trovato']);
    exit;
}

if ($progetto['stato'] !== 'completato') {
    echo json_encode(['success' => false, 'error' => 'Il progetto deve essere in stato "completato"']);
    exit;
}

// Aggiorna lo stato a "confermato"
try {
    $stmt = $pdo->prepare("UPDATE progetti SET stato = 'confermato', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Stato aggiornato a "Confermato"']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Errore durante l\'aggiornamento: ' . $e->getMessage()]);
}
