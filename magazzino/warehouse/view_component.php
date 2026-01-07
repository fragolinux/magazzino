<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-21 08:47:13 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-07 15:25:32
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="text-danger">ID componente non valido.</div>';
    exit;
}

$id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT c.*, 
                              l.name AS location_name, 
                              cmp.code AS compartment_code,
                              cat.name AS category_name
                       FROM components c
                       LEFT JOIN locations l ON c.location_id = l.id
                       LEFT JOIN compartments cmp ON c.compartment_id = cmp.id
                       LEFT JOIN categories cat ON c.category_id = cat.id
                       WHERE c.id = ?");
$stmt->execute([$id]);
$component = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$component) {
    echo '<div class="text-danger">Componente non trovato.</div>';
    exit;
}

function field($label, $value) {
    return '<tr><th style="width:30%;white-space:nowrap;">'.$label.'</th><td>'.($value !== '' ? $value : '<span class="text-muted">-</span>').'</td></tr>';
}

$equivalents = '';
if (!empty($component['equivalents'])) {
    $eq = json_decode($component['equivalents'], true);
    if (is_array($eq)) {
        $equivalents = htmlspecialchars(implode(', ', $eq));
    }
}

$notes = !empty($component['notes']) ? nl2br(htmlspecialchars($component['notes'])) : '';
$datasheet_url = trim($component['datasheet_url']);
$is_pdf = $datasheet_url && preg_match('/\.pdf(\?|$)/i', $datasheet_url);
?>

<div class="table-responsive mb-3">
    <table class="table table-bordered table-sm align-middle mb-0">
        <?= field('Codice prodotto', htmlspecialchars($component['codice_prodotto'])) ?>
        <?= field('Categoria', htmlspecialchars($component['category_name'] ?? '')) ?>
        <?= field('Quantità', intval($component['quantity'])) ?>
        <?= field('Posizione', htmlspecialchars($component['location_name'] ?? '')) ?>
        <?= field('Comparto', htmlspecialchars($component['compartment_code'] ?? '')) ?>
        <?= field('Costruttore', htmlspecialchars($component['costruttore'] ?? '')) ?>
        <?= field('Fornitore', htmlspecialchars($component['fornitore'] ?? '')) ?>
        <?= field('Codice fornitore', htmlspecialchars($component['codice_fornitore'] ?? '')) ?>
        <?= field('Equivalenti', $equivalents) ?>
        <?= field('Note', $notes) ?>
        <?php if ($datasheet_url): ?>
            <tr>
                <th>Datasheet URL</th>
                <td>
                    <a href="<?= htmlspecialchars($datasheet_url) ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-2">
                        <i class="fa-solid fa-file-pdf me-1"></i> Visualizza datasheet
                    </a>
                </td>
            </tr>
        <?php endif; ?>
        <?php if (!empty($component['datasheet_file'])): ?>
            <tr>
                <th>Datasheet</th>
                <td>
                    <a href="/magazzino/datasheet/<?= htmlspecialchars($component['datasheet_file']) ?>" target="_blank" class="btn btn-sm btn-outline-success mb-2">
                        <i class="fa-solid fa-file-pdf me-1"></i> Visualizza PDF
                    </a>
                </td>
            </tr>
        <?php endif; ?>
    </table>
</div>