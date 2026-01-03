<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-21 15:51:37 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-21 16:19:45
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Solo admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Recupera posizioni e comparti
$locations = $pdo->query("SELECT id, name FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $source_location = intval($_POST['source_location']);
    $source_compartment = intval($_POST['source_compartment']);
    $target_location = intval($_POST['target_location']);
    $target_compartment = intval($_POST['target_compartment']);

    if ($source_compartment && $target_compartment && $source_compartment !== $target_compartment) {
        try {
            $pdo->beginTransaction();

            // Recupera i componenti dal comparto di origine
            $stmt = $pdo->prepare("SELECT * FROM components WHERE compartment_id = ?");
            $stmt->execute([$source_compartment]);
            $components = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($components as $comp) {
                // Verifica se esiste già un componente con lo stesso codice nel comparto di destinazione
                $check = $pdo->prepare("SELECT id, quantity FROM components WHERE compartment_id = ? AND codice_prodotto = ?");
                $check->execute([$target_compartment, $comp['codice_prodotto']]);
                $existing = $check->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    // Somma quantità e aggiorna il componente esistente
                    $new_qty = $existing['quantity'] + $comp['quantity'];
                    $update = $pdo->prepare("UPDATE components SET quantity = ? WHERE id = ?");
                    $update->execute([$new_qty, $existing['id']]);

                    // Elimina il componente duplicato dal comparto di origine
                    $delete = $pdo->prepare("DELETE FROM components WHERE id = ?");
                    $delete->execute([$comp['id']]);
                } else {
                    // Aggiorna solo il compartment_id (spostamento semplice)
                    $update = $pdo->prepare("UPDATE components SET compartment_id = ?, location_id = ? WHERE id = ?");
                    $update->execute([$target_compartment, $target_location, $comp['id']]);
                }
            }

            $pdo->commit();
            $_SESSION['success'] = "Componenti spostati con successo da un comparto all'altro (quantità sommate se già presenti).";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Errore durante lo spostamento: " . $e->getMessage();
        }

        header("Location: bulk_move_components.php");
        exit;
    } else {
        $_SESSION['error'] = "Seleziona due comparti diversi.";
        header("Location: bulk_move_components.php");
        exit;
    }
}

include '../includes/header.php';
?>

<div class="container py-4">
  <h2><i class="fa-solid fa-right-left me-2"></i>Sposta componenti</h2>

  <?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>

  <form method="post" class="card p-3 shadow-sm">
    <div class="row g-3">
      <div class="col-md-6">
        <h5>Comparto di origine</h5>
        <div class="mb-2">
          <label class="form-label">Posizione</label>
          <select name="source_location" id="source_location" class="form-select" required>
            <option value="">-- Seleziona posizione --</option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Comparto</label>
          <select name="source_compartment" id="source_compartment" class="form-select" required>
            <option value="">-- Seleziona comparto --</option>
          </select>
        </div>
      </div>

      <div class="col-md-6">
        <h5>Comparto di destinazione</h5>
        <div class="mb-2">
          <label class="form-label">Posizione</label>
          <select name="target_location" id="target_location" class="form-select" required>
            <option value="">-- Seleziona posizione --</option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Comparto</label>
          <select name="target_compartment" id="target_compartment" class="form-select" required>
            <option value="">-- Seleziona comparto --</option>
          </select>
        </div>
      </div>
    </div>

    <div class="text-end mt-4">
      <button type="submit" class="btn btn-primary" onclick="return confirm('Confermi lo spostamento? Le quantità verranno sommate se i codici coincidono.')">
        <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Esegui spostamento
      </button>
    </div>
  </form>
</div>

<script>
$(document).ready(function(){
  function loadCompartments(selectId, locationId) {
    let select = $('#' + selectId);
    select.html('<option>Caricamento...</option>');
    if(locationId){
      $.getJSON('get_compartments.php', {location_id: locationId}, function(data){
        let options = '<option value="">-- Seleziona comparto --</option>';
        $.each(data, function(i, item){
          options += '<option value="'+item.id+'">'+item.code+'</option>';
        });
        select.html(options);
      });
    } else {
      select.html('<option value="">-- Seleziona comparto --</option>');
    }
  }

  $('#source_location').change(function(){
    loadCompartments('source_compartment', $(this).val());
  });
  $('#target_location').change(function(){
    loadCompartments('target_compartment', $(this).val());
  });
});
</script>

<?php include '../includes/footer.php'; ?>