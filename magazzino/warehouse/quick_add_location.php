<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-08
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-08
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';

if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Il nome della posizione è obbligatorio.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO locations (name) VALUES (?)");
    $stmt->execute([$name]);
    $newId = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'new_id' => $newId]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Errore database: ' . $e->getMessage()]);
}
