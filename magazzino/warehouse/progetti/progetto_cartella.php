<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-19
 * @Description: Visualizza contenuto cartella locale progetto
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

// Verifica permessi admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$path = isset($_GET['path']) ? $_GET['path'] : '';
$progetto_id = isset($_GET['progetto_id']) ? intval($_GET['progetto_id']) : 0;

if (empty($path)) {
    $_SESSION['error'] = 'Percorso non specificato.';
    header('Location: progetti.php');
    exit;
}

// Verifica che il percorso esista e sia una directory
$path = realpath($path);
if ($path === false || !is_dir($path)) {
    $error = 'La cartella specificata non esiste o non è accessibile.';
    $files = [];
} else {
    $error = '';
    // Leggi i file nella cartella
    $files = [];
    $items = scandir($path);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $fullPath = $path . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($fullPath);
        
        $files[] = [
            'name' => $item,
            'path' => $fullPath,
            'is_dir' => $isDir,
            'size' => $isDir ? '-' : formatFileSize(filesize($fullPath)),
            'modified' => date('d/m/Y H:i', filemtime($fullPath)),
            'extension' => $isDir ? 'folder' : strtolower(pathinfo($item, PATHINFO_EXTENSION))
        ];
    }
    
    // Ordina: cartelle prima, poi file per nome
    usort($files, function($a, $b) {
        if ($a['is_dir'] !== $b['is_dir']) {
            return $b['is_dir'] <=> $a['is_dir'];
        }
        return strcasecmp($a['name'], $b['name']);
    });
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function getFileIcon($ext, $isDir) {
    if ($isDir) return '<i class="fa-solid fa-folder text-warning"></i>';
    
    $icons = [
        'pdf' => '<i class="fa-solid fa-file-pdf text-danger"></i>',
        'txt' => '<i class="fa-solid fa-file-lines text-secondary"></i>',
        'jpg' => '<i class="fa-solid fa-file-image text-info"></i>',
        'jpeg' => '<i class="fa-solid fa-file-image text-info"></i>',
        'png' => '<i class="fa-solid fa-file-image text-info"></i>',
        'gif' => '<i class="fa-solid fa-file-image text-info"></i>',
        'bmp' => '<i class="fa-solid fa-file-image text-info"></i>',
        'svg' => '<i class="fa-solid fa-file-image text-info"></i>',
        'doc' => '<i class="fa-solid fa-file-word text-primary"></i>',
        'docx' => '<i class="fa-solid fa-file-word text-primary"></i>',
        'xls' => '<i class="fa-solid fa-file-excel text-success"></i>',
        'xlsx' => '<i class="fa-solid fa-file-excel text-success"></i>',
        'ppt' => '<i class="fa-solid fa-file-powerpoint text-warning"></i>',
        'pptx' => '<i class="fa-solid fa-file-powerpoint text-warning"></i>',
        'zip' => '<i class="fa-solid fa-file-zipper text-muted"></i>',
        'rar' => '<i class="fa-solid fa-file-zipper text-muted"></i>',
        '7z' => '<i class="fa-solid fa-file-zipper text-muted"></i>',
    ];
    
    return $icons[$ext] ?? '<i class="fa-solid fa-file text-secondary"></i>';
}

function canPreview($ext) {
    $previewable = ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'];
    return in_array($ext, $previewable);
}

include '../../includes/header.php';
?>

    <?php
    // Memorizza il percorso base (quello originale passato come parametro) per non salire oltre
    $base_path = isset($_SESSION['cartella_base_' . $progetto_id]) ? $_SESSION['cartella_base_' . $progetto_id] : $path;
    if (!isset($_SESSION['cartella_base_' . $progetto_id])) {
        $_SESSION['cartella_base_' . $progetto_id] = $base_path;
    }
    
    // Calcola il percorso parent
    $parent_path = dirname($path);
    // Mostra il bottone Su se il parent è diverso dal path attuale (possiamo risalire)
    // e il parent non è la root o vuoto
    $can_go_up = ($parent_path !== $path && !empty($parent_path) && $parent_path !== '.');
    ?>
    
    <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>
            <i class="fa-solid fa-folder-open me-2 text-success"></i>Contenuto Cartella
        </h2>
        <div>
            <?php if ($can_go_up): ?>
                <a href="?path=<?= urlencode($parent_path) ?>&progetto_id=<?= $progetto_id ?>" class="btn btn-warning">
                    <i class="fa-solid fa-arrow-up me-1"></i>Su
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary<?= $can_go_up ? ' ms-2' : '' ?>" onclick="window.close()">
                <i class="fa-solid fa-times me-1"></i>Chiudi finestra
            </button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fa-solid fa-location-dot me-2 text-muted"></i>
                        <code><?= htmlspecialchars($path) ?></code>
                    </div>
                    <span class="badge bg-secondary"><?= count($files) ?> elementi</span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($files)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-folder-open fa-3x mb-3"></i>
                        <p>La cartella è vuota.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Nome</th>
                                    <th>Dimensione</th>
                                    <th>Modificato</th>
                                    <th class="text-center">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $file): ?>
                                    <tr>
                                        <td class="text-center"><?= getFileIcon($file['extension'], $file['is_dir']) ?></td>
                                        <td>
                                            <?php if ($file['is_dir']): ?>
                                                <a href="?path=<?= urlencode($file['path']) ?>&progetto_id=<?= $progetto_id ?>" class="text-decoration-none fw-bold">
                                                    <?= htmlspecialchars($file['name']) ?>
                                                </a>
                                            <?php else: ?>
                                                <?= htmlspecialchars($file['name']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $file['size'] ?></td>
                                        <td><?= $file['modified'] ?></td>
                                        <td class="text-center">
                                            <?php if (!$file['is_dir']): ?>
                                                <?php if (canPreview($file['extension'])): ?>
                                                    <a href="file_viewer.php?file=<?= urlencode($file['path']) ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       target="_blank" title="Visualizza">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="file_download.php?file=<?= urlencode($file['path']) ?>" 
                                                   class="btn btn-sm btn-outline-success" 
                                                   title="Scarica">
                                                    <i class="fa-solid fa-download"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="?path=<?= urlencode($file['path']) ?>&progetto_id=<?= $progetto_id ?>" 
                                                   class="btn btn-sm btn-outline-warning" 
                                                   title="Apri cartella">
                                                    <i class="fa-solid fa-folder-open"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
