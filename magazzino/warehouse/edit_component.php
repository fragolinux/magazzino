<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:53:16 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-09 15:16:19
*/
// 2026-01-08: Aggiunta quantità minima
// 2026-01-09: Aggiunta gestione immagine componente

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header("Location: components.php");
  exit;
}

$id = intval($_GET['id']);
// Recupero categorie
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
// Recupero dati componente
$stmt = $pdo->prepare("SELECT * FROM components WHERE id = ?");
$stmt->execute([$id]);
$component = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$component) {
  header("Location: components.php?notfound=1");
  exit;
}

// Recupero posizioni, compartimenti e categorie
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$compartments = [];
if ($component['location_id']) {
  $stmt = $pdo->prepare("SELECT * FROM compartments WHERE location_id = ? ORDER BY code ASC");
  $stmt->execute([$component['location_id']]);
  $compartments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$error = '';

// Aggiornamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $codice_prodotto = trim($_POST['codice_prodotto']);
  $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
  $costruttore = trim($_POST['costruttore']);
  $fornitore = trim($_POST['fornitore']);
  $codice_fornitore = trim($_POST['codice_fornitore']);
  $quantity = intval($_POST['quantity']);
  $quantity_min = intval($_POST['quantity_min']);
  $quantity_min = intval($_POST['quantity_min']) > 0 ? intval($_POST['quantity_min']) : null;
  $location_id = $_POST['location_id'] ?: null;
  $compartment_id = $_POST['compartment_id'] ?: null;
  $datasheet_url = trim($_POST['datasheet_url']);
  $equivalents = isset($_POST['equivalents']) ? json_encode(array_filter(array_map('trim', explode(',', $_POST['equivalents'])))) : null;
  $notes = trim($_POST['notes']);
  
  $datasheet_file = $component['datasheet_file'];
  
  // Gestione upload nuovo file datasheet
  if (isset($_FILES['datasheet_file']) && $_FILES['datasheet_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['datasheet_file'];
    $maxSize = 10 * 1024 * 1024; // 10MB max
    $allowedTypes = ['application/pdf'];
    
    if ($file['size'] > $maxSize) {
      $error = "File datasheet troppo grande (max 10MB).";
    } elseif (!in_array($file['type'], $allowedTypes)) {
      $error = "Solo file PDF sono consentiti per il datasheet.";
    } else {
      $datasheet_dir = realpath(__DIR__ . '/..') . '/datasheet';
      if (!is_dir($datasheet_dir)) {
        @mkdir($datasheet_dir, 0755, true);
      }
      
      // Elimina il vecchio file se esiste
      if ($datasheet_file) {
        $old_file_path = $datasheet_dir . DIRECTORY_SEPARATOR . $datasheet_file;
        if (file_exists($old_file_path)) {
          @unlink($old_file_path);
        }
      }
      
      // Nome file: id.pdf
      $datasheet_file = $id . '.pdf';
      $file_path = $datasheet_dir . DIRECTORY_SEPARATOR . $datasheet_file;
      
      if (!@move_uploaded_file($file['tmp_name'], $file_path)) {
        $error = "Impossibile salvare il file datasheet.";
        $datasheet_file = $component['datasheet_file'];
      }
    }
  }

  if ($codice_prodotto === '') {
    $error = "Il campo codice prodotto è obbligatorio.";
  } else if (empty($error)) {
    $stmt = $pdo->prepare("UPDATE components SET 
            codice_prodotto=?, category_id=?, costruttore=?, fornitore=?, codice_fornitore=?, quantity=?, quantity_min=?, location_id=?, compartment_id=?, datasheet_url=?, datasheet_file=?, equivalents=?, notes=?
            WHERE id=?");
    $stmt->execute([$codice_prodotto, $category_id, $costruttore, $fornitore, $codice_fornitore, $quantity, $quantity_min, $location_id, $compartment_id, $datasheet_url, $datasheet_file, $equivalents, $notes, $id]);
    // Messaggio di conferma e redirect per evitare reinvio del form
    $_SESSION['success'] = "Componente aggiornato con successo.";
    header("Location: " . $_SERVER['REQUEST_URI']); // Ricarica la stessa pagina
    exit;
  }
}
include '../includes/header.php';

$success = '';
if (isset($_SESSION['success'])) {
  $success = $_SESSION['success'];
  unset($_SESSION['success']);
}
?>

<div class="container py-4">
  <h2><i class="fa-solid fa-pen-to-square me-2"></i>Modifica componente</h2>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php elseif ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="post" class="card shadow-sm p-4" enctype="multipart/form-data">
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Codice prodotto *</label>
        <input type="text" name="codice_prodotto" class="form-control" value="<?= htmlspecialchars($component['codice_prodotto']) ?>" required>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Categoria</label>
        <select name="category_id" class="form-select">
          <option value="">-- Seleziona categoria --</option>
          <?php foreach($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>"
            <?= (isset($component['category_id']) && $component['category_id'] == $cat['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Costruttore</label>
        <input type="text" name="costruttore" id="costruttore" class="form-control" value="<?= htmlspecialchars($component['costruttore'] ?? '') ?>">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Fornitore</label>
        <input type="text" name="fornitore" id="fornitore" class="form-control" value="<?= htmlspecialchars($component['fornitore'] ?? '') ?>">
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Codice fornitore</label>
        <input type="text" name="codice_fornitore" class="form-control" value="<?= htmlspecialchars($component['codice_fornitore']) ?>">
      </div>
      <div class="col-md-3 mb-3">
        <label class="form-label">Quantità</label>
        <input type="number" name="quantity" class="form-control" value="<?= $component['quantity'] ?>">
      </div>
      <div class="col-md-3 mb-3">
        <label class="form-label">Q.tà minima</label>
        <input type="number" name="quantity_min" class="form-control" value="<?= $component['quantity_min'] ?>">
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Posizione</label>
        <select name="location_id" class="form-select" onchange="this.form.submit()">
          <option value="">-- Seleziona posizione --</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= $loc['id'] ?>" <?= $component['location_id'] == $loc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($loc['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Comparto</label>
        <select name="compartment_id" class="form-select">
          <option value="">-- Seleziona comparto --</option>
          <?php foreach ($compartments as $cmp): ?>
            <option value="<?= $cmp['id'] ?>" <?= $component['compartment_id'] == $cmp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cmp['code']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Link datasheet</label>
      <input type="url" name="datasheet_url" class="form-control" value="<?= htmlspecialchars($component['datasheet_url']) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Datasheet PDF</label>
      <?php if ($component['datasheet_file']): ?>
        <div class="mb-2">
          <a href="/magazzino/datasheet/<?= htmlspecialchars($component['datasheet_file']) ?>" target="_blank" class="btn btn-sm btn-info">
            <i class="fa-solid fa-file-pdf me-1"></i>Visualizza PDF attuale
          </a>
        </div>
      <?php endif; ?>
      <input type="file" name="datasheet_file" class="form-control" accept=".pdf">
      <small class="text-muted">Carica un nuovo PDF per sostituire quello attuale (Max 10MB)</small>
    </div>

    <div class="mb-3">
      <label class="form-label">Immagine componente</label>
      <?php 
      $imagePath = '../images/components/' . $id . '.jpg';
      $thumbPath = '../images/components/thumbs/' . $id . '.jpg';
      $hasImage = file_exists($imagePath);
      ?>
      <?php if ($hasImage): ?>
        <div class="mb-2" id="current-image-container">
          <img src="<?= '/magazzino/images/components/thumbs/' . $id . '.jpg?' . time() ?>" alt="Immagine componente" style="max-width: 100px; border: 1px solid #ddd; border-radius: 4px;">
          <button type="button" id="delete-current-image" class="btn btn-sm btn-outline-danger ms-2" title="Elimina immagine">
            <i class="fa-solid fa-trash"></i> Elimina
          </button>
        </div>
      <?php endif; ?>
      <input type="file" id="component_image" class="form-control" accept="image/jpeg,image/jpg,image/gif,image/bmp,image/webp">
      <small class="text-muted">JPG, GIF, BMP, WebP - verrà ridimensionata a 500x500px</small>
      <div id="image-preview-container" style="display:none; margin-top: 10px;">
        <img id="image-preview" src="" alt="Preview" style="max-width: 100px; border: 1px solid #ddd; border-radius: 4px;">
        <button type="button" id="remove-image" class="btn btn-sm btn-outline-danger ms-2" title="Rimuovi">
          <i class="fa-solid fa-times"></i>
        </button>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Componenti equivalenti (separati da virgola)</label>
      <input type="text" name="equivalents" id="equivalents" class="form-control" placeholder="Es: UA78M05, LM340T5" value="<?= $component['equivalents'] ? implode(', ', json_decode($component['equivalents'], true)) : '' ?>">
      <small class="text-muted">Separare i componenti equivalenti con una virgola.</small>
    </div>

    <div class="mb-3">
      <label class="form-label">Note</label>
      <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($component['notes']) ?></textarea>
    </div>

    <div class="text-end">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Salva modifiche</button>
    </div>
  </form>

  <a href="components.php" class="btn btn-secondary mt-3"><i class="fa-solid fa-arrow-left"></i> Torna alla lista componenti</a>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  $(document).ready(function() {
    // Variabili per memorizzare le immagini ridimensionate
    let resizedImageData = null;
    let resizedThumbData = null;
    let deleteCurrentImage = false;

    // Gestione eliminazione immagine esistente
    $('#delete-current-image').on('click', function() {
      if (!confirm('Sei sicuro di voler eliminare l\'immagine?')) return;
      
      $.ajax({
        url: 'delete_component_image.php',
        method: 'POST',
        data: { component_id: <?= $id ?> },
        success: function() {
          $('#current-image-container').fadeOut();
          alert('Immagine eliminata con successo.');
        },
        error: function() {
          alert('Errore durante l\'eliminazione dell\'immagine.');
        }
      });
    });

    // Gestione upload e ridimensionamento nuova immagine
    $('#component_image').on('change', function(e) {
      const file = e.target.files[0];
      if (!file) return;

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
          
          let sourceX = 0, sourceY = 0, sourceSize = Math.min(img.width, img.height);
          if (img.width > img.height) {
            sourceX = (img.width - img.height) / 2;
          } else {
            sourceY = (img.height - img.width) / 2;
          }
          
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

    // Rimuovi nuova immagine
    $('#remove-image').on('click', function() {
      $('#component_image').val('');
      $('#image-preview-container').hide();
      resizedImageData = null;
      resizedThumbData = null;
    });

    // Intercetta submit per caricare immagine se presente
    $('form').on('submit', function(e) {
      if (!resizedImageData || !resizedThumbData) {
        return true; // Procedi normalmente
      }
      
      e.preventDefault();
      const $submitBtn = $(this).find('button[type="submit"]');
      $submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Salvataggio...');
      
      // Prima salva il form
      const formData = new FormData(this);
      
      $.ajax({
        url: '',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function() {
          // Poi carica l'immagine
          $.ajax({
            url: 'upload_component_image.php',
            method: 'POST',
            data: {
              component_id: <?= $id ?>,
              image_data: resizedImageData,
              thumb_data: resizedThumbData
            },
            success: function() {
              window.location.reload();
            },
            error: function() {
              alert('Componente aggiornato ma errore nel caricamento immagine.');
              window.location.reload();
            }
          });
        },
        error: function() {
          $submitBtn.prop('disabled', false).html('<i class="fa-solid fa-save"></i> Salva modifiche');
          alert('Errore durante il salvataggio.');
        }
      });
    });

    let currentCompartment = <?= $component['compartment_id'] ?: 'null' ?>;
    let compartmentSelect = $('select[name="compartment_id"]');

    function loadCompartments(location_id) {
      compartmentSelect.html('<option>Caricamento...</option>');

      if (location_id) {
        $.getJSON('get_compartments.php', {
          location_id: location_id
        }, function(data) {
          let options = '<option value="">-- Seleziona comparto --</option>';
          $.each(data, function(i, item) {
            let selected = (item.id == currentCompartment) ? ' selected' : '';
            options += '<option value="' + item.id + '"' + selected + '>' + item.code + '</option>';
          });
          compartmentSelect.html(options);
        });
      } else {
        compartmentSelect.html('<option value="">-- Seleziona comparto --</option>');
      }
    }

    // Carica inizialmente i comparti della posizione corrente
    let initialLocation = $('select[name="location_id"]').val();
    loadCompartments(initialLocation);

    // Aggiorna i comparti quando cambia la posizione
    $('select[name="location_id"]').change(function() {
      currentCompartment = null; // reset selezione quando cambio posizione
      loadCompartments($(this).val());
    });

    // Autocomplete codice prodotto
    $('input[name="codice_prodotto"]').autocomplete({
      source: 'search_products.php',
      minLength: 2,
      select: function(event, ui) {
        // Imposta codice prodotto selezionato
        $(this).val(ui.item.value);
        // Imposta automaticamente il datasheet se disponibile
        if (ui.item.datasheet) {
          $('input[name="datasheet_url"]').val(ui.item.datasheet);
        }
        return false;
      }
    });

    // Equivalents autocomplete
    $("#equivalents")
      // autocomplete
      .autocomplete({
        source: function(request, response) {
          $.getJSON("search_equivalents.php", {
            term: extractLast(request.term)
          }, response);
        },
        search: function() {
          // minimo 1 carattere per cercare
          var term = extractLast(this.value);
          return term.length >= 1;
        },
        focus: function() {
          // previeni l’inserimento dell’elemento al focus
          return false;
        },
        select: function(event, ui) {
          var terms = split(this.value);
          // rimuovi l’ultimo termine (quello corrente)
          terms.pop();
          // aggiungi quello selezionato
          terms.push(ui.item.value);
          // aggiungi spazio finale
          terms.push("");
          this.value = terms.join(", ");
          return false;
        }
      });

    function setupAutocomplete(field) {
      $('#' + field).autocomplete({
        source: function(request, response) {
          $.getJSON('search_manufacturers_suppliers.php', {
            term: request.term,
            type: field
          }, response);
        },
        minLength: 2
      });
    }

    setupAutocomplete('costruttore');
    setupAutocomplete('fornitore');

  });
</script>

<?php include '../includes/footer.php'; ?>