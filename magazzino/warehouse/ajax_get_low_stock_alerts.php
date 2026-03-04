<?php
/**
 * @Author: gabriele.riva 
 * @Date: 2026-03-03
 * Endpoint AJAX per recuperare i componenti sotto scorta filtrati dai cookie di esclusione
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Recupera componenti sotto scorta
$query = "SELECT c.id, c.codice_prodotto, c.quantity, c.quantity_min, c.unita_misura
          FROM components c
          WHERE c.quantity_min IS NOT NULL 
          AND c.quantity_min != 0 
          AND c.quantity < c.quantity_min";

$stmt = $pdo->query($query);
$all_low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Leggi il cookie delle esclusioni (ID componenti separati da virgola)
$excluded = isset($_COOKIE['hide_low_stock']) ? explode(',', $_COOKIE['hide_low_stock']) : [];

$to_alert = [];
foreach ($all_low_stock as $c) {
    if (!in_array($c['id'], $excluded)) {
        $to_alert[] = $c;
    }
}

header('Content-Type: application/json');
echo json_encode($to_alert);
