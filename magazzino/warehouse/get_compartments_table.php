<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-21 09:36:53 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2026-03-02
*/

// 2026-03-02: corretto bug bottone "Mostra tutte" che non resettava il filtro posizione

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$location_id = isset($_GET['location_id']) && is_numeric($_GET['location_id']) ? intval($_GET['location_id']) : null;
$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';
$order = $_GET['order'] ?? 'code';

if (!$location_id && !$show_all) {
    echo '<div class="alert alert-info">Seleziona una posizione o clicca su "Mostra tutte" per visualizzare i comparti.</div>';
    exit;
}

$order_sql = $order === 'id' ? 'cmp.id DESC' : 'LENGTH(cmp.code), cmp.code ASC';
$where_sql = $location_id ? "WHERE cmp.location_id = ?" : "";
$params = $location_id ? [$location_id] : [];

$stmt = $pdo->prepare("
    SELECT cmp.*, loc.name AS location_name,
           (SELECT COUNT(*) FROM components c WHERE c.compartment_id = cmp.id) AS component_count
    FROM compartments cmp
    LEFT JOIN locations loc ON cmp.location_id = loc.id
    $where_sql
    ORDER BY $order_sql
");
$stmt->execute($params);
$compartments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$compartments) {
    echo '<div class="alert alert-secondary">Nessun compartimento trovato.</div>';
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
        <td>
          <a href="#" class="view-components-link" data-id="<?= $cmp['id'] ?>"
            data-code="<?= htmlspecialchars($cmp['code'], ENT_QUOTES) ?>">
            <?= $cmp['component_count'] ?>
          </a>
        </td>
        <td class="text-end">
          <a href="edit_compartment.php?id=<?= $cmp['id'] ?>&location_id=<?= $cmp['location_id'] ?>" class="btn btn-sm btn-outline-secondary" title="Modifica">
            <i class="fa-solid fa-pen"></i>
          </a>
          <a href="delete_compartment.php?id=<?= $cmp['id'] ?>&location_id=<?= $cmp['location_id'] ?>" class="btn btn-sm btn-outline-danger"
             onclick="return confirm('Sei sicuro di eliminare <?= htmlspecialchars($cmp['code'], ENT_QUOTES) ?>?');" title="Elimina">
            <i class="fa-solid fa-trash"></i>
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>