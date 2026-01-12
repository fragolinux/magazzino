<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-12
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-12
*/
// Pagina profilo utente - gestione password e impostazioni personali

require 'includes/auth_check.php';
require 'includes/db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';

    if (empty($newPassword)) {
        $error = "Il campo password è obbligatorio.";
    } elseif (strlen($newPassword) < 8) {
        $error = "La password deve contenere almeno 8 caratteri.";
    } else {
        // Aggiorna la password
        $newPasswordHash = hash('sha256', $newPassword);
        $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmtUpdate->execute([$newPasswordHash, $_SESSION['user_id']]);

        $success = "Password modificata con successo!";
        // Reset dei campi
        $_POST = [];
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fa-solid fa-user-gear me-2"></i>Profilo Utente</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="new_password" 
                               name="new_password" 
                               required
                               minlength="8"
                               autocomplete="off">
                        <div class="form-text">La password deve contenere almeno 8 caratteri.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-save me-2"></i>Salva Nuova Password
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fa-solid fa-times me-2"></i>Annulla
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="mt-3 text-muted">
            <small>
                <i class="fa-solid fa-info-circle me-1"></i>
                <strong>Nota:</strong> Dopo aver cambiato la password, dovrai effettuare nuovamente il login con le nuove credenziali.
            </small>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>