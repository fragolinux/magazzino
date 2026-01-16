<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-09
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-15 18:08:02
*/
// Upload e salvataggio immagini componenti
// Riceve immagini ridimensionate dal browser in formato base64

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';
require_once '../includes/secure_upload.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo non valido']);
    exit;
}

$component_id = isset($_POST['component_id']) && is_numeric($_POST['component_id']) ? intval($_POST['component_id']) : null;
$image_data = isset($_POST['image_data']) ? $_POST['image_data'] : '';
$thumb_data = isset($_POST['thumb_data']) ? $_POST['thumb_data'] : '';

if (!$component_id) {
    echo json_encode(['success' => false, 'error' => 'ID componente non valido']);
    exit;
}

if (empty($image_data) || empty($thumb_data)) {
    echo json_encode(['success' => false, 'error' => 'Dati immagine mancanti']);
    exit;
}

// Decodifica le immagini base64 (supporta jpeg e webp)
$image_data = preg_replace('/^data:image\/(jpeg|webp);base64,/', '', $image_data);
$image_data = str_replace(' ', '+', $image_data);
$image_binary = base64_decode($image_data);

$thumb_data = preg_replace('/^data:image\/(jpeg|webp);base64,/', '', $thumb_data);
$thumb_data = str_replace(' ', '+', $thumb_data);
$thumb_binary = base64_decode($thumb_data);

if (!$image_binary || !$thumb_binary) {
    echo json_encode(['success' => false, 'error' => 'Errore nella decodifica delle immagini']);
    exit;
}

// Percorsi delle cartelle
$base_dir = realpath(__DIR__ . '/../images/components');
$thumb_dir = $base_dir . '/thumbs';

// Verifica che le cartelle esistano
if (!is_dir($base_dir)) {
    echo json_encode(['success' => false, 'error' => 'Cartella images/components non trovata']);
    exit;
}

if (!is_dir($thumb_dir)) {
    echo json_encode(['success' => false, 'error' => 'Cartella images/components/thumbs non trovata']);
    exit;
}

// Nomi dei file
$filename = $component_id . '.jpg';
$image_path = $base_dir . DIRECTORY_SEPARATOR . $filename;
$thumb_path = $thumb_dir . DIRECTORY_SEPARATOR . $filename;

// Salva le immagini
$image_saved = @file_put_contents($image_path, $image_binary);
$thumb_saved = @file_put_contents($thumb_path, $thumb_binary);

if ($image_saved === false || $thumb_saved === false) {
    echo json_encode(['success' => false, 'error' => 'Impossibile salvare le immagini sul server']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Immagini salvate con successo']);
