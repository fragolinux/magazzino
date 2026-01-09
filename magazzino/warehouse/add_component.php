<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:52:20 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-09 15:12:31
*/
// 2026-01-08: Aggiunta quantità minima
// 2026-01-08: aggiunti quick add per posizioni e categorie
// 2026-01-09: Aggiunta gestione upload immagini

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
    LIMIT 10
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
    $quantity_min    = isset($_POST['quantity_min']) && intval($_POST['quantity_min']) > 0 ? intval($_POST['quantity_min']) : null;
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
            (codice_prodotto, category_id, costruttore, fornitore, codice_fornitore, quantity, quantity_min, location_id, compartment_id, datasheet_url, datasheet_file, equivalents, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$codice_prodotto, $category_id, $costruttore, $fornitore, $codice_fornitore, $quantity, $quantity_min, $location_id, $compartment_id, $datasheet_url, null, $equivalents, $notes]);
        
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
            LIMIT 10
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
        <div class="input-group">
          <select name="location_id" id="locationSelect" class="form-select form-select-sm">
            <option value="">-- Seleziona posizione --</option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>" <?= (isset($_POST['location_id']) && $_POST['location_id'] == $loc['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($loc['name']) ?>
                </option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="quickAddLocationBtn" title="Aggiungi posizione"><i class="fa-solid fa-plus"></i></button>
        </div>
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
        <div class="input-group">
          <select name="category_id" id="categorySelect" class="form-select form-select-sm">
            <option value="">-- Seleziona categoria --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="quickAddCategoryBtn" title="Aggiungi categoria"><i class="fa-solid fa-plus"></i></button>
        </div>
      </div>

      <div class="col-md-4">
        <label class="form-label mb-1">Codice prodotto *</label>
        <input type="text" name="codice_prodotto" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['codice_prodotto'] ?? '') ?>" required>
      </div>

      <div class="col-md-2">
        <label class="form-label mb-1">Quantità *</label>
        <input type="number" name="quantity" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['quantity'] ?? 0) ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label mb-1">Q.tà minima</label>
        <input type="number" name="quantity_min" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['quantity_min'] ?? 0) ?>">
      </div>

      <div class="col-md-4">
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

      <div class="col-md-4">
        <label class="form-label mb-1">Immagine componente</label>
        <input type="file" id="component_image" class="form-control form-control-sm" accept="image/jpeg,image/jpg,image/gif,image/bmp,image/webp">
        <small class="text-muted">JPG, GIF, BMP, WebP - verrà ridimensionata a 500x500px</small>
      </div>

      <div class="col-md-4" id="image-preview-container" style="display:none;">
        <label class="form-label mb-1">Anteprima</label>
        <div>
          <img id="image-preview" src="" alt="Preview" style="max-width: 100px; max-height: 100px; border: 1px solid #ddd; border-radius: 4px;">
          <button type="button" id="remove-image" class="btn btn-sm btn-outline-danger ms-2" title="Rimuovi immagine">
            <i class="fa-solid fa-times"></i>
          </button>
        </div>
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
    <div class="card-header bg-light fw-bold"><i class="fa-solid fa-clock-rotate-left me-2"></i>Ultimi 10 componenti inseriti</div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Codice</th>
            <th>Categoria</th>
            <th>Posizione</th>
            <th>Comparto</th>
            <th class="text-end">Q.tà</th>
            <th class="text-center" style="width:50px;">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$lastComponents): ?>
            <tr><td colspan="6" class="text-center text-muted">Nessun componente ancora inserito.</td></tr>
          <?php else: ?>
            <?php foreach ($lastComponents as $c): ?>
              <tr data-component-id="<?= $c['id'] ?>">
                <td><a target="_blank" href="edit_component.php?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['codice_prodotto']) ?></a></td>
                <td><?= htmlspecialchars($c['category_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($c['location_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($c['compartment_code'] ?? '—') ?></td>
                <td class="text-end"><?= htmlspecialchars($c['quantity']) ?></td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-outline-danger btn-delete-component" data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['codice_prodotto']) ?>" title="Elimina componente">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal aggiunta posizione -->
<div class="modal fade" id="modalQuickAddLocation" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form id="formQuickAddLocation">
        <div class="modal-header">
          <h5 class="modal-title">Nuova posizione</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Nome posizione</label>
            <input type="text" name="name" id="quickLocationName" class="form-control form-control-sm" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary btn-sm">Salva</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal aggiunta categoria -->
<div class="modal fade" id="modalQuickAddCategory" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form id="formQuickAddCategory">
        <div class="modal-header">
          <h5 class="modal-title">Nuova categoria</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Nome categoria</label>
            <input type="text" name="name" id="quickCategoryName" class="form-control form-control-sm" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary btn-sm">Salva</button>
        </div>
      </form>
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
  // Variabili per memorizzare le immagini ridimensionate
  let resizedImageData = null;
  let resizedThumbData = null;

  // Gestione upload e ridimensionamento immagine
  $('#component_image').on('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    // Verifica tipo file
    const validTypes = ['image/jpeg', 'image/jpg', 'image/gif', 'image/bmp', 'image/webp'];
    if (!validTypes.includes(file.type)) {
      alert('Formato non valido. Usa JPG, GIF, BMP o WebP.');
      $(this).val('');
      return;
    }

    const reader = new FileReader();
    reader.onload = function(event) {
      const img = new Image();
      img.onload = function() {
        // Ridimensiona a 500x500
        const canvas500 = document.createElement('canvas');
        canvas500.width = 500;
        canvas500.height = 500;
        const ctx500 = canvas500.getContext('2d');
        
        // Calcola dimensioni per mantenere proporzioni
        let sourceX = 0, sourceY = 0, sourceSize = Math.min(img.width, img.height);
        if (img.width > img.height) {
          sourceX = (img.width - img.height) / 2;
        } else {
          sourceY = (img.height - img.width) / 2;
        }
        
        // Disegna immagine ridimensionata 500x500
        ctx500.drawImage(img, sourceX, sourceY, sourceSize, sourceSize, 0, 0, 500, 500);
        resizedImageData = canvas500.toDataURL('image/jpeg', 0.9);
        
        // Ridimensiona a 80x80 per thumbnail
        const canvas80 = document.createElement('canvas');
        canvas80.width = 80;
        canvas80.height = 80;
        const ctx80 = canvas80.getContext('2d');
        ctx80.drawImage(img, sourceX, sourceY, sourceSize, sourceSize, 0, 0, 80, 80);
        resizedThumbData = canvas80.toDataURL('image/jpeg', 0.85);
        
        // Mostra anteprima
        $('#image-preview').attr('src', resizedThumbData);
        $('#image-preview-container').show();
      };
      img.src = event.target.result;
    };
    reader.readAsDataURL(file);
  });

  // Rimuovi immagine
  $('#remove-image').on('click', function() {
    $('#component_image').val('');
    $('#image-preview-container').hide();
    resizedImageData = null;
    resizedThumbData = null;
  });

  // Intercetta submit del form per caricare l'immagine dopo l'inserimento
  const $form = $('form');
  const originalAction = $form.attr('action') || '';
  
  $form.on('submit', function(e) {
    // Se non c'è immagine, procedi normalmente
    if (!resizedImageData || !resizedThumbData) {
      return true;
    }
    
    // Se c'è un'immagine, blocca il submit normale
    e.preventDefault();
    
    const $submitBtn = $form.find('button[type="submit"]');
    $submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvataggio...');
    
    // Prima fai il submit normale per creare il componente
    const formData = new FormData(this);
    
    $.ajax({
      url: originalAction,
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(response) {
        // Estrai l'ID del componente dalla risposta (cerca nell'HTML)
        const $response = $(response);
        const successMsg = $response.find('.alert-success').text();
        
        // Se il componente è stato creato, cerca l'ID nell'ultima tabella
        const $lastTable = $response.find('tbody tr').first();
        const componentId = $lastTable.data('component-id');
        
        if (componentId && resizedImageData && resizedThumbData) {
          // Carica l'immagine
          $.ajax({
            url: 'upload_component_image.php',
            method: 'POST',
            data: {
              component_id: componentId,
              image_data: resizedImageData,
              thumb_data: resizedThumbData
            },
            success: function() {
              // Ricarica la pagina per mostrare il successo
              window.location.reload();
            },
            error: function() {
              alert('Componente creato ma errore nel caricamento dell\'immagine.');
              window.location.reload();
            }
          });
        } else {
          // Nessun ID trovato o errore, ricarica comunque
          window.location.reload();
        }
      },
      error: function() {
        $submitBtn.prop('disabled', false).html('<i class="fa-solid fa-save me-1"></i> Salva');
        alert('Errore durante il salvataggio del componente.');
      }
    });
  });

  // Gestione modal aggiunta posizione
  $('#quickAddLocationBtn').on('click', function(e) {
    e.preventDefault();
    $('#quickLocationName').val('');
    var modal = new bootstrap.Modal(document.getElementById('modalQuickAddLocation'));
    modal.show();
  });

  $('#modalQuickAddLocation').on('shown.bs.modal', function () {
    $('#quickLocationName').focus();
  });

  $('#formQuickAddLocation').on('submit', function(e) {
    e.preventDefault();
    const $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true);
    $.post('quick_add_location.php', $(this).serialize(), function(resp) {
      $btn.prop('disabled', false);
      if (resp.success) {
        // Ricarica le posizioni
        $.getJSON('get_locations.php', function(data) {
          let opts = '<option value="">-- Seleziona posizione --</option>';
          data.forEach(function(it) {
            const selected = (it.id == resp.new_id) ? ' selected' : '';
            opts += `<option value="${it.id}"${selected}>${it.name}</option>`;
          });
          $('#locationSelect').html(opts).trigger('change');
        });
        var modalEl = document.getElementById('modalQuickAddLocation');
        var modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
      } else {
        alert(resp.error || 'Errore durante la creazione della posizione.');
      }
    }, 'json').fail(function(){
      $btn.prop('disabled', false);
      alert('Errore di rete.');
    });
  });

  // Gestione modal aggiunta categoria
  $('#quickAddCategoryBtn').on('click', function(e) {
    e.preventDefault();
    $('#quickCategoryName').val('');
    var modal = new bootstrap.Modal(document.getElementById('modalQuickAddCategory'));
    modal.show();
  });

  $('#modalQuickAddCategory').on('shown.bs.modal', function () {
    $('#quickCategoryName').focus();
  });

  $('#formQuickAddCategory').on('submit', function(e) {
    e.preventDefault();
    const $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true);
    $.post('quick_add_category.php', $(this).serialize(), function(resp) {
      $btn.prop('disabled', false);
      if (resp.success) {
        // Ricarica le categorie
        $.getJSON('get_categories.php', function(data) {
          let opts = '<option value="">-- Seleziona categoria --</option>';
          data.forEach(function(it) {
            const selected = (it.id == resp.new_id) ? ' selected' : '';
            opts += `<option value="${it.id}"${selected}>${it.name}</option>`;
          });
          $('#categorySelect').html(opts);
        });
        var modalEl = document.getElementById('modalQuickAddCategory');
        var modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
      } else {
        alert(resp.error || 'Errore durante la creazione della categoria.');
      }
    }, 'json').fail(function(){
      $btn.prop('disabled', false);
      alert('Errore di rete.');
    });
  });

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

  // Gestione cancellazione componente dalla tabella ultimi inseriti
  $(document).on('click', '.btn-delete-component', function() {
    const componentId = $(this).data('id');
    const componentName = $(this).data('name');
    
    if (!confirm(`Sei sicuro di voler eliminare il componente "${componentName}"?`)) {
      return;
    }
    
    const $btn = $(this);
    $btn.prop('disabled', true);
    
    $.ajax({
      url: 'delete_component.php?id=' + componentId,
      method: 'GET',
      success: function() {
        // Rimuovi la riga dalla tabella con animazione
        $btn.closest('tr').fadeOut(400, function() {
          $(this).remove();
          // Verifica se non ci sono più righe
          const rowCount = $('tbody tr').length;
          if (rowCount === 0) {
            $('tbody').html('<tr><td colspan="6" class="text-center text-muted">Nessun componente ancora inserito.</td></tr>');
          }
        });
      },
      error: function() {
        $btn.prop('disabled', false);
        alert('Errore durante l\'eliminazione del componente.');
      }
    });
  });
});
</script>

<?php include '../includes/footer.php'; ?>