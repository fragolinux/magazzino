<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: AJAX Modifica Componente del Progetto
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$quantita = intval($_POST['quantita'] ?? 1);
$ks_fornitore = !empty($_POST['ks_fornitore']) ? intval($_POST['ks_fornitore']) : null;
$prezzo = !empty($_POST['prezzo']) ? floatval($_POST['prezzo']) : null;
$link_fornitore = !empty($_POST['link_fornitore']) ? trim($_POST['link_fornitore']) : null;
$note = !empty($_POST['note']) ? trim($_POST['note']) : null;

if (!$id || $quantita < 1) {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit;
}

try {
    // Verifica che il progetto sia in bozza
    $check = $pdo->prepare("SELECT pc.ks_progetto, p.stato 
                         FROM progetti_componenti pc
                         JOIN progetti p ON pc.ks_progetto = p.id
                         WHERE pc.id = ?");
    $check->execute([$id]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Componente non trovato']);
        exit;
    }
    
    if ($row['stato'] !== 'bozza') {
        echo json_encode(['success' => false, 'error' => 'Il progetto non è modificabile']);
        exit;
    }
    
    // Aggiorna componente
    $stmt = $pdo->prepare("UPDATE progetti_componenti 
        SET quantita = ?, ks_fornitore = ?, prezzo = ?, link_fornitore = ?, note = ? 
        WHERE id = ?");
    $stmt->execute([$quantita, $ks_fornitore, $prezzo, $link_fornitore, $note, $id]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
