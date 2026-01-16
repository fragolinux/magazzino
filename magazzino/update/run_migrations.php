<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-11 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-15 23:11:11
 *
 * Script per eseguire manualmente le migrazioni del database
 * Da usare dopo aver aggiornato i file del sistema
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/migration_manager.php';

// solo admin può eseguire le migrazioni
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
	http_response_code(403);
	echo "Accesso negato: permessi insufficienti.";
	exit;
}

// Esegui le migrazioni
$migrationManager = new MigrationManager($pdo);
$migrationResult = $migrationManager->runPendingMigrations();

// Dopo le migrazioni, verifica se dobbiamo aggiornare le credenziali DB
$currentVersion = $migrationManager->getCurrentVersion();
$configUpdated = false;
if (version_compare($currentVersion, '1.8', '>=')) {
    // Aggiorna il config/database.php con magazzino_user (l'utente è stato creato dalla migrazione 1.8)
    $configFile = realpath(__DIR__ . '/../config/database.php');
    if (is_writable($configFile)) {
        $configContent = file_get_contents($configFile);
        
        // Sostituisci l'utente e password con quelli dedicati
        $configContent = preg_replace("/'user'\s*=>\s*'[^']*'/", "'user' => 'magazzino_user'", $configContent);
        $configContent = preg_replace("/'pass'\s*=>\s*'[^']*'/", "'pass' => 'SecurePass2024!'", $configContent);
        
        $result = file_put_contents($configFile, $configContent);
        if ($result !== false) {
            $configUpdated = true;
        } else {
            error_log("Errore scrittura config: $configFile");
        }
    } else {
        error_log("File config non scrivibile: $configFile");
    }
}

// Leggi la versione corrente dal file versioni.txt e registrala nel DB
$versionFile = __DIR__ . '/../versioni.txt';
if (file_exists($versionFile)) {
	$lines = file($versionFile);
	if (!empty($lines)) {
		// Cerca l'ultima riga con una versione
		foreach (array_reverse($lines) as $line) {
			$trimmed = trim($line);
			if (preg_match('/^(\d+\.\d+)\s+(\d{1,2}\/\d{1,2}\/\d{4})\s+(.+)/', $trimmed, $matches)) {
				$fileVersion = $matches[1];
				$description = $matches[3];
				$currentDbVersion = $migrationManager->getCurrentVersion();
				
				// Se la versione nel file è superiore a quella nel DB, aggiornala
				if (version_compare($fileVersion, $currentDbVersion, '>')) {
					$migrationManager->recordVersion($fileVersion, $description);
				}
				break;
			}
		}
	}
}

// Crea un report
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
		'message' => $migrationResult['message'] . ($configUpdated ? ' Configurazione database aggiornata con utente dedicato.' : ' (Config non aggiornato. Versione: ' . $currentVersion . ')')
	];
} else {
	$dbReport = [
		'success' => true,
		'message' => $migrationResult['message'] . ($configUpdated ? ' Configurazione database aggiornata con utente dedicato.' : ' (Versione corrente: ' . $currentVersion . ')')
	];
}

?><!doctype html>
<html lang="it">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Migrazioni Database</title>
	<link href="/magazzino/assets/css/bootstrap.min.css" rel="stylesheet">
	<link href="/magazzino/assets/css/all.min.css" rel="stylesheet">
</head>
<body class="p-4">
	<div class="container">
		<h1><i class="fa-solid fa-database me-2"></i>Migrazioni Database</h1>
		
		<?php if (!empty($migrationResult['firstRun']) && !empty($migrationResult['detectedVersion'])): ?>
			<div class="alert alert-success mt-4">
				<i class="fa-solid fa-rocket me-2"></i>
				<strong>Sistema di versioning inizializzato automaticamente!</strong><br>
				Versione database rilevata: <strong><?= htmlspecialchars($migrationResult['detectedVersion']) ?></strong>
			</div>
		<?php endif; ?>
		
		<?php if ($dbReport['success']): ?>
			<div class="alert alert-success mt-4">
				<i class="fa-solid fa-check-circle me-2"></i>
				<strong><?= htmlspecialchars($dbReport['message']) ?></strong>
			</div>
			
			<?php if (!empty($dbReport['executed_statements']) || !empty($dbReport['no_effect_statements']) || !empty($dbReport['failed_statements'])): ?>
				<div class="card mt-3">
					<div class="card-header">
						<h5 class="mb-0">Dettagli esecuzione</h5>
					</div>
					<div class="card-body">
						<p class="mb-2">
							<strong>Statement eseguiti:</strong> <?= intval($dbReport['executed_statements']) ?>
							<?php if (!empty($dbReport['no_effect_statements'])): ?>
								<br><strong>Statement senza effetto (già esistenti):</strong> <?= intval($dbReport['no_effect_statements']) ?>
							<?php endif; ?>
							<?php if (!empty($dbReport['failed_statements'])): ?>
								<br><strong class="text-danger">Statement falliti:</strong> <?= intval($dbReport['failed_statements']) ?>
							<?php endif; ?>
						</p>
						
						<?php if (!empty($dbReport['details'])): ?>
							<hr>
							<h6>Dettagli:</h6>
							<ul class="mb-0">
								<?php foreach ($dbReport['details'] as $detail): ?>
								<li><?= $detail ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		<?php else: ?>
			<div class="alert alert-danger mt-4">
				<i class="fa-solid fa-exclamation-triangle me-2"></i>
				<strong>Errore durante le migrazioni:</strong><br>
				<?= htmlspecialchars($dbReport['message']) ?>
			</div>
		<?php endif; ?>
		
		<div class="mt-4">
			<a href="/magazzino/index.php" class="btn btn-primary">
				<i class="fa-solid fa-home me-1"></i>Torna all'applicazione
			</a>
			<a href="/magazzino/update/index.php" class="btn btn-secondary">
				<i class="fa-solid fa-arrow-left me-1"></i>Torna agli aggiornamenti
			</a>
		</div>
	</div>
</body>
</html>
