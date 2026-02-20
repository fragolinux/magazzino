<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-19
 * @Description: Download file
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

// Verifica permessi admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$file = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($file)) {
    $_SESSION['error'] = 'File non specificato.';
    header('Location: progetti.php');
    exit;
}

// Verifica che il file esista
$file = realpath($file);
if ($file === false || !is_file($file)) {
    $_SESSION['error'] = 'File non trovato.';
    header('Location: progetti.php');
    exit;
}

$filename = basename($file);
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

// Tipi MIME comuni
$mimes = [
    'pdf' => 'application/pdf',
    'txt' => 'text/plain',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'bmp' => 'image/bmp',
    'svg' => 'image/svg+xml',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed',
    '7z' => 'application/x-7z-compressed'
];

$mime = $mimes[$ext] ?? 'application/octet-stream';

// Invia header per download
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-cache, must-revalidate');

readfile($file);
exit;
