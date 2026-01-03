<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:44:57 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-21 10:16:49
*/


session_start();
require 'includes/db_connect.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && hash('sha256', $password) === $user['password_hash']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header('Location: index.php');
        exit;
    } else {
        $error = "Credenziali non valide";
    }
}
?>

<?php include 'includes/header.php'; ?>
<div class="container mt-5" style="max-width:400px;">
  <h3 class="text-center mb-4">Accesso Magazzino</h3>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Username</label>
      <input type="text" name="username" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button class="btn btn-primary w-100">Accedi</button>
  </form>
</div>
<?php include 'includes/footer.php'; ?>