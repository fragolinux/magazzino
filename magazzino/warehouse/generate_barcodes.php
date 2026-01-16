<?php
/*
 * @Author: gabriele.riva
 * @Date: 2026-01-15
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-15
*/

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

$compartments = $_POST['compartments'] ?? [];
if (!is_array($compartments) || count($compartments) === 0) {
    echo "<p>Errore: nessun comparto selezionato.</p>";
    exit;
}

// sanitize compartment ids
$ids = array_values(array_filter(array_map('intval', $compartments), function($v){ return $v>0; }));
if (count($ids) === 0){
    echo "<p>Errore: nessun comparto valido.</p>";
    exit;
}

$include_code = isset($_POST['include_code_under_barcode']) ? true : false;

// recupera componenti nei comparti
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT id, codice_prodotto FROM components WHERE compartment_id IN ($placeholders) ORDER BY codice_prodotto ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($ids);
$components = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$components){
    echo "<p>Nessun componente trovato nei comparti selezionati.</p>";
    exit;
}

// Costruisci HTML stampabile (A4)
// Fissiamo la dimensione in mm via CSS
$html = '<!doctype html><html><head><meta charset="utf-8"><title>Barcode</title>';
$html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
$html .= '<style>@media print{@page{size:A4;margin:5mm}}body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:5mm}';
$html .= '.labels{display:grid;grid-template-columns:repeat(5, 1fr);gap:2mm}';
$html .= '.label{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:1mm;box-sizing:border-box;text-align:center;}';
$html .= '.label img{width:20mm;height:10mm;display:block;margin-bottom:1mm;}';
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
    $html .= '<div style="width: 200px; height: 40px; display: flex; align-items: flex-end; justify-content: center;">' . $barcode_svg . '</div>';
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