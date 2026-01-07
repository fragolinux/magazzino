<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 18:00:51 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-03 09:59:28
*/
// 2026-01-03: Aggiunta funzionalità carico/scarico quantità componente

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$location_id   = isset($_GET['location_id']) && is_numeric($_GET['location_id']) ? intval($_GET['location_id']) : null;
$compartment_id = isset($_GET['compartment_id']) && is_numeric($_GET['compartment_id']) ? intval($_GET['compartment_id']) : null;
$category_id   = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? intval($_GET['category_id']) : null;
$search_code   = isset($_GET['search_code']) ? trim($_GET['search_code']) : '';

$query = "SELECT c.*, 
                 l.name AS location_name, 
                 cmp.code AS compartment_code,
                 cat.name AS category_name
          FROM components c
          LEFT JOIN locations l ON c.location_id = l.id
          LEFT JOIN compartments cmp ON c.compartment_id = cmp.id
          LEFT JOIN categories cat ON c.category_id = cat.id
          WHERE 1=1";
$params = [];

if ($location_id) {
    $query .= " AND c.location_id = ?";
    $params[] = $location_id;
}
if ($compartment_id) {
    $query .= " AND c.compartment_id = ?";
    $params[] = $compartment_id;
}
if ($category_id) {
    $query .= " AND c.category_id = ?";
    $params[] = $category_id;
}
if ($search_code !== '') {
    $query .= " AND (LOWER(c.codice_prodotto) LIKE ? OR JSON_CONTAINS(LOWER(c.equivalents), JSON_QUOTE(?)))";
    $params[] = "%$search_code%";
    $params[] = strtolower($search_code);
}

$query .= " ORDER BY c.id ASC LIMIT 500";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$components = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = '';
if (!$components) {
    $html .= '<tr><td colspan="6" class="text-center text-muted">Nessun componente trovato.</td></tr>';
} else {
    foreach ($components as $c) {
        $html .= '<tr>
                    <td>'.htmlspecialchars($c['codice_prodotto']).'</td>
                    <td>'.htmlspecialchars($c['category_name'] ?? '-').'</td>
                    <td>'.intval($c['quantity']).'</td>
                    <td>'.htmlspecialchars($c['location_name'] ?? '-').'</td>
                    <td>'.htmlspecialchars($c['compartment_code'] ?? '-').'</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-info btn-view me-1" data-id="'.$c['id'].'" title="Visualizza dettagli">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success btn-unload me-1" data-id="'.$c['id'].'" data-product="'.htmlspecialchars($c['codice_prodotto']).'" data-quantity="'.$c['quantity'].'" title="Carico/Scarico">
                            <i class="fa-solid fa-arrows-up-down"></i>
                        </button>
                        <a href="edit_component.php?id='.$c['id'].'" class="btn btn-sm btn-outline-secondary me-1" title="Modifica">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <button class="btn btn-sm btn-outline-danger" title="Elimina"
                            onclick="if(confirm(\'Sei sicuro di voler eliminare '.htmlspecialchars($c['codice_prodotto'], ENT_QUOTES).'?\')) window.location=\'delete_component.php?id='.$c['id'].'\';">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                  </tr>';
    }
}

echo $html;