<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: AJAX Aggiungi Componente al Progetto
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
$ks_componente = intval($_POST['ks_componente'] ?? 0);
$quantita = intval($_POST['quantita'] ?? 1);
$ks_fornitore = !empty($_POST['ks_fornitore']) ? intval($_POST['ks_fornitore']) : null;
$prezzo = !empty($_POST['prezzo']) ? floatval($_POST['prezzo']) : null;
$link_fornitore = !empty($_POST['link_fornitore']) ? trim($_POST['link_fornitore']) : null;
$note = !empty($_POST['note']) ? trim($_POST['note']) : null;

if (!$progetto_id || !$ks_componente || $quantita < 1) {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit;
}

try {
    // Verifica che il progetto sia in bozza
    $check = $pdo->prepare("SELECT stato FROM progetti WHERE id = ?");
    $check->execute([$progetto_id]);
    $progetto = $check->fetch(PDO::FETCH_ASSOC);
    
    if (!$progetto || $progetto['stato'] !== 'bozza') {
        echo json_encode(['success' => false, 'error' => 'Il progetto non è modificabile']);
        exit;
    }
    
    // Inserisce il componente
    $stmt = $pdo->prepare("INSERT INTO progetti_componenti 
        (ks_progetto, ks_componente, ks_fornitore, quantita, prezzo, link_fornitore, note) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$progetto_id, $ks_componente, $ks_fornitore, $quantita, $prezzo, $link_fornitore, $note]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'error' => 'Componente già presente nel progetto']);
    } else {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
