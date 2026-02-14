<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-04 12:59:50 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-10 14:04:07
 *
 * Update script:
 * - apre file .zip nella stessa cartella
 * - estrae in una cartella temporanea
 * - copia i file nella root del progetto (../)
 * - se presente, esegue db_update.php copiato nella root
 * - ritorna un report dei file aggiornati / errori
 */

// 2026-02-01: aggiunto controllo per rilevare il tema
// 2026-02-09: aggiunta verifica del sistema prima di procedere con l'aggiornamento (PHP, MySQL, permessi cartelle/file)

require_once __DIR__ . '/../config/base_path.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/migration_manager.php';

// Leggi il tema da file config
$appTheme = 'light'; // default
$settingsConfig = @include __DIR__ . '/../config/settings.php';
if ($settingsConfig && isset($settingsConfig['app_theme'])) {
    $appTheme = in_array($settingsConfig['app_theme'], ['light', 'dark']) ? $settingsConfig['app_theme'] : 'light';
}

// solo admin può eseguire l'aggiornamento
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
		http_response_code(403);
		echo "Accesso negato: permessi insufficienti.";
		exit;
}

// ============================================================
// VERIFICA PRELIMINARE SISTEMA
// ============================================================

/**
 * Verifica i requisiti di sistema prima dell'aggiornamento
 * @return array Report delle verifiche
 */
function checkSystemRequirements() {
    $report = [
        'php' => ['ok' => false, 'version' => PHP_VERSION, 'required' => '8.0.0', 'message' => ''],
        'mysql' => ['ok' => false, 'version' => '', 'required' => '10.4.0', 'message' => ''],
        'folders' => [],
        'files' => [],
        'can_proceed' => true,
        'warnings' => []
    ];
    
    // Verifica PHP >= 8.0
    $report['php']['ok'] = version_compare(PHP_VERSION, '8.0.0', '>=');
    if (!$report['php']['ok']) {
        $report['php']['message'] = "PHP " . PHP_VERSION . " è obsoleto. Richiesto >= 8.0";
        $report['can_proceed'] = false;
    } else {
        $report['php']['message'] = "PHP " . PHP_VERSION . " ✓";
    }
    
    // Verifica MySQL >= 8.0 o MariaDB >= 10.4
    try {
        global $pdo;
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT VERSION() as version");
            $dbVersion = $stmt->fetchColumn();
            $report['mysql']['version'] = $dbVersion;
            
            // Rileva se è MariaDB o MySQL
            $isMariaDB = stripos($dbVersion, 'MariaDB') !== false;
            $isMySQL = !$isMariaDB; // Se non è MariaDB, presumibilmente è MySQL
            
            // Estrai versione numerica (es. "10.5.8-MariaDB" -> "10.5.8", "8.0.33" -> "8.0.33")
            preg_match('/^(\d+\.\d+\.?\d*)/', $dbVersion, $matches);
            $numericVersion = isset($matches[1]) ? $matches[1] : $dbVersion;
            
            if ($isMariaDB) {
                // MariaDB richiede >= 10.4
                $report['mysql']['required'] = '10.4.0';
                $report['mysql']['ok'] = version_compare($numericVersion, '10.4.0', '>=');
                if (!$report['mysql']['ok']) {
                    $report['mysql']['message'] = "MariaDB $dbVersion è obsoleto. Richiesto >= 10.4";
                    $report['can_proceed'] = false;
                } else {
                    $report['mysql']['message'] = "MariaDB $dbVersion ✓";
                }
            } else {
                // MySQL richiede >= 8.0
                $report['mysql']['required'] = '8.0.0';
                $report['mysql']['ok'] = version_compare($numericVersion, '8.0.0', '>=');
                if (!$report['mysql']['ok']) {
                    $report['mysql']['message'] = "MySQL $dbVersion è obsoleto. Richiesto >= 8.0";
                    $report['can_proceed'] = false;
                } else {
                    $report['mysql']['message'] = "MySQL $dbVersion ✓";
                }
            }
        } else {
            $report['mysql']['message'] = "Connessione DB non disponibile";
            $report['can_proceed'] = false;
        }
    } catch (Exception $e) {
        $report['mysql']['message'] = "Errore: " . $e->getMessage();
        $report['can_proceed'] = false;
    }
    
    // Cartelle da verificare (ricorsive per images e datasheet)
    $foldersToCheck = [
        'images' => ['recursive' => true],
        'datasheet' => ['recursive' => true],
        'update' => ['recursive' => false],
        'config' => ['recursive' => false]
    ];
    
    $projectRoot = realpath(__DIR__ . '/..');
    $isLinux = (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN');
    
    foreach ($foldersToCheck as $folder => $options) {
        $path = $projectRoot . DIRECTORY_SEPARATOR . $folder;
        $folderReport = checkFolderWritable($path, $options['recursive'], $isLinux);
        $report['folders'][$folder] = $folderReport;
        
        if (!$folderReport['writable']) {
            $report['can_proceed'] = false;
        }
    }
    
    // File specifici da verificare
    $filesToCheck = [
        'config/database.php',
        'config/settings.php'
    ];
    
    foreach ($filesToCheck as $file) {
        $path = $projectRoot . DIRECTORY_SEPARATOR . $file;
        $fileReport = checkFileWritable($path, $isLinux);
        $report['files'][$file] = $fileReport;
        
        if (!$fileReport['writable']) {
            $report['can_proceed'] = false;
        }
    }
    
    return $report;
}

/**
 * Verifica se una cartella è scrivibile (opzionalmente ricorsivo)
 */
function checkFolderWritable($path, $recursive = false, $isLinux = false) {
    $result = [
        'path' => $path,
        'exists' => false,
        'writable' => false,
        'items' => [],
        'fixed' => false,
        'message' => ''
    ];
    
    if (!file_exists($path)) {
        $result['message'] = "Cartella non esistente";
        return $result;
    }
    
    $result['exists'] = true;
    
    // Verifica scrittura cartella principale
    $isWritable = is_writable($path);
    
    // Su Linux, tenta di fixare i permessi
    if (!$isWritable && $isLinux) {
        @chmod($path, 0755);
        $isWritable = is_writable($path);
        if ($isWritable) {
            $result['fixed'] = true;
        }
    }
    
    if (!$isWritable) {
        $result['message'] = "Cartella non scrivibile";
        return $result;
    }
    
    $result['writable'] = true;
    $result['message'] = "Scrivibile ✓";
    
    // Se ricorsivo, verifica anche le sottocartelle
    if ($recursive && is_dir($path)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();
            $itemWritable = $item->isWritable();
            
            // Su Linux, tenta di fixare
            if (!$itemWritable && $isLinux && $item->isDir()) {
                @chmod($itemPath, 0755);
                $itemWritable = is_writable($itemPath);
            } elseif (!$itemWritable && $isLinux && $item->isFile()) {
                @chmod($itemPath, 0644);
                $itemWritable = is_writable($itemPath);
            }
            
            $relativePath = str_replace($path . DIRECTORY_SEPARATOR, '', $itemPath);
            $result['items'][$relativePath] = [
                'type' => $item->isDir() ? 'dir' : 'file',
                'writable' => $itemWritable,
                'fixed' => ($isLinux && !$item->isWritable() && $itemWritable)
            ];
            
            if (!$itemWritable) {
                $result['writable'] = false;
                $result['message'] = "Elementi non scrivibili trovati";
            }
        }
    }
    
    return $result;
}

/**
 * Verifica se un file è scrivibile
 */
function checkFileWritable($path, $isLinux = false) {
    $result = [
        'path' => $path,
        'exists' => false,
        'writable' => false,
        'fixed' => false,
        'message' => ''
    ];
    
    if (!file_exists($path)) {
        $result['message'] = "File non esistente";
        return $result;
    }
    
    $result['exists'] = true;
    $isWritable = is_writable($path);
    
    // Su Linux, tenta di fixare i permessi
    if (!$isWritable && $isLinux) {
        @chmod($path, 0644);
        $isWritable = is_writable($path);
        if ($isWritable) {
            $result['fixed'] = true;
        }
    }
    
    $result['writable'] = $isWritable;
    $result['message'] = $isWritable ? "Scrivibile ✓" : "File non scrivibile";
    
    return $result;
}

// Esegui verifica preliminare
$systemCheck = checkSystemRequirements();

// Se c'è una richiesta di refresh dei check, forza la visualizzazione
$forceCheck = isset($_GET['check']) && $_GET['check'] === '1';

// Mostra report se ci sono problemi o se richiesto esplicitamente
if ((!$systemCheck['can_proceed'] || $forceCheck) && !isset($_POST['proceed_anyway'])) {
    ?><!doctype html>
    <html lang="it" data-bs-theme="<?= $appTheme ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Verifica Sistema - Aggiornamento</title>
        <link href="<?= BASE_PATH ?>assets/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?= BASE_PATH ?>assets/css/all.min.css" rel="stylesheet">
        <style>
            .check-item { padding: 10px; margin-bottom: 5px; border-radius: 5px; }
            .check-ok { background-color: #d4edda; border: 1px solid #c3e6cb; }
            .check-error { background-color: #f8d7da; border: 1px solid #f5c6cb; }
            .check-warning { background-color: #fff3cd; border: 1px solid #ffeeba; }
            .check-fixed { background-color: #d1ecf1; border: 1px solid #bee5eb; }
        </style>
    </head>
    <body class="p-4">
        <div class="container">
            <h1><i class="fa-solid fa-stethoscope me-2"></i>Verifica Preliminare Sistema</h1>
            
            <?php if (!$systemCheck['can_proceed']): ?>
            <div class="alert alert-danger mt-4">
                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                <strong>Attenzione:</strong> Sono stati rilevati problemi che impediscono l'aggiornamento.
                Correggili prima di procedere.
            </div>
            <?php else: ?>
            <div class="alert alert-success mt-4">
                <i class="fa-solid fa-check-circle me-2"></i>
                <strong>Tutto OK:</strong> Il sistema soddisfa tutti i requisiti per l'aggiornamento.
            </div>
            <?php endif; ?>
            
            <!-- PHP Version -->
            <h4 class="mt-4"><i class="fa-brands fa-php me-2"></i>Versione PHP</h4>
            <div class="check-item <?= $systemCheck['php']['ok'] ? 'check-ok' : 'check-error' ?>">
                <strong>Versione attuale:</strong> <?= htmlspecialchars($systemCheck['php']['version']) ?><br>
                <strong>Richiesta:</strong> >= <?= htmlspecialchars($systemCheck['php']['required']) ?><br>
                <i class="fa-solid <?= $systemCheck['php']['ok'] ? 'fa-check' : 'fa-xmark' ?>"></i>
                <?= htmlspecialchars($systemCheck['php']['message']) ?>
            </div>
            
            <!-- MySQL/MariaDB Version -->
            <h4 class="mt-4"><i class="fa-solid fa-database me-2"></i>Versione Database</h4>
            <div class="check-item <?= $systemCheck['mysql']['ok'] ? 'check-ok' : 'check-error' ?>">
                <strong>Versione rilevata:</strong> <?= htmlspecialchars($systemCheck['mysql']['version'] ?: 'N/D') ?><br>
                <strong>Richiesta:</strong> >= <?= htmlspecialchars($systemCheck['mysql']['required']) ?><br>
                <i class="fa-solid <?= $systemCheck['mysql']['ok'] ? 'fa-check' : 'fa-xmark' ?>"></i>
                <?= htmlspecialchars($systemCheck['mysql']['message']) ?>
            </div>
            
            <!-- Cartelle -->
            <h4 class="mt-4"><i class="fa-solid fa-folder me-2"></i>Permessi Cartelle</h4>
            <?php foreach ($systemCheck['folders'] as $folderName => $folderData): ?>
                <div class="check-item <?= $folderData['writable'] ? ($folderData['fixed'] ? 'check-fixed' : 'check-ok') : 'check-error' ?>">
                    <strong><?= htmlspecialchars($folderName) ?>/</strong><br>
                    <i class="fa-solid <?= $folderData['writable'] ? 'fa-check' : 'fa-xmark' ?>"></i>
                    <?= htmlspecialchars($folderData['message']) ?>
                    <?php if ($folderData['fixed']): ?>
                        <span class="badge bg-info">Permessi corretti automaticamente</span>
                    <?php endif; ?>
                    
                    <?php if (!empty($folderData['items']) && !$folderData['writable']): ?>
                        <div class="mt-2 small">
                            <strong>Elementi con problemi:</strong>
                            <ul class="mb-0">
                                <?php 
                                $problemItems = array_filter($folderData['items'], function($i) { return !$i['writable']; });
                                $shown = 0;
                                foreach ($problemItems as $itemPath => $itemData): 
                                    if ($shown++ > 5): 
                                ?>
                                    <li>... e altri <?= count($problemItems) - 5 ?> elementi</li>
                                    <?php break; endif; ?>
                                    <li><?= htmlspecialchars($itemPath) ?> (<?= $itemData['type'] ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <!-- File -->
            <h4 class="mt-4"><i class="fa-solid fa-file me-2"></i>Permessi File</h4>
            <?php foreach ($systemCheck['files'] as $fileName => $fileData): ?>
                <div class="check-item <?= $fileData['writable'] ? ($fileData['fixed'] ? 'check-fixed' : 'check-ok') : 'check-error' ?>">
                    <strong><?= htmlspecialchars($fileName) ?></strong><br>
                    <i class="fa-solid <?= $fileData['writable'] ? 'fa-check' : 'fa-xmark' ?>"></i>
                    <?= htmlspecialchars($fileData['message']) ?>
                    <?php if ($fileData['fixed']): ?>
                        <span class="badge bg-info">Permessi corretti automaticamente</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <!-- Azioni -->
            <div class="mt-4">
                <?php if (!$systemCheck['can_proceed']): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="proceed_anyway" value="1">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Procedere comunque potrebbe causare errori. Sei sicuro?');">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i>Procedi comunque (rischio tuo)
                        </button>
                    </form>
                <?php endif; ?>
                <a href="?check=1" class="btn btn-secondary">
                    <i class="fa-solid fa-rotate me-2"></i>Aggiorna verifica
                </a>
                <?php if ($systemCheck['can_proceed']): ?>
                    <a href="?" class="btn btn-primary">
                        <i class="fa-solid fa-arrow-right me-2"></i>Continua con l'aggiornamento
                    </a>
                <?php endif; ?>
                <a href="<?= BASE_PATH ?>index.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-home me-2"></i>Torna all'applicazione
                </a>
            </div>
            
            <!-- Info aggiuntive -->
            <?php if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN'): ?>
            <div class="alert alert-info mt-4">
                <i class="fa-solid fa-linux me-2"></i>
                <strong>Sistema Linux rilevato:</strong> Su Linux potrebbero essere necessari permessi specifici.
                Se i permessi non possono essere corretti automaticamente, esegui:<br>
                <code>chmod -R 755 images/ datasheet/ update/ config/</code><br>
                <code>chmod 644 config/database.php config/settings.php</code>
            </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
// FINE VERIFICA PRELIMINARE
// ============================================================

// Esegui pulizia preventiva SOLO di file temporanei (non ZIP)
include __DIR__ . '/cleanup.php';

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
			// Segna che abbiamo appena caricato un file
			$_SESSION['just_uploaded_zip'] = true;
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

/**
 * Pulisce file e cartelle temporanee dalla cartella update
 */
function cleanupOldFiles($updateDir) {
    $cleaned = ['temp_dirs' => [], 'zip_files' => []];
    
    // Elimina cartelle temporanee (_temp_*)
    $tempDirs = glob($updateDir . DIRECTORY_SEPARATOR . '_temp_*', GLOB_ONLYDIR);
    foreach ($tempDirs as $dir) {
        if (is_dir($dir)) {
            rrmdir($dir);
            $cleaned['temp_dirs'][] = basename($dir);
        }
    }
    
    // Elimina file ZIP vecchi (tranne update.zip se presente)
    $zipFiles = glob($updateDir . DIRECTORY_SEPARATOR . '*.zip');
    foreach ($zipFiles as $zipFile) {
        $basename = basename($zipFile);
        // Mantieni update.zip se è stato appena caricato, ma elimina altri ZIP
        $justUploaded = isset($_SESSION['just_uploaded_zip']) && $_SESSION['just_uploaded_zip'];
        if ($basename !== 'update.zip' || !$justUploaded) {
            if (@unlink($zipFile)) {
                $cleaned['zip_files'][] = $basename;
            }
        }
    }
    
    return $cleaned;
}

// Percorsi
$projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');

// Variabili per la pulizia
$tmpDir = null;

// Pulizia preventiva di file/cartelle temporanee
$cleanupResult = cleanupOldFiles(__DIR__);

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
	<html lang="it" data-bs-theme="<?= $appTheme ?>">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width,initial-scale=1">
		<title>Aggiornamento - Upload</title>
		<link href="<?= BASE_PATH ?>assets/css/bootstrap.min.css" rel="stylesheet">
		<link href="<?= BASE_PATH ?>assets/css/all.min.css" rel="stylesheet">
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
		
		<?php if (!empty($cleanupResult['temp_dirs']) || !empty($cleanupResult['zip_files'])): ?>
			<div class="alert alert-success">
				<i class="fa-solid fa-broom me-2"></i>
				<strong>Pulizia completata:</strong>
				<?php if (!empty($cleanupResult['temp_dirs'])): ?>
					Cartelle temporanee eliminate: <?= implode(', ', $cleanupResult['temp_dirs']) ?>
				<?php endif; ?>
				<?php if (!empty($cleanupResult['zip_files'])): ?>
					<?php if (!empty($cleanupResult['temp_dirs'])): ?><br><?php endif; ?>
					File ZIP eliminati: <?= implode(', ', $cleanupResult['zip_files']) ?>
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
						<a href="<?= BASE_PATH ?>index.php" class="btn btn-secondary">Annulla</a>
					<a href="?check=1" class="btn btn-outline-info">
						<i class="fa-solid fa-stethoscope me-1"></i>Verifica sistema
					</a>
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

// Reset del flag di upload appena fatto
unset($_SESSION['just_uploaded_zip']);

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

$zip->close();

// Elimina il file ZIP originale dopo l'estrazione (non serve più)
if (file_exists($zipPath)) {
    @unlink($zipPath);
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
	<html lang="it" data-bs-theme="<?= $appTheme ?>">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width,initial-scale=1">
		<title>Attenzione - Downgrade versione</title>
		<link href="<?= BASE_PATH ?>assets/css/bootstrap.min.css" rel="stylesheet">
		<link href="<?= BASE_PATH ?>assets/css/all.min.css" rel="stylesheet">
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

$filesReport = ['updated' => [], 'skipped' => [], 'errors' => [], 'unchanged' => [], 'currentVersion' => $currentVersion, 'newVersion' => $newVersion];

// Funzione per ottenere tutti i file dalla cartella temporanea in modo ricorsivo
function getAllFiles($dir, $baseDir = '') {
    $files = [];
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $relPath = $baseDir ? $baseDir . '/' . $item : $item;
        if (is_dir($path)) {
            $files = array_merge($files, getAllFiles($path, $relPath));
        } else {
            $files[] = $relPath;
        }
    }
    return $files;
}

// Ottieni tutti i file dalla cartella temporanea
$allFiles = getAllFiles($tmpDir);

// Copia file uno per uno, proteggendo da path traversal
foreach ($allFiles as $rel) {
    // normalizza percorso e impedisce path traversal
    $rel = str_replace(['\\', '/\0'], ['/', ''], $rel);
    $rel = preg_replace('#(^/|\.{2}/)#', '', $rel);
    $rel = ltrim($rel, '/');
    
    // non copiare il file db_update.php: verrà eseguito separatamente
    if (strtolower(basename($rel)) === 'db_update.php') {
        continue;
    }
    if ($rel === '') continue;

    $source = $tmpDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $dest = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $destDir = dirname($dest);
    
    // sicurezza: il destinazione deve iniziare con project root
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

// Controlla se update/index.php o migration_manager.php sono stati aggiornati
$needsManualMigration = in_array('update/index.php', $filesReport['updated']) || 
                        in_array('update/migration_manager.php', $filesReport['updated']);

// Se i file di update sono stati modificati E non siamo già in fase 2, mostra il messaggio
if ($needsManualMigration && !$isPhase2) {
	?><!doctype html>
	<html lang="it" data-bs-theme="<?= $appTheme ?>">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width,initial-scale=1">
		<title>Aggiornamento - File aggiornati</title>
		<link href="<?= BASE_PATH ?>assets/css/bootstrap.min.css" rel="stylesheet">
		<link href="<?= BASE_PATH ?>assets/css/all.min.css" rel="stylesheet">
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
				<strong>Clicca sul pulsante in fondo alla pagina </strong>per completare l'aggiornamento del database.
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
				<a href="<?= BASE_PATH ?>update/run_migrations.php" class="btn btn-primary btn-lg">
					<i class="fa-solid fa-database me-2"></i>Completa aggiornamento database
				</a>
			</div>
		</div>
	</body>
	</html>
	<?php
	exit;
}

// Se non serve migrazione manuale, esegui subito le migrazioni
$configUpdated = false;
if (!$isPhase2) {
	// Usa il nuovo sistema di migrazioni
	$migrationManager = new MigrationManager($pdo);
	$migrationResult = $migrationManager->runPendingMigrations();
	
	// Dopo le migrazioni, verifica se dobbiamo aggiornare le credenziali DB
	$currentVersion = $migrationManager->getCurrentVersion();
	$configUpdated = false;
	$configFile = realpath(__DIR__ . '/../config/database.php');
	if (file_exists($configFile) && is_writable($configFile)) {
		$configContent = file_get_contents($configFile);
		
		// Controlla se le credenziali sono ancora 'root' prima di sostituire
		if (preg_match("/'user'\s*=>\s*'root'/", $configContent)) {
			// Sostituisci l'utente e password con quelli dedicati
			$configContent = preg_replace("/'user'\s*=>\s*'root'/", "'user' => 'magazzino_user'", $configContent);
			$configContent = preg_replace("/'pass'\s*=>\s*'[^']*'/", "'pass' => 'SecurePass2024!'", $configContent);
			
			if (file_put_contents($configFile, $configContent) !== false) {
				$configUpdated = true;
			} else {
				error_log("Errore scrittura config: $configFile");
			}
		}
	}
	
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
		'message' => $migrationResult['message'] . ($configUpdated ? ' Configurazione database aggiornata con utente dedicato.' : ' (Config non aggiornato)')
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
// Esegui la pulizia finale
include __DIR__ . '/cleanup.php';
?>
<!doctype html>
<html lang="it" data-bs-theme="<?= $appTheme ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Aggiornamento</title>
	<link href="<?= BASE_PATH ?>assets/css/bootstrap.min.css" rel="stylesheet">
	<link href="<?= BASE_PATH ?>assets/css/all.min.css" rel="stylesheet">
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
								<li><?= $detail ?></li>
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
		<a href="<?= BASE_PATH ?>index.php" class="btn btn-primary mt-3">Torna all'applicazione</a>
	</div>
</body>
</html>

<?php
// Pulizia finale: elimina file ZIP e cartelle temporanee
$_GET['cleanup_zips'] = '1';
include __DIR__ . '/cleanup.php';
exit;