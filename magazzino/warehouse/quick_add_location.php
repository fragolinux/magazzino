<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-08
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-01 20:52:16
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$locale_id = isset($_POST['locale_id']) && is_numeric($_POST['locale_id']) && $_POST['locale_id'] !== '' ? intval($_POST['locale_id']) : null;

if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Il nome della posizione è obbligatorio.']);
    exit;
}

if ($locale_id === null) {
    echo json_encode(['success' => false, 'error' => 'Il locale è obbligatorio.']);
    exit;
}

// Controllo duplicati (nome unico per locale)
$checkStmt = $pdo->prepare("SELECT id FROM locations WHERE name = ? AND locale_id = ?");
$checkStmt->execute([$name, $locale_id]);
if ($checkStmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Esiste già una posizione con questo nome in questo locale.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO locations (name, locale_id) VALUES (?, ?)");
    $stmt->execute([$name, $locale_id]);
    $newId = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'new_id' => $newId]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Errore database: ' . $e->getMessage()]);
}
