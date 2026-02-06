<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-08
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-01 21:01:38
*/

// 2026-02-01: aggliunto locale nella select delle posizioni

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $locale_id = isset($_GET['locale_id']) && is_numeric($_GET['locale_id']) ? intval($_GET['locale_id']) : null;
    
    if ($locale_id) {
        $stmt = $pdo->prepare("SELECT l.id, l.name, loc.name AS locale_name FROM locations l LEFT JOIN locali loc ON l.locale_id = loc.id WHERE l.locale_id = ? ORDER BY l.name ASC");
        $stmt->execute([$locale_id]);
    } else {
        $stmt = $pdo->query("SELECT l.id, l.name, loc.name AS locale_name FROM locations l LEFT JOIN locali loc ON l.locale_id = loc.id ORDER BY l.name ASC");
    }
    
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($locations);
} catch (Exception $e) {
    echo json_encode([]);
}
