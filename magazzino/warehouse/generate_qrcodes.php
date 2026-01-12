<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-05 09:58:58 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-11 13:31:24
*/
// 2026-01-11: Aggiunto URL in alternativa all'IP del PC

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

// sanitize compartment ids
$ids = array_values(array_filter(array_map('intval', $compartments), function($v){ return $v>0; }));
if (count($ids) === 0){
    echo "<p>Errore: nessun comparto valido.</p>";
    exit;
}

// leggi IP dal setting
$stmt = $pdo->prepare("SELECT setting_value FROM setting WHERE setting_name = 'IP_Computer' LIMIT 1");
$stmt->execute();
$ip_row = $stmt->fetch(PDO::FETCH_ASSOC);
$ip = $ip_row ? trim($ip_row['setting_value']) : '';
if ($ip === ''){
    // fallback
    $ip = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
}

$include_code = isset($_POST['include_code_under_qr']) ? true : false;

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
// Usiamo un servizio esterno per generare le immagini QR e fissiamo la dimensione in mm via CSS (15x15mm)
$html = '<!doctype html><html><head><meta charset="utf-8"><title>QR Codes</title>';
$html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
$html .= '<style>@media print{@page{size:A4;margin:5mm}}body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:5mm}';
$html .= '.labels{display:grid;grid-template-columns:repeat(5, 1fr);gap:2mm}';
$html .= '.label{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:1mm;box-sizing:border-box;text-align:center;}';
$html .= '.label img{width:15mm;height:15mm;display:block;margin-bottom:1mm;}';
$html .= '.code{font-size:9pt;word-break:break-word;overflow:hidden;max-height:2.5em;line-height:1.2}';
$html .= '.print-note{margin-bottom:3mm}';
$html .= '</style></head><body>';
$html .= '<div class="print-note"><button onclick="window.print()" style="margin-bottom:3px">Stampa / Salva come PDF</button></div>';
$html .= '<div class="labels">';

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
    if (preg_match('#^https?://#i', $ip)) {
        // URL completo fornito (es: https://magazzino.miodominio.it/warehouse/mobile_component.php)
        $base_url = rtrim($ip, '/');
        // Se termina già con .php, aggiungi solo il parametro query
        if (preg_match('#\.php$#i', $base_url)) {
            $payload = $base_url . '?id=' . $comp_id;
        } else {
            // Altrimenti aggiungi il path del file
            $payload = $base_url . '/warehouse/mobile_component.php?id=' . $comp_id;
        }
    } else {
        // IP/hostname semplice - comportamento originale
        $payload = 'http://' . $ip . '/magazzino/warehouse/mobile_component.php?id=' . $comp_id;
    }
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($payload);

    $html .= '<div class="label">';
    $html .= '<img src="' . $qr_url . '" alt="QR">';
    if ($include_code) {
        $html .= '<div class="code">' . $code . '</div>';
    }
    $html .= '</div>';
}

$html .= '</div></body></html>';

header('Content-Type: text/html; charset=utf-8');
echo $html;

?>