<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:10:52 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-15
*/
// 2026-01-12: Permetti ad un admin di modificare i propri dati senza perdere i privilegi

require '../includes/auth_check.php';
require '../includes/db_connect.php';
require '../includes/csrf.php';

if ($_SESSION['role'] !== 'admin') {
    die("Accesso negato.");
}

// ID utente da modificare
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID utente non valido.");
}
$id = intval($_GET['id']);

// Recupera dati utente
$stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    die("Utente non trovato.");
}

$error = '';
$success = '';

// Se si tenta di modificare sé stessi
$isSelfEdit = ($id === intval($_SESSION['user_id']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Token di sicurezza non valido. Ricarica la pagina e riprova.";
    } else {
        $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Se è self-edit, mantieni il ruolo esistente, altrimenti prendi quello dal POST
    if ($isSelfEdit) {
        $role = $user['role']; // Mantieni il ruolo corrente
    } else {
        $role = $_POST['role'] ?? 'user';
    }

    if ($username === '') {
        $error = "Il campo username è obbligatorio.";
    } elseif ($password !== '' && strlen($password) < 8) {
        $error = "La password deve contenere almeno 8 caratteri.";
    } else {
        // Controlla se l'username è già usato da un altro utente
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmtCheck->execute([$username, $id]);
        if ($stmtCheck->fetch()) {
            $error = "Questo username è già utilizzato da un altro utente.";
        } else {
            // Aggiornamento query dinamico
            if ($password !== '') {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtUpdate = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, role = ? WHERE id = ?");
                $stmtUpdate->execute([$username, $password_hash, $role, $id]);
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $stmtUpdate->execute([$username, $role, $id]);
            }

            $success = "Dati utente aggiornati con successo.";
            // Aggiorna i dati mostrati
            $user['username'] = $username;
            $user['role'] = $role;

            // Se l'admin ha cambiato il proprio username, aggiorna la sessione
            if ($isSelfEdit && $username !== $_SESSION['username']) {
                $_SESSION['username'] = $username;
            }
        }
    }
    } // Chiude il blocco else della verifica CSRF
}

include '../includes/header.php';
?>

<div class="container mt-4">
  <h4>Modifica utente</h4>

  <a href="users.php" class="btn btn-secondary btn-sm mb-3">
    <i class="fa fa-arrow-left"></i> Torna alla lista
  </a>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php elseif ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="mb-3">
      <label class="form-label">Username</label>
      <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Nuova password min. 8 caratteri (lascia vuoto per non cambiare)</label>
      <input type="text" name="password" class="form-control">
    </div>

    <div class="mb-3">
      <label class="form-label">Ruolo</label>
      <select name="role" class="form-select" <?= $isSelfEdit ? 'disabled' : '' ?>>
        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
      </select>
      <?php if ($isSelfEdit): ?>
        <div class="form-text text-muted">Non puoi modificare il tuo ruolo mentre sei loggato.</div>
      <?php endif; ?>
    </div>

    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

    <button type="submit" class="btn btn-primary">
      <i class="fa fa-save"></i> Salva modifiche
    </button>
  </form>
</div>

<?php include '../includes/footer.php'; ?>