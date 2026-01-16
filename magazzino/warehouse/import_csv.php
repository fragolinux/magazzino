<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-13
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-15 18:10:01
 * 
 * Import componenti da file CSV
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/secure_upload.php';

// Solo admin può importare
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Accesso negato: permessi insufficienti.";
    exit;
}

$message = null;
$error = null;
$importResults = null;

// Mappa di tutti i campi disponibili
$allFields = [
    'codice_prodotto' => 'Codice prodotto (obbligatorio)',
    'category_id' => 'ID categoria',
    'costruttore' => 'Produttore',
    'fornitore' => 'Fornitore',
    'codice_fornitore' => 'Codice fornitore',
    'quantity' => 'Quantità',
    'quantity_min' => 'Quantità minima',
    'location_id' => 'ID posizione',
    'compartment_id' => 'ID comparto',
    'datasheet_url' => 'URL datasheet',
    'equivalents' => 'Equivalenti',
    'notes' => 'Note',
    'prezzo' => 'Prezzo',
    'link_fornitore' => 'Link fornitore',
    'unita_misura' => 'Unità misura',
    'package' => 'Package',
    'tensione' => 'Tensione',
    'corrente' => 'Corrente',
    'potenza' => 'Potenza',
    'hfe' => 'HFE',
    'tags' => 'Tags'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Ottieni configurazione import
    $separator = $_POST['separator'] ?? ';';
    $selectedFields = $_POST['fields'] ?? array_keys($allFields);
    
    // Validazione separatore
    $validSeparators = [';', ',', "\t", '|'];
    if (!in_array($separator, $validSeparators)) {
        $separator = ';';
    }
    
    // Verifica errori upload e valida il file
    $validator = new SecureUploadValidator();
    $validation = $validator->validateUpload($file, ['text/csv', 'text/plain', 'application/vnd.ms-excel']);
    
    if (!$validation['valid']) {
        $error = 'File non valido: ' . implode(', ', $validation['errors']);
    } elseif (!in_array(strtolower($validation['extension']), ['csv', 'txt'])) {
        $error = "Il file deve avere estensione .csv o .txt.";
    } else {
        // Leggi il file CSV
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            $error = "Impossibile leggere il file.";
        } else {
            $imported = 0;
            $skipped = 0;
            $errors = 0;
            $errorDetails = [];
            
            // Precarica ID validi per validazione foreign keys
            $validCategoryIds = $pdo->query("SELECT id FROM categories")->fetchAll(PDO::FETCH_COLUMN);
            $validLocationIds = $pdo->query("SELECT id FROM locations")->fetchAll(PDO::FETCH_COLUMN);
            $validCompartmentIds = $pdo->query("SELECT id FROM compartments")->fetchAll(PDO::FETCH_COLUMN);
            
            // Leggi la prima riga (header)
            $header = fgetcsv($handle, 0, $separator);
            if (!$header) {
                $error = "File CSV vuoto o formato non valido.";
                fclose($handle);
            } else {
                // Crea mappa indice -> nome campo
                $fieldMap = [];
                $cleanedHeader = [];
                foreach ($header as $index => $columnName) {
                    $originalColumn = $columnName;
                    $columnName = trim($columnName);
                    // Rimuovi eventuali virgolette residue e BOM
                    $columnName = trim($columnName, " \t\n\r\0\x0B\"'");
                    $columnName = str_replace("\xEF\xBB\xBF", '', $columnName); // Rimuovi UTF-8 BOM
                    $cleanedHeader[] = $columnName;
                    
                    // Cerca il campo tra quelli disponibili (case-insensitive per maggiore tolleranza)
                    foreach ($selectedFields as $selectedField) {
                        if (strcasecmp($columnName, $selectedField) === 0) {
                            $fieldMap[$index] = $selectedField;
                            break;
                        }
                    }
                }
                
                // Verifica che codice_prodotto sia presente
                if (!in_array('codice_prodotto', $fieldMap)) {
                    $error = "Il campo 'codice_prodotto' è obbligatorio nel CSV.<br>";
                    $error .= "Colonne trovate nel CSV: <strong>" . implode(', ', $cleanedHeader) . "</strong><br>";
                    $error .= "Campi ricercati: <strong>" . implode(', ', $selectedFields) . "</strong><br>";
                    $error .= "Campo mappato: <strong>" . implode(', ', $fieldMap) . "</strong>";
                    fclose($handle);
                } else {
                    // Processa ogni riga
                    $lineNumber = 1;
                    while (($data = fgetcsv($handle, 0, $separator)) !== false) {
                        $lineNumber++;
                        
                        try {
                            // Mappa i dati dalla riga CSV ai campi del database
                            $rowData = [];
                            foreach ($fieldMap as $index => $fieldName) {
                                $rowData[$fieldName] = isset($data[$index]) ? trim($data[$index]) : null;
                            }
                            
                            // Valida campo obbligatorio
                            if (empty($rowData['codice_prodotto'])) {
                                $skipped++;
                                $errorDetails[] = "Riga $lineNumber: codice_prodotto mancante";
                                continue;
                            }
                            
                            // Prepara i dati per l'inserimento (con valori di default per campi mancanti)
                            $codiceProdotto = $rowData['codice_prodotto'];
                            $categoryId = !empty($rowData['category_id']) && is_numeric($rowData['category_id']) ? (int)$rowData['category_id'] : null;
                            
                            // Valida foreign keys - se un ID è specificato ma non valido, salta la riga
                            $hasInvalidForeignKey = false;
                            
                            if ($categoryId !== null && !in_array($categoryId, $validCategoryIds)) {
                                $errors++;
                                $errorDetails[] = "Riga $lineNumber ($codiceProdotto): category_id=$categoryId non esiste. Riga saltata.";
                                $hasInvalidForeignKey = true;
                            }
                            
                            $costruttore = $rowData['costruttore'] ?? null;
                            $fornitore = $rowData['fornitore'] ?? null;
                            $codiceFornitore = $rowData['codice_fornitore'] ?? null;
                            $quantity = !empty($rowData['quantity']) && is_numeric($rowData['quantity']) ? (int)$rowData['quantity'] : 0;
                            $quantityMin = !empty($rowData['quantity_min']) && is_numeric($rowData['quantity_min']) ? (int)$rowData['quantity_min'] : 0;
                            $locationId = !empty($rowData['location_id']) && is_numeric($rowData['location_id']) ? (int)$rowData['location_id'] : null;
                            
                            // Valida location_id
                            if ($locationId !== null && !in_array($locationId, $validLocationIds)) {
                                $errors++;
                                $errorDetails[] = "Riga $lineNumber ($codiceProdotto): location_id=$locationId non esiste. Riga saltata.";
                                $hasInvalidForeignKey = true;
                            }
                            
                            $compartmentId = !empty($rowData['compartment_id']) && is_numeric($rowData['compartment_id']) ? (int)$rowData['compartment_id'] : null;
                            
                            // Valida compartment_id
                            if ($compartmentId !== null && !in_array($compartmentId, $validCompartmentIds)) {
                                $errors++;
                                $errorDetails[] = "Riga $lineNumber ($codiceProdotto): compartment_id=$compartmentId non esiste. Riga saltata.";
                                $hasInvalidForeignKey = true;
                            }
                            
                            // Se ci sono foreign keys non valide, salta questa riga
                            if ($hasInvalidForeignKey) {
                                continue;
                            }
                            
                            $datasheetUrl = $rowData['datasheet_url'] ?? null;
                            
                            // Gestione campo JSON equivalents
                            $equivalents = null;
                            if (!empty($rowData['equivalents'])) {
                                $equivalents = $rowData['equivalents'];
                                // Se non è già un JSON valido, convertilo in array
                                if (!json_decode($equivalents)) {
                                    $equivalents = json_encode(array_map('trim', explode(',', $equivalents)));
                                }
                            }
                            
                            $notes = $rowData['notes'] ?? null;
                            $prezzo = !empty($rowData['prezzo']) && is_numeric($rowData['prezzo']) ? (float)$rowData['prezzo'] : null;
                            $linkFornitore = $rowData['link_fornitore'] ?? null;
                            $unitaMisura = $rowData['unita_misura'] ?? 'pz';
                            $package = $rowData['package'] ?? null;
                            $tensione = $rowData['tensione'] ?? null;
                            $corrente = $rowData['corrente'] ?? null;
                            $potenza = $rowData['potenza'] ?? null;
                            $hfe = $rowData['hfe'] ?? null;
                            $tags = $rowData['tags'] ?? null;
                            
                            // Inserisci nel database
                            $stmt = $pdo->prepare("
                                INSERT INTO components 
                                (codice_prodotto, category_id, costruttore, fornitore, codice_fornitore,
                                 quantity, quantity_min, location_id, compartment_id, datasheet_url,
                                 equivalents, notes, prezzo, link_fornitore, unita_misura, package,
                                 tensione, corrente, potenza, hfe, tags)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            $stmt->execute([
                                $codiceProdotto, $categoryId, $costruttore, $fornitore, $codiceFornitore,
                                $quantity, $quantityMin, $locationId, $compartmentId, $datasheetUrl,
                                $equivalents, $notes, $prezzo, $linkFornitore, $unitaMisura, $package,
                                $tensione, $corrente, $potenza, $hfe, $tags
                            ]);
                        
                        $imported++;
                    } catch (PDOException $e) {
                        $errors++;
                        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            $errorDetails[] = "Riga $lineNumber: codice duplicato ($codiceProdotto)";
                        } else {
                            $errorDetails[] = "Riga $lineNumber: " . $e->getMessage();
                        }
                    }
                }
                
                fclose($handle);
                
                $importResults = [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'errors' => $errors,
                    'details' => $errorDetails
                ];
                
                if ($imported > 0) {
                    $message = "Import completato: $imported componenti importati.";
                } else {
                    $error = "Nessun componente importato.";
                }
                }
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-import me-2 text-success"></i>Import Componenti da CSV</h2>
        <a href="/magazzino/warehouse/components.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Torna ai Componenti
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($importResults): ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Risultati Import</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-success text-white">
                            <h3><?= $importResults['imported'] ?></h3>
                            <p class="mb-0">Importati</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-warning text-dark">
                            <h3><?= $importResults['skipped'] ?></h3>
                            <p class="mb-0">Saltati</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-danger text-white">
                            <h3><?= $importResults['errors'] ?></h3>
                            <p class="mb-0">Errori</p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($importResults['details'])): ?>
                    <hr class="my-3">
                    <h6>Dettagli:</h6>
                    <ul class="mb-0">
                        <?php foreach ($importResults['details'] as $detail): ?>
                            <li><?= htmlspecialchars($detail) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3"><i class="fas fa-upload me-2"></i>Configurazione Import</h5>
            
            <form method="post" enctype="multipart/form-data">
                <!-- Separatore -->
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-cog me-2"></i>Separatore campi nel CSV</label>
                    <select name="separator" class="form-select" required>
                        <option value=";">; (punto e virgola)</option>
                        <option value=",">, (virgola)</option>
                        <option value="&#9;">TAB (tabulazione)</option>
                        <option value="|">| (pipe)</option>
                    </select>
                </div>

                <!-- Selezione campi -->
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-check-square me-2"></i>Campi presenti nel CSV</label>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>Seleziona <strong>solo i campi che sono presenti nel tuo file CSV</strong>, nell'ordine in cui appaiono.
                    </div>
                    <div class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllFields(true)">
                            <i class="fas fa-check-double me-1"></i>Seleziona tutto
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllFields(false)">
                            <i class="fas fa-times me-1"></i>Deseleziona tutto
                        </button>
                    </div>
                    <div class="row">
                        <?php 
                        $col = 0;
                        foreach ($allFields as $fieldName => $fieldLabel): ?>
                            <?php if ($col % 3 == 0 && $col > 0): ?>
                                </div><div class="row">
                            <?php endif; ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input field-checkbox" type="checkbox" 
                                           name="fields[]" value="<?= $fieldName ?>" 
                                           id="field_<?= $fieldName ?>" checked>
                                    <label class="form-check-label" for="field_<?= $fieldName ?>">
                                        <?= htmlspecialchars($fieldLabel) ?>
                                    </label>
                                </div>
                            </div>
                        <?php 
                            $col++;
                        endforeach; 
                        ?>
                    </div>
                </div>

                <!-- File upload -->
                <div class="mb-3">
                    <label for="csv_file" class="form-label fw-bold"><i class="fas fa-file-csv me-2"></i>Seleziona file CSV</label>
                    <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv,.txt" required>
                </div>

                <div class="alert alert-danger">
                    <strong><i class="fas fa-exclamation-triangle me-2"></i>Attenzione:</strong> I codici duplicati saranno ignorati.
                </div>

                <div class="d-flex justify-content-between">
                    <a href="/magazzino/warehouse/components.php" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i>Annulla
                    </a>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-upload me-2"></i>Importa Componenti
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function selectAllFields(select) {
        document.querySelectorAll('.field-checkbox').forEach(function(checkbox) {
            checkbox.checked = select;
        });
    }
    </script>

    <!-- Esempio formato CSV -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-file-csv me-2"></i>Esempio formato CSV</h5>
        </div>
        <div class="card-body">
            <p class="mb-2"><strong>Esempio con tutti i campi (separatore ; ):</strong></p>
            <pre class="bg-light p-3 rounded border"><code>codice_prodotto;category_id;costruttore;fornitore;codice_fornitore;quantity;quantity_min;location_id;compartment_id;datasheet_url;equivalents;notes;prezzo;link_fornitore;unita_misura;package;tensione;corrente;potenza;hfe;tags
RES-1K-0805;1;Yageo;Mouser;603-RC0805FR-071KL;100;10;1;1;https://example.com/datasheet;;Resistenza 1K 1/4W 5%;0.05;https://mouser.com;pz;0805;;;;;resistenze,passivi</code></pre>

            <p class="mb-2 mt-3"><strong>Esempio con solo campi essenziali (separatore , ):</strong></p>
            <pre class="bg-light p-3 rounded border"><code>codice_prodotto,quantity,notes
RES-1K,100,Resistenza 1K ohm
CAP-100N,50,Condensatore 100nF</code></pre>
            
            <div class="alert alert-info mt-3 mb-0">
                <strong>Suggerimento:</strong> Esporta prima alcuni componenti per vedere il formato esatto, poi modifica il CSV ed importalo nuovamente.
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>