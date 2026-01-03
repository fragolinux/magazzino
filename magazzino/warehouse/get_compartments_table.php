<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-21 09:36:53 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2025-10-21 09:36:53
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$location_id = intval($_GET['location_id'] ?? 0);
$order = $_GET['order'] ?? 'code';

if (!$location_id) {
    echo '<div class="alert alert-info">Seleziona una posizione per visualizzare i compartimenti.</div>';
    exit;
}

$order_sql = $order === 'id' ? 'cmp.id DESC' : 'LENGTH(cmp.code), cmp.code ASC';

$stmt = $pdo->prepare("
    SELECT cmp.*, loc.name AS location_name,
           (SELECT COUNT(*) FROM components c WHERE c.compartment_id = cmp.id) AS component_count
    FROM compartments cmp
    LEFT JOIN locations loc ON cmp.location_id = loc.id
    WHERE cmp.location_id = ?
    ORDER BY $order_sql
");
$stmt->execute([$location_id]);
$compartments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$compartments) {
    echo '<div class="alert alert-secondary">Nessun compartimento presente in questa posizione.</div>';
    exit;
}
?>

<div class="table-responsive">
  <table class="table table-striped table-sm align-middle">
    <thead class="table-light">
      <tr>
        <th>Codice</th>
        <th>Posizione</th>
        <th>Componenti</th>
        <th class="text-end">Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($compartments as $cmp): ?>
      <tr>
        <td><?= htmlspecialchars($cmp['code']) ?></td>
        <td><?= htmlspecialchars($cmp['location_name']) ?></td>
        <td><?= $cmp['component_count'] ?></td>
        <td class="text-end">
          <a href="edit_compartment.php?id=<?= $cmp['id'] ?>&location_id=<?= $location_id ?>" class="btn btn-sm btn-outline-secondary" title="Modifica">
            <i class="fa-solid fa-pen"></i>
          </a>
          <a href="delete_compartment.php?id=<?= $cmp['id'] ?>&location_id=<?= $location_id ?>" class="btn btn-sm btn-outline-danger"
             onclick="return confirm('Sei sicuro di eliminare <?= htmlspecialchars($cmp['code'], ENT_QUOTES) ?>?');" title="Elimina">
            <i class="fa-solid fa-trash"></i>
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>