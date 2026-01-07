<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:52:20 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-07 15:12:31
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$error = '';
$success = '';
$component = [];

// Recupero posizioni, categorie e ultimi componenti
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$lastComponents = $pdo->query("
    SELECT c.id, c.codice_prodotto, c.quantity, cat.name AS category_name, l.name AS location_name, cmp.code AS compartment_code
    FROM components c
    LEFT JOIN categories cat ON c.category_id = cat.id
    LEFT JOIN locations l ON c.location_id = l.id
    LEFT JOIN compartments cmp ON c.compartment_id = cmp.id
    ORDER BY c.id DESC
    LIMIT 7
")->fetchAll(PDO::FETCH_ASSOC);

// Compartimenti se è selezionata una location
$compartments = [];
if (isset($_POST['location_id']) && is_numeric($_POST['location_id']) && $_POST['location_id'] !== '') {
    $stmt = $pdo->prepare("SELECT * FROM compartments WHERE location_id = ? ORDER BY
      REGEXP_REPLACE(code, '[0-9]', '') ASC,
      CAST(REGEXP_REPLACE(code, '[^0-9]', '') AS UNSIGNED) ASC");
    $stmt->execute([intval($_POST['location_id'])]);
    $compartments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Gestione form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codice_prodotto']) && !isset($_POST['quick_add_compartment'])) {
    $codice_prodotto = trim($_POST['codice_prodotto'] ?? '');
    $category_id     = isset($_POST['category_id']) && is_numeric($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $costruttore     = trim($_POST['costruttore'] ?? '');
    $fornitore       = trim($_POST['fornitore'] ?? '');
    $codice_fornitore= trim($_POST['codice_fornitore'] ?? '');
    $quantity        = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $location_id     = isset($_POST['location_id']) && is_numeric($_POST['location_id']) ? intval($_POST['location_id']) : null;
    $compartment_id  = isset($_POST['compartment_id']) && is_numeric($_POST['compartment_id']) ? intval($_POST['compartment_id']) : null;
    $datasheet_url   = trim($_POST['datasheet_url'] ?? '');
    $equivalents     = isset($_POST['equivalents']) && trim($_POST['equivalents']) !== '' ? json_encode(array_filter(array_map('trim', explode(',', $_POST['equivalents'])))) : null;
    $notes           = trim($_POST['notes'] ?? '');

    $datasheet_file = null;
    // Gestione upload file datasheet
    if (isset($_FILES['datasheet_file']) && $_FILES['datasheet_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['datasheet_file'];
        $maxSize = 10 * 1024 * 1024; // 10MB max
        $allowedTypes = ['application/pdf'];
        
        if ($file['size'] > $maxSize) {
            $error = "File datasheet troppo grande (max 10MB).";
        } elseif (!in_array($file['type'], $allowedTypes)) {
            $error = "Solo file PDF sono consentiti per il datasheet.";
        }
        // Se validazione OK, memorizziamo il file temporaneamente per elaborarlo dopo l'INSERT
    }

    if ($codice_prodotto === '') {
        $error = "Il campo codice prodotto è obbligatorio.";
    } else if (empty($error)) {
        $stmt = $pdo->prepare("INSERT INTO components 
            (codice_prodotto, category_id, costruttore, fornitore, codice_fornitore, quantity, location_id, compartment_id, datasheet_url, datasheet_file, equivalents, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$codice_prodotto, $category_id, $costruttore, $fornitore, $codice_fornitore, $quantity, $location_id, $compartment_id, $datasheet_url, null, $equivalents, $notes]);
        
        // Recupera l'ID del componente appena inserito
        $component_id = $pdo->lastInsertId();
        
        // Ora elabora il file datasheet se presente e non ci sono errori
        if (isset($_FILES['datasheet_file']) && $_FILES['datasheet_file']['error'] === UPLOAD_ERR_OK && empty($error)) {
            $file = $_FILES['datasheet_file'];
            $datasheet_dir = realpath(__DIR__ . '/..') . '/datasheet';
            if (!is_dir($datasheet_dir)) {
                @mkdir($datasheet_dir, 0755, true);
            }
            
            // Nome file: id.pdf
            $datasheet_file = $component_id . '.pdf';
            $file_path = $datasheet_dir . DIRECTORY_SEPARATOR . $datasheet_file;
            
            if (@move_uploaded_file($file['tmp_name'], $file_path)) {
                // Aggiorna il record con il nome del file
                $upd = $pdo->prepare("UPDATE components SET datasheet_file = ? WHERE id = ?");
                $upd->execute([$datasheet_file, $component_id]);
            } else {
                $error = "Impossibile salvare il file datasheet.";
            }
        }
        
        if (empty($error)) {
            $success = "Componente aggiunto con successo.";
        }

        $lastComponents = $pdo->query("
            SELECT c.id, c.codice_prodotto, c.quantity, cat.name AS category_name, l.name AS location_name, cmp.code AS compartment_code
            FROM components c
            LEFT JOIN categories cat ON c.category_id = cat.id
            LEFT JOIN locations l ON c.location_id = l.id
            LEFT JOIN compartments cmp ON c.compartment_id = cmp.id
            ORDER BY c.id DESC
            LIMIT 7
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}

include '../includes/header.php';
?>

<div class="container pb-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="fa-solid fa-plus me-2"></i>Aggiungi componente</h4>
    <a href="components.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Torna alla lista</a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php elseif ($success): ?>
    <div class="alert alert-success py-0"><?= htmlspecialchars($success) ?></div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      const input = document.querySelector('input[name="codice_prodotto"]');
      input.focus();
      input.select();
    });
    </script>
  <?php endif; ?>

  <form method="post" class="card shadow-sm p-3" enctype="multipart/form-data">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Posizione</label>
        <select name="location_id" id="locationSelect" class="form-select form-select-sm">
          <option value="">-- Seleziona posizione --</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= $loc['id'] ?>" <?= (isset($_POST['location_id']) && $_POST['location_id'] == $loc['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label mb-1 d-flex justify-content-between">
          <span>Comparto</span>
        </label>
        <div class="input-group">
          <select name="compartment_id" id="compartmentSelect" class="form-select form-select-sm">
            <option value="">-- Seleziona comparto --</option>
            <?php foreach ($compartments as $cmp): ?>
              <option value="<?= $cmp['id'] ?>" <?= (isset($_POST['compartment_id']) && $_POST['compartment_id'] == $cmp['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cmp['code']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="quickAddCompBtn" title="Aggiungi comparto"><i class="fa-solid fa-plus"></i></button>
        </div>
      </div>

      <div class="col-md-4">
        <label class="form-label mb-1">Categoria</label>
        <select name="category_id" class="form-select form-select-sm">
          <option value="">-- Seleziona categoria --</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label mb-1">Codice prodotto *</label>
        <input type="text" name="codice_prodotto" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['codice_prodotto'] ?? '') ?>" required>
      </div>

      <div class="col-md-2">
        <label class="form-label mb-1">Quantità</label>
        <input type="number" name="quantity" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['quantity'] ?? 0) ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label mb-1">Costruttore</label>
        <input type="text" name="costruttore" id="costruttore" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['costruttore'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label mb-1">Fornitore</label>
        <input type="text" name="fornitore" id="fornitore" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['fornitore'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label mb-1">Codice fornitore</label>
        <input type="text" name="codice_fornitore" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['codice_fornitore'] ?? '') ?>">
      </div>

      <div class="col-md-8">
        <label class="form-label mb-1">Link datasheet Web</label>
        <input type="url" name="datasheet_url" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['datasheet_url'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label mb-1">Datasheet PDF</label>
        <input type="file" name="datasheet_file" class="form-control form-control-sm" accept=".pdf">
        <small class="text-muted">Max 10MB</small>
      </div>

      <div class="col-12">
        <label class="form-label mb-1">Equivalenti (separati da virgola)</label>
        <input type="text" name="equivalents" id="equivalents" class="form-control form-control-sm" placeholder="Es. UA78M05, LM340T5" value="<?= isset($_POST['equivalents']) ? htmlspecialchars($_POST['equivalents']) : '' ?>">
      </div>

      <div class="col-12">
        <label class="form-label mb-1">Note</label>
        <textarea name="notes" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
      </div>

    </div>

    <div class="d-flex justify-content-end mt-3">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-save me-1"></i> Salva</button>
    </div>
  </form>

  <!-- Ultimi componenti in basso -->
  <div class="card shadow-sm mt-2">
    <div class="card-header bg-light fw-bold"><i class="fa-solid fa-clock-rotate-left me-2"></i>Ultimi 7 componenti inseriti</div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Codice</th>
            <th>Categoria</th>
            <th>Posizione</th>
            <th>Comparto</th>
            <th class="text-end">Q.tà</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$lastComponents): ?>
            <tr><td colspan="5" class="text-center text-muted">Nessun componente ancora inserito.</td></tr>
          <?php else: ?>
            <?php foreach ($lastComponents as $c): ?>
              <tr>
                <td><a target="_blank" href="edit_component.php?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['codice_prodotto']) ?></a></td>
                <td><?= htmlspecialchars($c['category_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($c['location_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($c['compartment_code'] ?? '—') ?></td>
                <td class="text-end"><?= htmlspecialchars($c['quantity']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal aggiunta comparto -->
<div class="modal fade" id="modalQuickAddCompartment" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form id="formQuickAddCompartment">
        <div class="modal-header">
          <h5 class="modal-title">Nuovo comparto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Codice comparto</label>
            <input type="text" name="code" id="quickCompCode" class="form-control form-control-sm" required>
          </div>
          <input type="hidden" name="location_id" id="quickCompLocationId">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary btn-sm">Salva</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(function() {
  $('#locationSelect').on('change', function() {
    const locId = $(this).val();
    const $sel = $('#compartmentSelect');
    $sel.html('<option>Caricamento...</option>');
    if (!locId) {
      $sel.html('<option value="">-- Seleziona comparto --</option>');
      return;
    }
    $.getJSON('get_compartments.php', { location_id: locId }, function(data) {
      let opts = '<option value="">-- Seleziona comparto --</option>';
      data.forEach(function(it) {
        opts += `<option value="${it.id}">${it.code}</option>`;
      });
      $sel.html(opts);
    });
  });

  $('#openAddCompartmentModal, #quickAddCompBtn').on('click', function(e) {
    e.preventDefault();
    const locId = $('#locationSelect').val();
    if (!locId) {
      alert('Seleziona prima una posizione.');
      return;
    }
    $('#quickCompLocationId').val(locId);
    $('#quickCompCode').val('');
    var modal = new bootstrap.Modal(document.getElementById('modalQuickAddCompartment'));
    modal.show();
  });

  // evento per gestire il focus
  $('#modalQuickAddCompartment').on('shown.bs.modal', function () {
      $('#quickCompCode').focus();
  });

  $('#formQuickAddCompartment').on('submit', function(e) {
    e.preventDefault();
    const $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true);
    $.post('quick_add_compartment.php', $(this).serialize(), function(resp) {
      $btn.prop('disabled', false);
      if (resp.success) {
        $.getJSON('get_compartments.php', { location_id: resp.location_id }, function(data) {
          let opts = '<option value="">-- Seleziona comparto --</option>';
          data.forEach(function(it) {
            const selected = (it.id == resp.new_id) ? ' selected' : '';
            opts += `<option value="${it.id}"${selected}>${it.code}</option>`;
          });
          $('#compartmentSelect').html(opts);
        });
        var modalEl = document.getElementById('modalQuickAddCompartment');
        var modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
      } else {
        alert(resp.error || 'Errore durante la creazione del comparto.');
      }
    }, 'json').fail(function(){
      $btn.prop('disabled', false);
      alert('Errore di rete.');
    });
  });
});
</script>

<?php include '../includes/footer.php'; ?>