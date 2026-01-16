<?php
/*
 * @Author: Nelson Rossi Bittencourt (nbittencourt@hotmail.com)
 * @Date: 2026-01-11 17:28:47 
 * @Last Modified by: Nelson Rossi Bittencourt
 * @Last Modified time: 2026-01-15 12:10:30
 * @Description: Distinta Materiali/Bill of Materials (BOM) for RG4 Gestionale Magazzino Project 
 * @Version: 0.0.4
 * 
 * RG4Tech: Version: 0.0.1
 * messo in sicurezza da SQL Injection ed altro 
 * Incluso una ricerca di codici componente equivalenti
 * tolto gestione errori php (inserita in settings.php, gestita tramite DB)
 * Incluso opzioni per gestire vari tipi di file BOM
 * Migliorato l'aspetto del report;
 *
 * Nelson Rossi Bittencourt: Version 0.0.2
 * Inclusa una colonna che mostra la posizione dei componenti in magazzino.
 * Le modifiche sono commentate nel codice come "Versione 0.0.2". 
 * 
 * RG4Tech: Version: 0.0.3
 * Inclusa unità di misura per i componenti
 *
 * Nelson Rossi Bittencourt: Version 0.0.4
 * Inclusa selectione automatica tipi di file BOM
 * Nuova "identation"
 * Piccole correzioni alle posizioni delle istruzioni "if".
 */

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// CSS per stampa
echo '<link rel="stylesheet" href="/magazzino/assets/css/boms_print.css">';

// Messaggi da sessione
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Intestazione
include '../includes/header.php';

// Inizializza variabile
$bomFileName = null;
$bomFormat = 'kicad'; // default

// POST con il nome file BOM 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bomFile1']))
{
	$file = $_FILES['bomFile1'];
	$bomFormat = isset($_POST['bom_format']) ? trim($_POST['bom_format']) : 'kicad';
	
	// Validazione formato
	if (!in_array($bomFormat, ['kicad', 'eagle', 'altium', 'generic'])) {
		$bomFormat = 'kicad';
	}
	
	// Validazione del file caricato
	if ($file['error'] === UPLOAD_ERR_OK) {
		// Verifica estensione
		$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if ($fileExt !== 'csv') {
			$error = 'Solo file CSV sono permessi.';
		}
		// Verifica dimensione (max 5MB)
		elseif ($file['size'] > 5 * 1024 * 1024) {
			$error = 'Il file è troppo grande. Massimo 5MB.';
		}
		// Verifica che sia un file realmente caricato
		elseif (!is_uploaded_file($file['tmp_name'])) {
			$error = 'Errore nel caricamento del file.';
		}
		else {
			$bomFileName = $file;
		}
	} else {
		$error = 'Errore nel caricamento del file.';
	}
}

?>

<div class="container py-4">

<!-- Titolo della pagina e logo -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa-solid fa-list me-2"></i>BOMS</h2>
</div>

<!-- Modulo per la Selezione del file BOM -->
<form method="POST" enctype="multipart/form-data">
	<div class="row">
		<div class="col-md-6 mb-3">
			<label for="bomFile1" class="form-label">BOM file (csv):</label>
			<input type="file" class="form-control" id="bomFile1" name="bomFile1" accept=".csv" required>			
		</div>
		
		<!-- Version 0.0.4 Non è più necessario selezionare il formato -->
		<!--
		<div class="col-md-6 mb-3">
			<label for="bom_format" class="form-label">Formato BOM:</label>
			<select class="form-select" id="bom_format" name="bom_format" required>
				<option value="kicad" selected>KiCAD (default)</option>
				<option value="eagle">Eagle CAD</option>
				<option value="altium">Altium Designer</option>
				<option value="generic">CSV Generico (prima colonna: codice, seconda: quantità)</option>
			</select>
			<div class="form-text">
				<small>
					<strong>KiCAD:</strong> delimitatore ';', codice col.1, qty col.3<br>
					<strong>Eagle:</strong> delimitatore ',', codice col.1, qty col.2<br>
					<strong>Altium:</strong> delimitatore ',', codice col.0, qty col.1<br>
					<strong>Generico:</strong> delimitatore ',', codice col.0, qty col.1
				</small>
			</div>
		</div>
		-->
	
	</div>
		
	<button type="submit" class="btn btn-primary mb-3"><i class="fa-solid fa-upload me-1"></i>Analizza</button>
</form>
  
<?php
// È stato fornito un nome per BOM file?
if ($bomFileName !== null && isset($bomFileName['tmp_name']))
{	
	try 
	{
		// Crea una tabella temporanea in database
		$sqlCreateTemp = "CREATE TEMPORARY TABLE IF NOT EXISTS bom (bom_codice_prodotto VARCHAR(50), bom_quantity INT)";
		$pdo->exec($sqlCreateTemp);

		$arquivo = $bomFileName['tmp_name'];		
		
		// ** Versione 0.0.4 - Analisi del formato del file BOM **
		$bom_lines = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		$total_comma = 0;			// Numero di righe con delimitatore ","
		$total_semicolon = 0;		// Numero di righe con delimitatore ";"
		$row_counter = 0;			// Numero di righe
		$col_counter = 0;			// Numero di colonne
		$prev_comma = 0;			// Numero di "," nella riga precedente
		$total_semicolon = 0;		// Numero di ";" nella riga precedente
		$found_delimiter = "";		// Delimitatore trovato
		$selected_col = [];			// Vettore contenente le possibili colonne di quantità
		$final_array = [];			// Matrice temporanea con colonne per codici e quantità
		$codes = [];				// Vettore con codices
		$quantities = [];			// Vettore con quantità
		
		// Ciclo per ogni riga
		foreach ($bom_lines as $line_num => $line) 
		{	
			$count_comma = substr_count($line,',');			
			$count_semicolon = substr_count($line,';');
			
			// Confronta la riga corrente con quella precedente
			if ($row_counter > 0)
			{
				if ($count_comma == $prev_comma) $total_comma++;								
				if ($count_semicolon == $prev_semicolon) $total_semicolon++;				
			}
						
			$prev_comma = $count_comma;
			$prev_semicolon = $count_semicolon;	
			$row_counter++;
		}
		
		// Selezionare il delimitatore e il numero di colonne
		if (($row_counter-1)==$total_comma)
		{			
			$found_delimiter = ",";
			$col_counter = $prev_comma;
		}		
		elseif (($row_counter-1)==$total_semicolon)
		{			
			$found_delimiter = ";";
			$col_counter = $prev_semicolon;
		}	
		else		
		{				
			$found_delimiter = "";
			$col_counter = 0;
		}
		
		// Cerca per colonna quantità		
		if ($col_counter > 0)
		{		
			$cols_int = array_fill(0,$col_counter+1,0);
			foreach ($bom_lines as $line_num => $line) 
			{
				$line2 = str_replace("'","",$line);			
				$columns = explode($found_delimiter,$line2);
				array_push($final_array, $columns);
				for($i=0;$i<=$col_counter;$i++)
				{	
					if (filter_var($columns[$i], FILTER_VALIDATE_INT)!=false) $cols_int[$i]++;									
				}			
			}		
			
			for($i=0;$i<=$col_counter;$i++)
			{
				if ($cols_int[$i] == $row_counter -1) $selected_col[] = $i;							
			}
		}
				
		// Configurazione parser in base al formato
		$parsers = [
			'kicad' => [
				'delimiter' => ';',
				'code_col' => 1,
				'qty_col' => 3,
				'skip_header' => true,
				'strip_quotes' => true,
				'description' => 'KiCAD BOM'
			],
			'eagle' => [
				'delimiter' => ',',
				'code_col' => 1,
				'qty_col' => 2,
				'skip_header' => true,
				'strip_quotes' => false,
				'description' => 'Eagle CAD BOM'
			],
			'altium' => [
				'delimiter' => ',',
				'code_col' => 0,
				'qty_col' => 1,
				'skip_header' => true,
				'strip_quotes' => false,
				'description' => 'Altium Designer BOM'
			],
			'generic' => [
				'delimiter' => ',',
				'code_col' => 0,
				'qty_col' => 1,
				'skip_header' => true,
				'strip_quotes' => false,
				'description' => 'CSV Generico'
			]
		];
		
		if (is_array($selected_col) && count($selected_col)==1)
		{	
			if ($selected_col[0]==3) $bomFormat = "kicad";
			else if ($selected_col[0]==2) $bomFormat = "eagle";
			else if ($selected_col[0]==1) $bomFormat = "altium";
			
			$col_code = $parsers[$bomFormat]['code_col'];			
			
			for($l=0;$l<$row_counter;$l++)
			{
				 $codes[] = $final_array[$l][$col_code];
				 $quantities[] = $final_array[$l][$selected_col[0]];
			}
		}
		unset($bom_lines,$final_array);	// Libera la memoria
		$parser = $parsers[$bomFormat] ?? $parsers['kicad'];

		// 
		if (is_array($codes) && count($codes)>0) 
		{
			$pdo->beginTransaction();    
			$stmtInsert = $pdo->prepare("INSERT INTO bom (bom_codice_prodotto, bom_quantity) VALUES (?, ?)");
		
			$start_row = 0;
			if ($parser['skip_header']) $start_row = 1;
	
			// ** Version 0.0.4 - File BOM già suddiviso in precedenza per l'analisi **
			for($l=$start_row;$l<$row_counter;$l++)
			{
				// Estrai codice e quantità dalle colonne configurate
				$codice = $codes[$l];
				$tmp_quant = $quantities[$l];
				
				$quantita = isset($tmp_quant) && is_numeric($tmp_quant) ? (int)$tmp_quant : 1;
				if ($quantita < 0) $quantita = 1; // Evita quantità negative
				
				$stmtInsert->execute([$codice, $quantita]);			
			}
			$pdo->commit(); 		
			
			// Esegue SQL utilizzando la tabella temporanea e la tabella dei componenti
			// Cerca per codice esatto, parziale e anche negli equivalenti JSON
			// Versione 0.0.2 - Il comando SQL è stato modificato per includere la posizione 
			// dei componenti all'interno dell'inventario.
			// 2026-01-14: Aggiunta unità di misura			
			$sqlAnalise = "
			SELECT 
				t.bom_codice_prodotto,
				t.bom_quantity,
				e.codice_prodotto,
				e.equivalents,
				e.unita_misura,
				COALESCE(loca.name, 0) AS locale,
				COALESCE(locs.name, 0) AS location,				
				COALESCE(cps.code, 0) AS comparto,
				COALESCE(e.quantity_min, 0) AS scorte_min,
				COALESCE(e.quantity, 0) AS scorte_corrente,
				CASE 
					WHEN e.codice_prodotto IS NULL THEN 'Prodotto non registrato'
					WHEN e.quantity < t.bom_quantity THEN 'Scorte insufficienti'
					ELSE 'OK'
				END AS azione,
				CASE
					WHEN e.codice_prodotto = t.bom_codice_prodotto THEN 'Esatto'
					WHEN e.codice_prodotto LIKE CONCAT(t.bom_codice_prodotto, '%') THEN 'Parziale'
					WHEN JSON_CONTAINS(e.equivalents, JSON_QUOTE(t.bom_codice_prodotto)) THEN 'Equivalente'
					ELSE NULL
				END AS tipo_match
			FROM 
				bom t 
			LEFT JOIN 
				components e ON (
					e.codice_prodotto = t.bom_codice_prodotto 
					OR e.codice_prodotto LIKE CONCAT(t.bom_codice_prodotto, '%')
					OR JSON_CONTAINS(e.equivalents, JSON_QUOTE(t.bom_codice_prodotto))
				)
			LEFT JOIN
				compartments cps ON e.compartment_id = cps.id			
			LEFT JOIN
				locations locs ON cps.location_id = locs.id		
			LEFT JOIN
				locali loca ON locs.locale_id = loca.id			
			ORDER BY 
				azione DESC, tipo_match ASC";
	
			$stmt = $pdo->query($sqlAnalise);
			$results = $stmt->fetchAll();
		
			if (!$results) 
			{
				$error = "Nessun risultato trovato nel file BOM.";
			}
	
			// Calcola statistiche
			$stats = [
				'totale' => 0,
				'ok' => 0,
				'insufficienti' => 0,
				'non_registrati' => 0,
				'da_acquistare' => []
			];
		
			if (is_array($results) && count($results) > 0) 
			{
				$stats['totale'] = count($results);
				foreach ($results as $row) 
				{
					if ($row['azione'] === 'OK') 
					{
						$stats['ok']++;
					} 
					elseif ($row['azione'] === 'Scorte insufficienti') 
					{
						$stats['insufficienti']++;
						$acquistare = $row['scorte_min'] - ($row['scorte_corrente'] - $row['bom_quantity']);
						if ($acquistare > 0) 
						{
							$stats['da_acquistare'][$row['bom_codice_prodotto']] = $acquistare;
						}
					} 
					elseif ($row['azione'] === 'Prodotto non registrato') 
					{
						$stats['non_registrati']++;
					}
				}
			}		
		} 
		else 
		{
			if ($pdo->inTransaction()) $pdo->rollBack();		
			$error = "Errore durante la lettura del file BOM.";
		}


		// Crea un report
		if ($error)
		{
			echo "<div class='alert alert-info'><i class='fa-solid fa-info-circle me-2'></i>" . htmlspecialchars($error) . "</div>";
		}
	
		else if (is_array($results) && count($results) > 0) {
			
			// Formato BOM file trovato
			echo "<div class='alert alert-info'><i class='fa-solid fa-info-circle me-2'></i>Formato trovato: <strong>" . htmlspecialchars($parser['description'], ENT_QUOTES, 'UTF-8') . "</strong></div>";
			
			// Card statistiche
			echo "<div class='row mb-4'>";		
				
				// Totale componenti
				echo "<div class='col-md-3'>";
				echo "<div class='card text-center'>";
				echo "<div class='card-body'>";
				echo "<h5 class='card-title'><i class='fa-solid fa-list-check text-primary'></i></h5>";
				echo "<h2 class='mb-0'>" . $stats['totale'] . "</h2>";
				echo "<small class='text-muted'>Componenti totali</small>";
				echo "</div></div></div>";
			
				// OK
				echo "<div class='col-md-3'>";
				echo "<div class='card text-center border-success'>";
				echo "<div class='card-body'>";
				echo "<h5 class='card-title'><i class='fa-solid fa-circle-check text-success'></i></h5>";
				echo "<h2 class='mb-0 text-success'>" . $stats['ok'] . "</h2>";
				echo "<small class='text-muted'>Disponibili</small>";
				echo "</div></div></div>";
			
				// Insufficienti
				echo "<div class='col-md-3'>";
				echo "<div class='card text-center border-warning'>";
				echo "<div class='card-body'>";
				echo "<h5 class='card-title'><i class='fa-solid fa-triangle-exclamation text-warning'></i></h5>";
				echo "<h2 class='mb-0 text-warning'>" . $stats['insufficienti'] . "</h2>";
				echo "<small class='text-muted'>Scorte basse</small>";
				echo "</div></div></div>";
			
				// Non registrati
				echo "<div class='col-md-3'>";
				echo "<div class='card text-center border-danger'>";
				echo "<div class='card-body'>";
				echo "<h5 class='card-title'><i class='fa-solid fa-circle-xmark text-danger'></i></h5>";
				echo "<h2 class='mb-0 text-danger'>" . $stats['non_registrati'] . "</h2>";
				echo "<small class='text-muted'>Non trovati</small>";
				echo "</div></div></div>";	
				
			echo "</div>";
			
			// Controlli report
			echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
			echo "<h2>Dettaglio BOM</h2>";
			echo "<div>";
			echo "<button class='btn btn-sm btn-outline-secondary me-2' onclick='window.print()'><i class='fa-solid fa-print me-1'></i>Stampa</button>";
			echo "<div class='btn-group' role='group'>";
			echo "<input type='radio' class='btn-check' name='filtro' id='tutti' value='tutti' checked onclick='filtraBOM(\"tutti\")'>";
			echo "<label class='btn btn-sm btn-outline-primary' for='tutti'>Tutti</label>";
			echo "<input type='radio' class='btn-check' name='filtro' id='problemi' value='problemi' onclick='filtraBOM(\"problemi\")'>";
			echo "<label class='btn btn-sm btn-outline-warning' for='problemi'>Solo problemi</label>";
			echo "<input type='radio' class='btn-check' name='filtro' id='ok' value='ok' onclick='filtraBOM(\"ok\")'>";
			echo "<label class='btn btn-sm btn-outline-success' for='ok'>Solo OK</label>";
			echo "</div></div></div>";
			
			echo "<div class='table-responsive'>";
			echo "<table class='table table-bordered table-hover' id='bomTable'>";
			
			// Versione 0.0.2 - Inclusa colonna relativa alla posizione del componente
			echo "<thead class='table-light'><tr><th>Codice BOM</th><th>Qty Richiesta</th><th>Qty Magazzino</th><th>Livello Scorte</th><th>Status</th><th>Note/Azioni</th><th>Locale/Posizione</th></tr></thead>";
			echo "<tbody>";
	
			foreach ($results as $row) 
			{
				// Determina classe e icona status
				$statusClass = '';
				$statusIcon = '';
				$statusBadge = '';
				$rowClass = '';
				
				if ($row['azione'] === 'OK') {
					$statusClass = 'success';
					$statusIcon = 'fa-circle-check';
					$statusBadge = '<span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Disponibile</span>';
					$rowClass = 'table-success-subtle';
				} elseif ($row['azione'] === 'Scorte insufficienti') {
					$statusClass = 'warning';
					$statusIcon = 'fa-triangle-exclamation';
					$statusBadge = '<span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i>Scorte basse</span>';
					$rowClass = 'table-warning';
				} else {
					$statusClass = 'danger';
					$statusIcon = 'fa-circle-xmark';
					$statusBadge = '<span class="badge bg-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Non trovato</span>';
					$rowClass = 'table-danger';
				}
				
				echo "<tr class='$rowClass' data-status='" . htmlspecialchars($row['azione'], ENT_QUOTES, 'UTF-8') . "'>";
				
				// Codice con tipo match
				echo "<td><strong>" . htmlspecialchars($row['bom_codice_prodotto'], ENT_QUOTES, 'UTF-8') . "</strong>";
				if ($row['azione'] != 'Prodotto non registrato' && $row['tipo_match']) {
					if ($row['tipo_match'] === 'Esatto') {
						echo "<br><small class='text-success'><i class='fa-solid fa-check me-1'></i>Match esatto</small>";
					} elseif ($row['tipo_match'] === 'Parziale') {
						echo "<br><small class='text-info'><i class='fa-solid fa-arrow-right me-1'></i>" . htmlspecialchars($row['codice_prodotto'], ENT_QUOTES, 'UTF-8') . "</small>";
					} elseif ($row['tipo_match'] === 'Equivalente') {
						echo "<br><small class='text-primary'><i class='fa-solid fa-repeat me-1'></i>" . htmlspecialchars($row['codice_prodotto'], ENT_QUOTES, 'UTF-8') . "</small>";
					}
				}
				echo "</td>";
				
				// Quantità richiesta
				echo "<td class='text-center'><span class='badge bg-primary'>" . htmlspecialchars($row['bom_quantity'], ENT_QUOTES, 'UTF-8') . "</span></td>";
				
				// Quantità magazzino (con unità di misura)
				$unit = $row['unita_misura'] ?? 'pz';
				echo "<td class='text-center'><span class='badge bg-secondary'>" . htmlspecialchars($row['scorte_corrente'], ENT_QUOTES, 'UTF-8') . " " . htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') . "</span></td>";
				
				// Progress bar livello scorte
				echo "<td>";
				if ($row['azione'] != 'Prodotto non registrato') {
					$percentuale = 0;
					if ($row['bom_quantity'] > 0) {
						$percentuale = min(100, ($row['scorte_corrente'] / $row['bom_quantity']) * 100);
					}
					$progressClass = $percentuale >= 100 ? 'bg-success' : ($percentuale >= 50 ? 'bg-warning' : 'bg-danger');
					echo "<div class='progress' style='height: 20px;'>";
					echo "<div class='progress-bar $progressClass' role='progressbar' style='width: " . round($percentuale) . "%;' aria-valuenow='" . round($percentuale) . "' aria-valuemin='0' aria-valuemax='100'>" . round($percentuale) . "%</div>";
					echo "</div>";
				} else {
					echo "<span class='text-muted'>N/A</span>";
				}
				echo "</td>";
				
				// Status badge
				echo "<td class='text-center'>$statusBadge</td>";
				
				// Note/Azioni
				echo "<td>";
				$acquistare = $row['scorte_min'] - ($row['scorte_corrente']-$row['bom_quantity']);
				if ($acquistare > 0)
				{
					echo "<div class='alert alert-warning mb-1 py-1'><small><i class='fa-solid fa-cart-shopping me-1'></i><strong>Da acquistare:</strong> " . htmlspecialchars($acquistare, ENT_QUOTES, 'UTF-8') . "</small></div>";
				}
				
				if ($row['azione'] == 'Prodotto non registrato') {
					echo "<small class='text-danger'><i class='fa-solid fa-exclamation-circle me-1'></i>Componente da registrare nel sistema</small>";
				}
				echo "</td>";
				
				// Version 0.0.2 - Per gli prodotti registrati, mostra la loro posizione nello magazzino
				$locpos = "N/A";
				if ($row['azione'] != 'Prodotto non registrato') 
				{
					$locpos = "{$row['locale']}/{$row['location']}/{$row['comparto']}";
				}
				echo "<td class='text-center'><span class='badge bg-secondary'>" . htmlspecialchars($locpos, ENT_QUOTES, 'UTF-8') . "</span></td>";
								
			}
			echo "</tbody></table>";
			echo "</div>"; // chiude table-responsive
			
			// Script per filtri
			echo "<script>
			function filtraBOM(tipo) {
				const rows = document.querySelectorAll('#bomTable tbody tr');
				rows.forEach(row => {
					const status = row.getAttribute('data-status');
					if (tipo === 'tutti') {
						row.style.display = '';
					} else if (tipo === 'problemi') {
						row.style.display = (status === 'OK') ? 'none' : '';
					} else if (tipo === 'ok') {
						row.style.display = (status === 'OK') ? '' : 'none';
					}
				});
			}
			</script>";
		}
	
	} catch (Exception $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		$error = "Errore durante l'elaborazione del file BOM: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
	}
	
	$bomFileName = null;
}
?>

</div>

<?php 
// Piè di pagina
include '../includes/footer.php'; 
?>