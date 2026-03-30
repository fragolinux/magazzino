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
    if (!isset($_POST['movimento_id']) || !is_numeric($_POST['movimento_id'])) {
        throw new Exception('ID movimento non valido.');
    }

    $movimentoId = intval($_POST['movimento_id']);

    $sql = "DELETE FROM movimenti_magazzino WHERE id = :movimento_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':movimento_id' => $movimentoId]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
    } else {
        $response['message'] = 'Nessun record trovato per l\'ID fornito.';
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
