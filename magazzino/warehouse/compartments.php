<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:28:47 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2026-03-28 17:11:42
*/

// 2026-02-01: aggliunto locale nella select delle posizioni
// 2026-03-02: corretto bug bottone "Mostra tutte" che non resettava il filtro posizione
// 2026-03-28: corretto bug che non permetteva di aprire il popup quando venivano selezionati tutti i comparti
// 2026-03-28: corretto bug ordinamento che non era alfanumerico

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Messaggi da sessione
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Recupero posizioni
$locations = $pdo->query("SELECT l.id, l.name, loc.name AS locale_name FROM locations l LEFT JOIN locali loc ON l.locale_id = loc.id ORDER BY l.name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Parametri GET
$location_id = isset($_GET['location_id']) && is_numeric($_GET['location_id']) ? intval($_GET['location_id']) : null;
$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';
$order = $_GET['order'] ?? 'code'; // default: ordina per codice

$compartments = [];
if ($location_id || $show_all) {
  // Recupero comparti (tutti o filtrati per posizione)
  $order_sql = $order === 'id' ? 'cmp.id DESC' : 'LENGTH(cmp.code), cmp.code ASC';
  $where_sql = $location_id ? "WHERE cmp.location_id = ?" : "";
  $params = $location_id ? [$location_id] : [];

  $stmt = $pdo->prepare("
      SELECT cmp.*, loc.name AS location_name,
             (SELECT COUNT(*) FROM components c WHERE c.compartment_id = cmp.id) AS component_count
      FROM compartments cmp
      LEFT JOIN locations loc ON cmp.location_id = loc.id
      $where_sql
      " . ($order === 'id' ? "ORDER BY $order_sql" : "") . "
  ");
  $stmt->execute($params);
  $compartments = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Ordinamento naturale per codice se richiesto
  if ($order === 'code') {
    usort($compartments, function ($a, $b) {
      return strnatcasecmp($a['code'], $b['code']);
    });
  }
}

include '../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa-solid fa-boxes-stacked me-2"></i>Comparti</h2>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <!-- Filtro posizione e ordinamento -->
  <form method="get" id="filterForm" class="row g-2 align-items-center mb-3">
    <?php if ($show_all): ?>
      <input type="hidden" name="show_all" value="1">
    <?php endif; ?>
    <div class="col-md-5 col-lg-4">
      <select name="location_id" id="locationSelect" class="form-select" onchange="this.form.submit()">
        <option value="">-- Seleziona una posizione --</option>
        <?php foreach ($locations as $loc): ?>
          <option value="<?= $loc['id'] ?>" <?= ($location_id == $loc['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($loc['name']) ?> - <?= htmlspecialchars($loc['locale_name'] ?? 'Senza locale') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3 col-lg-2">
      <select name="order" id="orderSelect" class="form-select" onchange="this.form.submit()">
        <option value="code" <?= $order === 'code' ? 'selected' : '' ?>>Ordina per codice</option>
        <option value="id" <?= $order === 'id' ? 'selected' : '' ?>>Ultimi inseriti</option>
      </select>
    </div>
    <div class="col-auto">
      <?php if ($location_id || $show_all): ?>
        <a href="compartments.php" class="btn btn-outline-secondary">Reset</a>
      <?php endif; ?>
      <?php if (!$show_all): ?>
        <a href="compartments.php?show_all=1" class="btn btn-outline-primary">Mostra tutte</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($location_id): ?>
    <!-- Inserimento rapido -->
    <div class="card p-3 mb-3 shadow-sm">
      <form id="quickAddForm" class="row g-2 align-items-end">
        <div class="col-md-4 col-lg-3">
          <label class="form-label mb-1">Codice comparto</label>
          <input type="text" name="code" id="newCode" class="form-control" placeholder="Es. A12" required autofocus>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-success">
            <i class="fa-solid fa-plus"></i> Aggiungi
          </button>
        </div>
      </form>
    </div>

  <?php endif; ?>

  <!-- Tabella comparti -->
  <div id="tableContainer">
    <?php if (!$location_id && !$show_all): ?>
      <div class="alert alert-info">
        <i class="fa-solid fa-info-circle me-2"></i>Seleziona una posizione o clicca su "Mostra tutte" per visualizzare i comparti.
      </div>
    <?php elseif (!$compartments): ?>
      <div class="alert alert-secondary">Nessun comparto trovato.</div>
    <?php else: ?>
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
            <tbody id="compartmentsTable">
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
                    <a href="edit_compartment.php?id=<?= $cmp['id'] ?>&location_id=<?= $cmp['location_id'] ?>"
                      class="btn btn-sm btn-outline-secondary" title="Modifica">
                      <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="delete_compartment.php?id=<?= $cmp['id'] ?>&location_id=<?= $cmp['location_id'] ?>"
                      class="btn btn-sm btn-outline-danger"
                      onclick="return confirm('Sei sicuro di eliminare <?= htmlspecialchars($cmp['code'], ENT_QUOTES) ?>?');"
                      title="Elimina">
                      <i class="fa-solid fa-trash"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>

          </table>
        </div>
      <?php endif; ?>
  </div>
</div>

<!-- Modal componenti comparto -->
<div class="modal fade" id="componentsModal" tabindex="-1" aria-labelledby="componentsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="componentsModalLabel">Componenti comparto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body" id="componentsModalBody">
        Caricamento...
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('quickAddForm');
    if (form) {
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      const code = document.getElementById('newCode').value.trim();
      if (!code) return;

      const response = await fetch('ajax_add_compartment.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          location_id: '<?= $location_id ?>',
          code: code
        })
      });

      const data = await response.json();
      if (data.success) {
        document.getElementById('newCode').value = '';
        // Imposta ordinamento su "Ultimi inseriti" e aggiorna tabella
        document.getElementById('orderSelect').value = 'id';
        refreshTable();
      } else {
        alert(data.error || 'Errore durante l’aggiunta.');
      }
    });
    }

    async function refreshTable() {
      const loc = document.getElementById('locationSelect').value;
      const order = document.getElementById('orderSelect').value;
      const showAll = '<?= $show_all ? "1" : "0" ?>';
      const response = await fetch('get_compartments_table.php?location_id=' + loc + '&order=' + order + '&show_all=' + showAll);
      const html = await response.text();
      document.getElementById('tableContainer').innerHTML = html;
    }

    // Delegazione eventi per click su numero componenti (gestisce anche righe aggiunte via AJAX)
    document.addEventListener('click', async function(e) {
      const link = e.target.closest('.view-components-link');
      if (link) {
        e.preventDefault();
        const compId = link.dataset.id;
        const compCode = link.dataset.code;
        const modalTitle = document.getElementById('componentsModalLabel');
        const modalBody = document.getElementById('componentsModalBody');

        modalTitle.textContent = `Componenti comparto ${compCode}`;
        modalBody.innerHTML = 'Caricamento...';

        const modalElement = document.getElementById('componentsModal');
        const modal = new bootstrap.Modal(modalElement);
        modal.show();

        const response = await fetch(`get_compartment_components.php?compartment_id=${compId}`);
        const html = await response.text();
        modalBody.innerHTML = html;
      }
    });


  });
</script>

<?php include '../includes/footer.php'; ?>