<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-08 18:50:13 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-08 20:17:16
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Recupero posizioni e categorie
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>

<div id="header-print-section">
    <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
        <img src="/magazzino/assets/img/logo.jpg" alt="logo" style="height:40px;" class="me-3">
        <span class="ms-auto text-muted">Utente: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
    </div>
</div>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Componenti sotto scorta</h2>
        <div class="print-hide">
            <button class="btn btn-primary btn-sm me-2" id="btn-print" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Stampa
            </button>
            <a href="components.php" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Torna a componenti
            </a>
        </div>
    </div>

    <!-- Filtri -->
    <div id="filters-section">
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
    </div>

    <!-- Tabella componenti sotto scorta -->
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>Codice prodotto</th>
                    <th>Categoria</th>
                    <th>Quantità</th>
                    <th>Q.tà minima</th>
                    <th>Posizione</th>
                    <th>Comparto</th>
                    <th class="text-end print-hide">Azioni</th>
                </tr>
            </thead>
            <tbody id="components-body">
                <tr><td colspan="7" class="text-center text-muted">Seleziona un filtro o cerca un componente.</td></tr>
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

<style type="text/css">
    /* Nascondi l'header di stampa sul web */
    #header-print-section {
        display: none;
    }

    @media print {
        .navbar { display: none; }
        #header-print-section { display: block; }
        
        /* Nascondi filtri e pulsanti */
        #filters-section { display: none; }
        .print-hide { display: none; }
        .btn-print { display: none; }
        
        /* Stili tabella per stampa */
        body { margin: 0; padding: 0; }
        .container { 
            margin: 0;
            padding: 10px 15px;
            max-width: 100%;
        }
        .table { font-size: 12px; }
        .table th { background-color: #f5f5f5; border: 1px solid #ddd; }
        .table td { border: 1px solid #ddd; }
    }
</style>

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

        $('#components-body').html('<tr><td colspan="7" class="text-center text-muted">Caricamento...</td></tr>');
        $.get('get_low_stock.php', {
            location_id: loc_id,
            compartment_id: cmp_id,
            category_id: cat_id,
            search_code: search
        }, function(data){
            $('#components-body').html(data);
        });
    }

    // Carica i componenti al caricamento della pagina
    loadComponents();

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