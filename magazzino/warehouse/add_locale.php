<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-08
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-08
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$error = '';

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
            $stmt = $pdo->prepare("INSERT INTO locali (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            
            $_SESSION['success'] = "Locale \"{$name}\" aggiunto con successo.";
            header("Location: locali.php");
            exit;
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
      <form method="post">
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

        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-save me-1"></i>Salva locale
        </button>
        <a href="locali.php" class="btn btn-secondary">Annulla</a>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
