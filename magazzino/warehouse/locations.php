<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:25:22 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-23 18:26:06
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Recupero messaggi da sessione
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Recupero posizioni con conteggio comparti e componenti
$locations = $pdo->query("
    SELECT loc.*,
           (SELECT COUNT(*) FROM compartments c WHERE c.location_id = loc.id) AS compartment_count,
           (SELECT COUNT(*) FROM components comp 
                JOIN compartments c ON comp.compartment_id = c.id
                WHERE c.location_id = loc.id) AS component_count
    FROM locations loc
    ORDER BY loc.name ASC
")->fetchAll();

include '../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa-solid fa-map-location-dot me-2"></i>Posizioni</h2>
    <a href="add_location.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Aggiungi posizione</a>
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
          <th>Nome Posizione</th>
          <th>Comparti</th>
          <th>Componenti</th>
          <th class="text-end">Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$locations): ?>
        <tr>
          <td colspan="4" class="text-center text-muted">Nessuna posizione presente.</td>
        </tr>
        <?php else: ?>
        <?php foreach ($locations as $loc): ?>
        <tr>
          <td><?= htmlspecialchars($loc['name']) ?></td>
          <td><?= $loc['compartment_count'] ?></td>
          <td><?= $loc['component_count'] ?></td>
          <td class="text-end">
            <a href="add_compartment.php?location_id=<?= $loc['id'] ?>" class="btn btn-sm btn-success me-1"
              title="Aggiungi comparto">
              <i class="fa-solid fa-square-plus"></i>
            </a>
            <a href="edit_location.php?id=<?= $loc['id'] ?>" class="btn btn-sm btn-outline-secondary me-1"
              title="Modifica posizione">
              <i class="fa-solid fa-pen"></i>
            </a>
            <a href="delete_location.php?id=<?= $loc['id'] ?>" class="btn btn-sm btn-outline-danger"
              onclick="return confirm('Sei sicuro di eliminare <?= htmlspecialchars($loc['name'], ENT_QUOTES) ?>?');"
              title="Elimina posizione">
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