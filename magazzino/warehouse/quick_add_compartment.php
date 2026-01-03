<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-21 08:11:15 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-21 08:19:14
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$location_id = isset($_POST['location_id']) && is_numeric($_POST['location_id']) ? intval($_POST['location_id']) : 0;
$code = isset($_POST['code']) ? trim($_POST['code']) : '';

if (!$location_id || $code === '') {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti.']);
    exit;
}

// Controllo duplicati
$stmt = $pdo->prepare("SELECT id FROM compartments WHERE location_id = ? AND code = ?");
$stmt->execute([$location_id, $code]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Comparto già esistente.']);
    exit;
}

// Inserimento
$stmt = $pdo->prepare("INSERT INTO compartments (location_id, code) VALUES (?, ?)");
$stmt->execute([$location_id, $code]);
$new_id = $pdo->lastInsertId();

echo json_encode(['success' => true, 'new_id' => $new_id, 'location_id' => $location_id]);