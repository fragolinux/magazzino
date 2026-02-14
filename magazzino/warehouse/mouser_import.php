<?php
/*
 * @Author: Andrea Gonzo
 * @Date: 20206-02-08
 */  

header('Content-Type: application/json');

// Inizio modifica by RG4Tech ==========================================================================================
// Recupera API key dalla tabella fornitori (case insensitive)
require_once __DIR__ . '/../includes/db_connect.php';
$stmt = $pdo->prepare("SELECT apikey FROM fornitori WHERE LOWER(nome) LIKE LOWER('%Mouser%')");
$stmt->execute();
$apiKey = $stmt->fetchColumn() ?? "";
// Fine modifica  by RG4Tech ==========================================================================================

if (empty($apiKey)) {
    echo json_encode(['success' => false, 'error' => 'API key Mouser non configurata in fornitori']);
    exit;
}

if (!isset($_POST['codice_prodotto']) || empty($_POST['codice_prodotto'])) {
    echo json_encode(['success' => false, 'error' => 'Nessun codice prodotto']);
    exit;
}

$partNumber = $_POST['codice_prodotto'];

$body = [
    "SearchByPartRequest" => [
        "MouserPartNumber" => $partNumber,
        "Records" => 50 // prendi fino a 50 risultati
    ]
];

$url = "https://api.mouser.com/api/v1/search/partnumber?apiKey=" . urlencode($apiKey);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

// Inizio modifica by RG4Tech ==========================================================================================
// Abilita il tracking degli header HTTP per controllare lo status code
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Separa header dal body
$headerSize = strpos($response, "\r\n\r\n");
$body = ($headerSize !== false) ? substr($response, $headerSize + 4) : $response;

// Controlla status code 401 (Unauthorized) - API key non valida
if ($httpCode === 401) {
    echo json_encode(['success' => false, 'error' => 'API key Mouser non valida o scaduta']);
    exit;
}

// Controlla altri errori HTTP
if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => "Errore HTTP {$httpCode} dalla API Mouser"]);
    exit;
}
// Fine modifica  by RG4Tech ==========================================================================================

$json = json_decode($body, true);
if (!$json) {
    echo json_encode(['success' => false, 'error' => 'Risposta non valida da Mouser']);
    exit;
}

// Inizio modifica by RG4Tech ==========================================================================================
// Controlla errori specifici nella risposta API (es. API key invalida restituisce 200 con errore nel body)
if (!empty($json['Errors']) && is_array($json['Errors'])) {
    $errorMsg = implode(', ', array_column($json['Errors'], 'Message'));
    echo json_encode(['success' => false, 'error' => "Errore API Mouser: {$errorMsg}"]);
    exit;
}
if (!empty($json['ErrorMessage'])) {
    echo json_encode(['success' => false, 'error' => "Errore API Mouser: {$json['ErrorMessage']}"]);
    exit;
}
// Fine modifica  by RG4Tech ==========================================================================================

$parts = $json['SearchResults']['Parts'] ?? [];
if (empty($parts)) {
    echo json_encode(['success' => false, 'error' => 'Nessun componente trovato']);
    exit;
}

// mappa il primo componente per default
$firstPart = $parts[0];

// estrazione prezzo quantità 1
$price = '';
if (!empty($firstPart['PriceBreaks'])) {
    foreach ($firstPart['PriceBreaks'] as $pb) {
        if (isset($pb['Quantity']) && $pb['Quantity'] == 1) {
            $price = $pb['Price'] ?? '';
            break;
        }
    }
}

$mapped = [
    'partNumber'         => $firstPart['ManufacturerPartNumber'] ?? '',
    'manufacturer'       => $firstPart['Manufacturer'] ?? '',
    'description'        => $firstPart['Description'] ?? '',
    'datasheet'          => $firstPart['DataSheetUrl'] ?? '',
    'mouser_image'       => $firstPart['ImagePath'] ?? '',
    'mouser_datasheet'   => $firstPart['DataSheetUrl'] ?? '',
    'price'              => $price,
    'fornitore'          => 'Mouser Electronics',
    'mouser_number'      => $firstPart['ManufacturerPartNumber'] ?? '',
    'mouser_product_url' => $firstPart['ProductDetailUrl'] ?? '',
];

echo json_encode([
    'success' => true,
    'dataParts' => $parts, // array completo dei componenti per la selezione
    'data' => $mapped     // primo componente mappato di default
]);
