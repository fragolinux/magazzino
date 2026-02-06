<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-13
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-02 18:10:01
 * 
 * Import componenti da file CSV
 */

// 2026-02-01 - Aggiunta verifica codici duplicati prima dell'inserimento, corretti bug e applicate migliorie

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/secure_upload.php';

// Solo admin può importare
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Accesso negato: permessi insufficienti.";
    exit;
}

// Export tabelle di riferimento
if (isset($_GET['export_references'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="riferimenti_import' . '.txt"');
    
    $output = fopen('php://output', 'w');
    
    // Aggiungi BOM UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Categorie
    fputcsv($output, ['=== CATEGORIE ==='], ';');
    fputcsv($output, ['category_id', 'Descrizione categoria'], ';');
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($categories as $cat) {
        fputcsv($output, [$cat['id'], $cat['name']], ';');
    }
    
    fputcsv($output, [], ';');
    
    // Posizioni
    fputcsv($output, ['=== POSIZIONI ==='], ';');
    fputcsv($output, ['location_id', 'Nome Posizione'], ';');
    $locations = $pdo->query("SELECT id, name FROM locations ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($locations as $loc) {
        fputcsv($output, [$loc['id'], $loc['name']], ';');
    }
    
    fputcsv($output, [], ';');
    
    // Comparti
    fputcsv($output, ['=== COMPARTI ==='], ';');
    fputcsv($output, ['compartment_id', 'Codice Comparto', 'Nome Posizione'], ';');
    $compartments = $pdo->query("
        SELECT c.id, c.code, l.name AS location_name 
        FROM compartments c 
        LEFT JOIN locations l ON c.location_id = l.id 
        ORDER BY l.name, c.code
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($compartments as $comp) {
        fputcsv($output, [$comp['id'], $comp['code'], $comp['location_name'] ?? ''], ';');
    }
    
    fclose($output);
    exit;
}

$message = null;
$error = null;
$importResults = null;

// Mappa di tutti i campi disponibili
$allFields = [
    'codice_prodotto' => '[codice_prodotto] Codice prodotto',
    'category_id' => '[category_id] ID categoria',
    'costruttore' => '[costruttore] Produttore',
    'fornitore' => '[fornitore] Fornitore',
    'codice_fornitore' => '[codice_fornitore] Codice fornitore',
    'quantity' => '[quantity] Quantità',
    'quantity_min' => '[quantity_min] Quantità minima',
    'location_id' => '[location_id] ID posizione',
    'compartment_id' => '[compartment_id] ID comparto',
    'datasheet_url' => '[datasheet_url] URL datasheet',
    'equivalents' => '[equivalents] Equivalenti (separati da virgole)',
    'notes' => '[notes] Note',
    'prezzo' => '[prezzo] Prezzo',
    'link_fornitore' => '[link_fornitore] Link fornitore',
    'unita_misura' => '[unita_misura] Unità misura',
    'package' => '[package] Package',
    'tensione' => '[tensione] Tensione',
    'corrente' => '[corrente] Corrente',
    'potenza' => '[potenza] Potenza',
    'hfe' => '[hfe] HFE',
    'tags' => '[tags] Tags (separati da virgole)'
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
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Verifica l'estensione prima
    if (!in_array($fileExtension, ['csv', 'txt'])) {
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
            
            // Crea mappe nome -> ID per permettere l'uso di nomi invece di ID
            $categoryNameToId = [];
            $categoryStmt = $pdo->query("SELECT id, name FROM categories");
            while ($row = $categoryStmt->fetch(PDO::FETCH_ASSOC)) {
                $categoryNameToId[strtolower(trim($row['name']))] = $row['id'];
            }
            
            $locationNameToId = [];
            $locationStmt = $pdo->query("SELECT id, name FROM locations");
            while ($row = $locationStmt->fetch(PDO::FETCH_ASSOC)) {
                $locationNameToId[strtolower(trim($row['name']))] = $row['id'];
            }
            
            $compartmentNameToId = [];
            $compartmentStmt = $pdo->query("SELECT id, code FROM compartments");
            while ($row = $compartmentStmt->fetch(PDO::FETCH_ASSOC)) {
                $compartmentNameToId[strtolower(trim($row['code']))] = $row['id'];
            }
            
            // Leggi la prima riga (header) per rilevare il separatore
            $header = fgetcsv($handle, 0, $separator);
            if (!$header) {
                $error = "File CSV vuoto o formato non valido.";
                fclose($handle);
            } else {
                // Rileva il separatore effettivo del file
                rewind($handle);
                $firstLine = fgets($handle);
                $detectedSeparator = null;
                $separatorCounts = [
                    ';' => substr_count($firstLine, ';'),
                    ',' => substr_count($firstLine, ','),
                    "\t" => substr_count($firstLine, "\t"),
                    '|' => substr_count($firstLine, '|')
                ];
                arsort($separatorCounts);
                $detectedSeparator = key($separatorCounts);
                
                // Se il separatore usato è diverso da quello rilevato, segnala l'errore
                if ($separator !== $detectedSeparator && $separatorCounts[$detectedSeparator] > 0) {
                    $error = "Separatore non corretto!<br>";
                    $error .= "Hai selezionato: <strong>" . htmlspecialchars(var_export($separator, true)) . "</strong><br>";
                    $error .= "Separatore rilevato nel file: <strong>" . htmlspecialchars(var_export($detectedSeparator, true)) . "</strong><br>";
                    $error .= "<br>Seleziona il separatore corretto e riprova.";
                    fclose($handle);
                } else {
                    // Rileggi l'header con il separatore corretto
                    rewind($handle);
                    $header = fgetcsv($handle, 0, $separator);
                    if (!$header) {
                        $error = "File CSV vuoto o formato non valido.";
                        fclose($handle);
                    } else {
                        // Crea mappa indice -> nome campo
                        $fieldMap = [];
                        $cleanedHeader = [];
                        foreach ($header as $index => $columnName) {
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
                        
                        // Verifica che tutti i campi obbligatori siano presenti
                        $requiredFields = ['codice_prodotto', 'category_id', 'location_id', 'compartment_id'];
                        $missingFields = [];
                        foreach ($requiredFields as $reqField) {
                            if (!in_array($reqField, $fieldMap)) {
                                $missingFields[] = $reqField;
                            }
                        }
                        
                        if (!empty($missingFields)) {
                            $error = "I seguenti campi obbligatori mancano nel CSV: <strong>" . implode(', ', $missingFields) . "</strong><br>";
                            $error .= "Colonne trovate nel CSV: <br><strong style=\"word-break: break-word; display: block;\">" . implode(', ', $cleanedHeader) . "</strong><br>";
                            $error .= "Campi ricercati: <br><strong style=\"word-break: break-word; display: block;\">" . implode(', ', $selectedFields) . "</strong><br>";
                            $error .= "Campi mappati: <strong style=\"word-break: break-word; display: block;\">" . implode(', ', array_values($fieldMap)) . "</strong>";
                            fclose($handle);
                        } else {
                            // Processa ogni riga
                            $lineNumber = 1;
                            while (($data = fgetcsv($handle, 0, $separator)) !== false) {
                                $lineNumber++;
                                
                                // Converti encoding da Windows-1252/ISO-8859-1 a UTF-8 per gestire caratteri speciali
                                $data = array_map(function($value) {
                                    if ($value === null || $value === '') return $value;
                                    // Rileva e converti encoding
                                    $encoding = mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
                                    if ($encoding && $encoding !== 'UTF-8') {
                                        return mb_convert_encoding($value, 'UTF-8', $encoding);
                                    }
                                    return $value;
                                }, $data);
                                
                                try {
                                    // Mappa i dati dalla riga CSV ai campi del database
                                    $rowData = [];
                                    foreach ($fieldMap as $index => $fieldName) {
                                        $rowData[$fieldName] = isset($data[$index]) ? trim($data[$index]) : null;
                                    }
                                    
                                    // Prepara i dati per l'inserimento - DEFINISCI SUBITO codiceProdotto per gli errori
                                    $codiceProdotto = $rowData['codice_prodotto'] ?? "SCONOSCIUTO";
                                    
                                    // Valida campi obbligatori
                                    if (empty($rowData['codice_prodotto'])) {
                                        $skipped++;
                                        $errorDetails[] = "Riga $lineNumber: codice_prodotto mancante";
                                        continue;
                                    }
                                    
                                    if (empty($rowData['category_id'])) {
                                        $skipped++;
                                        $errorDetails[] = "Riga $lineNumber ($codiceProdotto): category_id mancante";
                                        continue;
                                    }
                                    
                                    if (empty($rowData['location_id'])) {
                                        $skipped++;
                                        $errorDetails[] = "Riga $lineNumber ($codiceProdotto): location_id mancante";
                                        continue;
                                    }
                                    
                                    if (empty($rowData['compartment_id'])) {
                                        $skipped++;
                                        $errorDetails[] = "Riga $lineNumber ($codiceProdotto): compartment_id mancante";
                                        continue;
                                    }
                                    
                                    // Verifica se il codice prodotto esiste già nel database
                                    $checkStmt = $pdo->prepare("SELECT id FROM components WHERE codice_prodotto = ?");
                                    $checkStmt->execute([$codiceProdotto]);
                                    if ($checkStmt->rowCount() > 0) {
                                        $skipped++;
                                        $errorDetails[] = "Riga $lineNumber: codice duplicato ($codiceProdotto) - già presente nel database. Riga saltata.";
                                        continue;
                                    }
                                    
                                    // Category ID: accetta sia numeri che nomi
                                    $categoryId = null;
                                    if (!empty($rowData['category_id'])) {
                                        if (is_numeric($rowData['category_id'])) {
                                            $categoryId = (int)$rowData['category_id'];
                                        } else {
                                            // Cerca per nome
                                            $categoryName = strtolower(trim($rowData['category_id']));
                                            if (isset($categoryNameToId[$categoryName])) {
                                                $categoryId = $categoryNameToId[$categoryName];
                                            } else {
                                                // Nome non trovato
                                                $errors++;
                                                $errorDetails[] = "Riga $lineNumber ($codiceProdotto): categoria '$categoryName' non trovata. Riga saltata.";
                                                continue;
                                            }
                                        }
                                    }
                                    
                                    // Valida foreign keys - ora category_id non può essere null perché è obbligatorio
                                    $hasInvalidForeignKey = false;
                                    
                                    if (!in_array($categoryId, $validCategoryIds)) {
                                        $errors++;
                                        $errorDetails[] = "Riga $lineNumber ($codiceProdotto): category_id=$categoryId non esiste. Riga saltata.";
                                        $hasInvalidForeignKey = true;
                                    }
                                    
                                    $costruttore = $rowData['costruttore'] ?? null;
                                    $fornitore = $rowData['fornitore'] ?? null;
                                    $codiceFornitore = $rowData['codice_fornitore'] ?? null;
                                    $quantity = !empty($rowData['quantity']) && is_numeric($rowData['quantity']) ? (int)$rowData['quantity'] : 0;
                                    $quantityMin = !empty($rowData['quantity_min']) && is_numeric($rowData['quantity_min']) ? (int)$rowData['quantity_min'] : 0;
                                    
                                    // Location ID: accetta sia numeri che nomi
                                    $locationId = null;
                                    if (!empty($rowData['location_id'])) {
                                        if (is_numeric($rowData['location_id'])) {
                                            $locationId = (int)$rowData['location_id'];
                                        } else {
                                            // Cerca per nome
                                            $locationName = strtolower(trim($rowData['location_id']));
                                            if (isset($locationNameToId[$locationName])) {
                                                $locationId = $locationNameToId[$locationName];
                                            } else {
                                                // Nome non trovato
                                                $errors++;
                                                $errorDetails[] = "Riga $lineNumber ($codiceProdotto): posizione '$locationName' non trovata. Riga saltata.";
                                                continue;
                                            }
                                        }
                                    }
                                    
                                    if (!in_array($locationId, $validLocationIds)) {
                                        $errors++;
                                        $errorDetails[] = "Riga $lineNumber ($codiceProdotto): location_id=$locationId non esiste. Riga saltata.";
                                        $hasInvalidForeignKey = true;
                                    }
                                    
                                    // Compartment ID: accetta sia numeri che nomi
                                    $compartmentId = null;
                                    if (!empty($rowData['compartment_id'])) {
                                        if (is_numeric($rowData['compartment_id'])) {
                                            $compartmentId = (int)$rowData['compartment_id'];
                                        } else {
                                            // Cerca per nome
                                            $compartmentName = strtolower(trim($rowData['compartment_id']));
                                            if (isset($compartmentNameToId[$compartmentName])) {
                                                $compartmentId = $compartmentNameToId[$compartmentName];
                                            } else {
                                                // Nome non trovato
                                                $errors++;
                                                $errorDetails[] = "Riga $lineNumber ($codiceProdotto): comparto '$compartmentName' non trovato. Riga saltata.";
                                                continue;
                                            }
                                        }
                                    }
                                    
                                    if (!in_array($compartmentId, $validCompartmentIds)) {
                                        $errors++;
                                        $errorDetails[] = "Riga $lineNumber ($codiceProdotto): compartment_id=$compartmentId non esiste. Riga saltata.";
                                        $hasInvalidForeignKey = true;
                                    }
                                    
                                    if ($hasInvalidForeignKey) {
                                        continue;
                                    }
                                    
                                    $datasheetUrl = $rowData['datasheet_url'] ?? null;
                                    
                                    // Gestione campo JSON equivalents
                                    $equivalents = null;
                                    if (!empty($rowData['equivalents'])) {
                                        $equivalents = $rowData['equivalents'];
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
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-file-import me-2 text-success"></i>Import Componenti da CSV</h2>
    <div>
      <a href="<?= BASE_PATH ?>warehouse/components.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Torna ai Componenti
      </a>
    </div>
  </div>

  <?php if ($message): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
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
      <h6>Dettagli (saltati e errori):</h6>
      <ul class="mb-0">
        <?php foreach ($importResults['details'] as $detail): ?>
        <li><?= htmlspecialchars($detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
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

        <div class="mb-4">
          <label class="form-label fw-bold"><i class="fas fa-cog me-2"></i>Separatore campi nel CSV</label>
          <select name="separator" class="form-select" required>
            <option value=";">; (punto e virgola)</option>
            <option value=",">, (virgola)</option>
            <option value="&#9;">TAB (tabulazione)</option>
            <option value="|">| (pipe)</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="form-label fw-bold"><i class="fas fa-check-square me-2"></i>Campi presenti nel CSV</label>
          <div class="alert alert-warning">
            <i class="fas fa-info-circle me-2"></i>Seleziona <strong>solo i campi che sono presenti nel tuo file
              CSV</strong>, nell'ordine in cui appaiono.
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
                        $requiredFields = ['codice_prodotto', 'category_id', 'location_id', 'compartment_id'];
                        foreach ($allFields as $fieldName => $fieldLabel): 
                        $isRequired = in_array($fieldName, $requiredFields);
                        ?>
            <?php if ($col % 3 == 0 && $col > 0): ?>
          </div>
          <div class="row">
            <?php endif; ?>
            <div class="col-md-4">
              <div class="form-check">
                <input class="form-check-input <?= $isRequired ? '' : 'field-checkbox' ?>" type="checkbox"
                  name="fields[]" value="<?= $fieldName ?>" id="field_<?= $fieldName ?>" checked
                  <?= $isRequired ? 'disabled' : '' ?>>
                <?php if ($isRequired): ?>
                <input type="hidden" name="fields[]" value="<?= $fieldName ?>">
                <?php endif; ?>
                <label class="form-check-label" for="field_<?= $fieldName ?>">
                  <?= htmlspecialchars($fieldLabel) ?><?= $isRequired ? ' <span class="badge bg-danger">Obbligatorio</span>' : '' ?>
                </label>
              </div>
            </div>
            <?php 
                            $col++;
                        endforeach; 
                        ?>
          </div>
        </div>

        <div class="mb-3">
          <label for="csv_file" class="form-label fw-bold"><i class="fas fa-file-csv me-2"></i>Seleziona file
            CSV</label>
          <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv,.txt" required>
        </div>

        <div class="alert alert-warning">
          <strong><i class="fas fa-exclamation-triangle me-2"></i>Attenzione:</strong> I codici duplicati saranno
          ignorati.
        </div>

        <div class="d-flex justify-content-center">
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

  <div class="card shadow-sm mt-4">
    <div class="card-header bg-secondary text-white">
      <h5 class="mb-0"><i class="fas fa-file-csv me-2"></i>Esempio formato CSV</h5>
    </div>
    <div class="card-body">
      <p class="mb-2"><strong>Esempio importazione con ID numerici (separatore ; ):</strong></p>
      <pre
        class="bg-light p-3 rounded border"><code>codice_prodotto;category_id;costruttore;fornitore;codice_fornitore;quantity;quantity_min;location_id;compartment_id;datasheet_url;equivalents;notes;prezzo;link_fornitore;unita_misura;package;tensione;corrente;potenza;hfe;tags
RES-1K-0805;1;Yageo;Mouser;603-RC0805FR-071KL;100;10;1;1;https://example.com/datasheet;;Resistenza 1K 1/4W 5%;0.05;https://mouser.com;pz;0805;;;;;resistenze,passivi</code></pre>

      <div class="alert alert-warning mt-3">
        <strong><i class="fas fa-lightbulb me-2"></i>Novità:</strong> Puoi usare i <strong>nomi</strong> invece degli ID
        per category_id, location_id e compartment_id!
        Il sistema riconoscerà automaticamente se inserisci un numero (ID) o un nome.<br>
        <strong>ATTENZIONE:</strong> I nomi devono essere univoci, se hai utlizzato gli stessi nomi per posizioni o comparti devi ulilizzate l'ID numerico. 
      </div>

      <p class="mb-2 mt-3"><strong>✨ NOVITÀ: Esempio importazione con NOMI invece di ID (separatore , ):</strong></p>
      <pre class="bg-light p-3 rounded border"><code>codice_prodotto,category_id,costruttore,fornitore,codice_fornitore,quantity,quantity_min,location_id,compartment_id,datasheet_url,equivalents,notes
RES-1K,Resistenze,,,,100,,Magazzino Principale,Cassetto A1,,,Resistenza 1K ohm
CAP-100N,Condensatori,,,,50,,Magazzino Principale,Cassetto B2,,,Condensatore 100nF</code></pre>


      <div class="alert alert-info mt-3 mb-0">
        <strong><i class="fas fa-info-circle me-2"></i>Suggerimenti:</strong>
        <div class="mt-1">
          <a href="?export_references=1" class="btn btn-info me-2"
            title="Scarica un file CSV con tutte le categorie, posizioni e comparti per riferimento">
            <i class="fas fa-download me-1"></i>Esporta Riferimenti
          </a>
        </div>
        <ul class="mb-0 mt-2">
          <li>Clicca su <strong>"Esporta Riferimenti"</strong> per scaricare un file con tutti gli ID e nomi di
            categorie, posizioni e comparti</li>
          <li>Puoi usare sia ID numerici (es. 1, 2, 3) che nomi (es. "Resistenze", "Condensatori")</li>
          <li>Esporta prima alcuni componenti per vedere il formato esatto, poi modifica il CSV ed importalo nuovamente
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>