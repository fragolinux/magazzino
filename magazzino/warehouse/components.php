<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:50:58 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-03 09:59:11
*/
// 2026-01-03: Aggiunta funzionalità carico/scarico quantità componente

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Recupero posizioni e categorie
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fa-solid fa-microchip me-2"></i>Componenti</h2>
        <a href="add_component.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Aggiungi componente
        </a>
    </div>

    <!-- Filtri -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <select id="filter-location" class="form-select">
                <option value="">-- Filtra per posizione --</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select id="filter-compartment" class="form-select">
                <option value="">-- Filtra per comparto --</option>
            </select>
        </div>
        <div class="col-md-3">
            <select id="filter-category" class="form-select">
                <option value="">-- Filtra per categoria --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" id="search-code" class="form-control" placeholder="Cerca per codice prodotto...">
        </div>
    </div>

    <!-- Tabella componenti -->
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>Codice prodotto</th>
                    <th>Categoria</th>
                    <th>Quantità</th>
                    <th>Posizione</th>
                    <th>Comparto</th>
                    <th class="text-end">Azioni</th>
                </tr>
            </thead>
            <tbody id="components-body">
                <tr><td colspan="6" class="text-center text-muted">Seleziona un filtro o cerca un componente.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Dettagli -->
<div class="modal fade" id="componentModal" tabindex="-1" aria-labelledby="componentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="componentModalLabel"><i class="fa-solid fa-eye me-2"></i>Dettagli componente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        <div id="component-details" class="text-muted">Caricamento...</div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Carico/Scarico -->
<div class="modal fade" id="unloadModal" tabindex="-1" aria-labelledby="unloadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="unloadModalLabel"><i class="fa-solid fa-arrows-up-down me-2"></i>Carico/Scarico</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Prodotto:</label>
          <p class="form-control-plaintext fw-bold" id="unload-product-name"></p>
        </div>
        <div class="mb-3">
          <label class="form-label">Quantità attuale:</label>
          <p class="form-control-plaintext fw-bold" id="unload-current-qty"></p>
        </div>
        <div class="mb-3">
          <label class="form-label">Operazione:</label>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="operation" id="operation-unload" value="unload" checked>
            <label class="form-check-label" for="operation-unload">Scarico</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="operation" id="operation-load" value="load">
            <label class="form-check-label" for="operation-load">Carico</label>
          </div>
        </div>
        <div class="mb-3">
          <label for="unload-new-qty" class="form-label">Quantità da scaricare o caricare:</label>
          <input type="number" class="form-control" id="unload-new-qty" min="0" placeholder="Inserisci la quantità">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-success" id="unload-confirm">Conferma</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function(){
    function loadCompartments(location_id) {
        let compSelect = $('#filter-compartment');
        compSelect.html('<option>Caricamento...</option>');
        if(location_id){
            $.getJSON('get_compartments.php', {location_id: location_id}, function(data){
                let options = '<option value="">-- Filtra per comparto --</option>';
                $.each(data, function(i, item){
                    options += '<option value="'+item.id+'">'+item.code+'</option>';
                });
                compSelect.html(options);
            });
        } else {
            compSelect.html('<option value="">-- Filtra per comparto --</option>');
        }
    }

    function loadComponents() {
        let loc_id = $('#filter-location').val();
        let cmp_id = $('#filter-compartment').val();
        let cat_id = $('#filter-category').val();
        let search = $('#search-code').val();

        $('#components-body').html('<tr><td colspan="6" class="text-center text-muted">Caricamento...</td></tr>');
        $.get('get_components.php', {
            location_id: loc_id,
            compartment_id: cmp_id,
            category_id: cat_id,
            search_code: search
        }, function(data){
            $('#components-body').html(data);
        });
    }

    // Eventi filtri
    $('#filter-location').change(function(){
        loadCompartments($(this).val());
        loadComponents();
    });
    $('#filter-compartment, #filter-category').change(loadComponents);
    $('#search-code').on('keyup', loadComponents);

    // Modal dettagli componente
    $(document).on('click', '.btn-view', function(){
        const id = $(this).data('id');
        $('#component-details').html('Caricamento...');
        $('#componentModal').modal('show');
        $.get('view_component.php', {id: id}, function(data){
            $('#component-details').html(data);
        });
    });

    // Modal carico/scarico quantità
    let unloadComponentId = null;
    $(document).on('click', '.btn-unload', function(){
        unloadComponentId = $(this).data('id');
        const productName = $(this).data('product');
        const currentQty = $(this).data('quantity');
        
        $('#unload-product-name').text(productName);
        $('#unload-current-qty').text(currentQty);
        $('#unload-new-qty').val('');
        $('#operation-unload').prop('checked', true).focus();
        
        $('#unloadModal').modal('show');
    });

    $('#unload-confirm').click(function(){
        const quantityInput = $('#unload-new-qty').val();
        const operation = $('input[name="operation"]:checked').val();
        
        if(quantityInput === '' || isNaN(quantityInput)) {
            alert('Inserisci una quantità valida.');
            return;
        }
        
        const quantity = parseInt(quantityInput);
        if(quantity < 0) {
            alert('La quantità non può essere negativa.');
            return;
        }
        
        // Invia la richiesta di aggiornamento
        $.ajax({
            url: 'update_component_quantity.php',
            type: 'POST',
            dataType: 'json',
            data: {
                id: unloadComponentId,
                quantity: quantity,
                operation: operation
            },
            success: function(data) {
                if(data.success) {
                    $('#unloadModal').modal('hide');
                    loadComponents(); // Ricarica la tabella
                } else {
                    alert('Errore: ' + data.message);
                }
            },
            error: function(xhr, status, error) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    alert('Errore: ' + data.message);
                } catch(e) {
                    alert('Errore di comunicazione con il server');
                }
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>