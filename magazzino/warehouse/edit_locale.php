<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-08
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-03-02
*/

// 2026-03-02: aggiunto file pdf al locale

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';
require_once '../includes/secure_upload.php';

// Controllo parametro ID valido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: locali.php");
    exit;
}

$id = intval($_GET['id']);

// Recupero locale
$stmt = $pdo->prepare("SELECT * FROM locali WHERE id = ?");
$stmt->execute([$id]);
$locale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$locale) {
    $_SESSION['error'] = "Locale non trovato.";
    header("Location: locali.php");
    exit;
}

$error = '';

// Modifica locale
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error = 'Il nome del locale è obbligatorio.';
    } else {
        // Verifica duplicati (escluso questo)
        $stmt = $pdo->prepare("SELECT id FROM locali WHERE name = ? AND id != ?");
        $stmt->execute([$name, $id]);
        if ($stmt->fetch()) {
            $error = 'Esiste già un locale con questo nome.';
        } else {
            // Aggiornamento base
            $stmt = $pdo->prepare("UPDATE locali SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $id]);
            
            // Gestione eliminazione PDF
            if (isset($_POST['delete_pdf']) && $locale['pdf_filename']) {
                $pdf_path = realpath(__DIR__ . '/../uploads/locali/' . basename($locale['pdf_filename']));
                $expected_dir = realpath(__DIR__ . '/../uploads/locali/');
                
                if ($pdf_path && strpos($pdf_path, $expected_dir) === 0 && file_exists($pdf_path)) {
                    @unlink($pdf_path); // Elimina il file PDF
                }
                
                // Aggiorna il record rimuovendo il riferimento al file
                $stmt = $pdo->prepare("UPDATE locali SET pdf_filename = NULL WHERE id = ?");
                $stmt->execute([$id]);
            }
            
            // Gestione upload nuovo PDF
            if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                $validator = new SecureUploadValidator('../uploads/locali/', 5 * 1024 * 1024); // 5MB max
                $validation = $validator->validateUpload($_FILES['pdf_file'], ['application/pdf']);
                
                if ($validation['valid']) {
                    $filename = "locale_{$id}.pdf";
                    $result = $validator->saveValidatedFile($_FILES['pdf_file'], $validation, $filename);
                    
                    if ($result['success']) {
                        // Aggiorna il record con il nome del file
                        $stmt = $pdo->prepare("UPDATE locali SET pdf_filename = ? WHERE id = ?");
                        $stmt->execute([$filename, $id]);
                        
                        // Elimina vecchio PDF se esisteva (e non è stato già eliminato)
                        if ($locale['pdf_filename'] && $locale['pdf_filename'] !== $filename && !isset($_POST['delete_pdf'])) {
                            $old_pdf_path = realpath(__DIR__ . '/../uploads/locali/' . basename($locale['pdf_filename']));
                            $expected_dir = realpath(__DIR__ . '/../uploads/locali/');
                            if ($old_pdf_path && strpos($old_pdf_path, $expected_dir) === 0 && file_exists($old_pdf_path)) {
                                @unlink($old_pdf_path);
                            }
                        }
                    } else {
                        $error = 'Errore durante il salvataggio del PDF: ' . $result['error'];
                    }
                } else {
                    $error = 'File PDF non valido: ' . implode(', ', $validation['errors']);
                }
            }
            
            if (empty($error)) {
                $_SESSION['success'] = "Locale \"{$name}\" modificato con successo.";
                header("Location: locali.php");
                exit;
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa-solid fa-building me-2"></i>Modifica locale</h2>
    <a href="locali.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Torna alla lista</a>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm" style="max-width: 600px;">
    <div class="card-body">
      <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
          <label for="name" class="form-label">Nome locale *</label>
          <input type="text" id="name" name="name" class="form-control" 
                 value="<?= htmlspecialchars($_POST['name'] ?? $locale['name']) ?>" 
                 required autofocus>
        </div>
        
        <div class="mb-3">
          <label for="description" class="form-label">Descrizione</label>
          <textarea id="description" name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? $locale['description'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
          <label for="pdf_file" class="form-label">Allega PDF (mappa/info locale)</label>
          <input type="file" id="pdf_file" name="pdf_file" class="form-control" 
                 accept=".pdf,application/pdf">
          <div class="form-text">File PDF opzionale con mappa o informazioni sul locale (max 5MB). Se presente, sostituirà il PDF esistente.</div>
          <?php if ($locale['pdf_filename']): ?>
            <div class="mt-2">
              <a href="download_pdf_locale.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary me-2" target="_blank">
                <i class="fa-solid fa-file-pdf me-1"></i>Visualizza PDF attuale
              </a>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="delete_pdf" id="delete_pdf" value="1">
                <label class="form-check-label" for="delete_pdf">
                  <i class="fa-solid fa-trash text-danger me-1"></i>Elimina PDF attuale
                </label>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-save me-1"></i>Salva modifiche
        </button>
        <a href="locali.php" class="btn btn-secondary">Annulla</a>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
