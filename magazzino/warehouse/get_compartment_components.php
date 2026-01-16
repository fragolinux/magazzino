<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-21 11:30:31 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-01-14
*/
// 2026-01-14: Sistemati conteggi quantità per unità di misura

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// 2026-01-14: Aggiunta unità di misura
$compartment_id = isset($_GET['compartment_id']) && is_numeric($_GET['compartment_id']) ? intval($_GET['compartment_id']) : 0;

$stmt = $pdo->prepare("
    SELECT c.codice_prodotto, cat.name AS category_name, c.quantity, c.unita_misura, c.costruttore, c.fornitore
    FROM components c
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE c.compartment_id = ?
    ORDER BY c.codice_prodotto ASC
");
$stmt->execute([$compartment_id]);
$components = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$components) {
    echo '<div class="alert alert-secondary">Nessun componente in questo comparto.</div>';
    exit;
}

echo '<table class="table table-sm table-striped">';
echo '<thead><tr><th>Codice prodotto</th><th>Categoria</th><th>Quantità</th><th>Costruttore</th><th>Fornitore</th></tr></thead><tbody>';
foreach ($components as $c) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($c['codice_prodotto']) . '</td>';
    echo '<td>' . htmlspecialchars($c['category_name']) . '</td>';
    echo '<td>' . $c['quantity'] . ' ' . htmlspecialchars($c['unita_misura'] ?? 'pz') . '</td>';
    echo '<td>' . htmlspecialchars($c['costruttore']) . '</td>';
    echo '<td>' . htmlspecialchars($c['fornitore']) . '</td>';
    echo '</tr>';
}
echo '</tbody></table>';