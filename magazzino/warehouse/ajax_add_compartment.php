<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-21 09:33:25 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-21 09:36:32
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$location_id = intval($_POST['location_id'] ?? 0);
$code = trim($_POST['code'] ?? '');

if (!$location_id || $code === '') {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit;
}

// Verifica duplicato
$stmt = $pdo->prepare("SELECT id FROM compartments WHERE location_id = ? AND code = ?");
$stmt->execute([$location_id, $code]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Esiste già un compartimento con questo codice.']);
    exit;
}

// Inserisci nuovo compartimento
$stmt = $pdo->prepare("INSERT INTO compartments (location_id, code) VALUES (?, ?)");
$stmt->execute([$location_id, $code]);

echo json_encode(['success' => true]);
exit;