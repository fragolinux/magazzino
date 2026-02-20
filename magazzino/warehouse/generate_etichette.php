<?php
/*
 * @Author: gabriele.riva
 * @Date: 2026-02-19
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-19 15:29:09
*/
// Generatore di etichette personalizzate

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

// Carica impostazioni Etichette dal database
$etichettePerRiga = 6;  // valore default
$etichetteFontSize = '10'; // valore default (pt)

try {
    // Leggi etichette_per_riga
    $stmt = $pdo->prepare("SELECT setting_value FROM setting WHERE setting_name = ? LIMIT 1");
    $stmt->execute(['etichette_per_riga']);
    $result = $stmt->fetch();
    if ($result) {
        $etichettePerRiga = intval($result['setting_value']);
    }
    
    // Leggi etichette_font_size
    $stmt = $pdo->prepare("SELECT setting_value FROM setting WHERE setting_name = ? LIMIT 1");
    $stmt->execute(['etichette_font_size']);
    $result = $stmt->fetch();
    if ($result) {
        $etichetteFontSize = $result['setting_value'];
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

$html = '<!doctype html><html><head><meta charset="utf-8"><title>Etichette</title>';
$html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
$html .= '<style>@media print{@page{size:A4;margin:5mm}}body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:5mm}';
$html .= '.labels{display:grid;grid-template-columns:repeat(' . intval($etichettePerRiga) . ', 1fr);gap:2mm}';
$html .= '.label{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1mm;box-sizing:border-box;text-align:center;border:1px solid #ddd;border-radius:4px;}';
$html .= '.label-code{font-size:' . intval($etichetteFontSize) . 'pt;font-weight:bold;word-break:break-word;overflow:hidden;max-height:1.5em;line-height:1.2;}';
$html .= '.label-id{font-size:' . intval($etichetteFontSize) . 'pt;color:#666;margin-top:2px;}';
$html .= '.print-note{margin-bottom:3mm}';
$html .= '</style></head><body>';
$html .= '<div class="print-note"><button onclick="window.print()" style="margin-bottom:3px">Stampa / Salva come PDF</button></div>';
$html .= '<div class="labels">';

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

    $html .= '<div class="label">';
    $html .= '<div class="label-code">' . $code . '</div>';
    if ($include_code) {
        $html .= '<div class="label-id">ID: ' . $comp_id . '</div>';
    }
    $html .= '</div>';
}

$html .= '</div></body></html>';

header('Content-Type: text/html; charset=utf-8');
echo $html;

?>