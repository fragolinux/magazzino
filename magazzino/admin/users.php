<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:46:41 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-20 20:00:12
*/

require '../includes/auth_check.php';
require '../includes/db_connect.php';

if ($_SESSION['role'] !== 'admin') {
    die("Accesso negato");
}

// Eliminazione utente (se richiesta)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Impedisci all'admin corrente di cancellarsi
    if ($id === intval($_SESSION['user_id'])) {
        $error = "Non puoi eliminare te stesso.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Utente eliminato con successo.";
    }
}

// Recupera elenco utenti
$users = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY id ASC")->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">
  <h4>Gestione Utenti</h4>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php elseif (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <a href="add_user.php" class="btn btn-success mb-3">
    <i class="fa fa-plus"></i> Aggiungi utente
  </a>

  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Ruolo</th>
        <th>Creato</th>
        <th class="text-end">Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= $u['role'] ?></td>
          <td><?= $u['created_at'] ?></td>
          <td class="text-end">
            <a href="edit_user.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-primary me-1">
              <i class="fa fa-pen"></i>
            </a>
            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
              <a href="?delete=<?= $u['id'] ?>"
                 class="btn btn-sm btn-danger"
                 onclick="return confirm('Sei sicuro di voler eliminare questo utente?');">
                <i class="fa fa-trash"></i>
              </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include '../includes/footer.php'; ?>