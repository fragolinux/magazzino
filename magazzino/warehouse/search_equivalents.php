<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 18:06:13 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2025-10-20 18:06:13
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$results = [];

if ($term !== '') {
    $stmt = $pdo->prepare("SELECT codice_prodotto FROM components WHERE codice_prodotto LIKE ? LIMIT 10");
    $stmt->execute(["%$term%"]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($data as $row) {
        $results[] = $row['codice_prodotto'];
    }
}

echo json_encode($results);