<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:04:40 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-20 20:00:41
*/

require '../includes/auth_check.php';
require '../includes/db_connect.php';

if ($_SESSION['role'] !== 'admin') {
    die("Accesso negato.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $password_hash = hash('sha256', $password);
            $stmt2 = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
            $stmt2->execute([$username, $password_hash, $role]);
            $success = "Utente creato con successo.";
        }
    }
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
  <button type="submit" class="btn btn-primary">Crea utente</button>
</form>

<?php
include '../includes/footer.php';
?>