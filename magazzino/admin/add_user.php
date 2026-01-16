<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:04:40 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-15
*/

require '../includes/auth_check.php';
require '../includes/db_connect.php';
require '../includes/csrf.php';

if ($_SESSION['role'] !== 'admin') {
    die("Accesso negato.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Token di sicurezza non valido. Ricarica la pagina e riprova.";
    } else {
        $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if ($username === '' || $password === '') {
        $error = "Username e password sono obbligatori.";
    } else {
        // Controllo se esiste già
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username già utilizzato.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt2 = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
            $stmt2->execute([$username, $password_hash, $role]);
            $success = "Utente creato con successo.";
        }
    }
    } // Chiude il blocco else della verifica CSRF
}

include '../includes/header.php';
?>
<h4>Aggiungi nuovo utente</h4>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php elseif ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="post">
  <div class="mb-3">
    <label class="form-label">Username</label>
    <input type="text" name="username" class="form-control" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Password</label>
    <input type="password" name="password" class="form-control" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Ruolo</label>
    <select name="role" class="form-select">
      <option value="user">User</option>
      <option value="admin">Admin</option>
    </select>
  </div>
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
  <button type="submit" class="btn btn-primary">Crea utente</button>
</form>

<?php
include '../includes/footer.php';
?>