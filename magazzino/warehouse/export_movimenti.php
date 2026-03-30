<?php

/*
 * @Author: Andrea Gonzo 
 * @Date: 2026-03-29 
*/


require_once '../config/base_path.php';
require_once '../includes/db_connect.php';
$settings = include '../config/settings.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<p class='text-danger'>Non autenticato</p>";
    exit;
}

if (!isset($_GET['component_id']) || !is_numeric($_GET['component_id'])) {
    echo "<p class='text-muted'>ID componente non valido</p>";
    exit;
}

$component_id = (int)$_GET['component_id'];
$date_from = $_GET['date_from'] ?? null;
$date_to   = $_GET['date_to'] ?? null;

// --- Recupera informazioni del componente ---
$stmt = $pdo->prepare("SELECT * FROM components WHERE id = :component_id");
$stmt->execute(['component_id' => $component_id]);
$component = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$component) {
    echo "<p>Componente non trovato</p>";
    exit;
}

$image_file = __DIR__ . '/../images/components/' . $component_id . '.jpg';


$posizione = '-';
if (!empty($component['location_id']) && is_numeric($component['location_id'])) {
    $stmt_loc = $pdo->prepare("
        SELECT 
            l.name AS location_name,
            c.code AS compartment_name,
            loc.name AS locali_name
        FROM locations l
        LEFT JOIN compartments c ON c.id = :compartment_id
        LEFT JOIN locali loc ON loc.id = l.locale_id
        WHERE l.id = :location_id
        LIMIT 1
    ");
    $stmt_loc->execute([
        ':compartment_id' => $component['compartment_id'] ?? 0,
        ':location_id' => $component['location_id']
    ]);
    $loc = $stmt_loc->fetch(PDO::FETCH_ASSOC);

    if ($loc) {
        $posizione_parts = [];
        if (!empty($loc['locali_name'])) $posizione_parts[] = $loc['locali_name'];
        if (!empty($loc['location_name'])) $posizione_parts[] = $loc['location_name'];
        if (!empty($loc['compartment_name'])) $posizione_parts[] = $loc['compartment_name'];
        if (!empty($posizione_parts)) {
            $posizione = implode(' - ', $posizione_parts);
        }
    }
}

$categoria = '-';
if (!empty($component['category_id']) && is_numeric($component['category_id'])) {
    $stmt_cat = $pdo->prepare("
        SELECT name 
        FROM categories 
        WHERE id = :category_id
        LIMIT 1
    ");
    $stmt_cat->execute([
        ':category_id' => $component['category_id']
    ]);
    $cat = $stmt_cat->fetch(PDO::FETCH_ASSOC);
    if ($cat && !empty($cat['name'])) {
        $categoria = $cat['name'];
    }
}

// --- Recupera movimenti filtrati ---
$where = "WHERE m.component_id = :component_id";
$params = ['component_id' => $component_id];

if ($date_from) {
    $where .= " AND DATE(m.data_ora) >= :date_from";
    $params['date_from'] = $date_from;
}
if ($date_to) {
    $where .= " AND DATE(m.data_ora) <= :date_to";
    $params['date_to'] = $date_to;
}

$sql = "
    SELECT 
        m.movimento,
        m.quantity,
        m.commento,
        m.data_ora,
        u.username,
        c.unita_misura
    FROM movimenti_magazzino m
    LEFT JOIN users u ON u.id = m.user_id
    LEFT JOIN components c ON c.id = m.component_id
    $where
    ORDER BY m.data_ora DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Genera HTML stampabile ---
$html = '<!doctype html><html><head><meta charset="utf-8"><title>Movimenti Componente</title>';
$html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
$html .= '<style>
body{font-family:Arial,Helvetica,sans-serif;margin:10px;}
h2{margin-bottom:5px;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{border:1px solid #ccc;padding:5px;text-align:left;font-size:12px;}
th{background:#f0f0f0;}
.table-success{background-color:#d4edda;}
.table-danger{background-color:#f8d7da;}
.table-warning{background-color:#fff3cd;}
.component-info{margin-bottom:10px;}
.component-info td{border:none;padding:2px;}
.print-note{margin-bottom:5px;}

/* Nascondi il pulsante nella stampa */
@media print {
    .print-note {
        display: none;
    }
}
</style>
</head><body>';

$html .= '<div class="print-note"><button onclick="window.print()">Stampa / Salva PDF</button></div>';

$html .= '<style>
.component-info {
    margin-bottom: 10px;
    border-collapse: collapse;
}
.component-info td {
    border: none;
    padding: 2px 5px; /* riduce spazio */
    vertical-align: top;
}
.component-info td:first-child {
    width: 200px; /* larghezza fissa per i titoli */
    text-align: left;
    font-size:15px;
}
.component-info td:last-child {
    text-align: left; /* i valori vicini al titolo */
}
</style>';

// Informazioni componente
$html .= '<h1>Informazioni Componente</h1>';
$html .= '<table class="component-info" style="width:100%; border:none; ">';
$html .= '<tr><td style="border:none; vertical-align:top;">';

// Blocco dati
$quantita_attuale = htmlspecialchars($component['quantity'] ?? '-') . ' - ' . htmlspecialchars($component['unita_misura'] ?? '-');
$html .= '<div style="display:inline-block; vertical-align:top;">';
$html .= '<p><strong>Codice prodotto:</strong> ' . htmlspecialchars($component['codice_prodotto']) . '</p>';
$html .= '<p><strong>Categoria:</strong> ' . htmlspecialchars($categoria) . '</p>';
$html .= '<p><strong>Descrizione / Note:</strong> ' . htmlspecialchars($component['notes'] ?? '-') . '</p>';
$html .= '<p><strong>Disponibilità a magazzino:</strong> ' . htmlspecialchars($quantita_attuale) . '</p>';
$html .= '<p><strong>Posizione:</strong> ' . htmlspecialchars($posizione) . '</p>';

$html .= '</div>';

// Immagine subito a destra con piccolo gap
if (file_exists($image_file)) {
    $html .= '<img src="../images/components/' . $component_id . '.jpg" style="display:inline-block; vertical-align:top; margin-left:50px; max-width:170px; height:auto;">';
}

$html .= '</td></tr>';
$html .= '</table>';


// --- Tabella movimenti ---
$html .= '<h2>Movimenti</h2>';
$html .= '<table>';
$html .= '<thead><tr><th>Movimento</th><th>Quantità</th><th>Commento</th><th>Utente</th><th>Data/Ora</th></tr></thead>';
$html .= '<tbody>';

if ($movimenti) {
    foreach ($movimenti as $mov) {
        $classe_riga = '';
        if (isset($settings['mov_theme']) && $settings['mov_theme'] === 'color') {
            if ($mov['movimento'] === 'Scarico') $classe_riga = 'table-danger';
            elseif ($mov['movimento'] === 'Carico' && str_starts_with($mov['commento'], 'Storno')) $classe_riga = 'table-warning';
            elseif ($mov['movimento'] === 'Carico') $classe_riga = 'table-success';
        }
        $html .= '<tr class="' . $classe_riga . '">';
        $html .= '<td>' . htmlspecialchars($mov['movimento']) . '</td>';
        $html .= '<td>' . htmlspecialchars($mov['quantity']) . ' ' . htmlspecialchars($mov['unita_misura']) . '</td>';
        $html .= '<td>' . htmlspecialchars($mov['commento']) . '</td>';
        $html .= '<td>' . htmlspecialchars($mov['username'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars(date('d-m-Y H:i', strtotime($mov['data_ora']))) . '</td>';
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="5" style="text-align:center;color:#777;">Nessun movimento trovato</td></tr>';
}

$html .= '</tbody></table></body></html>';

header('Content-Type: text/html; charset=utf-8');
echo $html;
