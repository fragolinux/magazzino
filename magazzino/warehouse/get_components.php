<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 18:00:51 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-09 20:13:59
*/
// 2026-01-03: Aggiunta funzionalità carico/scarico quantità componente
// 2026-01-08: Aggiunta quantità minima
// 2026-01-08: Aggiunto filtro per locale
// 2026-01-09: Aggiunta gestione immagine componente
// 2026-01-12: Aggiunti ricerca anche per tags
// 2026-01-14: Sistemati conteggi quantità per unità di misura
// 2026-02-01: Aggiunto return_url al link di modifica componente
// 2026-02-09: Aggiunto ordinamento per codice prodotto, categoria, comparto, quantità e posizione

require_once '../config/base_path.php';
require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$locale_id     = isset($_GET['locale_id']) && is_numeric($_GET['locale_id']) ? intval($_GET['locale_id']) : null;
$location_id   = isset($_GET['location_id']) && is_numeric($_GET['location_id']) ? intval($_GET['location_id']) : null;
$compartment_id = isset($_GET['compartment_id']) && is_numeric($_GET['compartment_id']) ? intval($_GET['compartment_id']) : null;
$category_id   = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? intval($_GET['category_id']) : null;
$search_code   = isset($_GET['search_code']) ? trim($_GET['search_code']) : '';
$package       = isset($_GET['package']) ? trim($_GET['package']) : '';
$tensione      = isset($_GET['tensione']) ? trim($_GET['tensione']) : '';
$corrente      = isset($_GET['corrente']) ? trim($_GET['corrente']) : '';
$potenza       = isset($_GET['potenza']) ? trim($_GET['potenza']) : '';
$hfe           = isset($_GET['hfe']) ? trim($_GET['hfe']) : '';
$tags          = isset($_GET['tags']) ? trim($_GET['tags']) : '';
$notes         = isset($_GET['notes']) ? trim($_GET['notes']) : '';

// Parametri di ordinamento
$sort_column   = isset($_GET['sort_column']) ? trim($_GET['sort_column']) : '';
$sort_direction = isset($_GET['sort_direction']) ? strtoupper(trim($_GET['sort_direction'])) : 'ASC';

// Validazione direzione ordinamento
if (!in_array($sort_direction, ['ASC', 'DESC'])) {
    $sort_direction = 'ASC';
}

// Mappa colonne consentite per ordinamento (colonna => campo SQL)
$allowed_sort_columns = [
    'codice_prodotto' => 'c.codice_prodotto',
    'category' => 'cat.name',
    'compartment' => 'cmp.code',
    'quantity' => 'c.quantity',
    'location' => 'l.name'
];

$query = "SELECT c.*, 
                 l.name AS location_name,
                 loc.name AS locale_name,
                 cmp.code AS compartment_code,
                 cat.name AS category_name
          FROM components c
          LEFT JOIN locations l ON c.location_id = l.id
          LEFT JOIN locali loc ON l.locale_id = loc.id
          LEFT JOIN compartments cmp ON c.compartment_id = cmp.id
          LEFT JOIN categories cat ON c.category_id = cat.id
          WHERE 1=1";
$params = [];

if ($locale_id) {
    $query .= " AND l.locale_id = ?";
    $params[] = $locale_id;
}
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
    $query .= " AND (LOWER(c.codice_prodotto) LIKE ? OR JSON_SEARCH(LOWER(c.equivalents), 'one', ?, NULL, '$[*]') IS NOT NULL OR JSON_SEARCH(LOWER(c.tags), 'one', ?, NULL, '$[*]') IS NOT NULL)";
    $searchLower = strtolower($search_code);
    $params[] = "%" . $searchLower . "%";
    $params[] = $searchLower;
    $params[] = $searchLower;
}
if ($package !== '') {
    $query .= " AND LOWER(c.package) LIKE ?";
    $params[] = "%" . strtolower($package) . "%";
}
if ($tensione !== '') {
    $query .= " AND LOWER(c.tensione) LIKE ?";
    $params[] = "%" . strtolower($tensione) . "%";
}
if ($corrente !== '') {
    $query .= " AND LOWER(c.corrente) LIKE ?";
    $params[] = "%" . strtolower($corrente) . "%";
}
if ($potenza !== '') {
    $query .= " AND LOWER(c.potenza) LIKE ?";
    $params[] = "%" . strtolower($potenza) . "%";
}
if ($hfe !== '') {
    $query .= " AND LOWER(c.hfe) LIKE ?";
    $params[] = "%" . strtolower($hfe) . "%";
}
if ($tags !== '') {
    $query .= " AND JSON_SEARCH(LOWER(c.tags), 'one', ?, NULL, '$[*]') IS NOT NULL";
    $params[] = strtolower($tags);
}
if ($notes !== '') {
    $query .= " AND LOWER(c.notes) LIKE ?";
    $params[] = "%" . strtolower($notes) . "%";
}

// Gestione ordinamento
if ($sort_column && isset($allowed_sort_columns[$sort_column])) {
    $query .= " ORDER BY " . $allowed_sort_columns[$sort_column] . " " . $sort_direction . ", c.id ASC";
} else {
    $query .= " ORDER BY c.id ASC";
}

$query .= " LIMIT 500";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$components = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = '';
if (!$components) {
    $html .= '<tr><td colspan="7" class="text-center text-muted">Nessun componente trovato.</td></tr>';
} else {
    foreach ($components as $c) {
        $qty = intval($c['quantity']);
        $qty_min = $c['quantity_min'];
        $unit = $c['unita_misura'] ?? 'pz';
        
        // Formatta quantità con unità di misura
        $qty_cell = $qty . ' ' . htmlspecialchars($unit);
        
        // Aggiungi icona rossa se quantità sotto scorta
        if ($qty_min !== null && $qty_min != 0 && $qty < $qty_min) {
            $qty_cell .= ' <button class="btn btn-sm btn-danger btn-view" data-id="'.$c['id'].'" title="Sotto scorta - Clicca per dettagli" style="padding: 0.125rem 0.375rem;"><i class="fa-solid fa-exclamation"></i></button>';
        }
        
        // Controlla se esiste l'immagine
        $thumbPath = realpath(__DIR__ . '/../images/components/thumbs/' . $c['id'] . '.jpg');
        $hasImage = $thumbPath && file_exists($thumbPath);
        $imageCell = '';
        if ($hasImage) {
            $imageCell = '<img src="'.BASE_PATH.'images/components/thumbs/'.$c['id'].'.jpg?'.time().'" alt="Thumb" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">';
        } else {
            $imageCell = '<span class="text-muted" style="font-size: 0.75rem;">—</span>';
        }
        
        $html .= '<tr>
                    <td class="text-center">'.$imageCell.'</td>
                    <td>'.htmlspecialchars($c['codice_prodotto']).'</td>
                    <td>'.htmlspecialchars($c['category_name'] ?? '-').'</td>
                    <td>'.$qty_cell.'</td>
                    <td>'.htmlspecialchars($c['location_name'] ?? '-').'</td>
                    <td>'.htmlspecialchars($c['compartment_code'] ?? '-').'</td>
                    <td class="text-end">
                        <button class="btn btn-xs btn-outline-secondary btn-clone me-1" data-id="'.$c['id'].'" title="Clona in Add Component" style="--bs-btn-padding-y: .15rem; --bs-btn-padding-x: .3rem; --bs-btn-font-size: .75rem;">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                        <button class="btn btn-xs btn-outline-info btn-view me-1" data-id="'.$c['id'].'" title="Visualizza dettagli" style="--bs-btn-padding-y: .15rem; --bs-btn-padding-x: .3rem; --bs-btn-font-size: .75rem;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        <button class="btn btn-xs btn-outline-success btn-unload me-1" data-id="'.$c['id'].'" data-product="'.htmlspecialchars($c['codice_prodotto']).'" data-quantity="'.$c['quantity'].'" title="Carico/Scarico" style="--bs-btn-padding-y: .15rem; --bs-btn-padding-x: .3rem; --bs-btn-font-size: .75rem;">
                            <i class="fa-solid fa-arrows-up-down"></i>
                        </button>
                        <a href="edit_component.php?id='.$c['id'].'&return_url='.urlencode(BASE_PATH . 'warehouse/components.php?search_code=' . urlencode($search_code)).'" class="btn btn-xs btn-outline-secondary me-1" title="Modifica" style="--bs-btn-padding-y: .15rem; --bs-btn-padding-x: .3rem; --bs-btn-font-size: .75rem;">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <button class="btn btn-xs btn-outline-danger btn-delete" data-id="'.$c['id'].'" data-product="'.htmlspecialchars($c['codice_prodotto'], ENT_QUOTES).'" title="Elimina" style="--bs-btn-padding-y: .15rem; --bs-btn-padding-x: .3rem; --bs-btn-font-size: .75rem;">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                  </tr>';
    }
}

echo $html;