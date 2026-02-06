<?php
/*
 * @Author: gabriele.riva
 * @Date: 2026-01-15
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-02 18:11:00
*/
// 2026-02-01: aggiunto locale nella select delle posizioni
// 2026-02-01: Aggiunti parametri Barcode nei setting
// 2026-02-02: Aggiunta possibilità di selezionare componenti senza comparto

require_once __DIR__ . '/../config/base_path.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

// solo admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
	http_response_code(403);
	echo "Accesso negato: permessi insufficienti.";
	exit;
}

// carica posizioni
$locations = $pdo->query("SELECT l.id, l.name, loc.name AS locale_name FROM locations l LEFT JOIN locali loc ON l.locale_id = loc.id ORDER BY l.name ASC")->fetchAll(PDO::FETCH_ASSOC);

?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2><i class="fa-solid fa-barcode me-2"></i>Genera Barcode</h2>
		<a href="<?= BASE_PATH ?>warehouse/components.php" class="btn btn-secondary">Torna a componenti</a>
	</div>

	<div class="card" style="max-width:900px;">
		<div class="card-body">
			<form id="barcodeForm" method="post" action="generate_barcodes.php" target="_blank">
				<div class="row g-3">
					<div class="col-md-6">
						<label for="location_id" class="form-label">Posizione</label>
						<select id="location_id" name="location_id" class="form-select">
							<option value="">-- Tutte le posizioni --</option>
							<?php foreach ($locations as $loc): ?>
								<option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?> - <?= htmlspecialchars($loc['locale_name'] ?? 'Senza locale') ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-6">
						<label class="form-label">Comparti</label>
						<div>
							<div id="select_all_container" class="form-check mb-2" style="display:none;">
								<input class="form-check-input" type="checkbox" id="select_all_compartments">
								<label class="form-check-label" for="select_all_compartments">Seleziona tutti i comparti</label>
							</div>
							<div id="compartments_container">
								<div class="form-text">Seleziona una posizione per caricare i comparti.</div>
							</div>
							<div id="total_container" class="form-text mt-2" style="display:none;">Componenti selezionati: <span id="total_components_selected">0</span></div>
						</div>
					</div>

					<div class="col-12">
						<label class="form-label">Opzioni stampa</label>
						<div class="form-check">
							<input class="form-check-input" type="checkbox" name="include_code_under_barcode" id="include_code_under_barcode" checked>
							<label class="form-check-label" for="include_code_under_barcode">Mostra il codice del prodotto sotto il barcode (opzionale)</label>
						</div>
					</div>

					<div class="col-12 text-end">
						<button id="generateBtn" type="submit" class="btn btn-primary" disabled>Genera Barcode</button>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Nota informativa -->
<div class="container mt-4" style="max-width:900px;">
	<div class="alert alert-info">
		<h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Come utilizzare i Barcode</h6>
		<small>
			I barcode vengono utilizzati per identificare univocamente ciascun componente nel magazzino. Ogni barcode contiene l'ID numerico del componente nel database.<br><br>
			<strong>Istruzioni per l'uso:</strong><br>
			1. <strong>Selezione componenti:</strong> scegli una categoria per filtrare i componenti, oppure seleziona manualmente quelli desiderati.<br>
			2. <strong>Generazione barcode:</strong> clicca su "Genera PDF Barcode" per creare un PDF con tutti i barcode selezionati.<br>
			3. <strong>Stampa e applicazione:</strong> stampa il PDF e applica i barcode sui componenti fisici nel magazzino.<br>
			4. <strong>Utilizzo:</strong> i barcode verranno utilizzati per identificare rapidamente i componenti durante le operazioni di carico/scarico tramite scanner barcode alla pagina <a href="<?= BASE_PATH ?>warehouse/barcode_scan.php">Barcode</a>.
		</small>
	</div>
</div>

<script>
$(function(){
	function loadCompartments(locationId){
		const container = $('#compartments_container');
		container.html('<div class="form-text">Caricamento...</div>');
		$.get('<?= BASE_PATH ?>warehouse/get_compartments.php', { location_id: locationId }, function(html){
			// get_compartments.php restituisce JSON con compartments e unassigned_count
			try {
				const data = JSON.parse(html);
				if (data.compartments !== undefined){
					let out = '';
					// Aggiungi checkbox per componenti senza comparto se presenti
					if (parseInt(data.unassigned_count || 0, 10) > 0){
						var unassignedCount = parseInt(data.unassigned_count, 10);
						out += '<div class="form-check">'
							+ '<input class="form-check-input unassigned-checkbox" type="checkbox" name="compartments[]" value="unassigned" id="cmp_unassigned" data-count="'+unassignedCount+'">'
							+ '<label class="form-check-label" for="cmp_unassigned"><strong>Componenti senza comparto</strong> <span class="text-muted">('+unassignedCount+' componenti)</span></label></div>';
						out += '<hr class="my-2">';
					}
					// Aggiungi checkbox per i comparti
					data.compartments.forEach(function(c){
						// get_compartments.php returns {id, code, description, components_count}
						var code = c.code || ('Compartimento ' + c.id);
						var desc = c.description ? (' - ' + c.description) : '';
						var count = parseInt(c.components_count || 0, 10);
						var cnt = (count > 0) ? (' <span class="text-muted">('+count+' componenti)</span>') : '';
						out += '<div class="form-check">'
							+ '<input class="form-check-input compartment-checkbox" type="checkbox" name="compartments[]" value="'+c.id+'" id="cmp_'+c.id+'" data-count="'+count+'">'
							+ '<label class="form-check-label" for="cmp_'+c.id+'"> '+code+desc+cnt+'</label></div>';
					});
					container.html(out);
					// mostra controlli select-all e totale
					$('#select_all_container').show();
					$('#total_container').show();
					// resetta il conteggio a 0 (nessuna checkbox è selezionata)
					updateTotal();
					return;
				}
			} catch(e){
				// non JSON: assumiamo HTML pronto
				container.html(html);
				return;
			}
			container.html('<div class="form-text">Nessun comparto trovato.</div>');
		}).fail(function(){
			container.html('<div class="form-text text-danger">Errore nel caricamento comparti.</div>');
		});
	}

	$('#location_id').on('change', function(){
		const loc = $(this).val();
		if (!loc) {
			// nessuna posizione selezionata: non mostrare comparti
			$('#compartments_container').html('<div class="form-text">Seleziona una posizione per caricare i comparti.</div>');
			$('#select_all_container').hide();
			$('#total_container').hide();
			$('#total_components_selected').text('0');
			return;
		}
		// posizione selezionata: mostra comparti
		$('#select_all_container').show();
		$('#total_container').show();
		loadCompartments(loc);
	});

	// Gestione select all e conteggio
	$(document).on('change', '.compartment-checkbox, .unassigned-checkbox', function(){
		updateTotal();
		// sincronizza select all
		var total = $('.compartment-checkbox, .unassigned-checkbox').length;
		var checked = $('.compartment-checkbox:checked, .unassigned-checkbox:checked').length;
		$('#select_all_compartments').prop('checked', total > 0 && checked === total);
	});

	$('#select_all_compartments').on('change', function(){
		var checked = $(this).is(':checked');
		$('.compartment-checkbox, .unassigned-checkbox').prop('checked', checked);
		updateTotal();
	});

	function updateTotal(){
		var total = 0;
		$('.compartment-checkbox:checked, .unassigned-checkbox:checked').each(function(){
			total += parseInt($(this).data('count') || 0, 10);
		});
		$('#total_components_selected').text(total);
		updateGenerateButton();
	}

	function updateGenerateButton(){
		var total = parseInt($('#total_components_selected').text(), 10) || 0;
		$('#generateBtn').prop('disabled', total <= 0);
	}

	// submit: apri generate_barcodes.php in nuova finestra (target _blank)
	$('#barcodeForm').on('submit', function(e){
		// blocca l'invio se non ci sono componenti selezionati
		if ($('#generateBtn').is(':disabled')){
			e.preventDefault();
			return false;
		}
	});
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>