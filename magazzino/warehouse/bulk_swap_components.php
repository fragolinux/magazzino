<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-21 16:05:32 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-02 18:00:44
*/
// 2026-02-01: aggiunto locale nella select delle posizioni

require_once '../config/base_path.php';
require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Accesso solo admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Messaggi sessione
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Carica posizioni
$locations = $pdo->query("SELECT l.id, l.name, loc.name AS locale_name FROM locations l LEFT JOIN locali loc ON l.locale_id = loc.id ORDER BY l.name ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $locA = intval($_POST['location_a']);
    $cmpA = intval($_POST['compartment_a']);
    $locB = intval($_POST['location_b']);
    $cmpB = intval($_POST['compartment_b']);

    if (!$locA || !$cmpA || !$locB || !$cmpB) {
        $_SESSION['error'] = "Tutti i campi sono obbligatori.";
        header("Location: bulk_swap_components.php");
        exit;
    }

    if ($cmpA === $cmpB) {
        $_SESSION['error'] = "I comparti selezionati devono essere diversi.";
        header("Location: bulk_swap_components.php");
        exit;
    }

    $pdo->beginTransaction();

    try {
        // Crea una tabella temporanea in memoria con gli ID dei componenti di A
        $pdo->exec("CREATE TEMPORARY TABLE tmp_ids (id INT PRIMARY KEY)");
        $pdo->prepare("INSERT INTO tmp_ids (id) SELECT id FROM components WHERE compartment_id = ?")->execute([$cmpA]);

        // Sposta componenti del comparto A → nel comparto B
        $stmt1 = $pdo->prepare("UPDATE components SET location_id = ?, compartment_id = ? WHERE id IN (SELECT id FROM tmp_ids)");
        $stmt1->execute([$locB, $cmpB]);

        // Sposta componenti del comparto B → nel comparto A
        $stmt2 = $pdo->prepare("UPDATE components SET location_id = ?, compartment_id = ? WHERE compartment_id = ?");
        $stmt2->execute([$locA, $cmpA, $cmpB]);

        // Ora sposta indietro i componenti originari di A (che abbiamo in tmp_ids)
        $stmt3 = $pdo->prepare("UPDATE components SET location_id = ?, compartment_id = ? WHERE id IN (SELECT id FROM tmp_ids)");
        $stmt3->execute([$locB, $cmpB]);

        $pdo->commit();
        $_SESSION['success'] = "Scambio completato tra i due comparti selezionati.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Errore durante lo scambio: " . htmlspecialchars($e->getMessage());
    }

    header("Location: bulk_swap_components.php");
    exit;
}

include '../includes/header.php';
?>

<div class="container py-4">
  <h2><i class="fa-solid fa-right-left me-2"></i>Scambia componenti tra comparti</h2>
  <p class="text-muted">Scambia tutti i componenti tra due comparti, anche in posizioni diverse.</p>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <form method="post" id="swapForm" class="card p-4 shadow-sm">
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <h5><i class="fa-solid fa-box-open me-2"></i>Comparto A</h5>
        <div class="mb-3">
          <label class="form-label">Posizione</label>
          <select name="location_a" id="location_a" class="form-select" required>
            <option value="">-- Seleziona posizione --</option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?> - <?= htmlspecialchars($loc['locale_name'] ?? 'Senza locale') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Comparto</label>
          <select name="compartment_a" id="compartment_a" class="form-select" required>
            <option value="">-- Seleziona comparto --</option>
          </select>
        </div>
      </div>

      <div class="col-md-6">
        <h5><i class="fa-solid fa-box-archive me-2"></i>Comparto B</h5>
        <div class="mb-3">
          <label class="form-label">Posizione</label>
          <select name="location_b" id="location_b" class="form-select" required>
            <option value="">-- Seleziona posizione --</option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?> - <?= htmlspecialchars($loc['locale_name'] ?? 'Senza locale') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Comparto</label>
          <select name="compartment_b" id="compartment_b" class="form-select" required>
            <option value="">-- Seleziona comparto --</option>
          </select>
        </div>
      </div>
    </div>

    <div class="text-end">
      <button type="submit" class="btn btn-danger" onclick="return confirmSwap()">
        <i class="fa-solid fa-right-left me-1"></i> Scambia componenti
      </button>
    </div>
  </form>
</div>

<script>
function confirmSwap() {
  const aLoc = document.querySelector('#location_a option:checked').textContent.trim();
  const aCmp = document.querySelector('#compartment_a option:checked').textContent.trim();
  const bLoc = document.querySelector('#location_b option:checked').textContent.trim();
  const bCmp = document.querySelector('#compartment_b option:checked').textContent.trim();
  return confirm(`Confermi di scambiare tutti i componenti tra:\n- "${aCmp}" (${aLoc})\n- "${bCmp}" (${bLoc}) ?`);
}

$(document).ready(function(){
  function loadCompartments(locationSelect, compartmentSelect) {
    const locationId = $(locationSelect).val();
    if (!locationId) {
      $(compartmentSelect).html('<option value="">-- Seleziona comparto --</option>');
      return;
    }
    $.getJSON('<?= BASE_PATH ?>warehouse/get_compartments.php', {location_id: locationId}, function(data){
      let html = '<option value="">-- Seleziona comparto --</option>';
      $.each(data.compartments, function(_, cmp) {
        html += `<option value="${cmp.id}">${cmp.code}</option>`;
      });
      $(compartmentSelect).html(html);
    });
  }

  $('#location_a').change(function() { loadCompartments('#location_a', '#compartment_a'); });
  $('#location_b').change(function() { loadCompartments('#location_b', '#compartment_b'); });
});
</script>

<?php include '../includes/footer.php'; ?>