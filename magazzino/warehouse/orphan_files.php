<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-09
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-09
*/
// Trova file orfani (datasheet e immagini senza componente corrispondente)

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Solo admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /magazzino/index.php");
    exit;
}

$orphanDatasheets = [];
$orphanImages = [];
$orphanThumbs = [];

// Recupera tutti gli ID dei componenti esistenti
$stmt = $pdo->query("SELECT id FROM components");
$existingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$existingIdsMap = array_flip($existingIds);

// Controlla datasheet orfani
$datasheetDir = realpath(__DIR__ . '/../datasheet');
if ($datasheetDir && is_dir($datasheetDir)) {
    $datasheetFiles = glob($datasheetDir . '/*.pdf');
    foreach ($datasheetFiles as $file) {
        $filename = basename($file);
        // Estrai l'ID dal nome del file (formato: ID.pdf)
        if (preg_match('/^(\d+)\.pdf$/', $filename, $matches)) {
            $fileId = intval($matches[1]);
            if (!isset($existingIdsMap[$fileId])) {
                $orphanDatasheets[] = [
                    'filename' => $filename,
                    'path' => $file,
                    'size' => filesize($file),
                    'id' => $fileId
                ];
            }
        }
    }
}

// Controlla immagini orfane
$imagesDir = realpath(__DIR__ . '/../images/components');
if ($imagesDir && is_dir($imagesDir)) {
    $imageFiles = glob($imagesDir . '/*.jpg');
    foreach ($imageFiles as $file) {
        $filename = basename($file);
        // Estrai l'ID dal nome del file (formato: ID.jpg)
        if (preg_match('/^(\d+)\.jpg$/', $filename, $matches)) {
            $fileId = intval($matches[1]);
            if (!isset($existingIdsMap[$fileId])) {
                $orphanImages[] = [
                    'filename' => $filename,
                    'path' => $file,
                    'size' => filesize($file),
                    'id' => $fileId
                ];
            }
        }
    }
}

// Controlla thumbnail orfane
$thumbsDir = realpath(__DIR__ . '/../images/components/thumbs');
if ($thumbsDir && is_dir($thumbsDir)) {
    $thumbFiles = glob($thumbsDir . '/*.jpg');
    foreach ($thumbFiles as $file) {
        $filename = basename($file);
        // Estrai l'ID dal nome del file (formato: ID.jpg)
        if (preg_match('/^(\d+)\.jpg$/', $filename, $matches)) {
            $fileId = intval($matches[1]);
            if (!isset($existingIdsMap[$fileId])) {
                $orphanThumbs[] = [
                    'filename' => $filename,
                    'path' => $file,
                    'size' => filesize($file),
                    'id' => $fileId
                ];
            }
        }
    }
}

// Funzione per formattare la dimensione
function formatSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

$success = '';
$error = '';

// Gestione eliminazione file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $filePath = $_POST['file_path'];
    // Verifica che il file sia nelle cartelle consentite
    $realPath = realpath($filePath);
    $allowedDirs = [
        realpath(__DIR__ . '/../datasheet'),
        realpath(__DIR__ . '/../images/components'),
        realpath(__DIR__ . '/../images/components/thumbs')
    ];
    
    $isAllowed = false;
    foreach ($allowedDirs as $dir) {
        if ($dir && strpos($realPath, $dir) === 0) {
            $isAllowed = true;
            break;
        }
    }
    
    if ($isAllowed && file_exists($realPath) && @unlink($realPath)) {
        $success = "File eliminato con successo.";
        // Ricarica la pagina per aggiornare la lista
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit;
    } else {
        $error = "Errore durante l'eliminazione del file.";
    }
}

if (isset($_GET['success'])) {
    $success = "File eliminato con successo.";
}

include '../includes/header.php';
?>

<div class="container py-4">
    <h2><i class="fa-solid fa-file-circle-question me-2"></i>File orfani</h2>
    <p class="text-muted">File presenti nel filesystem ma senza un componente corrispondente nel database.</p>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
        </div>
    <?php endif; ?>

    <!-- Datasheet orfani -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fa-solid fa-file-pdf me-2"></i>Datasheet orfani (<?= count($orphanDatasheets) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($orphanDatasheets)): ?>
                <p class="text-muted mb-0">Nessun datasheet orfano trovato.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nome file</th>
                                <th>Dimensione</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orphanDatasheets as $file): ?>
                                <tr>
                                    <td><?= $file['id'] ?></td>
                                    <td><?= htmlspecialchars($file['filename']) ?></td>
                                    <td><?= formatSize($file['size']) ?></td>
                                    <td class="text-end">
                                        <a href="/magazzino/datasheet/<?= htmlspecialchars($file['filename']) ?>" target="_blank" class="btn btn-sm btn-outline-info me-1" title="Visualizza">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Sei sicuro di voler eliminare questo file?');">
                                            <input type="hidden" name="file_path" value="<?= htmlspecialchars($file['path']) ?>">
                                            <button type="submit" name="delete_file" class="btn btn-sm btn-outline-danger" title="Elimina">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Immagini orfane -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fa-solid fa-image me-2"></i>Immagini orfane (<?= count($orphanImages) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($orphanImages)): ?>
                <p class="text-muted mb-0">Nessuna immagine orfana trovata.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nome file</th>
                                <th>Dimensione</th>
                                <th>Anteprima</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orphanImages as $file): ?>
                                <tr>
                                    <td><?= $file['id'] ?></td>
                                    <td><?= htmlspecialchars($file['filename']) ?></td>
                                    <td><?= formatSize($file['size']) ?></td>
                                    <td>
                                        <img src="/magazzino/images/components/thumbs/<?= htmlspecialchars($file['filename']) ?>?<?= time() ?>" alt="Thumb" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                    </td>
                                    <td class="text-end">
                                        <a href="/magazzino/images/components/<?= htmlspecialchars($file['filename']) ?>" target="_blank" class="btn btn-sm btn-outline-info me-1" title="Visualizza">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Sei sicuro di voler eliminare questa immagine?');">
                                            <input type="hidden" name="file_path" value="<?= htmlspecialchars($file['path']) ?>">
                                            <button type="submit" name="delete_file" class="btn btn-sm btn-outline-danger" title="Elimina">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Thumbnail orfane -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fa-solid fa-images me-2"></i>Thumbnail orfane (<?= count($orphanThumbs) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($orphanThumbs)): ?>
                <p class="text-muted mb-0">Nessuna thumbnail orfana trovata.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nome file</th>
                                <th>Dimensione</th>
                                <th>Anteprima</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orphanThumbs as $file): ?>
                                <tr>
                                    <td><?= $file['id'] ?></td>
                                    <td><?= htmlspecialchars($file['filename']) ?></td>
                                    <td><?= formatSize($file['size']) ?></td>
                                    <td>
                                        <img src="/magazzino/images/components/thumbs/<?= htmlspecialchars($file['filename']) ?>?<?= time() ?>" alt="Thumb" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                    </td>
                                    <td class="text-end">
                                        <a href="/magazzino/images/components/thumbs/<?= htmlspecialchars($file['filename']) ?>" target="_blank" class="btn btn-sm btn-outline-info me-1" title="Visualizza">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Sei sicuro di voler eliminare questa thumbnail?');">
                                            <input type="hidden" name="file_path" value="<?= htmlspecialchars($file['path']) ?>">
                                            <button type="submit" name="delete_file" class="btn btn-sm btn-outline-danger" title="Elimina">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <a href="components.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Torna ai componenti</a>
</div>

<?php include '../includes/footer.php'; ?>