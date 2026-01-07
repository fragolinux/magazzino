<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:57:59 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-05 09:52:56
*/

// 2026-01-05: Aggiunta descrizione comparto nella risposta e conteggio componenti

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

if (!isset($_GET['location_id']) || !is_numeric($_GET['location_id'])) {
    echo json_encode([]);
    exit;
}

$location_id = intval($_GET['location_id']);

$stmt = $pdo->prepare("SELECT id, code, description,
    (SELECT COUNT(*) FROM components WHERE compartment_id = compartments.id) AS components_count
    FROM compartments WHERE location_id = ? ORDER BY
    REGEXP_REPLACE(code, '[0-9]', '') ASC,
    CAST(REGEXP_REPLACE(code, '[^0-9]', '') AS UNSIGNED) ASC");
$stmt->execute([$location_id]);
$compartments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($compartments);