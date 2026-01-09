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
    $id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : null;
    
    if ($id) {
        $stmt = $pdo->prepare("SELECT id, name, locale_id FROM locations WHERE id = ?");
        $stmt->execute([$id]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($location ?: null);
    } else {
        echo json_encode(null);
    }
} catch (Exception $e) {
    echo json_encode(null);
}
