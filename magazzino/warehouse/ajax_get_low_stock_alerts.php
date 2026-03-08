<?php
/**
 * @Author: gabriele.riva 
 * @Date: 2026-03-03
 * @Last Modified time: 2026-03-07
 * Endpoint AJAX per recuperare i componenti sotto scorta filtrati dai cookie di esclusione
*/

// 2026-03-07: aggiunti bottoni per nascondere tutto

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

// Leggi i cookie delle esclusioni (ID componenti separati da virgola)
$excluded_today = isset($_COOKIE['hide_low_stock_today']) ? explode(',', $_COOKIE['hide_low_stock_today']) : [];
$excluded_forever = isset($_COOKIE['hide_low_stock_forever']) ? explode(',', $_COOKIE['hide_low_stock_forever']) : [];

// Unisci le liste di esclusione
$all_excluded = array_unique(array_merge($excluded_today, $excluded_forever));

$to_alert = [];
foreach ($all_low_stock as $c) {
    if (!in_array($c['id'], $all_excluded)) {
        $to_alert[] = $c;
    }
}

header('Content-Type: application/json');
echo json_encode($to_alert);
