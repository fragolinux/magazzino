<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:57:59 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-09 09:52:56
*/
// 2026-01-05: Aggiunta descrizione comparto nella risposta e conteggio componenti
// 2026-01-09: Ordinamento naturale dei comparti

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

if (!isset($_GET['location_id']) || !is_numeric($_GET['location_id'])) {
    echo json_encode([]);
    exit;
}

$location_id = intval($_GET['location_id']);

$stmt = $pdo->prepare("SELECT id, code, description,
    (SELECT COUNT(*) FROM components WHERE compartment_id = compartments.id) AS components_count
    FROM compartments WHERE location_id = ?");
$stmt->execute([$location_id]);
$compartments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ordinamento naturale in PHP
usort($compartments, function($a, $b) {
    return strnatcmp($a['code'], $b['code']);
});

// Conteggio componenti senza comparto
$stmtUnassigned = $pdo->prepare("SELECT COUNT(*) AS count FROM components WHERE location_id = ? AND (compartment_id IS NULL OR compartment_id = 0)");
$stmtUnassigned->execute([$location_id]);
$unassigned_result = $stmtUnassigned->fetch(PDO::FETCH_ASSOC);
$unassigned_count = $unassigned_result['count'] ?? 0;

$response = [
    'compartments' => $compartments,
    'unassigned_count' => $unassigned_count
];

echo json_encode($response);