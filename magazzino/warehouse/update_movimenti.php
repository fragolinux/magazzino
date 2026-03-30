<?php

/*
 * @Author: Andrea Gonzo 
 * @Date: 2026-03-29 
*/

header('Content-Type: application/json');

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$response = ['success' => false, 'message' => ''];

try {
    // Verifica che tutti i dati siano presenti
    if (!isset($_POST['movimento_id'], $_POST['comment'])) {
        throw new Exception('Dati mancanti.');
    }

    $movimentoId = intval($_POST['movimento_id']); // id univoco del movimento
    $commento = trim($_POST['comment']);

    if ($commento === '') {
        throw new Exception('Il commento non può essere vuoto.');
    }

    // Prepara la query di aggiornamento
    $sql = "UPDATE movimenti_magazzino
            SET commento = :comment
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':comment' => $commento,
        ':id' => $movimentoId
    ]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
    } else {
        $response['message'] = 'Nessun record aggiornato. Controlla l\'ID.';
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
