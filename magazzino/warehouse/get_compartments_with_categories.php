<?php
/*
 * @Author: gabriele.riva
 * @Date: 2026-05-03 21:23:13
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2026-05-03 21:24:03
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if (!isset($_GET['location_id']) || !is_numeric($_GET['location_id'])) {
    echo json_encode(['success' => false, 'error' => 'ID posizione non valido']);
    exit;
}

$location_id = intval($_GET['location_id']);

// 1. Recupera i comparti della location
$stmt = $pdo->prepare("SELECT id, code, description FROM compartments WHERE location_id = ?");
$stmt->execute([$location_id]);
$compartments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Per ogni comparto, recupera le categorie e i relativi conteggi
foreach ($compartments as &$comp) {
    $comp_id = $comp['id'];
    
    // Conteggio totale comparto
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM components WHERE compartment_id = ?");
    $stmtCount->execute([$comp_id]);
    $comp['components_count'] = $stmtCount->fetchColumn();
    
    // Categorie nel comparto
    $stmtCats = $pdo->prepare("
        SELECT c.id, c.name, COUNT(comp.id) as count
        FROM categories c
        JOIN components comp ON comp.category_id = c.id
        WHERE comp.compartment_id = ?
        GROUP BY c.id, c.name
        ORDER BY c.name ASC
    ");
    $stmtCats->execute([$comp_id]);
    $comp['categories'] = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
}

// Ordinamento naturale dei comparti
usort($compartments, function($a, $b) {
    return strnatcmp($a['code'], $b['code']);
});

// 2. Recupera componenti senza comparto per questa location
$stmtUnassigned = $pdo->prepare("SELECT COUNT(*) FROM components WHERE location_id = ? AND (compartment_id IS NULL OR compartment_id = 0)");
$stmtUnassigned->execute([$location_id]);
$unassigned_total = $stmtUnassigned->fetchColumn();

$unassigned_categories = [];
if ($unassigned_total > 0) {
    $stmtUnCats = $pdo->prepare("
        SELECT c.id, c.name, COUNT(comp.id) as count
        FROM categories c
        JOIN components comp ON comp.category_id = c.id
        WHERE comp.location_id = ? AND (comp.compartment_id IS NULL OR comp.compartment_id = 0)
        GROUP BY c.id, c.name
        ORDER BY c.name ASC
    ");
    $stmtUnCats->execute([$location_id]);
    $unassigned_categories = $stmtUnCats->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode([
    'success' => true,
    'compartments' => $compartments,
    'unassigned_count' => $unassigned_total,
    'unassigned_categories' => $unassigned_categories
]);
