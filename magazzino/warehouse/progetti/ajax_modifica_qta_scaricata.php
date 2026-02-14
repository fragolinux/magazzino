<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-09
 * @Description: AJAX Modifica Quantità Scaricata
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
$quantita = intval($_POST['quantita'] ?? -1);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID componente mancante']);
    exit;
}

if ($quantita < 0) {
    echo json_encode(['success' => false, 'error' => 'Quantità non valida']);
    exit;
}

try {
    // Recupera il componente del progetto
    $stmt = $pdo->prepare("SELECT pc.*, p.stato 
                         FROM progetti_componenti pc
                         JOIN progetti p ON pc.ks_progetto = p.id
                         WHERE pc.id = ?");
    $stmt->execute([$id]);
    $comp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comp) {
        echo json_encode(['success' => false, 'error' => 'Componente non trovato']);
        exit;
    }
    
    // Verifica che il progetto sia in stato "confermato"
    if ($comp['stato'] !== 'confermato') {
        echo json_encode(['success' => false, 'error' => 'Il progetto non è in stato "confermato"']);
        exit;
    }
    
    // Verifica che la nuova quantità non superi quella richiesta
    if ($quantita > $comp['quantita']) {
        echo json_encode(['success' => false, 'error' => 'La quantità scaricata non può superare quella richiesta (' . $comp['quantita'] . ')']);
        exit;
    }
    
    // Aggiorna la quantità scaricata
    $stmt = $pdo->prepare("UPDATE progetti_componenti SET quantita_scaricata = ? WHERE id = ?");
    $stmt->execute([$quantita, $id]);
    
    echo json_encode(['success' => true, 'message' => 'Quantità scaricata aggiornata']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Errore: ' . $e->getMessage()]);
}
