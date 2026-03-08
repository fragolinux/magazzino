<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-09
 * @Last Modified by: Andrea Gonzo
 * @Last Modified time: 2026-03-07
 * @Description: AJAX Annulla Conferma - Torna a Bozza e ricarica magazzino
 */
 
// 2026-03-07 (Andrea Gonzo) aggiunta gestione carico/scarico magazzino

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID progetto mancante']);
    exit;
}

// Verifica che il progetto esista e sia in stato "confermato"
$check = $pdo->prepare("SELECT * FROM progetti WHERE id = ?");
$check->execute([$id]);
$progetto = $check->fetch(PDO::FETCH_ASSOC);

if (!$progetto) {
    echo json_encode(['success' => false, 'error' => 'Progetto non trovato']);
    exit;
}

if ($progetto['stato'] !== 'confermato') {
    echo json_encode(['success' => false, 'error' => 'Il progetto deve essere in stato "confermato"']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Recupera i componenti del progetto per ricaricarli in magazzino
    // Usa quantita_scaricata se disponibile, altrimenti quantita
    $stmt = $pdo->prepare("
        SELECT pc.id, pc.ks_componente, pc.quantita, pc.quantita_scaricata, c.codice_prodotto
        FROM progetti_componenti pc
        LEFT JOIN components c ON pc.ks_componente = c.id
        WHERE pc.ks_progetto = ? AND pc.ks_componente IS NOT NULL
    ");
    $stmt->execute([$id]);
    $componenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $ricaricati = 0;
    $dettaglio_carico = [];

    // Ricarica le quantità in magazzino e registra movimenti
    foreach ($componenti as $comp) {
        $codice = $comp['codice_prodotto'] ?: 'ID_' . $comp['ks_componente'];

        // Determina quantità da ricaricare
        $qta_da_ricaricare = ($comp['quantita_scaricata'] !== null) ? intval($comp['quantita_scaricata']) : intval($comp['quantita']);

        if ($qta_da_ricaricare > 0) {
            // Aggiorna la quantità in magazzino
            $stmt = $pdo->prepare("UPDATE components SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$qta_da_ricaricare, $comp['ks_componente']]);
            $ricaricati++;

            $dettaglio_carico[] = [
                'codice' => $codice,
                'quantita' => $qta_da_ricaricare
            ];

            // Inserimento nella tabella movimenti_magazzino (Andrea)
            $user_id = $_SESSION['user_id'] ?? 0;
            $commento = "Storno progetto: " . $progetto['nome'];
            $movimento = "Carico";

            $stmtMov = $pdo->prepare("
                INSERT INTO movimenti_magazzino 
                (component_id, data_ora, movimento, commento, quantity, user_id)
                VALUES (?, NOW(), ?, ?, ?, ?)
            ");
            $stmtMov->execute([
                $comp['ks_componente'], // component_id
                $movimento,             // movimento
                $commento,              // commento
                $qta_da_ricaricare,     // quantity
                $user_id                // user_id
            ]);
        }

        // Resetta quantita_scaricata
        $stmt = $pdo->prepare("UPDATE progetti_componenti SET quantita_scaricata = NULL WHERE id = ?");
        $stmt->execute([$comp['id']]);
    }

    // Aggiorna lo stato progetto a "bozza"
    $stmt = $pdo->prepare("UPDATE progetti SET stato = 'bozza', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Progetto tornato in "Bozza"',
        'componenti_ricaricati' => $ricaricati,
        'dettaglio_carico' => $dettaglio_carico
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Errore durante l\'annullamento: ' . $e->getMessage()]);
}