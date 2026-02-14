<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: AJAX Aggiungi Componente al Progetto dopo import CSV
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
    exit;
}

$progetto_id = intval($_POST['progetto_id'] ?? 0);
$codice = trim($_POST['codice'] ?? '');
$quantita = intval($_POST['quantita'] ?? 1);
$prezzo = isset($_POST['prezzo']) && $_POST['prezzo'] !== '' ? floatval($_POST['prezzo']) : null;
$fornitore = trim($_POST['fornitore'] ?? '');
$note = trim($_POST['note'] ?? '');
$link_fornitore = trim($_POST['link_fornitore'] ?? '');

if (!$progetto_id || empty($codice) || $quantita < 1) {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit;
}

// Verifica che il progetto sia in bozza
$check = $pdo->prepare("SELECT stato FROM progetti WHERE id = ?");
$check->execute([$progetto_id]);
$progetto = $check->fetch(PDO::FETCH_ASSOC);

if (!$progetto || $progetto['stato'] !== 'bozza') {
    echo json_encode(['success' => false, 'error' => 'Il progetto non è modificabile']);
    exit;
}

// Cerca ID fornitore
$id_fornitore = null;
if ($fornitore) {
    $stmt = $pdo->prepare("SELECT id FROM fornitori WHERE nome = ?");
    $stmt->execute([$fornitore]);
    $forn = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($forn) {
        $id_fornitore = $forn['id'];
    }
}

// Cerca componente in magazzino
$stmt = $pdo->prepare("SELECT id FROM components 
                     WHERE codice_prodotto = ? 
                        OR JSON_CONTAINS(equivalents, JSON_QUOTE(?))");
$stmt->execute([$codice, $codice]);
$componente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$componente) {
    echo json_encode(['success' => false, 'error' => 'Componente non trovato in magazzino']);
    exit;
}

// Verifica se già presente nel progetto
$checkDup = $pdo->prepare("SELECT id FROM progetti_componenti WHERE ks_progetto = ? AND ks_componente = ?");
$checkDup->execute([$progetto_id, $componente['id']]);

if ($checkDup->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Componente già presente nel progetto']);
    exit;
}

// Aggiungi al progetto
try {
    $stmt = $pdo->prepare("INSERT INTO progetti_componenti 
        (ks_progetto, ks_componente, quantita, prezzo, ks_fornitore, note, link_fornitore) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$progetto_id, $componente['id'], $quantita, $prezzo, $id_fornitore, $note, $link_fornitore]);
    
    echo json_encode(['success' => true, 'message' => 'Componente aggiunto al progetto']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Errore durante l\'inserimento']);
}
