<?php
/*
 * @Author: gabriele.riva
 * @Date: 2026-01-15
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-02 18:11:06
*/
// 2026-02-01: Aggiunti parametri Barcode nei setting
// 2026-02-02: Aggiunta possibilità di selezionare componenti senza comparto

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Includi autoload di Composer per la libreria barcode
require_once __DIR__ . '/../vendor/autoload.php';

// solo admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Accesso negato: permessi insufficienti.";
    exit;
}

// Carica impostazioni Barcode dal database
$barcodePerRiga = 6;  // valore default
$barcodeWidth = '50'; // valore default (mm)
$barcodeHeight = '10'; // valore default (mm)

try {
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

$compartments = $_POST['compartments'] ?? [];
if (!is_array($compartments) || count($compartments) === 0) {
    echo "<p>Errore: nessun comparto selezionato.</p>";
    exit;
}

$location_id = isset($_POST['location_id']) && is_numeric($_POST['location_id']) ? intval($_POST['location_id']) : null;

// Separa i compartimenti dai componenti senza comparto
$ids = [];
$includeUnassigned = false;
foreach ($compartments as $val) {
    if ($val === 'unassigned') {
        $includeUnassigned = true;
    } else {
        $intVal = intval($val);
        if ($intVal > 0) {
            $ids[] = $intVal;
        }
    }
}

// Controlla se abbiamo almeno un comparto oppure unassigned
if (count($ids) === 0 && !$includeUnassigned) {
    echo "<p>Errore: nessun comparto valido.</p>";
    exit;
}

$include_code = isset($_POST['include_code_under_barcode']) ? true : false;

// Costruisci la query in base a cosa è stato selezionato
$whereConditions = [];
$params = [];

if (count($ids) > 0) {
    // Aggiungi condizione per i compartimenti
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $whereConditions[] = "compartment_id IN ($placeholders)";
    $params = array_merge($params, $ids);
}

if ($includeUnassigned) {
    // Aggiungi condizione per componenti senza comparto
    $unassignedCondition = "(compartment_id IS NULL OR compartment_id = 0)";
    // Se è stata selezionata una location specifica, filtriamo anche per location_id
    if ($location_id) {
        $unassignedCondition .= " AND location_id = ?";
        $whereConditions[] = $unassignedCondition;
        $params[] = $location_id;
    } else {
        $whereConditions[] = $unassignedCondition;
    }
}

$whereClause = implode(' OR ', $whereConditions);
$sql = "SELECT id, codice_prodotto FROM components WHERE $whereClause ORDER BY codice_prodotto ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$components = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$components){
    echo "<p>Nessun componente trovato nei comparti o tra quelli senza comparto selezionati.</p>";
    exit;
}

$html = '<!doctype html><html><head><meta charset="utf-8"><title>Barcode</title>';
$html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
$html .= '<style>@media print{@page{size:A4;margin:5mm}}body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:5mm}';
$html .= '.labels{display:grid;grid-template-columns:repeat(' . intval($barcodePerRiga) . ', 1fr);gap:2mm}';
$html .= '.label{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:1mm;box-sizing:border-box;text-align:center;}';
$html .= '.barcode-container{width:' . intval($barcodeWidth) . 'mm;height:' . intval($barcodeHeight) . 'mm;display:flex;align-items:flex-end;justify-content:center;}';
$html .= '.barcode-container svg{width:100%;height:100%;}';
$html .= '.code{font-size:7pt;word-break:break-word;overflow:hidden;max-height:1.5em;line-height:1;font-weight:bold;margin-top:1px;}';
$html .= '.print-note{margin-bottom:3mm}';
$html .= '</style></head><body>';
$html .= '<div class="print-note"><button onclick="window.print()" style="margin-bottom:3px">Stampa / Salva come PDF</button></div>';
$html .= '<div class="labels">';

$delay = 0.1; // 100ms delay tra richieste per evitare rate limit
foreach ($components as $index => $c){
    $comp_id = intval($c['id']);
    // gestisci correttamente caratteri multibyte e troncamento a 20 caratteri per il codice
    $raw_code = $c['codice_prodotto'] ?? '';
    if (function_exists('mb_substr')) {
        $trunc = mb_substr($raw_code, 0, 20, 'UTF-8');
    } else {
        $trunc = substr($raw_code, 0, 20);
    }
    $code = htmlspecialchars($trunc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Genera barcode lineare (Code 128) usando libreria locale
    $generator = new Picqer\Barcode\BarcodeGeneratorSVG();
    $barcode_svg = $generator->getBarcode((string)$comp_id, $generator::TYPE_CODE_128);

    $html .= '<div class="label">';
    $html .= '<div class="barcode-container">' . $barcode_svg . '</div>';
    $html .= '<div class="code" style="margin-top: 2px;">' . $comp_id . '</div>';
    if ($include_code) {
        $html .= '<div class="code">' . $code . '</div>';
    }
    $html .= '</div>';
}

$html .= '</div></body></html>';

header('Content-Type: text/html; charset=utf-8');
echo $html;

?>