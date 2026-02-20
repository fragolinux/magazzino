<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-19
 * @Description: AJAX - Recupera info complete progetto per modal
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Accesso negato']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo json_encode(['error' => 'ID progetto non valido']);
    exit;
}

// Recupera progetto
$stmt = $pdo->prepare("SELECT * FROM progetti WHERE id = ?");
$stmt->execute([$id]);
$progetto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$progetto) {
    echo json_encode(['error' => 'Progetto non trovato']);
    exit;
}

// Recupera link web
$stmt_web = $pdo->prepare("SELECT * FROM progetti_link_web WHERE progetto_id = ? ORDER BY id");
$stmt_web->execute([$id]);
$link_web = $stmt_web->fetchAll(PDO::FETCH_ASSOC);

// Recupera link locali
$stmt_local = $pdo->prepare("SELECT * FROM progetti_link_locali WHERE progetto_id = ? ORDER BY id");
$stmt_local->execute([$id]);
$link_locali = $stmt_local->fetchAll(PDO::FETCH_ASSOC);

// Recupera conteggio componenti e costo totale
$sql = "SELECT COUNT(*) as num_componenti,
               COALESCE(SUM(quantita * COALESCE(prezzo, 0)), 0) as costo_totale
        FROM progetti_componenti 
        WHERE ks_progetto = ?";
$stmt_comp = $pdo->prepare($sql);
$stmt_comp->execute([$id]);
$componenti = $stmt_comp->fetch(PDO::FETCH_ASSOC);

$response = [
    'progetto' => $progetto,
    'link_web' => $link_web,
    'link_locali' => $link_locali,
    'num_componenti' => $componenti['num_componenti'] ?? 0,
    'costo_totale' => $componenti['costo_totale'] ?? 0
];

echo json_encode($response);
