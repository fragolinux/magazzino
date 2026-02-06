<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-05 09:20:18 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-03 11:41:10
*/
// 2026-01-08: Aggiunto supporto tema dark/light
// 2026-01-11: Aggiunto URL in alternativa all'IP del PC
// 2026-01-12: Aggiunta gestione modalità errori PHP da impostazioni
// 2026-01-13: Aggiunta configurazione database, salvataggio in file, persistenza tab
// 2026-01-14: Aggiunta funzionalità download backup database
// 2026-02-01: Aggiunti parametri QR Code (qr_per_riga, qr_size) e barcode
// 2026-02-02: Aggiunto sito personale completamente configurabile

/**
 * Pagina impostazioni generali (solo admin)
 * NOTA: Non include db_connect.php per permettere modifica credenziali database
 */

require_once __DIR__ . '/includes/auth_check.php';

// solo admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Accesso negato: permessi insufficienti.";
    exit;
}

$message = null;
$error = null;
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'general'; // tab predefinito o da URL

// Carica configurazione database corrente
$dbConfigFile = __DIR__ . '/config/database.php';
$dbConfig = file_exists($dbConfigFile) ? require $dbConfigFile : [
    'host' => 'localhost',
    'db' => 'magazzino_db',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
    'port' => 3306
];

// Carica configurazione impostazioni generali
$settingsConfigFile = __DIR__ . '/config/settings.php';
$appSettings = file_exists($settingsConfigFile) ? require $settingsConfigFile : [
    'ip_address' => '',
    'app_theme' => 'light',
    'environment_mode' => 'production'
];

// Rilevamento automatico IP della scheda di rete
function detectLocalIP() {
    // Prima prova: socket UDP verso DNS pubblico per determinare IP locale
    $ip = null;
    $sock = @stream_socket_client("udp://8.8.8.8:53", $errno, $errstr, 1);
    if ($sock !== false) {
        $name = stream_socket_get_name($sock, false); // local address:port
        if ($name !== false) {
            $parts = explode(':', $name);
            if (filter_var($parts[0], FILTER_VALIDATE_IP)) {
                $ip = $parts[0];
            }
        }
        fclose($sock);
    }

    // Seconda prova: socket extension se disponibile
    if (empty($ip) && function_exists('socket_create')) {
        $s = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($s !== false) {
            @socket_connect($s, '8.8.8.8', 53);
            @socket_getsockname($s, $localIp, $port);
            if (!empty($localIp) && filter_var($localIp, FILTER_VALIDATE_IP)) {
                $ip = $localIp;
            }
            @socket_close($s);
        }
    }

    // Fallback: gethostbyname
    if (empty($ip) || $ip === '127.0.0.1') {
        $host = gethostname();
        $resolved = gethostbyname($host);
        if ($resolved && $resolved !== '127.0.0.1') {
            $ip = $resolved;
        }
    }

    return $ip ?: '127.0.0.1';
}

$detectedIp = detectLocalIP();

// Carica impostazioni QR Code dal database
$qrPerRiga = 10;  // valore default
$qrSize = '100'; // valore default

// Carica impostazioni Barcode dal database
$barcodePerRiga = 6;  // valore default
$barcodeWidth = '50'; // valore default (mm)
$barcodeHeight = '10'; // valore default (mm)

try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['db']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Leggi qr_per_riga
    $stmt = $pdo->prepare("SELECT setting_value FROM setting WHERE setting_name = ? LIMIT 1");
    $stmt->execute(['qr_per_riga']);
    $result = $stmt->fetch();
    if ($result) {
        $qrPerRiga = intval($result['setting_value']);
    }
    
    // Leggi qr_size
    $stmt = $pdo->prepare("SELECT setting_value FROM setting WHERE setting_name = ? LIMIT 1");
    $stmt->execute(['qr_size']);
    $result = $stmt->fetch();
    if ($result) {
        $qrSize = $result['setting_value'];
    }
    
    // Leggi barcode_per_riga
    $stmt = $pdo->prepare("SELECT setting_value FROM setting WHERE setting_name = ? LIMIT 1");
    $stmt->execute(['barcode_per_riga']);
    $result = $stmt->fetch();
    if ($result) {
        $barcodePerRiga = intval($result['setting_value']);
    }
    
    // Leggi barcode_width
    $stmt = $pdo->prepare("SELECT setting_value FROM setting WHERE setting_name = ? LIMIT 1");
    $stmt->execute(['barcode_width']);
    $result = $stmt->fetch();
    if ($result) {
        $barcodeWidth = $result['setting_value'];
    }
    
    // Leggi barcode_height
    $stmt = $pdo->prepare("SELECT setting_value FROM setting WHERE setting_name = ? LIMIT 1");
    $stmt->execute(['barcode_height']);
    $result = $stmt->fetch();
    if ($result) {
        $barcodeHeight = $result['setting_value'];
    }
} catch (Exception $e) {
    // Se il database non è raggiungibile, usa i default
}

// Gestione download backup database
if (isset($_GET['action']) && $_GET['action'] === 'backup_db') {
    try {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['db']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Genera dump SQL
        $backup = "-- Backup Database: {$dbConfig['db']}\n";
        $backup .= "-- Data: " . date('Y-m-d H:i:s') . "\n";
        $backup .= "-- Host: {$dbConfig['host']}:{$dbConfig['port']}\n\n";
        $backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        // Ottieni tutte le tabelle
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            // DROP TABLE
            $backup .= "-- Table: $table\n";
            $backup .= "DROP TABLE IF EXISTS `$table`;\n\n";
            
            // CREATE TABLE
            $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            $backup .= $createTable['Create Table'] . ";\n\n";
            
            // INSERT DATA
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $backup .= "-- Data for table: $table\n";
                
                foreach ($rows as $row) {
                    $columns = array_keys($row);
                    $values = array_map(function($val) use ($pdo) {
                        return $val === null ? 'NULL' : $pdo->quote($val);
                    }, array_values($row));
                    
                    $backup .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $backup .= "\n";
            }
        }
        
        $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Invia file per download
        $filename = $dbConfig['db'] . '_backup_' . date('Y-m-d_His') . '.sql';
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($backup));
        echo $backup;
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['backup_error'] = 'Errore backup database: ' . $e->getMessage();
        header('Location: settings.php?tab=database');
        exit;
    }
}

// Messaggio errore backup da sessione
if (isset($_SESSION['backup_error'])) {
    $error = $_SESSION['backup_error'];
    unset($_SESSION['backup_error']);
    $activeTab = 'database';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = isset($_POST['ip_address']) ? trim($_POST['ip_address']) : '';
    $theme = isset($_POST['app_theme']) ? trim($_POST['app_theme']) : 'light';
    $envMode = isset($_POST['environment_mode']) ? trim($_POST['environment_mode']) : 'production';
    $activeTab = isset($_POST['active_tab']) ? $_POST['active_tab'] : 'general'; // Mantieni tab attivo
    
    // Parametri QR Code
    $qrPerRiga = isset($_POST['qr_per_riga']) ? intval($_POST['qr_per_riga']) : 3;
    $qrSize = isset($_POST['qr_size']) ? trim($_POST['qr_size']) : '300';
    
    // Parametri Barcode
    $barcodePerRiga = isset($_POST['barcode_per_riga']) ? intval($_POST['barcode_per_riga']) : 5;
    $barcodeWidth = isset($_POST['barcode_width']) ? trim($_POST['barcode_width']) : '20';
    $barcodeHeight = isset($_POST['barcode_height']) ? trim($_POST['barcode_height']) : '10';
    
    // Parametri database
    $dbHost = isset($_POST['db_host']) ? trim($_POST['db_host']) : '';
    $dbName = isset($_POST['db_name']) ? trim($_POST['db_name']) : '';
    $dbUser = isset($_POST['db_user']) ? trim($_POST['db_user']) : '';
    $dbPass = isset($_POST['db_pass']) ? $_POST['db_pass'] : ''; // non trim sulla password
    $dbPort = isset($_POST['db_port']) ? trim($_POST['db_port']) : '';
    
    if ($ip === '') {
        $error = 'Indirizzo IP/URL non può essere vuoto.';
    } elseif (!filter_var($ip, FILTER_VALIDATE_IP) && !filter_var($ip, FILTER_VALIDATE_URL)) {
        $error = 'Formato non valido. Inserire un IP valido oppure un URL completo.';
    } elseif (!in_array($theme, ['light', 'dark'])) {
        $error = 'Tema non valido.';
    } elseif (!in_array($envMode, ['production', 'development'])) {
        $error = 'Modalità ambiente non valida.';
    } elseif ($qrPerRiga < 1 || $qrPerRiga > 20) {
        $error = 'QR per riga deve essere tra 1 e 20.';
    } elseif ($qrSize < 10 || $qrSize > 500) {
        $error = 'Dimensione QR deve essere tra 10 e 500 pixel.';
    } elseif ($barcodePerRiga < 1 || $barcodePerRiga > 20) {
        $error = 'Barcode per riga deve essere tra 1 e 20.';
    } elseif ($barcodeWidth < 5 || $barcodeWidth > 100) {
        $error = 'Larghezza barcode deve essere tra 5 e 100 mm.';
    } elseif ($barcodeHeight < 5 || $barcodeHeight > 50) {
        $error = 'Altezza barcode deve essere tra 5 e 50 mm.';
    } elseif ($activeTab === 'database' && ($dbHost === '' || $dbName === '' || $dbUser === '' || $dbPort === '')) {
        $error = 'Tutti i campi del database sono obbligatori (tranne la password).';
    } elseif ($activeTab === 'database' && (!is_numeric($dbPort) || $dbPort < 1 || $dbPort > 65535)) {
        $error = 'Porta database non valida (1-65535).';
    } else {
        // inserisci o aggiorna
        try {
            // Salva impostazioni generali in file
            $newAppSettings = [
                'ip_address' => $ip,
                'app_theme' => $theme,
                'environment_mode' => $envMode
            ];
            
            $settingsContent = "<?php\n";
            $settingsContent .= "/*\n";
            $settingsContent .= " * @Author: gabriele.riva \n";
            $settingsContent .= " * @Date: 2026-01-13\n";
            $settingsContent .= " * @Last Modified by: gabriele.riva\n";
            $settingsContent .= " * @Last Modified time: " . date('Y-m-d H:i:s') . "\n";
            $settingsContent .= " * \n";
            $settingsContent .= " * Configurazione Impostazioni Generali\n";
            $settingsContent .= " * Questo file contiene le impostazioni generali dell'applicazione\n";
            $settingsContent .= " */\n\n";
            $settingsContent .= "return [\n";
            $settingsContent .= "    'ip_address' => " . var_export($newAppSettings['ip_address'], true) . ",\n";
            $settingsContent .= "    'app_theme' => " . var_export($newAppSettings['app_theme'], true) . ",\n";
            $settingsContent .= "    'environment_mode' => " . var_export($newAppSettings['environment_mode'], true) . "\n";
            $settingsContent .= "];\n";
            
            if (file_put_contents($settingsConfigFile, $settingsContent) === false) {
                $error = 'Errore scrittura file configurazione impostazioni.';
            } else {
                $appSettings = $newAppSettings;
            }
            
            // Tenta anche di salvare nel database (se connessione funziona)
            try {
                $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['db']};charset={$dbConfig['charset']}";
                $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                
                // Salva IP
                $stmt = $pdo->prepare("SELECT id_setting FROM setting WHERE setting_name = ? LIMIT 1");
                $stmt->execute(['IP_Computer']);
                $exists = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($exists) {
                    $upd = $pdo->prepare("UPDATE setting SET setting_value = ? WHERE setting_name = ?");
                    $upd->execute([$ip, 'IP_Computer']);
                } else {
                    $ins = $pdo->prepare("INSERT INTO setting (setting_name, setting_value) VALUES (?, ?)");
                    $ins->execute(['IP_Computer', $ip]);
                }
                
                // Salva Tema
                $stmtT = $pdo->prepare("SELECT id_setting FROM setting WHERE setting_name = ? LIMIT 1");
                $stmtT->execute(['app_theme']);
                $existsT = $stmtT->fetch(PDO::FETCH_ASSOC);
                if ($existsT) {
                    $updT = $pdo->prepare("UPDATE setting SET setting_value = ? WHERE setting_name = ?");
                    $updT->execute([$theme, 'app_theme']);
                } else {
                    $insT = $pdo->prepare("INSERT INTO setting (setting_name, setting_value) VALUES (?, ?)");
                    $insT->execute(['app_theme', $theme]);
                }
                
                // Salva modalità ambiente
                $stmtE = $pdo->prepare("SELECT id_setting FROM setting WHERE setting_name = ? LIMIT 1");
                $stmtE->execute(['environment_mode']);
                $existsE = $stmtE->fetch(PDO::FETCH_ASSOC);
                if ($existsE) {
                    $updE = $pdo->prepare("UPDATE setting SET setting_value = ? WHERE setting_name = ?");
                    $updE->execute([$envMode, 'environment_mode']);
                } else {
                    $insE = $pdo->prepare("INSERT INTO setting (setting_name, setting_value) VALUES (?, ?)");
                    $insE->execute(['environment_mode', $envMode]);
                }
                
                // Salva QR per riga
                $stmtQR1 = $pdo->prepare("SELECT id_setting FROM setting WHERE setting_name = ? LIMIT 1");
                $stmtQR1->execute(['qr_per_riga']);
                $existsQR1 = $stmtQR1->fetch(PDO::FETCH_ASSOC);
                if ($existsQR1) {
                    $updQR1 = $pdo->prepare("UPDATE setting SET setting_value = ? WHERE setting_name = ?");
                    $updQR1->execute([$qrPerRiga, 'qr_per_riga']);
                } else {
                    $insQR1 = $pdo->prepare("INSERT INTO setting (setting_name, setting_value) VALUES (?, ?)");
                    $insQR1->execute(['qr_per_riga', $qrPerRiga]);
                }
                
                // Salva dimensione QR
                $stmtQR2 = $pdo->prepare("SELECT id_setting FROM setting WHERE setting_name = ? LIMIT 1");
                $stmtQR2->execute(['qr_size']);
                $existsQR2 = $stmtQR2->fetch(PDO::FETCH_ASSOC);
                if ($existsQR2) {
                    $updQR2 = $pdo->prepare("UPDATE setting SET setting_value = ? WHERE setting_name = ?");
                    $updQR2->execute([$qrSize, 'qr_size']);
                } else {
                    $insQR2 = $pdo->prepare("INSERT INTO setting (setting_name, setting_value) VALUES (?, ?)");
                    $insQR2->execute(['qr_size', $qrSize]);
                }
                
                // Salva barcode per riga
                $stmtBC1 = $pdo->prepare("SELECT id_setting FROM setting WHERE setting_name = ? LIMIT 1");
                $stmtBC1->execute(['barcode_per_riga']);
                $existsBC1 = $stmtBC1->fetch(PDO::FETCH_ASSOC);
                if ($existsBC1) {
                    $updBC1 = $pdo->prepare("UPDATE setting SET setting_value = ? WHERE setting_name = ?");
                    $updBC1->execute([$barcodePerRiga, 'barcode_per_riga']);
                } else {
                    $insBC1 = $pdo->prepare("INSERT INTO setting (setting_name, setting_value) VALUES (?, ?)");
                    $insBC1->execute(['barcode_per_riga', $barcodePerRiga]);
                }
                
                // Salva larghezza barcode
                $stmtBC2 = $pdo->prepare("SELECT id_setting FROM setting WHERE setting_name = ? LIMIT 1");
                $stmtBC2->execute(['barcode_width']);
                $existsBC2 = $stmtBC2->fetch(PDO::FETCH_ASSOC);
                if ($existsBC2) {
                    $updBC2 = $pdo->prepare("UPDATE setting SET setting_value = ? WHERE setting_name = ?");
                    $updBC2->execute([$barcodeWidth, 'barcode_width']);
                } else {
                    $insBC2 = $pdo->prepare("INSERT INTO setting (setting_name, setting_value) VALUES (?, ?)");
                    $insBC2->execute(['barcode_width', $barcodeWidth]);
                }
                
                // Salva altezza barcode
                $stmtBC3 = $pdo->prepare("SELECT id_setting FROM setting WHERE setting_name = ? LIMIT 1");
                $stmtBC3->execute(['barcode_height']);
                $existsBC3 = $stmtBC3->fetch(PDO::FETCH_ASSOC);
                if ($existsBC3) {
                    $updBC3 = $pdo->prepare("UPDATE setting SET setting_value = ? WHERE setting_name = ?");
                    $updBC3->execute([$barcodeHeight, 'barcode_height']);
                } else {
                    $insBC3 = $pdo->prepare("INSERT INTO setting (setting_name, setting_value) VALUES (?, ?)");
                    $insBC3->execute(['barcode_height', $barcodeHeight]);
                }
            } catch (Exception $dbErr) {
                // Ignora errori DB, i dati sono già salvati su file
            }
            
            $message = 'Impostazioni salvate.';
            
            // Salva configurazione database SOLO se sei nel tab database
            if ($activeTab === 'database') {
                $newDbConfig = [
                    'host'    => $dbHost,
                    'db'      => $dbName,
                    'user'    => $dbUser,
                    'pass'    => $dbPass,
                    'charset' => 'utf8mb4',
                    'port'    => (int)$dbPort
                ];
                
                $configContent = "<?php\n";
            $configContent .= "/*\n";
            $configContent .= " * @Author: gabriele.riva \n";
            $configContent .= " * @Date: 2026-01-13\n";
            $configContent .= " * @Last Modified by: gabriele.riva\n";
            $configContent .= " * @Last Modified time: " . date('Y-m-d H:i:s') . "\n";
            $configContent .= " * \n";
            $configContent .= " * Configurazione Database\n";
            $configContent .= " * Questo file contiene le credenziali e parametri per la connessione al database MySQL\n";
            $configContent .= " */\n\n";
            $configContent .= "return [\n";
            $configContent .= "    'host'    => " . var_export($newDbConfig['host'], true) . ",\n";
            $configContent .= "    'db'      => " . var_export($newDbConfig['db'], true) . ",\n";
            $configContent .= "    'user'    => " . var_export($newDbConfig['user'], true) . ",\n";
            $configContent .= "    'pass'    => " . var_export($newDbConfig['pass'], true) . ",\n";
            $configContent .= "    'charset' => " . var_export($newDbConfig['charset'], true) . ",\n";
            $configContent .= "    'port'    => " . var_export($newDbConfig['port'], true) . "\n";
            $configContent .= "];\n";
            
            if (file_put_contents($dbConfigFile, $configContent) === false) {
                $error = 'Errore scrittura file configurazione database.';
            } else {
                $dbConfig = $newDbConfig; // aggiorna in memoria per visualizzazione
            }
            } // Chiudi if ($activeTab === 'database')
        } catch (Exception $e) {
            $error = 'Errore salvataggio: ' . $e->getMessage();
        }
    }
}

?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-cog me-2"></i>Impostazioni Sistema</h2>
        <a href="<?= BASE_PATH ?>index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Torna alla Home
        </a>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <!-- Nav Tabs -->
            <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                        <i class="fas fa-sliders-h me-2"></i>Generali
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="appearance-tab" data-bs-toggle="tab" data-bs-target="#appearance" type="button" role="tab">
                        <i class="fas fa-palette me-2"></i>Aspetto
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                        <i class="fas fa-tools me-2"></i>Sistema
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="database-tab" data-bs-toggle="tab" data-bs-target="#database" type="button" role="tab">
                        <i class="fas fa-database me-2"></i>Database
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="personal-site-tab" data-bs-toggle="tab" data-bs-target="#personal-site" type="button" role="tab">
                        <i class="fas fa-globe me-2"></i>Sito Personale
                    </button>
                </li>
            </ul>

            <form method="post">
                <!-- Tab Content -->
                <div class="tab-content" id="settingsTabsContent">
                    
                    <!-- TAB GENERALI -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <h5 class="mb-3"><i class="fas fa-qrcode me-2 text-primary"></i>Configurazione QR Code</h5>
                        <div class="mb-4">
                            <label for="ip_address" class="form-label fw-bold">IP/URL dell'applicativo</label>
                            <input type="text" id="ip_address" name="ip_address" class="form-control form-control-lg" value="<?= htmlspecialchars($appSettings['ip_address'] ?: $detectedIp) ?>" placeholder="es. 192.168.1.100">
                            <div class="form-text mt-2">
                                <div class="alert alert-info mb-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <strong>IP rilevato automaticamente:</strong> <?= htmlspecialchars($detectedIp) ?>
                                </div>
                                <strong>Esempi validi:</strong>
                                <ul class="mb-0 mt-2">
                                    <li><strong>IP locale:</strong> <code>192.168.1.100</code> → genera http://192.168.1.100/magazzino/warehouse/mobile_component.php?id=X</li>
                                    <li><strong>URL completo:</strong> <code>https://magazzino.miodominio.it/warehouse/mobile_component.php</code></li>
                                    <li><strong>URL base:</strong> <code>https://magazzino.miodominio.it</code> → aggiunge automaticamente il percorso</li>
                                </ul>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="qr_per_riga" class="form-label fw-bold">QR per riga</label>
                                <input type="number" id="qr_per_riga" name="qr_per_riga" class="form-control form-control-lg" value="<?= intval($qrPerRiga) ?>" min="1" max="20" required>
                                <div class="form-text">Numero di QR code per riga nella stampa (1-20, default: 10)</div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <label for="qr_size" class="form-label fw-bold">Dimensione QR</label>
                                <input type="number" id="qr_size" name="qr_size" class="form-control form-control-lg" value="<?= intval($qrSize) ?>" min="10" max="500" required>
                                <div class="form-text">Dimensione di ogni QR code in pixel (10-500, default: 100)</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3"><i class="fas fa-barcode me-2 text-primary"></i>Configurazione Barcode</h5>
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <label for="barcode_per_riga" class="form-label fw-bold">Barcode per riga</label>
                                <input type="number" id="barcode_per_riga" name="barcode_per_riga" class="form-control form-control-lg" value="<?= intval($barcodePerRiga) ?>" min="1" max="20" required>
                                <div class="form-text">Numero di barcode per riga nella stampa (1-20, default: 6)</div>
                            </div>
                            
                            <div class="col-md-4 mb-4">
                                <label for="barcode_width" class="form-label fw-bold">Larghezza Barcode</label>
                                <input type="number" id="barcode_width" name="barcode_width" class="form-control form-control-lg" value="<?= intval($barcodeWidth) ?>" min="5" max="100" required>
                                <div class="form-text">Larghezza del barcode (5-100, default: 50)</div>
                            </div>
                            
                            <div class="col-md-4 mb-4">
                                <label for="barcode_height" class="form-label fw-bold">Spaziatura Barcode</label>
                                <input type="number" id="barcode_height" name="barcode_height" class="form-control form-control-lg" value="<?= intval($barcodeHeight) ?>" min="5" max="50" required>
                                <div class="form-text">Spaziatura del barcode (5-50, default: 10)</div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB ASPETTO -->
                    <div class="tab-pane fade" id="appearance" role="tabpanel">
                        <h5 class="mb-3"><i class="fas fa-paint-brush me-2 text-primary"></i>Personalizzazione Interfaccia</h5>
                        <div class="mb-4">
                            <label for="app_theme" class="form-label fw-bold">Tema dell'applicazione</label>
                            <select id="app_theme" name="app_theme" class="form-select form-select-lg">
                                <option value="light" <?= $appSettings['app_theme'] === 'light' ? 'selected' : '' ?>>☀️ Chiaro</option>
                                <option value="dark" <?= $appSettings['app_theme'] === 'dark' ? 'selected' : '' ?>>🌙 Scuro</option>
                            </select>
                            <div class="form-text">Scegli il tema preferito per l'interfaccia dell'applicazione</div>
                        </div>
                    </div>

                    <!-- TAB SISTEMA -->
                    <div class="tab-pane fade" id="system" role="tabpanel">
                        <h5 class="mb-3"><i class="fas fa-code me-2 text-primary"></i>Configurazione Sviluppo</h5>
                        <div class="mb-4">
                            <label for="environment_mode" class="form-label fw-bold">Modalità errori PHP</label>
                            <select id="environment_mode" name="environment_mode" class="form-select form-select-lg">
                                <option value="production" <?= $appSettings['environment_mode'] === 'production' ? 'selected' : '' ?>>🔒 Produzione (nasconde errori)</option>
                                <option value="development" <?= $appSettings['environment_mode'] === 'development' ? 'selected' : '' ?>>🔧 Sviluppo (mostra errori)</option>
                            </select>
                            <div class="alert alert-warning mt-3">
                                <strong>🔒 Produzione:</strong> Gli errori PHP sono nascosti agli utenti (consigliato per ambiente live)<br>
                                <strong>🔧 Sviluppo:</strong> Gli errori PHP sono visibili (utile per debug e sviluppo)
                            </div>
                        </div>
                    </div>

                    <!-- TAB DATABASE -->
                    <div class="tab-pane fade" id="database" role="tabpanel">
                        <h5 class="mb-3"><i class="fas fa-server me-2 text-primary"></i>Connessione Database MySQL</h5>
                        
                        <div class="alert alert-warning mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Attenzione:</strong> Modificare questi parametri può causare errori di connessione. Assicurati che i valori siano corretti prima di salvare.
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="db_host" class="form-label fw-bold">Host Database</label>
                                <input type="text" id="db_host" name="db_host" class="form-control" value="<?= htmlspecialchars($dbConfig['host']) ?>" required>
                                <div class="form-text">Indirizzo del server MySQL (es. localhost, 127.0.0.1)</div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="db_port" class="form-label fw-bold">Porta</label>
                                <input type="number" id="db_port" name="db_port" class="form-control" value="<?= htmlspecialchars($dbConfig['port']) ?>" min="1" max="65535" required>
                                <div class="form-text">Default: 3306</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="db_name" class="form-label fw-bold">Nome Database</label>
                            <input type="text" id="db_name" name="db_name" class="form-control" value="<?= htmlspecialchars($dbConfig['db']) ?>" required>
                            <div class="form-text">Nome del database MySQL utilizzato dall'applicazione</div>
                        </div>

                        <?php if ($activeTab === 'database'): ?>
                        <div class="row" id="db-credentials-row">
                            <div class="col-md-6 mb-3">
                                <label for="db_user" class="form-label fw-bold">Username</label>
                                <input type="text" id="db_user" name="db_user" class="form-control" value="<?= htmlspecialchars($dbConfig['user']) ?>" required>
                                <div class="form-text">Nome utente per la connessione</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="db_pass" class="form-label fw-bold">Password</label>
                                <input type="text" id="db_pass" name="db_pass" class="form-control" value="<?= htmlspecialchars($dbConfig['pass']) ?>" placeholder="Lascia vuoto se non c'è password">
                                <div class="form-text">Password del database (opzionale)</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Le credenziali sono salvate nel file <code>config/database.php</code>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- Sezione Backup Database -->
                        <h5 class="mb-3"><i class="fas fa-download me-2 text-success"></i>Backup Database</h5>
                        <div class="alert alert-secondary">
                            <i class="fas fa-database me-2"></i>
                            Scarica un backup completo del database in formato SQL. Include struttura tabelle e tutti i dati.
                        </div>
                        
                        <a href="settings.php?action=backup_db" class="btn btn-success mb-3" onclick="return confirm('Vuoi scaricare il backup completo del database?')">
                            <i class="fas fa-download me-2"></i>Scarica Backup Completo
                        </a>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Ripristino backup:</strong> Il backup può essere ripristinato importandolo in phpMyAdmin o tramite command line con <code>mysql -u root -p magazzino_db &lt; backup.sql</code>
                        </div>
                    </div>
                </div>
                
                <!-- TAB SITO PERSONALE -->
                <div class="tab-pane fade" id="personal-site" role="tabpanel">
                    <h5 class="mb-3"><i class="fas fa-globe me-2 text-primary"></i>Homepage Personale</h5>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Informazione:</strong> Gestisci il tuo sito personale/landing page completamente configurabile. 
                        Quando attivato, diventerà la homepage del sistema.
                    </div>
                    
                    <div class="text-center py-4">
                        <a href="<?= BASE_PATH ?>personal_home_settings.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-cog me-2"></i>Configura Sito Personale
                        </a>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <i class="fas fa-palette fa-3x text-primary mb-3"></i>
                                    <h6>5 Temi Predefiniti</h6>
                                    <p class="small text-muted">Scegli tra temi moderni, dark, creative e business</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <i class="fas fa-layer-group fa-3x text-success mb-3"></i>
                                    <h6>Sezioni Dinamiche</h6>
                                    <p class="small text-muted">Crea sezioni illimitate con contenuti personalizzati</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <i class="fas fa-mobile-alt fa-3x text-info mb-3"></i>
                                    <h6>Responsive Design</h6>
                                    <p class="small text-muted">Perfetto su desktop, tablet e smartphone</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pulsanti azione -->
                <hr class="my-4">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?= BASE_PATH ?>index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Annulla
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-save me-2"></i>Salva Impostazioni
                    </button>
                </div>
                
                <!-- Campo hidden per mantenere tab attivo -->
                <input type="hidden" id="active_tab" name="active_tab" value="<?= htmlspecialchars($activeTab) ?>">
            </form>
        </div>
    </div>
</div>

<script>
// Mantieni tab attivo dopo salvataggio
document.addEventListener('DOMContentLoaded', function() {
    // Imposta il tab attivo all'inizio
    var initialTab = document.getElementById('active_tab').value;
    
    // Se arriviamo con ?tab=database nel GET, attiva il tab database
    var urlParams = new URLSearchParams(window.location.search);
    var tabFromUrl = urlParams.get('tab');
    if (tabFromUrl) {
        var tabButton = document.getElementById(tabFromUrl + '-tab');
        if (tabButton) {
            var tab = new bootstrap.Tab(tabButton);
            tab.show();
            document.getElementById('active_tab').value = tabFromUrl;
        }
    }
    
    // Se c'è un tab attivo da POST, ripristinalo
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($activeTab)): ?>
        var tabToActivate = '<?= $activeTab ?>';
        var tabButton = document.getElementById(tabToActivate + '-tab');
        if (tabButton) {
            var tab = new bootstrap.Tab(tabButton);
            tab.show();
            // Aggiorna il campo hidden
            document.getElementById('active_tab').value = tabToActivate;
        }
    <?php endif; ?>
    
    // Aggiorna campo hidden quando si cambia tab
    var tabButtons = document.querySelectorAll('#settingsTabs button[data-bs-toggle="tab"]');
    tabButtons.forEach(function(button) {
        button.addEventListener('shown.bs.tab', function(event) {
            var tabId = event.target.getAttribute('data-bs-target').substring(1); // rimuovi #
            document.getElementById('active_tab').value = tabId;
            
            // Se entri nel tab database, ricarica la pagina per mostrare i campi
            if (tabId === 'database' && !document.getElementById('db_user')) {
                location.href = 'settings.php?tab=database';
            } else {
                // Se esci da database, rimuovi il parametro ?tab dall'URL
                var url = window.location.pathname;
                window.history.replaceState({}, document.title, url);
            }
        });
    });
    
    // IMPORTANTE: Aggiorna il campo hidden con il tab attualmente attivo PRIMA di inviare il form
    var form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            var activeTabButton = document.querySelector('#settingsTabs .nav-link.active');
            if (activeTabButton) {
                var activeTabId = activeTabButton.getAttribute('data-bs-target').substring(1);
                document.getElementById('active_tab').value = activeTabId;
            }
        });
    }
    
    // Auto-nascondi alert dopo 3 secondi
    var alerts = document.querySelectorAll('.alert-success, .alert-danger');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 3000);
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>