<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-05 09:15:19 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-05 09:58:22
*/
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

// solo admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
	http_response_code(403);
	echo "Accesso negato: permessi insufficienti.";
	exit;
}

// carica posizioni
$locations = $pdo->query("SELECT id, name FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2><i class="fa-solid fa-qrcode me-2"></i>Genera QR Code</h2>
		<a href="/magazzino/warehouse/components.php" class="btn btn-secondary">Torna a componenti</a>
	</div>

	<div class="card" style="max-width:900px;">
		<div class="card-body">
			<form id="qrForm" method="post" action="generate_qrcodes.php" target="_blank">
				<div class="row g-3">
					<div class="col-md-6">
						<label for="location_id" class="form-label">Posizione</label>
						<select id="location_id" name="location_id" class="form-select">
							<option value="">-- Tutte le posizioni --</option>
							<?php foreach ($locations as $loc): ?>
								<option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
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
							<input class="form-check-input" type="checkbox" name="include_code_under_qr" id="include_code_under_qr" checked>
							<label class="form-check-label" for="include_code_under_qr">Mostra il codice del prodotto sotto il QR (opzionale)</label>
						</div>
					</div>

					<div class="col-12 text-end">
						<button id="generateBtn" type="submit" class="btn btn-primary" disabled>Genera PDF QR Code</button>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
$(function(){
	function loadCompartments(locationId){
		const container = $('#compartments_container');
		container.html('<div class="form-text">Caricamento...</div>');
		$.get('/magazzino/warehouse/get_compartments.php', { location_id: locationId }, function(html){
			// get_compartments.php restituisce probabilmente HTML o JSON; qui generiamo checkbox dalla risposta JSON se presente
			try {
				const data = JSON.parse(html);
				if (Array.isArray(data)){
					let out = '';
					data.forEach(function(c){
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
	$(document).on('change', '.compartment-checkbox', function(){
		updateTotal();
		// sincronizza select all
		var total = $('.compartment-checkbox').length;
		var checked = $('.compartment-checkbox:checked').length;
		$('#select_all_compartments').prop('checked', total > 0 && checked === total);
	});

	$('#select_all_compartments').on('change', function(){
		var checked = $(this).is(':checked');
		$('.compartment-checkbox').prop('checked', checked);
		updateTotal();
	});

	function updateTotal(){
		var total = 0;
		$('.compartment-checkbox:checked').each(function(){
			total += parseInt($(this).data('count') || 0, 10);
		});
		$('#total_components_selected').text(total);
		updateGenerateButton();
	}

	function updateGenerateButton(){
		var total = parseInt($('#total_components_selected').text(), 10) || 0;
		$('#generateBtn').prop('disabled', total <= 0);
	}

	// submit: apri generate_qrcodes.php in nuova finestra (target _blank)
	$('#qrForm').on('submit', function(e){
		// blocca l'invio se non ci sono componenti selezionati
		if ($('#generateBtn').is(':disabled')){
			e.preventDefault();
			return false;
		}
	});
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>