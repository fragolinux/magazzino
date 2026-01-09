<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:55:25 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2026-01-09 16:10:157
*/
// 2026-01-09: Aggiunta cancellazione immagine componente

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: components.php");
    exit;
}

$id = intval($_GET['id']);

// Elimina le immagini se esistono
$base_dir = realpath(__DIR__ . '/../images/components');
$thumb_dir = $base_dir . '/thumbs';

$filename = $id . '.jpg';
$image_path = $base_dir . DIRECTORY_SEPARATOR . $filename;
$thumb_path = $thumb_dir . DIRECTORY_SEPARATOR . $filename;

if (file_exists($image_path)) {
    @unlink($image_path);
}

if (file_exists($thumb_path)) {
    @unlink($thumb_path);
}

// Eliminazione componente
$stmt = $pdo->prepare("DELETE FROM components WHERE id = ?");
$stmt->execute([$id]);

// Reindirizzo con messaggio
header("Location: components.php?deleted=1");
exit;