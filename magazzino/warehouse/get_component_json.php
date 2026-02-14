<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-02-09 18:00:25 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2026-02-09 19:00:25
*/

/*
 * Restituisce i dati di un componente in formato JSON
 * Usato per la funzione "Clona componente"
 */

require_once '../config/base_path.php';
require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'ID componente non valido']);
    exit;
}

$id = intval($_GET['id']);

$stmt = $pdo->prepare("
    SELECT c.*, cat.name AS category_name, l.name AS location_name, cmp.code AS compartment_code
    FROM components c
    LEFT JOIN categories cat ON c.category_id = cat.id
    LEFT JOIN locations l ON c.location_id = l.id
    LEFT JOIN compartments cmp ON c.compartment_id = cmp.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$component = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$component) {
    echo json_encode(['error' => 'Componente non trovato']);
    exit;
}

// Decodifica equivalenti e tags da JSON a array
if ($component['equivalents']) {
    $component['equivalents'] = json_decode($component['equivalents'], true);
}
if ($component['tags']) {
    $component['tags'] = json_decode($component['tags'], true);
}

echo json_encode($component);
