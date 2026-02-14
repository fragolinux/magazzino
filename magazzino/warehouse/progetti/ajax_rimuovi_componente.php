<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: AJAX Rimuovi Componente dal Progetto
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID mancante']);
    exit;
}

try {
    // Recupera info componente progetto
    $stmt = $pdo->prepare("SELECT pc.ks_progetto, p.stato 
                         FROM progetti_componenti pc
                         JOIN progetti p ON pc.ks_progetto = p.id
                         WHERE pc.id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Componente non trovato']);
        exit;
    }
    
    if ($row['stato'] !== 'bozza') {
        echo json_encode(['success' => false, 'error' => 'Il progetto non è modificabile']);
        exit;
    }
    
    // Elimina
    $stmt = $pdo->prepare("DELETE FROM progetti_componenti WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
