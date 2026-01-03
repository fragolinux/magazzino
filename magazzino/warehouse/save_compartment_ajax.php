<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-21 08:07:11 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-21 08:07:33
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$location_id = intval($_POST['location_id'] ?? 0);
$code = trim($_POST['code'] ?? '');

if (!$location_id || $code === '') {
    echo json_encode(['error' => 'Dati mancanti']);
    exit;
}

// Controllo duplicati
$stmt = $pdo->prepare("SELECT id FROM compartments WHERE location_id = ? AND code = ?");
$stmt->execute([$location_id, $code]);
if ($stmt->fetch()) {
    echo json_encode(['error' => 'Comparto già esistente']);
    exit;
}

// Inserisci nuovo
$stmt = $pdo->prepare("INSERT INTO compartments (location_id, code) VALUES (?, ?)");
$stmt->execute([$location_id, $code]);

echo json_encode(['success' => true]);