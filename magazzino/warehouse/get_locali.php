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

try {
    $stmt = $pdo->query("SELECT id, name, description FROM locali ORDER BY name ASC");
    $locali = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($locali);
} catch (Exception $e) {
    echo json_encode([]);
}
