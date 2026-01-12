<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-04 12:59:50 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-11 18:03:00
 *
 * Update script:
 * - apre file .zip nella stessa cartella
 * - estrae in una cartella temporanea
 * - copia i file nella root del progetto (../)
 * - se presente, esegue db_update.php copiato nella root
 * - ritorna un report dei file aggiornati / errori
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/migration_manager.php';

// solo admin può eseguire l'aggiornamento
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
		http_response_code(403);
		echo "Accesso negato: permessi insufficienti.";
		exit;
}

ini_set('display_errors', 0);

// Flag per indicare che siamo nella fase 2 (dopo aver aggiornato i file)
$isPhase2 = isset($_GET['phase2']) && $_GET['phase2'] === '1';

// Gestione upload file .zip
$uploadSuccess = false;
$uploadError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['updateZip'])) {
	$uploadDir = __DIR__;
	$maxSize = 50 * 1024 * 1024; // 50MB max
	
	$file = $_FILES['updateZip'];
	
	if ($file['error'] !== UPLOAD_ERR_OK) {
		$uploadError = "Errore upload: " . $file['error'];
	} elseif ($file['size'] > $maxSize) {
		$uploadError = "File troppo grande (max 50MB).";
	} elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'zip') {
		$uploadError = "Solo file .zip sono consentiti.";
	} else {
		$destPath = $uploadDir . DIRECTORY_SEPARATOR . 'update.zip';
		if (@move_uploaded_file($file['tmp_name'], $destPath)) {
			$uploadSuccess = true;
			// Ricarica la pagina per iniziare l'estrazione
			header('Location: ' . $_SERVER['REQUEST_URI']);
			exit;
		} else {
			$uploadError = "Impossibile salvare il file.";
		}
	}
}

function rrmdir($dir) {
		if (!is_dir($dir)) return;
		$objects = scandir($dir);
		foreach ($objects as $object) {
				if ($object == '.' || $object == '..') continue;
				$path = $dir . DIRECTORY_SEPARATOR . $object;
				if (is_dir($path)) rrmdir($path); else @unlink($path);
		}
		@rmdir($dir);
}

// Percorsi
$projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');

// Cerca qualsiasi file .zip nella cartella update (scegli il più recente)
$zipFilesInDir = glob(__DIR__ . '/*.zip');
$zipPath = null;
if (!empty($zipFilesInDir)) {
	$latest = null;
	foreach ($zipFilesInDir as $zf) {
		if ($latest === null || filemtime($zf) > filemtime($latest)) {
			$latest = $zf;
		}
	}
	$zipPath = $latest;
}

if ($zipPath === null) {
	// Leggi la versione corrente
	$currentVersionFile = $projectRoot . DIRECTORY_SEPARATOR . 'versioni.txt';
	$currentVersion = getLatestVersion($currentVersionFile);
	
	?><!doctype html>
	<html lang="it">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width,initial-scale=1">
		<title>Aggiornamento - Upload</title>
		<link href="/magazzino/assets/css/bootstrap.min.css" rel="stylesheet">
		<link href="/magazzino/assets/css/all.min.css" rel="stylesheet">
	</head>
	<body class="p-4">
		<div class="container">
			<h1><i class="fa-solid fa-download me-2"></i>Aggiornamento</h1>
			
			<?php if ($currentVersion): ?>
				<div class="alert alert-info">
					<i class="fa-solid fa-info-circle me-2"></i>
					<strong>Versione corrente:</strong> <?= htmlspecialchars($currentVersion['version']) ?>
					<?php if (!empty($currentVersion['date'])): ?>
						<small class="text-muted">(<?= htmlspecialchars($currentVersion['date']) ?>)</small>
					<?php endif; ?>
			</div>
		<?php endif; ?>
		
		<div class="card mt-4" style="max-width: 600px;">
			<div class="card-header">
				<h5 class="mb-0">Carica file di aggiornamento</h5>
			</div>			<div class="card-body">
				<?php if ($uploadError): ?>
					<div class="alert alert-danger"><?= htmlspecialchars($uploadError) ?></div>
				<?php endif; ?>
				
				<form method="POST" enctype="multipart/form-data">
					<div class="mb-3">							<label for="updateZip" class="form-label">File .zip:</label>
							<input type="file" class="form-control" id="updateZip" name="updateZip" accept=".zip" required>
							<small class="text-muted">Carica il file update.zip (max 50MB)</small>
						</div>
						<button type="submit" class="btn btn-primary">
							<i class="fa-solid fa-upload me-1"></i>Carica e avvia aggiornamento
						</button>
						<a href="/magazzino/index.php" class="btn btn-secondary">Annulla</a>
					</form>
				</div>
			</div>
		</div>
	</body>
	</html>
	<?php
	exit;
}

if (!$projectRoot) {
	http_response_code(500);
	echo "Errore: impossibile determinare la root del progetto.";
	exit;
}

// Funzione per estrarre versione da versioni.txt
function getLatestVersion($versionFile) {
	if (!file_exists($versionFile)) return null;
	$lines = file($versionFile);
	if (empty($lines)) return null;
	
	// Cerca l'ultima riga che inizia con un numero di versione (es. "1.0", "1.1", "1.2")
	foreach (array_reverse($lines) as $line) {
		$trimmed = trim($line);
		// Una riga di versione inizia con cifre e contiene un punto (es. 1.0, 1.1, 1.2)
		if (preg_match('/^(\d+\.\d+)\s+/', $trimmed, $matches)) {
			$version = $matches[1];
			// Estrai data e descrizione (tutto quello dopo il numero versione e la data)
			if (preg_match('/^' . preg_quote($version) . '\s+(\d{1,2}\/\d{1,2}\/\d{4})\s+(.+)/', $trimmed, $matches)) {
				$date = $matches[1];
				$description = $matches[2];
				return ['version' => $version, 'date' => $date, 'description' => $description];
			}
			return ['version' => $version, 'date' => '', 'description' => ''];
		}
	}
	return null;
}

// Leggi versione attuale PRIMA dell'aggiornamento
$currentVersionFile = $projectRoot . DIRECTORY_SEPARATOR . 'versioni.txt';
$currentVersion = getLatestVersion($currentVersionFile);

$zip = new ZipArchive();
$res = $zip->open($zipPath);
if ($res !== true) {
		http_response_code(500);
		echo "Impossibile aprire l'archivio zip (code: $res).";
		exit;
}

// Usa una cartella temporanea all'interno del progetto (più sicuro e con permessi garantiti)
$tmpDir = $projectRoot . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR . '_temp_' . time();
if (!mkdir($tmpDir, 0777, true)) {
		http_response_code(500);
		echo "Impossibile creare cartella temporanea: $tmpDir";
		$zip->close();
		exit;
}

// Estrai tutto nella cartella temporanea
if (!$zip->extractTo($tmpDir)) {
		rrmdir($tmpDir);
		$zip->close();
		http_response_code(500);
		echo "Estrazione fallita.";
		exit;
}

// Leggi versione NUOVA dalla cartella temporanea
$newVersionFile = $tmpDir . DIRECTORY_SEPARATOR . 'versioni.txt';
$newVersion = getLatestVersion($newVersionFile);

// Controlla se si sta tentando un downgrade (versione più vecchia)
$isDowngrade = false;
if (!empty($currentVersion['version']) && !empty($newVersion['version'])) {
	if (version_compare($newVersion['version'], $currentVersion['version'], '<')) {
		$isDowngrade = true;
	}
}

// Se è un downgrade e l'utente non ha confermato, chiedi conferma
if ($isDowngrade && !isset($_GET['confirm_downgrade'])) {
	?><!doctype html>
	<html lang="it">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width,initial-scale=1">
		<title>Attenzione - Downgrade versione</title>
		<link href="/magazzino/assets/css/bootstrap.min.css" rel="stylesheet">
		<link href="/magazzino/assets/css/all.min.css" rel="stylesheet">
	</head>
	<body class="p-4">
		<div class="container">
			<h1><i class="fa-solid fa-exclamation-triangle text-warning me-2"></i>Attenzione: Downgrade</h1>
			
			<div class="alert alert-warning mt-4">
				<h5 class="alert-heading"><i class="fa-solid fa-exclamation-triangle me-2"></i>Stai tentando di installare una versione precedente!</h5>
				<hr>
				<p class="mb-2">
					<strong>Versione corrente:</strong> <?= htmlspecialchars($currentVersion['version']) ?>
					<?php if (!empty($currentVersion['date'])): ?>
						<small class="text-muted">(<?= htmlspecialchars($currentVersion['date']) ?>)</small>
					<?php endif; ?>
				</p>
				<p class="mb-0">
					<strong>Versione da installare:</strong> <?= htmlspecialchars($newVersion['version']) ?>
					<?php if (!empty($newVersion['date'])): ?>
						<small class="text-muted">(<?= htmlspecialchars($newVersion['date']) ?>)</small>
					<?php endif; ?>
				</p>
			</div>
			
			<div class="alert alert-danger">
				<h6><i class="fa-solid fa-exclamation-circle me-2"></i>Rischi del downgrade:</h6>
				<ul class="mb-0">
					<li>Potrebbero verificarsi problemi di incompatibilità con il database</li>
					<li>Alcune funzionalità potrebbero non funzionare correttamente</li>
					<li>Potrebbero verificarsi perdite di dati</li>
				</ul>
			</div>
			
			<div class="d-grid gap-2 d-md-block">
				<a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?confirm_downgrade=1" class="btn btn-warning btn-lg">
					<i class="fa-solid fa-exclamation-triangle me-2"></i>Procedi comunque con il downgrade
				</a>
				<a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-lg" onclick="return confirm('Vuoi annullare l\'aggiornamento? Il file ZIP verrà eliminato.');">
					<i class="fa-solid fa-times me-2"></i>Annulla
				</a>
			</div>
		</div>
	</body>
	</html>
	<?php
	// Pulisci la cartella temporanea
	rrmdir($tmpDir);
	exit;
}

$filesReport = ['updated' => [], 'skipped' => [], 'errors' => [], 'currentVersion' => $currentVersion, 'newVersion' => $newVersion];

// Copia file uno per uno, proteggendo da path traversal
for ($i = 0; $i < $zip->numFiles; $i++) {
		$entry = $zip->getNameIndex($i);
		
		// normalizza percorso e impedisce path traversal
		$rel = str_replace(['\\', '/\0'], ['/', ''], $entry);
		$rel = preg_replace('#(^/|\.{2}/)#', '', $rel);
		$rel = ltrim($rel, '/');
		
		// Se è una directory (termina con /), creala e continua
		if (substr($entry, -1) === '/') {
			if ($rel === '') continue;
			$destDir = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, rtrim($rel, '/'));
			if (!is_dir($destDir)) {
				if (@mkdir($destDir, 0777, true)) {
					$filesReport['updated'][] = $rel . ' (cartella)';
				} else {
					$filesReport['errors'][] = [ 'file' => $rel, 'message' => 'Impossibile creare cartella' ];
				}
			}
			continue;
		}
		
		// non copiare il file db_update.php: verrà eseguito separatamente
		if (strtolower(basename($rel)) === 'db_update.php') {
			continue;
		}
		if ($rel === '') continue;

		$source = $tmpDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry);
		$dest = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

		// sicurezza: il destinazione deve iniziare con project root
		$destDir = dirname($dest);
		if (strpos(realpath($destDir) ?: $destDir, $projectRoot) !== 0) {
				$filesReport['errors'][] = [ 'file' => $rel, 'message' => 'Percorso non consentito' ];
				continue;
		}

		// crea directory se serve
		if (!is_dir($destDir)) {
				if (!@mkdir($destDir, 0777, true)) {
						// prova a cambiare permessi alla directory padre
						$filesReport['errors'][] = [ 'file' => $rel, 'message' => 'Impossibile creare directory: ' . $destDir ];
						continue;
				}
		}

		// controllo permessi scrittura
		if (file_exists($dest) && !is_writable($dest)) {
				@chmod($dest, 0666);
		}
		if (!is_writable($destDir)) {
				@chmod($destDir, 0777);
		}
		if (!is_writable($destDir)) {
				$filesReport['errors'][] = [ 'file' => $rel, 'message' => 'Destinazione non scrivibile: ' . $destDir ];
				continue;
		}

		// Se il file esiste, controlla se è già aggiornato
		if (file_exists($dest)) {
				$sourceHash = md5_file($source);
				$destHash = md5_file($dest);
				if ($sourceHash === $destHash) {
						// File identico, non serve copiare
						$filesReport['unchanged'][] = $rel;
						continue;
				}
		}

		// copia file
		if (!copy($source, $dest)) {
				$filesReport['errors'][] = [ 'file' => $rel, 'message' => 'Copia fallita' ];
				continue;
		}
		$filesReport['updated'][] = $rel;
}

$zip->close();

// Controlla se update/index.php o migration_manager.php sono stati aggiornati
$needsManualMigration = in_array('update/index.php', $filesReport['updated']) || 
                        in_array('update/migration_manager.php', $filesReport['updated']);

// Se i file di update sono stati modificati E non siamo già in fase 2, mostra il messaggio
if ($needsManualMigration && !$isPhase2) {
	?><!doctype html>
	<html lang="it">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width,initial-scale=1">
		<title>Aggiornamento - File aggiornati</title>
		<link href="/magazzino/assets/css/bootstrap.min.css" rel="stylesheet">
		<link href="/magazzino/assets/css/all.min.css" rel="stylesheet">
	</head>
	<body class="p-4">
		<div class="container">
			<h1><i class="fa-solid fa-download me-2"></i>File aggiornati con successo</h1>
			
			<div class="alert alert-success mt-4">
				<i class="fa-solid fa-check-circle me-2"></i>
				<strong>I file sono stati aggiornati correttamente!</strong>
			</div>
			
			<div class="alert alert-warning">
				<i class="fa-solid fa-info-circle me-2"></i>
				<strong>Importante:</strong> I file del sistema di aggiornamento sono stati modificati.<br>
				<strong>Clicca sul pulsante qui sotto per completare l'aggiornamento del database.</strong>
			</div>
			
			<?php if (!empty($filesReport['currentVersion']) || !empty($filesReport['newVersion'])): ?>
				<div class="card mb-3">
					<div class="card-header">
						<h5 class="mb-0">Informazioni versione</h5>
					</div>
					<div class="card-body">
						<?php if (!empty($filesReport['currentVersion'])): ?>
							<i class="fa-solid fa-check-circle text-success"></i> 
							<strong>Versione precedente:</strong> <?= htmlspecialchars($filesReport['currentVersion']['version']) ?>
							<?php if (!empty($filesReport['currentVersion']['date'])): ?>
								<small class="text-muted">(<?= htmlspecialchars($filesReport['currentVersion']['date']) ?>)</small>
							<?php endif; ?>
						<?php endif; ?>
						<br>
						<?php if (!empty($filesReport['newVersion'])): ?>
							<i class="fa-solid fa-arrow-up text-primary"></i> 
							<strong>Nuova versione:</strong> <?= htmlspecialchars($filesReport['newVersion']['version']) ?>
							<?php if (!empty($filesReport['newVersion']['date'])): ?>
								<small class="text-muted">(<?= htmlspecialchars($filesReport['newVersion']['date']) ?>)</small>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
			
			<h5>File aggiornati:</h5>
			<ul class="list-group mb-4">
				<?php foreach ($filesReport['updated'] as $f): ?>
					<li class="list-group-item text-success"><i class="fa-solid fa-arrow-up"></i> <?= htmlspecialchars($f) ?></li>
				<?php endforeach; ?>
			</ul>
			
			<?php if (!empty($filesReport['errors'])): ?>
				<h5>Errori:</h5>
				<ul class="list-group mb-4">
					<?php foreach ($filesReport['errors'] as $e): ?>
						<li class="list-group-item text-danger"><i class="fa-solid fa-xmark"></i> <?= htmlspecialchars($e['file'] . ': ' . $e['message']) ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			
			<div class="d-grid gap-2 d-md-block">
				<a href="/magazzino/update/run_migrations.php" class="btn btn-primary btn-lg">
					<i class="fa-solid fa-database me-2"></i>Completa aggiornamento database
				</a>
				<a href="/magazzino/index.php" class="btn btn-secondary btn-lg">
					<i class="fa-solid fa-home me-2"></i>Torna all'applicazione
				</a>
			</div>
		</div>
	</body>
	</html>
	<?php
	exit;
}

// Se non serve migrazione manuale, esegui subito le migrazioni
if (!$isPhase2) {
	// Usa il nuovo sistema di migrazioni
	$migrationManager = new MigrationManager($pdo);
	$migrationResult = $migrationManager->runPendingMigrations();
	
	// NON registrare la versione qui per evitare problemi con PDO unbuffered queries
	// La versione verrà registrata automaticamente da runPendingMigrations()
} else {
	// Non eseguire migrazioni, sono già state eseguite da run_migrations.php
	$migrationResult = [
		'applied' => [],
		'message' => 'Migrazioni già eseguite'
	];
}
// Crea un report compatibile con il formato precedente
$dbReport = null;
if (!empty($migrationResult['applied'])) {
	$totalSuccess = 0;
	$totalSkipped = 0;
	$totalErrors = 0;
	$allDetails = [];
	
	foreach ($migrationResult['applied'] as $applied) {
		$totalSuccess += $applied['stats']['success'];
		$totalSkipped += $applied['stats']['skipped'];
		$totalErrors += $applied['stats']['errors'];
		
		$allDetails[] = "<strong>Versione {$applied['version']}:</strong>";
		$allDetails = array_merge($allDetails, $applied['stats']['details']);
	}
	
	$dbReport = [
		'success' => true,
		'executed_statements' => $totalSuccess,
		'no_effect_statements' => $totalSkipped,
		'failed_statements' => $totalErrors,
		'details' => $allDetails,
		'message' => $migrationResult['message']
	];
} else {
	// Nessuna migrazione applicata
	$dbReport = [
		'success' => true,
		'message' => $migrationResult['message']
	];
}

// pulizia
rrmdir($tmpDir);

// Elimina tutti gli archivi .zip nella cartella update
$deletedZips = [];
$zipFiles = glob(__DIR__ . '/*.zip');
foreach ($zipFiles as $zf) {
    // sicurezza: elimina solo file con estensione .zip
    if (!is_file($zf)) continue;
    if (!str_ends_with(strtolower($zf), '.zip')) continue;
    if (!is_writable($zf)) {@chmod($zf, 0666);} 
    if (@unlink($zf)) {
        $deletedZips[] = basename($zf);
    } else {
        $filesReport['errors'][] = [ 'file' => basename($zf), 'message' => 'Impossibile eliminare l\'archivio zip' ];
    }
}

// Report HTML semplice
?><!doctype html>
<html lang="it">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Aggiornamento</title>
	<link href="/magazzino/assets/css/bootstrap.min.css" rel="stylesheet">
	<link href="/magazzino/assets/css/all.min.css" rel="stylesheet">
</head>
<body class="p-4">
	<div class="container">
		<h1><i class="fa-solid fa-download me-2"></i>Aggiornamento pacchetto</h1>
		
		<?php if (!empty($filesReport['currentVersion']) || !empty($filesReport['newVersion'])): ?>
			<div class="alert alert-info mt-3">
				<strong>Versioni:</strong><br>
				<?php if (!empty($filesReport['currentVersion'])): ?>
					<i class="fa-solid fa-check-circle text-success"></i> 
					<strong>Versione precedente:</strong> <?= htmlspecialchars($filesReport['currentVersion']['version']) ?>
					<?php if (!empty($filesReport['currentVersion']['date'])): ?>
						<small class="text-muted">(<?= htmlspecialchars($filesReport['currentVersion']['date']) ?>)</small>
					<?php endif; ?>
				<?php else: ?>
					<i class="fa-solid fa-circle-question text-muted"></i> 
					<strong>Versione precedente:</strong> Sconosciuta
				<?php endif; ?>
				<br>
				<?php if (!empty($filesReport['newVersion'])): ?>
					<i class="fa-solid fa-arrow-up text-primary"></i> 
					<strong>Versione aggiornata:</strong> <?= htmlspecialchars($filesReport['newVersion']['version']) ?>
					<?php if (!empty($filesReport['newVersion']['date'])): ?>
						<small class="text-muted">(<?= htmlspecialchars($filesReport['newVersion']['date']) ?>)</small>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		
		<?php if (!empty($migrationResult['firstRun']) && !empty($migrationResult['detectedVersion'])): ?>
			<div class="alert alert-success">
				<i class="fa-solid fa-rocket me-2"></i>
				<strong>Sistema di versioning inizializzato automaticamente!</strong><br>
				Versione database rilevata: <strong><?= htmlspecialchars($migrationResult['detectedVersion']) ?></strong>
			</div>
		<?php endif; ?>
		
		<h4 class="mt-4">File aggiornati</h4>
		<?php if (!empty($filesReport['updated'])): ?>
			<ul class="list-group mb-3">
				<?php foreach ($filesReport['updated'] as $f): ?>
					<li class="list-group-item text-success"><i class="fa-solid fa-arrow-up"></i> <?= htmlspecialchars($f) ?></li>
				<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<div class="alert alert-warning">Nessun file aggiornato.</div>
		<?php endif; ?>

		<?php if (!empty($filesReport['unchanged'])): ?>
			<h5>File non modificati (già aggiornati)</h5>
			<ul class="list-group mb-3">
				<?php foreach ($filesReport['unchanged'] as $f): ?>
					<li class="list-group-item text-muted"><i class="fa-solid fa-check"></i> <?= htmlspecialchars($f) ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if (!empty($filesReport['skipped'])): ?>
			<h5>File saltati (ignorati)</h5>
			<ul class="list-group mb-3">
				<?php foreach ($filesReport['skipped'] as $f): ?>
					<li class="list-group-item text-info"><i class="fa-solid fa-forward"></i> <?= htmlspecialchars($f) ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if (!empty($filesReport['errors'])): ?>
			<h5>Errori</h5>
			<ul class="list-group mb-3">
				<?php foreach ($filesReport['errors'] as $e): ?>
					<li class="list-group-item text-danger"><i class="fa-solid fa-xmark"></i> <?= htmlspecialchars($e['file'] . ': ' . $e['message']) ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ($dbReport !== null): ?>
			<h5>DB update</h5>
			<?php if ($dbReport['success']): ?>
				<?php if (!empty($dbReport['executed_statements']) || !empty($dbReport['no_effect_statements']) || !empty($dbReport['failed_statements'])): ?>
					<div class="alert alert-info">
						<strong>Risultato esecuzione SQL:</strong><br>
						Eseguiti: <?= intval($dbReport['executed_statements']) ?> statement
						<?php if (!empty($dbReport['no_effect_statements'])): ?>
							| Senza effetto: <?= intval($dbReport['no_effect_statements']) ?> statement
						<?php endif; ?>
						<?php if (!empty($dbReport['failed_statements'])): ?>
							| Falliti: <?= intval($dbReport['failed_statements']) ?> statement
						<?php endif; ?>
						<?php if (!empty($dbReport['details'])): ?>
							<ul class="mt-2 mb-0">
								<?php foreach ($dbReport['details'] as $detail): ?>
									<li><?= htmlspecialchars($detail) ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php elseif (!empty($dbReport['output'])): ?>
					<div class="alert alert-success">Eseguito con output:<pre><?= htmlspecialchars($dbReport['output']) ?></pre></div>
				<?php else: ?>
					<div class="alert alert-success">DB update eseguito.</div>
				<?php endif; ?>
			<?php else: ?>
				<div class="alert alert-danger">Errore DB: <?= htmlspecialchars($dbReport['message']) ?></div>
			<?php endif; ?>
		<?php endif; ?>

		<a href="/magazzino/index.php" class="btn btn-primary mt-3">Torna all'applicazione</a>
	</div>
</body>
</html>

<?php
exit;