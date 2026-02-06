<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-13
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-14
 * 
 * Export componenti in file CSV
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Solo admin può esportare
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Accesso negato: permessi insufficienti.";
    exit;
}

// Mappa di tutti i campi disponibili
$allFields = [
    'codice_prodotto' => 'Codice prodotto',
    'category_id' => 'ID categoria',
    'category_name' => 'Nome categoria (solo lettura)',
    'costruttore' => 'Produttore',
    'fornitore' => 'Fornitore',
    'codice_fornitore' => 'Codice fornitore',
    'quantity' => 'Quantità',
    'quantity_min' => 'Quantità minima',
    'location_id' => 'ID posizione',
    'location_name' => 'Nome posizione (solo lettura)',
    'compartment_id' => 'ID comparto',
    'compartment_code' => 'Codice comparto (solo lettura)',
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

// Campi "human-readable" non adatti per import
$readOnlyFields = ['category_name', 'location_name', 'compartment_code'];

// Se richiesto il download del CSV
if (isset($_POST['export'])) {
    try {
        // Ottieni campi selezionati
        $selectedFields = $_POST['fields'] ?? array_keys($allFields);
        $separator = $_POST['separator'] ?? ';';
        
        // Validazione separatore
        $validSeparators = [';' => ';', ',' => ',', "\t" => 'TAB', '|' => '|'];
        if (!isset($validSeparators[$separator])) {
            $separator = ';';
        }
        
        // Costruisci query SELECT con solo i campi selezionati
        $selectFields = [];
        $needsJoins = ['category' => false, 'location' => false, 'compartment' => false];
        
        foreach ($selectedFields as $field) {
            switch ($field) {
                case 'category_name':
                    $selectFields[] = "cat.name as category_name";
                    $needsJoins['category'] = true;
                    break;
                case 'location_name':
                    $selectFields[] = "loc.name as location_name";
                    $needsJoins['location'] = true;
                    break;
                case 'compartment_code':
                    $selectFields[] = "cmp.code as compartment_code";
                    $needsJoins['compartment'] = true;
                    break;
                default:
                    $selectFields[] = "c.$field";
            }
        }
        
        // Costruisci query con JOIN opzionali
        $sql = "SELECT " . implode(', ', $selectFields) . " FROM components c";
        if ($needsJoins['category']) {
            $sql .= " LEFT JOIN categories cat ON c.category_id = cat.id";
        }
        if ($needsJoins['location']) {
            $sql .= " LEFT JOIN locations loc ON c.location_id = loc.id";
        }
        if ($needsJoins['compartment']) {
            $sql .= " LEFT JOIN compartments cmp ON c.compartment_id = cmp.id";
        }
        $sql .= " ORDER BY c.codice_prodotto";
        
        $stmt = $pdo->query($sql);
        $components = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Imposta header per download CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="componenti_export_' . date('Y-m-d_His') . '.csv"');
        
        // Apri output stream
        $output = fopen('php://output', 'w');
        
        // Scrivi BOM UTF-8 per Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Scrivi intestazioni (solo campi selezionati)
        fputcsv($output, $selectedFields, $separator);
        
        // Scrivi dati
        foreach ($components as $component) {
            $row = [];
            foreach ($selectedFields as $field) {
                $row[] = $component[$field] ?? '';
            }
            fputcsv($output, $row, $separator);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo "Errore durante l'export: " . $e->getMessage();
        exit;
    }
}

// Conta i componenti da esportare
$totalComponents = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM components");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalComponents = $row['total'];
} catch (Exception $e) {
    $totalComponents = 0;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-export me-2 text-info"></i>Export Componenti in CSV</h2>
        <a href="<?= BASE_PATH ?>warehouse/components.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Torna ai Componenti
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3"><i class="fas fa-download me-2"></i>Configurazione Export</h5>
            
            <form method="post">
                <!-- Separatore -->
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-cog me-2"></i>Separatore campi</label>
                    <select name="separator" class="form-select" required>
                        <option value=";">; (punto e virgola)</option>
                        <option value=",">, (virgola)</option>
                        <option value="&#9;">TAB (tabulazione)</option>
                        <option value="|">| (pipe)</option>
                    </select>
                </div>

                <!-- Selezione campi -->
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-check-square me-2"></i>Campi da esportare</label>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Nota:</strong> I campi marcati come <em>"(solo lettura)"</em> sono utili per Excel ma 
                        <strong>non devono essere usati per il re-import</strong> (usa gli ID invece).
                    </div>
                    
                    <div class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllFields(true)">
                            <i class="fas fa-check-double me-1"></i>Seleziona tutto
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllFields(false)">
                            <i class="fas fa-times me-1"></i>Deseleziona tutto
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="selectOnlyImportable()">
                            <i class="fas fa-file-import me-1"></i>Solo campi importabili
                        </button>
                    </div>
                    <div class="row">
                        <?php 
                        $col = 0;
                        foreach ($allFields as $fieldName => $fieldLabel): 
                            if ($col % 3 == 0 && $col > 0) echo '</div><div class="row">';
                            $isReadOnly = in_array($fieldName, $readOnlyFields);
                        ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input field-checkbox <?= $isReadOnly ? 'readonly-field' : 'importable-field' ?>" 
                                           type="checkbox" 
                                           name="fields[]" value="<?= $fieldName ?>" 
                                           id="field_<?= $fieldName ?>" <?= $isReadOnly ? '' : 'checked' ?>>
                                    <label class="form-check-label <?= $isReadOnly ? 'text-muted fst-italic' : '' ?>" 
                                           for="field_<?= $fieldName ?>">
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

                <div class="alert alert-info">
                    <strong><i class="fas fa-info-circle me-2"></i>Info:</strong>
                    Verranno esportati <strong><?= number_format($totalComponents, 0, ',', '.') ?></strong> componenti con i campi selezionati.
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="<?= BASE_PATH ?>warehouse/components.php" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i>Annulla
                    </a>
                    <button type="submit" name="export" class="btn btn-info btn-lg">
                        <i class="fas fa-download me-2"></i>Scarica CSV
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
    
    function selectOnlyImportable() {
        // Deseleziona tutto
        document.querySelectorAll('.field-checkbox').forEach(function(checkbox) {
            checkbox.checked = false;
        });
        // Seleziona solo i campi importabili
        document.querySelectorAll('.importable-field').forEach(function(checkbox) {
            checkbox.checked = true;
        });
    }
    </script>

    <!-- Info aggiuntive -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Suggerimenti</h5>
        </div>
        <div class="card-body">
            <ul class="mb-0">
                <li><strong>Separatori:</strong> Excel preferisce ; o TAB, altri software spesso usano ,</li>
                <li><strong>Campi essenziali:</strong> codice_prodotto è obbligatorio per re-import</li>
                <li><strong>Campi "solo lettura":</strong> category_name, location_name, compartment_code sono utili per leggere in Excel ma vanno esclusi dal re-import</li>
                <li><strong>Modifiche massive:</strong> Esporta solo campi importabili, modifica in Excel, re-importa</li>
                <li><strong>Riferimenti ID:</strong> Per il re-import usa category_id, location_id, compartment_id (non i nomi)</li>
            </ul>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>