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

$error = '';
$success = '';

// Aggiunta locale
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error = 'Il nome del locale è obbligatorio.';
    } else {
        // Verifica duplicati
        $stmt = $pdo->prepare("SELECT id FROM locali WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $error = 'Esiste già un locale con questo nome.';
        } else {
            // Inserimento
            $stmt = $pdo->prepare("INSERT INTO locali (name, description, pdf_filename) VALUES (?, ?, NULL)");
            $stmt->execute([$name, $description]);
            
            $locale_id = $pdo->lastInsertId();
            
            // Gestione upload PDF
            if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                $validator = new SecureUploadValidator('../uploads/locali/', 5 * 1024 * 1024); // 5MB max
                $validation = $validator->validateUpload($_FILES['pdf_file'], ['application/pdf']);
                
                if ($validation['valid']) {
                    $filename = "locale_{$locale_id}.pdf";
                    $result = $validator->saveValidatedFile($_FILES['pdf_file'], $validation, $filename);
                    
                    if ($result['success']) {
                        // Aggiorna il record con il nome del file
                        $stmt = $pdo->prepare("UPDATE locali SET pdf_filename = ? WHERE id = ?");
                        $stmt->execute([$filename, $locale_id]);
                        
                        $success = "Locale \"{$name}\" aggiunto con successo. PDF allegato salvato.";
                    } else {
                        $error = 'Errore durante il salvataggio del PDF: ' . $result['error'];
                    }
                } else {
                    $error = 'File PDF non valido: ' . implode(', ', $validation['errors']);
                }
            } else if ($_FILES['pdf_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $error = 'Errore durante l\'upload del PDF.';
            } else {
                $success = "Locale \"{$name}\" aggiunto con successo.";
            }
            
            if (empty($error)) {
                $_SESSION['success'] = $success;
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
    <h2><i class="fa-solid fa-building me-2"></i>Aggiungi locale</h2>
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
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                 placeholder="Es. Studio, Garage, Sede remota" required autofocus>
        </div>
        
        <div class="mb-3">
          <label for="description" class="form-label">Descrizione</label>
          <textarea id="description" name="description" class="form-control" rows="3"
                    placeholder="Descrizione opzionale del locale"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
          <label for="pdf_file" class="form-label">Allega PDF (mappa/info locale)</label>
          <input type="file" id="pdf_file" name="pdf_file" class="form-control" 
                 accept=".pdf,application/pdf">
          <div class="form-text">File PDF opzionale con mappa o informazioni sul locale (max 5MB)</div>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-save me-1"></i>Salva locale
        </button>
        <a href="locali.php" class="btn btn-secondary">Annulla</a>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
