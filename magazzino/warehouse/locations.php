<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:25:22 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-08 18:26:06
*/
// 2026-01-08: Aggiunto filtro per locale


require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Recupero messaggi da sessione
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Recupero locali per il filtro
$locali = $pdo->query("SELECT id, name FROM locali ORDER BY name ASC")->fetchAll();

// Filtro per locale
$filter_locale_id = isset($_GET['locale_id']) && is_numeric($_GET['locale_id']) ? intval($_GET['locale_id']) : null;

// Recupero posizioni con conteggio comparti e componenti
$query = "
    SELECT loc.*,
           l.name AS locale_name,
           (SELECT COUNT(*) FROM compartments c WHERE c.location_id = loc.id) AS compartment_count,
           (SELECT COUNT(*) FROM components comp 
                JOIN compartments c ON comp.compartment_id = c.id
                WHERE c.location_id = loc.id) AS component_count
    FROM locations loc
    LEFT JOIN locali l ON loc.locale_id = l.id";

if ($filter_locale_id) {
    $query .= " WHERE loc.locale_id = :locale_id";
}

$query .= " ORDER BY loc.name ASC";

$stmt = $pdo->prepare($query);
if ($filter_locale_id) {
    $stmt->bindValue(':locale_id', $filter_locale_id, PDO::PARAM_INT);
}
$stmt->execute();
$locations = $stmt->fetchAll();

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

  <!-- Filtro per locale -->
  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-4">
          <label for="filter-locale" class="form-label">Filtra per locale</label>
          <select id="filter-locale" name="locale_id" class="form-select" onchange="this.form.submit()">
            <option value="">-- Tutti i locali --</option>
            <?php foreach ($locali as $loc): ?>
              <option value="<?= $loc['id'] ?>" <?= $filter_locale_id == $loc['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($loc['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($filter_locale_id): ?>
          <div class="col-md-4 d-flex align-items-end">
            <a href="locations.php" class="btn btn-secondary">
              <i class="fa-solid fa-xmark me-1"></i>Rimuovi filtro
            </a>
          </div>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>Nome Posizione</th>
          <th>Locale</th>
          <th>Comparti</th>
          <th>Componenti</th>
          <th class="text-end">Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$locations): ?>
        <tr>
          <td colspan="5" class="text-center text-muted">Nessuna posizione presente.</td>
        </tr>
        <?php else: ?>
        <?php foreach ($locations as $loc): ?>
        <tr>
          <td><?= htmlspecialchars($loc['name']) ?></td>
          <td>
            <?php if ($loc['locale_name']): ?>
              <span class="badge bg-info"><?= htmlspecialchars($loc['locale_name']) ?></span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
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