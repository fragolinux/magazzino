<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-03-02
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-03-02
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Verifica che l'ID sia stato fornito
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Locale non specificato.';
    header('Location: locali.php');
    exit;
}

$locale_id = intval($_GET['id']);

// Recupera i dati del locale
$stmt = $pdo->prepare("SELECT name, pdf_filename FROM locali WHERE id = ?");
$stmt->execute([$locale_id]);
$locale = $stmt->fetch();

if (!$locale) {
    $_SESSION['error'] = 'Locale non trovato.';
    header('Location: locali.php');
    exit;
}

if (!$locale['pdf_filename']) {
    $_SESSION['error'] = 'Nessun PDF allegato a questo locale.';
    header('Location: locali.php');
    exit;
}

// Costruisci il percorso del file in modo sicuro
$filename = basename($locale['pdf_filename']); // Previene directory traversal
$filepath = realpath(__DIR__ . '/../uploads/locali/' . $filename);

// Verifica che il file esista e sia nella directory corretta
$expected_dir = realpath(__DIR__ . '/../uploads/locali/');
if (!$filepath || strpos($filepath, $expected_dir) !== 0 || !file_exists($filepath)) {
    $_SESSION['error'] = 'File PDF non trovato.';
    header('Location: locali.php');
    exit;
}

// Imposta gli header per il download
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . htmlspecialchars($locale['name']) . '_mappa.pdf"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=86400'); // Cache 24 ore

// Leggi e invia il file
readfile($filepath);
exit;