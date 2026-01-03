<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 18:11:56 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-20 18:12:17
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$type = isset($_GET['type']) && in_array($_GET['type'], ['costruttore','fornitore']) ? $_GET['type'] : 'costruttore';
$results = [];

if ($term !== '') {
    $stmt = $pdo->prepare("SELECT DISTINCT $type AS value FROM components WHERE $type LIKE ? LIMIT 10");
    $stmt->execute(["%$term%"]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($data as $row) {
        $results[] = $row['value'];
    }
}

echo json_encode($results);