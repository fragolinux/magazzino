<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-08
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-08
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Recupero messaggi da sessione
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Recupero locali con conteggio posizioni e componenti
$locali = $pdo->query("
    SELECT l.*,
           (SELECT COUNT(*) FROM locations loc WHERE loc.locale_id = l.id) AS location_count,
           (SELECT COUNT(*) FROM components comp 
                JOIN compartments cmp ON comp.compartment_id = cmp.id
                JOIN locations loc ON cmp.location_id = loc.id
                WHERE loc.locale_id = l.id) AS component_count
    FROM locali l
    ORDER BY l.name ASC
")->fetchAll();

include '../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa-solid fa-building me-2"></i>Locali</h2>
    <a href="add_locale.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Aggiungi locale</a>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-striped table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>Nome Locale</th>
          <th>Descrizione</th>
          <th>Posizioni</th>
          <th>Componenti</th>
          <th class="text-end">Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$locali): ?>
        <tr>
          <td colspan="5" class="text-center text-muted">Nessun locale presente.</td>
        </tr>
        <?php else: ?>
        <?php foreach ($locali as $loc): ?>
        <tr>
          <td><strong><?= htmlspecialchars($loc['name']) ?></strong></td>
          <td><?= htmlspecialchars($loc['description'] ?? '') ?></td>
          <td><?= $loc['location_count'] ?></td>
          <td><?= $loc['component_count'] ?></td>
          <td class="text-end">
            <a href="edit_locale.php?id=<?= $loc['id'] ?>" class="btn btn-sm btn-outline-secondary me-1"
              title="Modifica locale">
              <i class="fa-solid fa-pen"></i>
            </a>
            <a href="delete_locale.php?id=<?= $loc['id'] ?>" class="btn btn-sm btn-outline-danger"
              onclick="return confirm('Sei sicuro di eliminare il locale <?= htmlspecialchars($loc['name'], ENT_QUOTES) ?>?<?= $loc['location_count'] > 0 ? '\n\nATTENZIONE: Ci sono ' . $loc['location_count'] . ' posizioni collegate che verranno scollegate.' : '' ?>');"
              title="Elimina locale">
              <i class="fa-solid fa-trash"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
