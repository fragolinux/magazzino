<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-09
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-09
*/
// Elimina immagine componente

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo non valido']);
    exit;
}

$component_id = isset($_POST['component_id']) && is_numeric($_POST['component_id']) ? intval($_POST['component_id']) : null;

if (!$component_id) {
    echo json_encode(['success' => false, 'error' => 'ID componente non valido']);
    exit;
}

// Percorsi dei file
$base_dir = realpath(__DIR__ . '/../images/components');
$thumb_dir = $base_dir . '/thumbs';

$filename = $component_id . '.jpg';
$image_path = $base_dir . DIRECTORY_SEPARATOR . $filename;
$thumb_path = $thumb_dir . DIRECTORY_SEPARATOR . $filename;

$image_deleted = true;
$thumb_deleted = true;

// Elimina immagine principale
if (file_exists($image_path)) {
    $image_deleted = @unlink($image_path);
}

// Elimina thumbnail
if (file_exists($thumb_path)) {
    $thumb_deleted = @unlink($thumb_path);
}

if ($image_deleted && $thumb_deleted) {
    echo json_encode(['success' => true, 'message' => 'Immagine eliminata con successo']);
} else {
    echo json_encode(['success' => false, 'error' => 'Errore durante l\'eliminazione']);
}
