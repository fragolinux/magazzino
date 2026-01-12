<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:50:58 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-12 15:23:22
*/
// 2026-01-03: Aggiunta funzionalità carico/scarico quantità componente
// 2026-01-05: Aggiunta ricerca tramite equivalente del codice prodotto
// 2026-01-08: Aggiunta quantità minima nel modal
// 2026-01-08: Aggiunto filtro per locale
// 2026-01-09: Aggiunto bottone per eliminare tutti i filtri
// 2026-01-09: Impostazione focus sul campo di ricerca all'apertura della pagina
// 2026-01-09: Aggiunta gestione immagine componente
// 2026-01-12: Aggiunti filtri per package, tensione, corrente, potenza, hfe e tags

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Recupero locali, posizioni e categorie
$locali = $pdo->query("SELECT * FROM locali ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Trova il locale con ID più basso per la selezione predefinita
$lowestIdLocale = $pdo->query("SELECT id FROM locali ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$defaultLocaleId = $lowestIdLocale ? $lowestIdLocale['id'] : null;
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>

<div class="container py-1">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fa-solid fa-microchip me-2"></i>Componenti</h2>
        <a href="add_component.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Aggiungi componente
        </a>
    </div>

    <!-- Filtri -->
    <div class="row g-3 mb-2">
        <div class="col-md-3">
            <select id="filter-locale" class="form-select">
                <option value="">-- Filtra per locale --</option>
                <?php foreach ($locali as $locale): ?>
                    <option value="<?= $locale['id'] ?>"><?= htmlspecialchars($locale['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select id="filter-location" class="form-select">
                <option value="">-- Filtra per posizione --</option>
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
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <button type="button" id="clear-filters" class="btn btn-outline-secondary w-100">
                <i class="fa-solid fa-filter-circle-xmark me-1"></i>Elimina filtri
            </button>
        </div>
        <div class="col-md-6 offset-md-3">
            <input type="text" id="search-code" class="form-control border border-primary border-3" placeholder="Cerca per codice prodotto...">
        </div>
    </div>

    <!-- Ricerca avanzata (accordion) -->
    <div class="accordion mb-3" id="advancedSearchAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingAdvanced">
                <button class="accordion-button collapsed py-1" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
                    <i class="fa-solid fa-sliders me-2"></i>Ricerca avanzata
                </button>
            </h2>
            <div id="collapseAdvanced" class="accordion-collapse collapse" aria-labelledby="headingAdvanced" data-bs-parent="#advancedSearchAccordion">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Package</label>
                            <input type="text" id="filter-package" class="form-control" placeholder="Es. TO-220, SO-8" list="packageList">
                            <datalist id="packageList">
                                <option value="TO-220">
                                <option value="TO-92">
                                <option value="TO-126">
                                <option value="TO-247">
                                <option value="SO-8">
                                <option value="SO-16">
                                <option value="DIP-8">
                                <option value="DIP-14">
                                <option value="DIP-16">
                                <option value="SMD">
                                <option value="0805">
                                <option value="1206">
                                <option value="SOT-23">
                            </datalist>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tensione (V)</label>
                            <input type="text" id="filter-tensione" class="form-control" placeholder="Es. 5, 12">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Corrente (A)</label>
                            <input type="text" id="filter-corrente" class="form-control" placeholder="Es. 1, 0.5">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Potenza (W)</label>
                            <input type="text" id="filter-potenza" class="form-control" placeholder="Es. 1, 0.25">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">hFE</label>
                            <input type="text" id="filter-hfe" class="form-control" placeholder="Es. 100">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Tags</label>
                            <input type="text" id="filter-tags" class="form-control" placeholder="Cerca per tag...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Note</label>
                            <input type="text" id="filter-notes" class="form-control" placeholder="Cerca nelle note...">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabella componenti -->
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-center" style="width: 60px;">Img</th>
                    <th>Codice prodotto</th>
                    <th>Categoria</th>
                    <th>Quantità</th>
                    <th>Posizione</th>
                    <th>Comparto</th>
                    <th class="text-end">Azioni</th>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function(){
    function loadLocations(locale_id) {
        let locSelect = $('#filter-location');
        locSelect.html('<option>Caricamento...</option>');
        $('#filter-compartment').html('<option value="">-- Filtra per comparto --</option>');
        
        if(locale_id){
            $.getJSON('get_locations.php', {locale_id: locale_id}, function(data){
                let options = '<option value="">-- Filtra per posizione --</option>';
                $.each(data, function(i, item){
                    options += '<option value="'+item.id+'">'+item.name+'</option>';
                });
                locSelect.html(options);
            });
        } else {
            locSelect.html('<option value="">-- Filtra per posizione --</option>');
        }
    }
    
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
        let locale_id = $('#filter-locale').val();
        let loc_id = $('#filter-location').val();
        let cmp_id = $('#filter-compartment').val();
        let cat_id = $('#filter-category').val();
        let search = $('#search-code').val();
        let package = $('#filter-package').val();
        let tensione = $('#filter-tensione').val();
        let corrente = $('#filter-corrente').val();
        let potenza = $('#filter-potenza').val();
        let hfe = $('#filter-hfe').val();
        let tags = $('#filter-tags').val();
        let notes = $('#filter-notes').val();

        $('#components-body').html('<tr><td colspan="7" class="text-center text-muted">Caricamento...</td></tr>');
        $.get('get_components.php', {
            locale_id: locale_id,
            location_id: loc_id,
            compartment_id: cmp_id,
            category_id: cat_id,
            search_code: search,
            package: package,
            tensione: tensione,
            corrente: corrente,
            potenza: potenza,
            hfe: hfe,
            tags: tags,
            notes: notes
        }, function(data){
            $('#components-body').html(data);
        });
    }

    // Eventi filtri
    $('#filter-locale').change(function(){
        loadLocations($(this).val());
        loadComponents();
    });
    $('#filter-location').change(function(){
        loadCompartments($(this).val());
        loadComponents();
    });
    $('#filter-compartment, #filter-category').change(loadComponents);
    $('#search-code').on('keyup', loadComponents);
    
    // Eventi filtri ricerca avanzata
    $('#filter-package, #filter-tensione, #filter-corrente, #filter-potenza, #filter-hfe, #filter-tags, #filter-notes').on('keyup', loadComponents);

    // Gestione parametri GET dall'URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlLocaleId = urlParams.get('locale_id');
    const urlLocationId = urlParams.get('location_id');
    const urlCompartmentId = urlParams.get('compartment_id');
    const urlCategoryId = urlParams.get('category_id');
    const urlSearchCode = urlParams.get('search_code');

    // Funzione per inizializzare i filtri da URL
    function initializeFromURL() {
        let promise;
        
        // Se c'è compartment_id, devo risalire a location_id e locale_id
        if (urlCompartmentId) {
            promise = new Promise(function(resolve) {
                $.getJSON('get_compartment_details.php', {id: urlCompartmentId}, function(comp) {
                    if (comp && comp.location_id) {
                        // Carica dettagli della location
                        $.getJSON('get_location_details.php', {id: comp.location_id}, function(loc) {
                            if (loc && loc.locale_id) {
                                // Ha un locale, carica la cascata completa
                                $('#filter-locale').val(loc.locale_id);
                                loadLocationsSync(loc.locale_id).then(function() {
                                    $('#filter-location').val(comp.location_id);
                                    loadCompartmentsSync(comp.location_id).then(function() {
                                        $('#filter-compartment').val(urlCompartmentId);
                                        resolve();
                                    });
                                });
                            } else {
                                // Nessun locale, carica solo location e compartment
                                loadCompartmentsSync(comp.location_id).then(function() {
                                    $('#filter-location').val(comp.location_id);
                                    $('#filter-compartment').val(urlCompartmentId);
                                    resolve();
                                });
                            }
                        });
                    } else {
                        resolve();
                    }
                });
            });
        } else if (urlLocationId) {
            // Se c'è solo location_id, risali al locale
            promise = new Promise(function(resolve) {
                $.getJSON('get_location_details.php', {id: urlLocationId}, function(loc) {
                    if (loc && loc.locale_id) {
                        $('#filter-locale').val(loc.locale_id);
                        loadLocationsSync(loc.locale_id).then(function() {
                            $('#filter-location').val(urlLocationId);
                            loadCompartmentsSync(urlLocationId).then(function() {
                                resolve();
                            });
                        });
                    } else {
                        $('#filter-location').val(urlLocationId);
                        loadCompartmentsSync(urlLocationId).then(function() {
                            resolve();
                        });
                    }
                });
            });
        } else if (urlLocaleId) {
            // Solo locale
            $('#filter-locale').val(urlLocaleId);
            promise = loadLocationsSync(urlLocaleId);
        } else {
            promise = Promise.resolve();
        }
        
        if (urlCategoryId) {
            $('#filter-category').val(urlCategoryId);
        }
        
        if (urlSearchCode) {
            $('#search-code').val(urlSearchCode);
        }
        
        // Dopo aver impostato tutti i filtri, carica i componenti
        promise.then(function() {
            loadComponents();
        });
    }
    
    // Versioni sincrone (con Promise) delle funzioni di caricamento
    function loadLocationsSync(locale_id) {
        return new Promise(function(resolve) {
            if (locale_id) {
                $.getJSON('get_locations.php', {locale_id: locale_id}, function(data) {
                    let options = '<option value="">-- Filtra per posizione --</option>';
                    $.each(data, function(i, item) {
                        options += '<option value="'+item.id+'">'+item.name+'</option>';
                    });
                    $('#filter-location').html(options);
                    resolve();
                });
            } else {
                $('#filter-location').html('<option value="">-- Filtra per posizione --</option>');
                resolve();
            }
        });
    }
    
    function loadCompartmentsSync(location_id) {
        return new Promise(function(resolve) {
            if (location_id) {
                $.getJSON('get_compartments.php', {location_id: location_id}, function(data) {
                    let options = '<option value="">-- Filtra per comparto --</option>';
                    $.each(data, function(i, item) {
                        options += '<option value="'+item.id+'">'+item.code+'</option>';
                    });
                    $('#filter-compartment').html(options);
                    resolve();
                });
            } else {
                $('#filter-compartment').html('<option value="">-- Filtra per comparto --</option>');
                resolve();
            }
        });
    }
    
    // Inizializza da URL se ci sono parametri
    if (urlLocaleId || urlLocationId || urlCompartmentId || urlCategoryId || urlSearchCode) {
        initializeFromURL();
    } else {
        // Se non ci sono parametri, seleziona il locale con ID più basso senza caricare
        <?php if ($defaultLocaleId): ?>
        $('#filter-locale').val(<?= $defaultLocaleId ?>);
        loadLocationsSync(<?= $defaultLocaleId ?>);
        <?php endif; ?>
        // Imposta il focus sul campo di ricerca
        $('#search-code').focus();
    }

    // Pulsante per eliminare tutti i filtri
    $('#clear-filters').click(function() {
        $('#filter-locale').val('');
        $('#filter-location').html('<option value="">-- Filtra per posizione --</option>');
        $('#filter-compartment').html('<option value="">-- Filtra per comparto --</option>');
        $('#filter-category').val('');
        $('#filter-package').val('');
        $('#filter-tensione').val('');
        $('#filter-corrente').val('');
        $('#filter-potenza').val('');
        $('#filter-hfe').val('');
        $('#filter-tags').val('');
        $('#filter-notes').val('');
        loadComponents(); // Ricarica la tabella con solo il filtro di ricerca se presente
    });

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

    // Eliminazione componente con AJAX
    $(document).on('click', '.btn-delete', function(){
        const id = $(this).data('id');
        const productName = $(this).data('product');
        
        if(!confirm('Sei sicuro di voler eliminare ' + productName + '?')) {
            return;
        }
        
        $.ajax({
            url: 'delete_component.php?id=' + id,
            type: 'GET',
            dataType: 'json',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            success: function(data) {
                if(data.success) {
                    loadComponents(); // Ricarica solo la tabella mantenendo i filtri
                } else {
                    alert('Errore durante l\'eliminazione: ' + (data.message || 'Errore sconosciuto'));
                }
            },
            error: function() {
                alert('Errore di comunicazione con il server');
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>