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
    $locale_id = isset($_GET['locale_id']) && is_numeric($_GET['locale_id']) ? intval($_GET['locale_id']) : null;
    
    if ($locale_id) {
        $stmt = $pdo->prepare("SELECT id, name FROM locations WHERE locale_id = ? ORDER BY name ASC");
        $stmt->execute([$locale_id]);
    } else {
        $stmt = $pdo->query("SELECT id, name FROM locations ORDER BY name ASC");
    }
    
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($locations);
} catch (Exception $e) {
    echo json_encode([]);
}
