<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-09
 * @Description: AJAX Ricerca Componente
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([]);
    exit;
}

$q = $_GET['q'] ?? '';
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

// Cerca in codice_prodotto ed equivalents (JSON)
$sql = "SELECT id, codice_prodotto, costruttore, quantity, prezzo, fornitore 
        FROM components 
        WHERE codice_prodotto LIKE ? 
           OR JSON_CONTAINS(equivalents, JSON_QUOTE(?))
           OR codice_prodotto LIKE CONCAT(?, '%')
        ORDER BY codice_prodotto ASC
        LIMIT 20";

$stmt = $pdo->prepare($sql);
$search = "%$q%";
$stmt->execute([$search, $q, $q]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);
