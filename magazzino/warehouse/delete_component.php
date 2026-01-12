<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:55:25 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2026-01-12 16:10:157
*/
// 2026-01-09: Aggiunta cancellazione immagine componente

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: components.php");
    exit;
}

$id = intval($_GET['id']);

// Recupera il nome del file datasheet prima di eliminare il record
$stmt_info = $pdo->prepare("SELECT datasheet_file FROM components WHERE id = ?");
$stmt_info->execute([$id]);
$component = $stmt_info->fetch(PDO::FETCH_ASSOC);

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

// Elimina il file datasheet PDF se esiste
if ($component && !empty($component['datasheet_file'])) {
    $datasheet_dir = realpath(__DIR__ . '/../datasheet');
    if ($datasheet_dir) {
        $datasheet_path = $datasheet_dir . DIRECTORY_SEPARATOR . $component['datasheet_file'];
        if (file_exists($datasheet_path)) {
            @unlink($datasheet_path);
        }
    }
}

// Eliminazione componente
$stmt = $pdo->prepare("DELETE FROM components WHERE id = ?");
$stmt->execute([$id]);

// Se è una richiesta AJAX, ritorna JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Componente eliminato con successo']);
    exit;
}

// Altrimenti reindirizzo con messaggio
header("Location: components.php?deleted=1");
exit;