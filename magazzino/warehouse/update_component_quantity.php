<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-03 09:42:29 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-03 10:30:00
*/
// 2026-01-03: Aggiunta funzionalità carico/scarico quantità componente

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = ['success' => false, 'message' => 'Errore sconosciuto'];

try {
    if (!isset($_POST['id']) || !is_numeric($_POST['id']) || !isset($_POST['quantity']) || !is_numeric($_POST['quantity'])) {
        $response['message'] = 'Parametri non validi';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    $component_id = intval($_POST['id']);
    $quantity_change = intval($_POST['quantity']);
    $operation = isset($_POST['operation']) ? $_POST['operation'] : 'unload';

    if ($quantity_change < 0) {
        $response['message'] = 'La quantità non può essere negativa';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Recupera la quantità attuale
    $stmt = $pdo->prepare("SELECT quantity FROM components WHERE id = ?");
    $stmt->execute([$component_id]);
    $component = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$component) {
        $response['message'] = 'Componente non trovato';
        http_response_code(404);
        echo json_encode($response);
        exit;
    }

    $current_quantity = intval($component['quantity']);
    
    // Calcola la nuova quantità in base all'operazione
    if ($operation === 'load') {
        $new_quantity = $current_quantity + $quantity_change;
    } else {
        $new_quantity = $current_quantity - $quantity_change;
    }

    // Verifica che la quantità non diventi negativa
    if ($new_quantity < 0) {
        $response['message'] = 'Quantità insufficiente in magazzino';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Aggiorna la quantità nel database
    $stmt = $pdo->prepare("UPDATE components SET quantity = ? WHERE id = ?");
    $stmt->execute([$new_quantity, $component_id]);

    $response['success'] = true;
    $response['message'] = 'Quantità aggiornata con successo';
    $response['new_quantity'] = $new_quantity;
    http_response_code(200);

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
