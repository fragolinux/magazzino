<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-11
 * 
 * Script OPZIONALE di inizializzazione sistema versioning
 * 
 * NOTA: Con il sistema automatico implementato in migration_manager.php,
 * questo script è RARAMENTE necessario. Il sistema si auto-inizializza
 * quando si carica un aggiornamento con il nuovo sistema di migrazioni.
 * 
 * Usa questo script solo in casi particolari:
 * - Troubleshooting
 * - Inizializzazione manuale forzata
 * - Se il rilevamento automatico fallisce
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/migration_manager.php';

// Solo admin può eseguire questo script
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Accesso negato: permessi insufficienti.";
    exit;
}

$migrationManager = new MigrationManager($pdo);

// Controlla quale versione settare come punto di partenza
$initVersion = '1.6'; // Versione attuale del sistema

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['init_version'])) {
    $initVersion = $_POST['init_version'];
    
    try {
        $result = $migrationManager->initializeVersioning($initVersion);
        $success = true;
        $message = $result['message'];
    } catch (Exception $e) {
        $success = false;
        $message = "Errore: " . $e->getMessage();
    }
}

?><!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Inizializza Sistema Versioning</title>
    <link href="/magazzino/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/magazzino/assets/css/all.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h1><i class="fa-solid fa-database me-2"></i>Inizializzazione Sistema Versioning</h1>
        
        <div class="alert alert-info mt-4">
            <h5><i class="fa-solid fa-info-circle me-2"></i>Informazioni</h5>
            <p><strong>ATTENZIONE:</strong> Questo script è <strong>OPZIONALE</strong> e raramente necessario.</p>
            <p>Il sistema di versioning si <strong>auto-inizializza automaticamente</strong> quando carichi un aggiornamento tramite <a href="index.php">update/index.php</a>.</p>
            <p class="mb-0">Usa questo script solo se:</p>
            <ul>
                <li>Vuoi inizializzare manualmente il sistema</li>
                <li>Il rilevamento automatico ha fallito</li>
                <li>Stai facendo troubleshooting</li>
            </ul>
            <div class="alert alert-warning mt-2 mb-0">
                <small><strong>Consiglio:</strong> Se stai facendo un aggiornamento normale, usa direttamente <a href="index.php">update/index.php</a> invece di questo script.</small>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-<?= $success ? 'success' : 'danger' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            
            <?php if ($success): ?>
                <div class="mt-3">
                    <a href="/magazzino/index.php" class="btn btn-primary">
                        <i class="fa-solid fa-home me-1"></i>Torna all'applicazione
                    </a>
                    <a href="/magazzino/update/index.php" class="btn btn-secondary">
                        <i class="fa-solid fa-download me-1"></i>Vai agli aggiornamenti
                    </a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php
            $currentVersion = $migrationManager->getCurrentVersion();
            $dbVersionExists = $currentVersion !== '1.0' || $migrationManager->getCurrentVersion() !== '1.0';
            ?>
            
            <?php if ($dbVersionExists && $currentVersion !== '1.0'): ?>
                <div class="alert alert-warning">
                    <strong>Attenzione:</strong> Il sistema di versioning sembra già inizializzato (versione corrente: <?= htmlspecialchars($currentVersion) ?>).
                    <br>Se procedi, verrà registrata una nuova versione base.
                </div>
            <?php endif; ?>
            
            <div class="card mt-4" style="max-width: 600px;">
                <div class="card-header">
                    <h5 class="mb-0">Configura Versione Iniziale</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="init_version" class="form-label">Versione attuale del database:</label>
                            <select class="form-select" id="init_version" name="init_version" required>
                                <option value="1.0">1.0 - Installazione base</option>
                                <option value="1.1">1.1 - Carico/scarico quantità</option>
                                <option value="1.2">1.2 - Sistema aggiornamento + tabella setting</option>
                                <option value="1.3">1.3 - QR Code + datasheet PDF + remember me</option>
                                <option value="1.4">1.4 - Locali + quantità minima + gerarchia</option>
                                <option value="1.5">1.5 - Associazione locali default</option>
                                <option value="1.6" selected>1.6 - Configurazione DB porta personalizzata</option>
                            </select>
                            <small class="text-muted">Seleziona la versione corrispondente al tuo database attuale. Se hai dubbi, scegli l'ultima versione che hai installato.</small>
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>Importante:</strong> Assicurati di aver fatto un backup del database prima di procedere!
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-check me-1"></i>Inizializza Sistema
                        </button>
                        <a href="/magazzino/index.php" class="btn btn-secondary">Annulla</a>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4" style="max-width: 600px;">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Metodo Alternativo: Esecuzione Manuale</h6>
                </div>
                <div class="card-body">
                    <p class="small">Se preferisci, puoi eseguire manualmente questo SQL:</p>
                    <pre class="bg-light p-2 small"><code>-- Esegui questo SQL nel tuo database
<?= htmlspecialchars(file_get_contents(__DIR__ . '/../_DOC/create_db_version_table.sql')) ?>
</code></pre>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>