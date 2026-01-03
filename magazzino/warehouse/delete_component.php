<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:55:25 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2025-10-20 17:55:25
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: components.php");
    exit;
}

$id = intval($_GET['id']);

// Eliminazione componente
$stmt = $pdo->prepare("DELETE FROM components WHERE id = ?");
$stmt->execute([$id]);

// Reindirizzo con messaggio
header("Location: components.php?deleted=1");
exit;