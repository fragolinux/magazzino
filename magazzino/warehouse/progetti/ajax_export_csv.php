<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: AJAX Export CSV Componenti Mancanti
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('Permessi insufficienti');
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    exit('ID mancante');
}

// Recupera progetto
$stmt = $pdo->prepare("SELECT nome FROM progetti WHERE id = ?");
$stmt->execute([$id]);
$progetto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$progetto) {
    exit('Progetto non trovato');
}

// Recupera componenti non disponibili o parzialmente disponibili
$sql = "SELECT pc.*, c.codice_prodotto, c.costruttore, c.quantity as magazzino_qty, 
               c.prezzo as comp_prezzo, f.nome as fornitore_nome, f.link as fornitore_link
        FROM progetti_componenti pc
        LEFT JOIN components c ON pc.ks_componente = c.id
        LEFT JOIN fornitori f ON pc.ks_fornitore = f.id
        WHERE pc.ks_progetto = ?
        ORDER BY c.codice_prodotto ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$componenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepara CSV
$filename = 'progetto_' . preg_replace('/[^a-zA-Z0-9]/', '_', $progetto['nome']) . '_mancanti_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM per Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header
fputcsv($output, ['Codice', 'Costruttore', 'Q.tà Richiesta', 'Q.tà Disponibile', 'Q.tà Mancante', 'Prezzo Unit.', 'Prezzo Totale', 'Fornitore', 'Link Fornitore', 'Note']);

// Dati
foreach ($componenti as $comp) {
    $disponibile = $comp['magazzino_qty'] ?? 0;
    $richiesta = $comp['quantita'];
    $mancante = max(0, $richiesta - $disponibile);
    
    // Esporta solo se manca qualcosa o non è in magazzino
    if ($mancante > 0 || !$comp['ks_componente']) {
        $prezzo = $comp['prezzo'] ?? $comp['comp_prezzo'] ?? 0;
        $totale = $prezzo * ($mancante > 0 ? $mancante : $richiesta);
        
        fputcsv($output, [
            $comp['codice_prodotto'] ?? $comp['codice_componente'] ?? 'N/A',
            $comp['costruttore'] ?? '',
            $richiesta,
            $disponibile,
            $mancante > 0 ? $mancante : $richiesta,
            $prezzo > 0 ? number_format($prezzo, 2, ',', '.') : '',
            $totale > 0 ? number_format($totale, 2, ',', '.') : '',
            $comp['fornitore_nome'] ?? '',
            $comp['link_fornitore'] ?? $comp['fornitore_link'] ?? '',
            $comp['note'] ?? ''
        ]);
    }
}

fclose($output);
exit;
