<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-08
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-08
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

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
            // Aggiornamento
            $stmt = $pdo->prepare("UPDATE locali SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $id]);
            
            $_SESSION['success'] = "Locale \"{$name}\" modificato con successo.";
            header("Location: locali.php");
            exit;
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
      <form method="post">
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

        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-save me-1"></i>Salva modifiche
        </button>
        <a href="locali.php" class="btn btn-secondary">Annulla</a>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
