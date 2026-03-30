<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:50:58 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2026-03-29 22:04:39
*/
// 2026-01-03: Aggiunta funzionalità carico/scarico quantità componente
// 2026-01-05: Aggiunta ricerca tramite equivalente del codice prodotto
// 2026-01-08: Aggiunta quantità minima nel modal
// 2026-01-08: Aggiunto filtro per locale
// 2026-01-09: Aggiunto bottone per eliminare tutti i filtri
// 2026-01-09: Impostazione focus sul campo di ricerca all'apertura della pagina
// 2026-01-09: Aggiunta gestione immagine componente
// 2026-01-12: Aggiunti filtri per package, tensione, corrente, potenza, hfe e tags
// 2026-02-08: Aggiunto id del componente nel modal dei dettagli
// 2026-02-09: Aggiunta funzionalità di clonazione componente (apre add_component.php in nuova finestra)
// 2026-02-09: Aggiunto ordinamento per codice prodotto, categoria, comparto, quantità e posizione
// 2026-02-23: gestione passaggio parametri GET migliorata per precompilare i filtri di posizione, comparto e categoria
// 2026-02-25: Aggiunta funzionalità per ricordare i filtri selezionati al ritorno alla pagina (tramite localStorage e selettore "Ricorda valori")
// 2026-03-07 (Andrea Gonzo) aggiunta gestione carico/scarico magazzino (modificati modal)
// 2026-03-29 (Andrea Gonzo) aggiornamento gestione carico/scarico magazzino, aggiunti bottoni edit, cancella e esportazione pdf

require_once '../config/base_path.php';
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
    <button type="button" id="btn-add-component" class="btn btn-primary">
      <i class="fa-solid fa-plus"></i> Aggiungi componente
    </button>
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
    <div class="col-md-3">
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" role="switch" name="ricorda_valori_ritorno" id="ricorda_val_ritorno" value="1" checked="" style="cursor: pointer;">
        <label class="form-check-label" for="ricorda_val_ritorno" style="cursor: pointer; user-select: none;">Ricorda valori</label>
      </div>
    </div>
    <div class="col-md-6">
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
    <table class="table table-striped table-sm align-middle" id="components-table">
      <thead class="table-light">
        <tr>
          <th class="text-center" style="width: 60px;">Img</th>
          <th class="sortable" data-sort="codice_prodotto" style="cursor: pointer; user-select: none;">
            Codice prodotto <span class="sort-icon"></span>
          </th>
          <th class="sortable" data-sort="category" style="cursor: pointer; user-select: none;">
            Categoria <span class="sort-icon"></span>
          </th>
          <th class="sortable" data-sort="quantity" style="cursor: pointer; user-select: none;">
            Quantità <span class="sort-icon"></span>
          </th>
          <th class="sortable" data-sort="location" style="cursor: pointer; user-select: none;">
            Posizione <span class="sort-icon"></span>
          </th>
          <th class="sortable" data-sort="compartment" style="cursor: pointer; user-select: none;">
            Comparto <span class="sort-icon"></span>
          </th>
          <th class="text-end">Azioni</th>
        </tr>
      </thead>
      <tbody id="components-body">
        <tr>
          <td colspan="7" class="text-center text-muted">Seleziona un filtro o cerca un componente.</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Dettagli MODIFICATO (Andrea) -->
<div class="modal fade" id="componentModal" tabindex="-1" aria-labelledby="componentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="componentModalLabel">
          <i class="fa-solid fa-eye me-2"></i>Dettagli componente
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        <div id="component-details" class="text-muted">Caricamento...</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="btn-view-all-movements">
          <i class="fa-solid fa-list"></i> Vedi tutti i movimenti
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Tutti Movimenti Magazzino (Andrea) -->
<div class="modal fade" id="allMovementsModal" tabindex="-1" aria-labelledby="allMovementsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="allMovementsModalLabel">
          <i class="fa-solid fa-list"></i> Tutti i movimenti di magazzino
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-2">
          <div class="col-md-4">
            <label class="form-label">Da data</label>
            <input type="date" id="filter-date-from" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">A data</label>
            <input type="date" id="filter-date-to" class="form-control">
          </div>
          <div class="col-md-4 d-flex align-items-end justify-content-between">
            <button class="btn btn-secondary flex-grow-1 me-2" id="btn-reset-filters">
              <i class="fa-solid fa-rotate-left"></i> Reset filtri
            </button>
            <button class="btn btn-success flex-grow-1 ms-2" id="btn-export-pdf">
              <i class="fa-solid fa-file-pdf"></i> Esporta PDF
            </button>
          </div>
        </div>
        <div
          id="all-movements-container"
          style="width:100%; border:1px solid #ddd; border-radius:6px; padding:10px; max-height:600px; overflow-y:auto;">
          <!-- Tutti i movimenti saranno caricati qui via JS -->
          <p class="text-muted">Caricamento...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Carico/Scarico MODIFICATO (Andrea)-->
<div class="modal fade" id="unloadModal" tabindex="-1" aria-labelledby="unloadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" style="max-width:950px;">
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
        <div class="mb-3">
          <label for="unload-comment" class="form-label">Commento (opzionale):</label>
          <input type="text" class="form-control" id="unload-comment" placeholder="Inserisci un commento..." autocomplete="off" maxlength="50">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-success" id="unload-confirm">Conferma</button>
      </div>
      <div class="mb-3">
        <h6 class="ms-3">Ultimi 10 movimenti</h6>
        <div
          id="movements-container"
          class="ms-3"
          style="width:96.5%; border:1px solid #ddd; border-radius:6px; padding:10px; max-height:200px; overflow-y:auto;">
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal modifica commento -->
<div class="modal fade" id="editCommentModal" tabindex="-1" aria-labelledby="editCommentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editCommentModalLabel">Modifica Commento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        <textarea id="edit-comment-text" class="form-control" rows="3" placeholder="Inserisci il commento..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annulla</button>
        <button type="button" id="edit-comment-save" class="btn btn-primary btn-sm">Salva</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal elimina movimento magazzino -->
<div class="modal fade" id="deleteMovementModal" tabindex="-1" aria-labelledby="deleteMovementModalLabel" aria-hidden="true">
  <div class="modal-dialog  modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteMovementModalLabel">Conferma eliminazione</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        Confermi di voler eliminare questo movimento?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-danger btn-sm" id="confirm-delete-movement">Elimina</button>
      </div>
    </div>
  </div>
</div>

<script>
  $(function() {
    function loadLocations(locale_id) {
      let locSelect = $('#filter-location');
      locSelect.html('<option>Caricamento...</option>');
      $('#filter-compartment').html('<option value="">-- Filtra per comparto --</option>');

      if (locale_id) {
        $.getJSON('<?= BASE_PATH ?>warehouse/get_locations.php', {
          locale_id: locale_id
        }, function(data) {
          let options = '<option value="">-- Filtra per posizione --</option>';
          $.each(data, function(i, item) {
            options += '<option value="' + item.id + '">' + item.name + '</option>';
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
      if (location_id) {
        $.getJSON('<?= BASE_PATH ?>warehouse/get_compartments.php', {
          location_id: location_id
        }, function(data) {
          let options = '<option value="">-- Filtra per comparto --</option>';
          $.each(data.compartments, function(i, item) {
            options += '<option value="' + item.id + '">' + item.code + '</option>';
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

      let requestData = {
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
      };

      // Aggiungi parametri di ordinamento se presenti
      if (currentSortColumn) {
        requestData.sort_column = currentSortColumn;
        requestData.sort_direction = currentSortDirection;
      }

      $.get('<?= BASE_PATH ?>warehouse/get_components.php', requestData, function(data) {
        $('#components-body').html(data);
      });
    }

    // Eventi filtri
    $('#filter-locale').change(function() {
      loadLocations($(this).val());
      loadComponents();
      saveFiltersToLocalStorage();
    });
    $('#filter-location').change(function() {
      loadCompartments($(this).val());
      loadComponents();
      saveFiltersToLocalStorage();
    });
    $('#filter-compartment, #filter-category').change(function() {
      loadComponents();
      saveFiltersToLocalStorage();
    });
    $('#search-code').on('keyup', function() {
      loadComponents();
      saveFiltersToLocalStorage();
    });

    // Eventi filtri ricerca avanzata
    $('#filter-package, #filter-tensione, #filter-corrente, #filter-potenza, #filter-hfe, #filter-tags, #filter-notes').on('keyup', function() {
      loadComponents();
      saveFiltersToLocalStorage();
    });

    // Variabili per l'ordinamento
    let currentSortColumn = '';
    let currentSortDirection = 'ASC';

    // Gestione click sulle intestazioni per ordinamento
    $(document).on('click', '.sortable', function() {
      const column = $(this).data('sort');

      // Se si clicca sulla stessa colonna, inverte la direzione
      if (currentSortColumn === column) {
        currentSortDirection = currentSortDirection === 'ASC' ? 'DESC' : 'ASC';
      } else {
        // Nuova colonna, ordine crescente di default
        currentSortColumn = column;
        currentSortDirection = 'ASC';
      }

      // Aggiorna le icone di ordinamento
      updateSortIcons();

      // Salva i filtri (incluso l'ordinamento) in localStorage
      saveFiltersToLocalStorage();

      // Ricarica i componenti con il nuovo ordinamento
      loadComponents();
    });

    // Funzione per aggiornare le icone di ordinamento
    function updateSortIcons() {
      $('.sortable').each(function() {
        const column = $(this).data('sort');
        const iconSpan = $(this).find('.sort-icon');

        if (column === currentSortColumn) {
          // Mostra icona in base alla direzione
          const icon = currentSortDirection === 'ASC' ? '<i class="fa-solid fa-sort-up"></i>' : '<i class="fa-solid fa-sort-down"></i>';
          iconSpan.html(' ' + icon);
          $(this).addClass('table-active');
        } else {
          // Icona neutra o vuota per le altre colonne
          iconSpan.html(' <i class="fa-solid fa-sort text-muted" style="opacity: 0.3;"></i>');
          $(this).removeClass('table-active');
        }
      });
    }

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
          $.getJSON('<?= BASE_PATH ?>warehouse/get_compartment_details.php', {
            id: urlCompartmentId
          }, function(comp) {
            if (comp && comp.location_id) {
              // Carica dettagli della location
              $.getJSON('<?= BASE_PATH ?>warehouse/get_location_details.php', {
                id: comp.location_id
              }, function(loc) {
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
          $.getJSON('<?= BASE_PATH ?>warehouse/get_location_details.php', {
            id: urlLocationId
          }, function(loc) {
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
          $.getJSON('<?= BASE_PATH ?>warehouse/get_locations.php', {
            locale_id: locale_id
          }, function(data) {
            let options = '<option value="">-- Filtra per posizione --</option>';
            $.each(data, function(i, item) {
              options += '<option value="' + item.id + '">' + item.name + '</option>';
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
          $.getJSON('<?= BASE_PATH ?>warehouse/get_compartments.php', {
            location_id: location_id
          }, function(data) {
            let options = '<option value="">-- Filtra per comparto --</option>';
            $.each(data.compartments, function(i, item) {
              options += '<option value="' + item.id + '">' + item.code + '</option>';
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

    // Funzione per salvare tutti i filtri in localStorage
    function saveFiltersToLocalStorage() {
      if ($('#ricorda_val_ritorno').is(':checked')) {
        const filters = {
          locale: $('#filter-locale').val(),
          location: $('#filter-location').val(),
          compartment: $('#filter-compartment').val(),
          category: $('#filter-category').val(),
          search: $('#search-code').val(),
          package: $('#filter-package').val(),
          tensione: $('#filter-tensione').val(),
          corrente: $('#filter-corrente').val(),
          potenza: $('#filter-potenza').val(),
          hfe: $('#filter-hfe').val(),
          tags: $('#filter-tags').val(),
          notes: $('#filter-notes').val(),
          sortColumn: currentSortColumn,
          sortDirection: currentSortDirection
        };
        localStorage.setItem('component_filters', JSON.stringify(filters));
      }
    }

    // Funzione per ripristinare i filtri dal localStorage
    function loadFiltersFromLocalStorage() {
      const savedFilters = localStorage.getItem('component_filters');
      if (savedFilters) {
        try {
          const filters = JSON.parse(savedFilters);

          // Ripristina i valori dei filtri
          if (filters.locale) {
            $('#filter-locale').val(filters.locale);
            loadLocationsSync(filters.locale).then(function() {
              if (filters.location) {
                $('#filter-location').val(filters.location);
                loadCompartmentsSync(filters.location).then(function() {
                  if (filters.compartment) {
                    $('#filter-compartment').val(filters.compartment);
                  }
                  if (filters.category) {
                    $('#filter-category').val(filters.category);
                  }
                  if (filters.search) {
                    $('#search-code').val(filters.search);
                  }
                  if (filters.package) {
                    $('#filter-package').val(filters.package);
                  }
                  if (filters.tensione) {
                    $('#filter-tensione').val(filters.tensione);
                  }
                  if (filters.corrente) {
                    $('#filter-corrente').val(filters.corrente);
                  }
                  if (filters.potenza) {
                    $('#filter-potenza').val(filters.potenza);
                  }
                  if (filters.hfe) {
                    $('#filter-hfe').val(filters.hfe);
                  }
                  if (filters.tags) {
                    $('#filter-tags').val(filters.tags);
                  }
                  if (filters.notes) {
                    $('#filter-notes').val(filters.notes);
                  }

                  // Ripristina l'ordinamento
                  if (filters.sortColumn) {
                    currentSortColumn = filters.sortColumn;
                    currentSortDirection = filters.sortDirection || 'ASC';
                    updateSortIcons();
                  }

                  // Carica i componenti con i filtri ripristinati
                  loadComponents();
                });
              } else {
                // Se non c'è location salvata, carica comunque gli altri filtri
                if (filters.category) {
                  $('#filter-category').val(filters.category);
                }
                if (filters.search) {
                  $('#search-code').val(filters.search);
                }
                if (filters.package) {
                  $('#filter-package').val(filters.package);
                }
                if (filters.tensione) {
                  $('#filter-tensione').val(filters.tensione);
                }
                if (filters.corrente) {
                  $('#filter-corrente').val(filters.corrente);
                }
                if (filters.potenza) {
                  $('#filter-potenza').val(filters.potenza);
                }
                if (filters.hfe) {
                  $('#filter-hfe').val(filters.hfe);
                }
                if (filters.tags) {
                  $('#filter-tags').val(filters.tags);
                }
                if (filters.notes) {
                  $('#filter-notes').val(filters.notes);
                }

                // Ripristina l'ordinamento
                if (filters.sortColumn) {
                  currentSortColumn = filters.sortColumn;
                  currentSortDirection = filters.sortDirection || 'ASC';
                  updateSortIcons();
                }

                // Carica i componenti con i filtri ripristinati
                loadComponents();
              }
            });
          } else {
            // Se non c'è locale salvato, carica solo gli altri filtri
            if (filters.location) {
              $('#filter-location').val(filters.location);
              loadCompartmentsSync(filters.location).then(function() {
                if (filters.compartment) {
                  $('#filter-compartment').val(filters.compartment);
                }
                if (filters.category) {
                  $('#filter-category').val(filters.category);
                }
                if (filters.search) {
                  $('#search-code').val(filters.search);
                }
                if (filters.package) {
                  $('#filter-package').val(filters.package);
                }
                if (filters.tensione) {
                  $('#filter-tensione').val(filters.tensione);
                }
                if (filters.corrente) {
                  $('#filter-corrente').val(filters.corrente);
                }
                if (filters.potenza) {
                  $('#filter-potenza').val(filters.potenza);
                }
                if (filters.hfe) {
                  $('#filter-hfe').val(filters.hfe);
                }
                if (filters.tags) {
                  $('#filter-tags').val(filters.tags);
                }
                if (filters.notes) {
                  $('#filter-notes').val(filters.notes);
                }

                // Ripristina l'ordinamento
                if (filters.sortColumn) {
                  currentSortColumn = filters.sortColumn;
                  currentSortDirection = filters.sortDirection || 'ASC';
                  updateSortIcons();
                }

                // Carica i componenti con i filtri ripristinati
                loadComponents();
              });
            } else {
              // Carica tutti i filtri tranne locale e location
              if (filters.compartment) {
                $('#filter-compartment').val(filters.compartment);
              }
              if (filters.category) {
                $('#filter-category').val(filters.category);
              }
              if (filters.search) {
                $('#search-code').val(filters.search);
              }
              if (filters.package) {
                $('#filter-package').val(filters.package);
              }
              if (filters.tensione) {
                $('#filter-tensione').val(filters.tensione);
              }
              if (filters.corrente) {
                $('#filter-corrente').val(filters.corrente);
              }
              if (filters.potenza) {
                $('#filter-potenza').val(filters.potenza);
              }
              if (filters.hfe) {
                $('#filter-hfe').val(filters.hfe);
              }
              if (filters.tags) {
                $('#filter-tags').val(filters.tags);
              }
              if (filters.notes) {
                $('#filter-notes').val(filters.notes);
              }

              // Ripristina l'ordinamento
              if (filters.sortColumn) {
                currentSortColumn = filters.sortColumn;
                currentSortDirection = filters.sortDirection || 'ASC';
                updateSortIcons();
              }

              // Carica i componenti con i filtri ripristinati
              loadComponents();
            }
          }
        } catch (e) {
          console.error('Errore nel ripristino dei filtri:', e);
        }
      }
    }

    // Gestione checkbox "Ricorda valori di ritorno"
    $('#ricorda_val_ritorno').change(function() {
      // Salva lo stato del checkbox in localStorage
      localStorage.setItem('ricorda_valori_ritorno', $(this).is(':checked') ? '1' : '0');

      if ($(this).is(':checked')) {
        // Se il checkbox è selezionato, salva i filtri correnti
        saveFiltersToLocalStorage();
      }
    });

    // Carica lo stato del checkbox dal localStorage all'avvio
    function loadCheckboxState() {
      const savedState = localStorage.getItem('ricorda_valori_ritorno');
      if (savedState === '1') {
        $('#ricorda_val_ritorno').prop('checked', true);
      } else {
        $('#ricorda_val_ritorno').prop('checked', false);
      }
    }

    // Carica lo stato del checkbox all'avvio
    loadCheckboxState();

    // Inizializza da URL se ci sono parametri
    if (urlLocaleId || urlLocationId || urlCompartmentId || urlCategoryId || urlSearchCode) {
      initializeFromURL();
    } else {
      // Se non ci sono parametri, controlla lo stato del checkbox
      if ($('#ricorda_val_ritorno').is(':checked')) {
        // Se il checkbox è selezionato, ripristina i filtri memorizzati
        loadFiltersFromLocalStorage();
      } else {
        // Se il checkbox non è selezionato e non ci sono parametri URL, esegui clear-filters
        const return_url = window.location.href;
        if (return_url.indexOf('?') === -1) {
          $('#clear-filters').click();
        }
      }
      // Imposta il focus sul campo di ricerca
      $('#search-code').focus();
    }

    // Inizializza le icone di ordinamento
    updateSortIcons();

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

      // Resetta anche l'ordinamento
      currentSortColumn = '';
      currentSortDirection = 'ASC';
      updateSortIcons();

      // Cancella i filtri memorizzati in localStorage se il checkbox è selezionato
      if ($('#ricorda_val_ritorno').is(':checked')) {
        localStorage.removeItem('component_filters');
      }

      loadComponents(); // Ricarica la tabella con solo il filtro di ricerca se presente
    });

    // Modal dettagli componente MODIFICATO (Andrea)
    $(document).on('click', '.btn-view', function() {
      const componentId = $(this).data('id'); // ID componente
      const productName = $(this).data('product'); // opzionale

      $('#component-details').html('Caricamento...');
      // Salvo ID sul modal
      $('#componentModal').data('component-id', componentId);

      // Mostro il modal
      $('#componentModal').modal('show');

      // Carico i dettagli via AJAX
      $.get('<?= BASE_PATH ?>warehouse/view_component.php', {
        id: componentId
      }, function(data) {
        $('#component-details').html(`
                    <div class="alert alert-secondary py-1 mb-2">
                        <small><strong>ID Componente:</strong> ${componentId}</small>
                    </div>
                    ${data}
                `);
      });
    });



    // Modal carico/scarico quantità
    let unloadComponentId = null;
    $(document).on('click', '.btn-unload', function() {
      unloadComponentId = $(this).data('id');
      const productName = $(this).data('product');
      const currentQty = $(this).data('quantity');

      $('#unload-product-name').text(productName);
      $('#unload-current-qty').text(currentQty);
      $('#unload-new-qty').val('');
      $('#operation-unload').prop('checked', true).focus();
      $('#unload-comment').val('');

      // Carica movimenti nel div (non più iframe)
      const container = document.getElementById('movements-container');
      container.innerHTML = '<p class="text-muted">Caricamento...</p>';

      fetch('<?= BASE_PATH ?>warehouse/ajax_movimento_componente.php?component_id=' + unloadComponentId + '&from=unload_modal')
        .then(res => res.text())
        .then(html => {
          container.innerHTML = html;
        })
        .catch(err => {
          container.innerHTML = '<p class="text-danger">Errore nel caricamento dei movimenti</p>';
          console.error(err);
        });

      // Mostra il modal
      $('#unloadModal').modal('show');
    });

    $('#unload-confirm').click(function() {
      const quantityInput = $('#unload-new-qty').val();
      const operation = $('input[name="operation"]:checked').val();
      const comment = $('#unload-comment').val();

      if (quantityInput === '' || isNaN(quantityInput)) {
        alert('Inserisci una quantità valida.');
        return;
      }

      const quantity = parseInt(quantityInput);
      if (quantity < 0) {
        alert('La quantità non può essere negativa.');
        return;
      }

      // Invia la richiesta di aggiornamento
      $.ajax({
        url: '<?= BASE_PATH ?>warehouse/update_component_quantity.php',
        type: 'POST',
        dataType: 'json',
        data: {
          id: unloadComponentId,
          quantity: quantity,
          operation: operation,
          comment: comment
        },
        success: function(data) {
          if (data.success) {
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
          } catch (e) {
            alert('Errore di comunicazione con il server');
          }
        }
      });
    });

    // Eliminazione componente con AJAX
    $(document).on('click', '.btn-delete', function() {
      const id = $(this).data('id');
      const productName = $(this).data('product');

      if (!confirm('Sei sicuro di voler eliminare ' + productName + '?')) {
        return;
      }

      $.ajax({
        url: '<?= BASE_PATH ?>warehouse/delete_component.php?id=' + id,
        type: 'GET',
        dataType: 'json',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(data) {
          if (data.success) {
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

    // Clonazione componente - salva in localStorage e apre in nuova pagina
    $(document).on('click', '.btn-clone', function() {
      const componentId = $(this).data('id');

      $.getJSON('<?= BASE_PATH ?>warehouse/get_component_json.php', {
        id: componentId
      }, function(data) {
        if (data.error) {
          alert('Errore: ' + data.error);
          return;
        }

        // Salva i dati del componente in localStorage
        localStorage.setItem('clone_component_data', JSON.stringify(data));

        // Apre add_component.php nella stessa finestra
        window.location.href = '<?= BASE_PATH ?>warehouse/add_component.php';
      }).fail(function() {
        alert('Errore durante il caricamento dei dati del componente.');
      });
    });

    // Pulsante "Aggiungi componente" - apre con parametri dei filtri selezionati
    $('#btn-add-component').click(function() {
      // Raccogli i valori dei filtri selezionati
      const localeId = $('#filter-locale').val();
      const locationId = $('#filter-location').val();
      const compartmentId = $('#filter-compartment').val();
      const categoryId = $('#filter-category').val();

      // Costruisci l'URL con i parametri
      let url = 'add_component.php';
      const params = [];

      if (localeId) params.push('locale_id=' + encodeURIComponent(localeId));
      if (locationId) params.push('location_id=' + encodeURIComponent(locationId));
      if (compartmentId) params.push('compartment_id=' + encodeURIComponent(compartmentId));
      if (categoryId) params.push('category_id=' + encodeURIComponent(categoryId));

      if (params.length > 0) {
        url += '?' + params.join('&');
      }

      // Apri la pagina nella stessa finestra
      window.location.href = url;
    });

    // Pulsante "Modifica" - apre con parametri dei filtri selezionati come return_url
    $(document).on('click', '.btn-edit', function() {
      const componentId = $(this).data('id');

      // Raccogli i valori dei filtri selezionati
      const localeId = $('#filter-locale').val();
      const locationId = $('#filter-location').val();
      const compartmentId = $('#filter-compartment').val();
      const categoryId = $('#filter-category').val();

      // Costruisci il return_url con i parametri
      let return_url = 'components.php';
      const params = [];

      if (localeId) params.push('locale_id=' + encodeURIComponent(localeId));
      if (locationId) params.push('location_id=' + encodeURIComponent(locationId));
      if (compartmentId) params.push('compartment_id=' + encodeURIComponent(compartmentId));
      if (categoryId) params.push('category_id=' + encodeURIComponent(categoryId));

      if (params.length > 0) {
        return_url += '?' + params.join('&');
      }

      // Costruisci l'URL per la modifica
      let edit_url = 'edit_component.php?id=' + encodeURIComponent(componentId);
      edit_url += '&return_url=' + encodeURIComponent(return_url);

      // Apri la pagina nella stessa finestra
      window.location.href = edit_url;
    });
  });

  // Modal movimenti magazzino (Andrea)
  function loadAllMovements(componentId, showActions = false) {
    const from = $('#filter-date-from').val();
    const to = $('#filter-date-to').val();

    let url = '<?= BASE_PATH ?>warehouse/ajax_movimento_componente.php?component_id=' + componentId;
    if (from) url += '&date_from=' + from;
    if (to) url += '&date_to=' + to;
    if (showActions) url += '&show_actions=1'; // aggiunto parametro per colonna azioni

    const container = document.getElementById('all-movements-container');
    container.innerHTML = '<p class="text-muted">Caricamento...</p>';

    fetch(url)
      .then(res => res.text())
      .then(html => {
        container.innerHTML = html;
      })
      .catch(err => {
        container.innerHTML = '<p class="text-danger">Errore nel caricamento dei movimenti</p>';
        console.error(err);
      });
  }

  // Pulsante apri modal "tutti i movimenti"
  $('#btn-view-all-movements').on('click', function() {
    const componentId = $('#componentModal').data('component-id');
    if (!componentId) return;

    // reset filtri quando apri il modal
    $('#filter-date-from').val('');
    $('#filter-date-to').val('');

    // carica movimenti con colonna azioni
    loadAllMovements(componentId, true);

    $('#allMovementsModal').modal('show');
  });

  // Cambiamento filtri
  $('#filter-date-from, #filter-date-to').on('change', function() {
    const componentId = $('#componentModal').data('component-id');
    if (!componentId) return;

    // Per il modal "tutti i movimenti", manteniamo showActions = true
    loadAllMovements(componentId, true);
  });

  // Reset filtri data
  $('#btn-reset-filters').on('click', function() {
    const componentId = $('#componentModal').data('component-id');
    if (!componentId) return;

    $('#filter-date-from').val('');
    $('#filter-date-to').val('');

    loadAllMovements(componentId, true);
  });

  // Esporta Movimenti di Magazzino
  $('#btn-export-pdf').on('click', function() {
    const componentId = $('#componentModal').data('component-id');
    if (!componentId) return;

    const from = $('#filter-date-from').val();
    const to = $('#filter-date-to').val();

    let url = '<?= BASE_PATH ?>warehouse/export_movimenti.php?component_id=' + componentId;
    if (from) url += '&date_from=' + from;
    if (to) url += '&date_to=' + to;

    window.open(url, '_blank');
  });

  // Variabili globali per tenere traccia del movimento e componente correnti
  let currentComponentId = null;
  let currentMovimentoId = null;

  // Quando clicchi sul pulsante "Modifica commento"
  $(document).on('click', '[data-action="edit-comment"]', function() {
    currentComponentId = $(this).data('component'); // ID componente della riga
    currentMovimentoId = $(this).data('movimento-id'); // ID univoco del movimento

    // Pre-riempi il campo con il commento attuale
    const currentComment = $(this).data('comment') || '';
    $('#edit-comment-text').val(currentComment);

    // Mostra il modal
    const editModal = new bootstrap.Modal(document.getElementById('editCommentModal'));
    editModal.show();
  });

  // Quando clicchi "Salva" nel modal
  $('#edit-comment-save').click(function() {
    const nuovoCommento = $('#edit-comment-text').val().trim();

    if (nuovoCommento === '') {
      alert('Il commento non può essere vuoto.');
      return;
    }

    // Invia la richiesta AJAX al file di update
    $.ajax({
      url: '<?= BASE_PATH ?>warehouse/update_movimenti.php',
      type: 'POST',
      dataType: 'json',
      data: {
        movimento_id: currentMovimentoId,
        comment: nuovoCommento
      },
      success: function(data) {
        if (data.success) {
          // Chiudi il modal di modifica commento
          bootstrap.Modal.getInstance(document.getElementById('editCommentModal')).hide();

          // Prendi l'id del componente dal modal principale
          const componentId = $('#componentModal').data('component-id');
          if (componentId) {

            // Ricarica la lista dei movimenti con colonna azioni
            loadAllMovements(componentId, true);

            // Mostra il modal dei movimenti
            $('#allMovementsModal').modal('show');
          }
        } else {
          alert('Errore: ' + (data.message || 'Non è stato possibile aggiornare il commento.'));
        }
      },
      error: function() {
        alert('Errore di comunicazione con il server.');
      }
    });
  });

  // Pulsante "Elimina movimento"
  let movimentoIdToDelete = null;

  // Quando clicchi sul pulsante "Elimina movimento"
  $(document).on('click', '[data-action="delete-movement"]', function() {
    movimentoIdToDelete = $(this).data('movimento-id');
    if (!movimentoIdToDelete) return;

    // Apri il modal di conferma
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteMovementModal'));
    deleteModal.show();
  });

  // Quando clicchi "Elimina" nel modal
  $('#confirm-delete-movement').click(function() {
    if (!movimentoIdToDelete) return;

    $.ajax({
      url: '<?= BASE_PATH ?>warehouse/delete_movimento.php',
      type: 'POST',
      dataType: 'json',
      data: {
        movimento_id: movimentoIdToDelete
      },
      success: function(data) {
        if (data.success) {
          // Chiudi il modal di conferma
          bootstrap.Modal.getInstance(document.getElementById('deleteMovementModal')).hide();

          // Prendi id del componente
          const componentId = $('#componentModal').data('component-id');
          if (componentId) {
            // Reset filtri
            $('#filter-date-from').val('');
            $('#filter-date-to').val('');

            // Ricarica lista movimenti con azioni
            loadAllMovements(componentId, true);

            // Mostra il modal dei movimenti
            $('#allMovementsModal').modal('show');
          }
        } else {
          alert('Errore: ' + (data.message || 'Non è stato possibile eliminare il movimento.'));
        }
      },
      error: function() {
        alert('Errore di comunicazione con il server.');
      }
    });
  });
</script>

<?php include '../includes/footer.php'; ?>