<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:28:47 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-01 21:14:26
*/

// 2026-02-01: aggliunto locale nella select delle posizioni

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
$order = $_GET['order'] ?? 'code'; // default: ordina per codice

// Se selezionata una posizione
if ($location_id) {
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
} else {
  $compartments = [];
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

    <?php if ($location_id): ?>
      <div class="col-md-3 col-lg-2">
        <select name="order" id="orderSelect" class="form-select" onchange="this.form.submit()">
          <option value="code" <?= $order === 'code' ? 'selected' : '' ?>>Ordina per codice</option>
          <option value="id" <?= $order === 'id' ? 'selected' : '' ?>>Ultimi inseriti</option>
        </select>
      </div>
      <div class="col-auto">
        <a href="compartments.php" class="btn btn-outline-secondary">Mostra tutte</a>
      </div>
    <?php endif; ?>
  </form>

  <?php if (!$location_id): ?>
    <div class="alert alert-info">
      <i class="fa-solid fa-info-circle me-2"></i>Seleziona una posizione per visualizzare o aggiungere comparti.
    </div>

  <?php else: ?>
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

    <!-- Tabella comparti -->
    <div id="tableContainer">
      <?php if (!$compartments): ?>
        <div class="alert alert-secondary">Nessun comparto presente in questa posizione.</div>
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
                    <a href="edit_compartment.php?id=<?= $cmp['id'] ?>&location_id=<?= $location_id ?>"
                      class="btn btn-sm btn-outline-secondary" title="Modifica">
                      <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="delete_compartment.php?id=<?= $cmp['id'] ?>&location_id=<?= $location_id ?>"
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
  <?php endif; ?>
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
    if (!form) return;

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

    async function refreshTable() {
      const loc = document.getElementById('locationSelect').value;
      const order = document.getElementById('orderSelect').value;
      const response = await fetch('get_compartments_table.php?location_id=' + loc + '&order=' + order);
      const html = await response.text();
      document.getElementById('tableContainer').innerHTML = html;
    }

    // Click su numero componenti
    document.querySelectorAll('.view-components-link').forEach(link => {
      link.addEventListener('click', async function(e) {
        e.preventDefault();
        const compId = this.dataset.id;
        const compCode = this.dataset.code;
        const modalTitle = document.getElementById('componentsModalLabel');
        const modalBody = document.getElementById('componentsModalBody');

        modalTitle.textContent = `Componenti comparto ${compCode}`;
        modalBody.innerHTML = 'Caricamento...';

        const response = await fetch(`get_compartment_components.php?compartment_id=${compId}`);
        const html = await response.text();
        modalBody.innerHTML = html;

        const modal = new bootstrap.Modal(document.getElementById('componentsModal'));
        modal.show();
      });
    });


  });
</script>

<?php include '../includes/footer.php'; ?>