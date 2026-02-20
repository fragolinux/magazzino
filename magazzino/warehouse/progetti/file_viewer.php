<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-19
 * @Description: Visualizzatore file (PDF, immagini, TXT)
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

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$filename = basename($file);

// Tipi MIME supportati
$mimes = [
    'pdf' => 'application/pdf',
    'txt' => 'text/plain',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'bmp' => 'image/bmp',
    'svg' => 'image/svg+xml'
];

if (!isset($mimes[$ext])) {
    $_SESSION['error'] = 'Tipo di file non supportato per la visualizzazione.';
    header('Location: progetti.php');
    exit;
}

// Per PDF e immagini, mostra in un iframe/viewer
// Per TXT, mostra il contenuto

if ($ext === 'txt') {
    $content = file_get_contents($file);
    include '../../includes/header.php';
    ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5><i class="fa-solid fa-file-lines me-2"></i><?= htmlspecialchars($filename) ?></h5>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.close()">
                <i class="fa-solid fa-times me-1"></i>Chiudi finestra
            </button>
        </div>
        <div class="card">
            <div class="card-body">
                <pre class="mb-0" style="max-height: 70vh; overflow: auto;"><?= htmlspecialchars($content) ?></pre>
            </div>
        </div>
    </div>
    <?php
    include '../../includes/footer.php';
} else {
    // Per PDF e immagini, servi direttamente
    header('Content-Type: ' . $mimes[$ext]);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    readfile($file);
    exit;
}
