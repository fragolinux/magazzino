<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-05 09:58:58 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-02 20:02:38
*/
// 2026-01-11: Aggiunto URL in alternativa all'IP del PC
// 2026-01-15: utilizzata libreria locale per generare QR qrcode.min.js
// 2026-02-01: Aggiunti parametri QR Code nei setting
// 2026-02-02: Aggiunta possibilità di selezionare componenti senza comparto

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

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

// leggi IP dal setting
$settingsConfigFile = __DIR__ . '/../config/settings.php';
$appSettings = file_exists($settingsConfigFile) ? require $settingsConfigFile : ['ip_address' => ''];
$ip = $appSettings['ip_address'] ?: '';
if ($ip === ''){
    // fallback
    $ip = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
}

// Leggi impostazioni QR dal database
$qrPerRiga = 10;  // valore default
$qrSize = 100;   // valore default in pixel
try {
    // Leggi qr_per_riga dal database
    $stmtQR1 = $pdo->prepare("SELECT setting_value FROM setting WHERE setting_name = ? LIMIT 1");
    $stmtQR1->execute(['qr_per_riga']);
    $resultQR1 = $stmtQR1->fetch(PDO::FETCH_ASSOC);
    if ($resultQR1) {
        $qrPerRiga = intval($resultQR1['setting_value']);
    }
    
    // Leggi qr_size dal database
    $stmtQR2 = $pdo->prepare("SELECT setting_value FROM setting WHERE setting_name = ? LIMIT 1");
    $stmtQR2->execute(['qr_size']);
    $resultQR2 = $stmtQR2->fetch(PDO::FETCH_ASSOC);
    if ($resultQR2) {
        $qrSize = intval($resultQR2['setting_value']);
    }
} catch (Exception $dbErr) {
    // Se il database non è raggiungibile, usa i default
}

$include_code = isset($_POST['include_code_under_qr']) ? true : false;

try {
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

// Costruisci HTML stampabile (A4)
// Usiamo una libreria JavaScript per generare QR code lato client
$html = '<!doctype html><html><head><meta charset="utf-8"><title>QR Codes</title>';
$html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
$html .= '<script src="../assets/js/qrcode.min.js"></script>';
$html .= '<style>@media print{@page{size:A4;margin:5mm}}body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:5mm}';
$html .= '.labels{display:grid;grid-template-columns:repeat(' . intval($qrPerRiga) . ', 1fr);gap:2mm}';
$html .= '.label{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:1mm;box-sizing:border-box;text-align:center;}';
$html .= '.label canvas{width:' . intval($qrSize) . 'px;height:' . intval($qrSize) . 'px;display:block;margin-bottom:1mm;}';
$html .= '.code{font-size:9pt;word-break:break-word;overflow:hidden;max-height:2.5em;line-height:1.2}';
$html .= '.print-note{margin-bottom:3mm}';
$html .= '</style></head><body>';
$html .= '<div class="print-note"><button onclick="window.print()" style="margin-bottom:3px">Stampa / Salva come PDF</button></div>';
$html .= '<div class="labels">';

// Funzione helper per determinare se è un IP locale
function is_local_ip($ip) {
    $clean_ip = preg_replace('#^https?://#', '', $ip);
    return filter_var($clean_ip, FILTER_VALIDATE_IP) !== false;
}

foreach ($components as $c){
    $comp_id = intval($c['id']);
    // gestisci correttamente caratteri multibyte e troncamento a 17 caratteri
    $raw_code = $c['codice_prodotto'] ?? '';
    if (function_exists('mb_substr')) {
        $trunc = mb_substr($raw_code, 0, 17, 'UTF-8');
    } else {
        $trunc = substr($raw_code, 0, 17);
    }
    $code = htmlspecialchars($trunc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // payload URL - punta a mobile_component.php per carico/scarico da smartphone
    // Supporta sia IP locali che URL esterni completi (http:// o https://)
    
    if (is_local_ip($ip)) {
        $clean_ip = preg_replace('#^https?://#', '', $ip);
        $payload = 'http://' . $clean_ip . BASE_PATH . 'warehouse/mobile_component.php?id=' . $comp_id;
    } else {
        // URL esterno
        $base_url = rtrim($ip, '/');
        if (preg_match('#\.php$#i', $base_url)) {
            $payload = $base_url . '?id=' . $comp_id;
        } else {
            $payload = $base_url . '/warehouse/mobile_component.php?id=' . $comp_id;
        }
    }
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($payload);

    // Fallback: se il QR esterno non funziona, mostra un placeholder
    $html .= '<div class="label">';
    $html .= '<div data-qr="' . htmlspecialchars($payload) . '"></div>';
    if ($include_code) {
        $html .= '<div class="code">' . $code . '</div>';
    }
    $html .= '</div>';
}

$html .= '</div>';
$html .= '<script>';
$html .= 'document.addEventListener("DOMContentLoaded", function() {';
$html .= '    var qrElements = document.querySelectorAll("div[data-qr]");';
$html .= '    qrElements.forEach(function(element) {';
$html .= '        var qrData = element.getAttribute("data-qr");';
$html .= '        new QRCode(element, {';
$html .= '            text: qrData,';
$html .= '            width: ' . intval($qrSize) . ',';
$html .= '            height: ' . intval($qrSize) . ',';
$html .= '            colorDark: "#000000",';
$html .= '            colorLight: "#ffffff",';
$html .= '            correctLevel: QRCode.CorrectLevel.M';
$html .= '        });';
$html .= '    });';
$html .= '});';
$html .= '</script>';
$html .= '</body></html>';

	header('Content-Type: text/html; charset=utf-8');
	echo $html;

} catch (Exception $e) {
	header('Content-Type: text/html; charset=utf-8');
	echo "<!DOCTYPE html><html><head><title>Errore QR Code</title></head><body>";
	echo "<h1>Errore nella generazione dei QR Code</h1>";
	echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
	echo "<p><a href='javascript:window.close()'>Chiudi finestra</a></p>";
	echo "</body></html>";
}

?>