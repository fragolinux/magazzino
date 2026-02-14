<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: AJAX Conferma Progetto e Scarico Magazzino
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID mancante']);
    exit;
}

try {
    // Verifica progetto
    $check = $pdo->prepare("SELECT * FROM progetti WHERE id = ? AND stato = 'bozza'");
    $check->execute([$id]);
    $progetto = $check->fetch(PDO::FETCH_ASSOC);
    
    if (!$progetto) {
        echo json_encode(['success' => false, 'error' => 'Progetto non trovato o già confermato']);
        exit;
    }
    
    // Recupera componenti
    $stmt = $pdo->prepare("SELECT pc.*, c.codice_prodotto, c.quantity as magazzino_qty
                         FROM progetti_componenti pc
                         LEFT JOIN components c ON pc.ks_componente = c.id
                         WHERE pc.ks_progetto = ?");
    $stmt->execute([$id]);
    $componenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($componenti)) {
        echo json_encode(['success' => false, 'error' => 'Nessun componente nel progetto']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    $scaricati = 0;
    $mancanti = 0;
    $report_scarico = [];
    
    foreach ($componenti as $comp) {
        if (!$comp['ks_componente']) continue;
        
        $disp = intval($comp['magazzino_qty'] ?? 0);
        $richiesta = intval($comp['quantita']);
        $codice = $comp['codice_prodotto'] ?: 'ID_' . $comp['ks_componente'];
        
        $qta_scaricata = 0;
        
        if ($disp >= $richiesta) {
            // Scarico completo
            $qta_scaricata = $richiesta;
            $stmt = $pdo->prepare("UPDATE components SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$qta_scaricata, $comp['ks_componente']]);
            $scaricati++;
            $report_scarico[] = $codice . ' (-' . $qta_scaricata . ')';
        } elseif ($disp > 0) {
            // Scarico parziale
            $qta_scaricata = $disp;
            $stmt = $pdo->prepare("UPDATE components SET quantity = 0 WHERE id = ?");
            $stmt->execute([$comp['ks_componente']]);
            $scaricati++;
            $report_scarico[] = $codice . ' (-' . $qta_scaricata . ') [parziale: scaricati ' . $qta_scaricata . ' su ' . $richiesta . ' richiesti]';
        } else {
            // Non disponibile
            $mancanti++;
            $report_scarico[] = $codice . ' (0) [non disponibile, ' . $richiesta . ' richiesti]';
        }
        
        // Salva quantità scaricata nel progetto
        $stmt = $pdo->prepare("UPDATE progetti_componenti SET quantita_scaricata = ? WHERE id = ?");
        $stmt->execute([$qta_scaricata, $comp['id']]);
    }
    
    // Aggiorna stato progetto
    $stmt = $pdo->prepare("UPDATE progetti SET stato = 'confermato' WHERE id = ?");
    $stmt->execute([$id]);
    
    $pdo->commit();
    
    $report = "Componenti scaricati: $scaricati\n";
    $report .= "Componenti mancanti: $mancanti\n";
    if (!empty($report_scarico)) {
        $report .= "\nDettaglio scarico:\n" . implode("\n", $report_scarico);
    }
    
    echo json_encode([
        'success' => true,
        'scaricati' => $scaricati,
        'mancanti' => $mancanti,
        'report' => $report
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
