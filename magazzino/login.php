<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:44:57 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-07 14:32:06
*/
// 2026-01-04: Aggiunta opzione "Ricordami" nel login

session_start();
require 'includes/db_connect.php';

// Pulisce eventuali token scaduti (silenzioso se la tabella non esiste ancora)
try {
  $pdo->prepare("DELETE FROM remember_tokens WHERE expires < NOW()")->execute();
} catch (Exception $e) {
  // Ignora errori (es. tabella non ancora presente)
}
// Determina se siamo su HTTPS per impostare il flag `secure` del cookie
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

// Se già connesso, reindirizza a index.php
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: /magazzino/index.php');
    exit;
}

// Controlla se esiste un cookie di ricordo valido dalla tabella remember_tokens
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.role, rt.id AS token_id FROM remember_tokens rt
                           INNER JOIN users u ON rt.user_id = u.id
                           WHERE rt.token = ? AND rt.expires > NOW()");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    
    if ($row) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        // Refresh il token per estendere la scadenza (1 anno)
        $new_token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 year'));
        $stmt = $pdo->prepare("UPDATE remember_tokens SET token = ?, expires = ? WHERE id = ?");
        $stmt->execute([$new_token, $expires, $row['token_id']]);
        setcookie('remember_token', $new_token, strtotime('+1 year'), '/', '', $secure, true);
    }
}


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
        
        // Gestisci il checkbox "Ricordami"
        if (isset($_POST['remember_me']) && $_POST['remember_me'] === 'on') {
          $token = bin2hex(random_bytes(32));
          $expires = date('Y-m-d H:i:s', strtotime('+1 year'));
          $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires, created_at) VALUES (?, ?, ?, NOW())");
          $stmt->execute([$user['id'], $token, $expires]);
          setcookie('remember_token', $token, strtotime('+1 year'), '/', '', $secure, true);
        }
        
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
    <div class="mb-3 form-check">
      <input type="checkbox" name="remember_me" class="form-check-input" id="rememberMe">
      <label class="form-check-label" for="rememberMe">Ricordami</label>
    </div>

    <button class="btn btn-primary w-100">Accedi</button>
  </form>
</div>
<?php include 'includes/footer.php'; ?>