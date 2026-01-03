<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:53:16 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-23 15:12:40
*/


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
  $location_id = $_POST['location_id'] ?: null;
  $compartment_id = $_POST['compartment_id'] ?: null;
  $datasheet_url = trim($_POST['datasheet_url']);
  $equivalents = isset($_POST['equivalents']) ? json_encode(array_filter(array_map('trim', explode(',', $_POST['equivalents'])))) : null;
  $notes = trim($_POST['notes']);

  if ($codice_prodotto === '') {
    $error = "Il campo codice prodotto è obbligatorio.";
  } else {
    $stmt = $pdo->prepare("UPDATE components SET 
            codice_prodotto=?, category_id=?, costruttore=?, fornitore=?, codice_fornitore=?, quantity=?, location_id=?, compartment_id=?, datasheet_url=?, equivalents=?, notes=?
            WHERE id=?");
    $stmt->execute([$codice_prodotto, $category_id, $costruttore, $fornitore, $codice_fornitore, $quantity, $location_id, $compartment_id, $datasheet_url, $equivalents, $notes, $id]);
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

  <form method="post" class="card shadow-sm p-4">
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
      <div class="col-md-6 mb-3">
        <label class="form-label">Quantità</label>
        <input type="number" name="quantity" class="form-control" value="<?= $component['quantity'] ?>">
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