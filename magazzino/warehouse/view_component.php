<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-21 08:47:13 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-09 23:31:14
*/
// 2026-01-08: Aggiunta quantità minima
// 2026-01-08: Aggiunto locale
// 2026-01-09: Aggiunta gestione immagine componente
// 2026-01-14: Sistemati conteggi quantità per unità di misura
// 2026-02-08: Tolti campi vuoti e unità di misura
// 2026-02-09: Aggiunta gestione prezzo/fornitore da progetti_componenti

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="text-danger">ID componente non valido.</div>';
    exit;
}

$id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT c.*, 
                              l.name AS location_name,
                              loc.name AS locale_name,
                              cmp.code AS compartment_code,
                              cat.name AS category_name
                       FROM components c
                       LEFT JOIN locations l ON c.location_id = l.id
                       LEFT JOIN locali loc ON l.locale_id = loc.id
                       LEFT JOIN compartments cmp ON c.compartment_id = cmp.id
                       LEFT JOIN categories cat ON c.category_id = cat.id
                       WHERE c.id = ?");
$stmt->execute([$id]);
$component = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$component) {
    echo '<div class="text-danger">Componente non trovato.</div>';
    exit;
}

// Se viene richiesto da un progetto, cerca prima i dati in progetti_componenti
$progetto_prezzo = null;
$progetto_fornitore = null;
$progetto_fornitore_nome = null;
$progetto_link_fornitore = null;

if (isset($_GET['progetto_componente_id']) && is_numeric($_GET['progetto_componente_id'])) {
    $progetto_componente_id = intval($_GET['progetto_componente_id']);
    
    $stmt = $pdo->prepare("SELECT pc.*, f.nome as fornitore_nome 
                           FROM progetti_componenti pc 
                           LEFT JOIN fornitori f ON pc.ks_fornitore = f.id 
                           WHERE pc.id = ? AND pc.ks_componente = ?");
    $stmt->execute([$progetto_componente_id, $id]);
    $progetto_componente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($progetto_componente) {
        // Usa prezzo, fornitore, link e note da progetti_componenti se presenti
        if (!empty($progetto_componente['prezzo'])) {
            $progetto_prezzo = $progetto_componente['prezzo'];
        }
        if (!empty($progetto_componente['ks_fornitore'])) {
            $progetto_fornitore = $progetto_componente['ks_fornitore'];
            $progetto_fornitore_nome = $progetto_componente['fornitore_nome'];
        }
        if (!empty($progetto_componente['link_fornitore'])) {
            $progetto_link_fornitore = $progetto_componente['link_fornitore'];
        }
        if (!empty($progetto_componente['note'])) {
            $progetto_note = $progetto_componente['note'];
        }
        if (!empty($progetto_componente['quantita'])) {
            $progetto_quantita = $progetto_componente['quantita'];
        }
    }
}

// Determina i valori finali da mostrare (da progetto se disponibili, altrimenti da component)
$prezzo_finale = $progetto_prezzo ?? $component['prezzo'] ?? null;
$fornitore_finale = $progetto_fornitore_nome ?? $component['fornitore'] ?? null;
$link_fornitore_finale = $progetto_link_fornitore ?? $component['link_fornitore'] ?? null;
$note_finale = $progetto_note ?? $component['notes'] ?? null;
$quantita_finale = $progetto_quantita ?? $component['quantity'] ?? 0;

function field($label, $value) {
    // Non mostrare il campo se il valore è vuoto
    if ($value === '' || $value === null) {
        return '';
    }
    return '<tr><th style="width:30%;white-space:nowrap;">'.$label.'</th><td>'.$value.'</td></tr>';
}

$equivalents = '';
if (!empty($component['equivalents'])) {
    $eq = json_decode($component['equivalents'], true);
    if (is_array($eq)) {
        $equivalents = htmlspecialchars(implode(', ', $eq));
    }
}

$tags = '';
if (!empty($component['tags'])) {
    $tg = json_decode($component['tags'], true);
    if (is_array($tg)) {
        $tags = htmlspecialchars(implode(', ', $tg));
    }
}

$notes = !empty($note_finale) ? nl2br(htmlspecialchars($note_finale)) : '';
$datasheet_url = trim($component['datasheet_url']);
$is_pdf = $datasheet_url && preg_match('/\.pdf(\?|$)/i', $datasheet_url);

// Verifica se esiste l'immagine del componente
$imagePath = realpath(__DIR__ . '/../images/components/' . $id . '.jpg');
$hasImage = $imagePath && file_exists($imagePath);
?>

<div class="table-responsive mb-3">
    <table class="table table-bordered table-sm align-middle mb-0">
        <?= field('Codice prodotto', htmlspecialchars($component['codice_prodotto'])) ?>
        <?= field('Categoria', htmlspecialchars($component['category_name'] ?? '')) ?>
        <?php 
        $unit = $component['unita_misura'] ?? 'pz';
        $qty = intval($quantita_finale);
        $qty_min = $component['quantity_min'];
        ?>
        <?= field('Quantità', $qty . ' ' . htmlspecialchars($unit)) ?>
        <?php if ($qty_min !== null && $qty_min != 0): ?>
            <?php $rowClass = ($qty_min > $qty) ? ' class="table-danger"' : ''; ?>
            <tr<?= $rowClass ?>><th style="width:30%;white-space:nowrap;">Q.tà minima</th><td><?= $qty_min . ' ' . htmlspecialchars($unit) ?></td></tr>
        <?php endif; ?>
        <?= field('Locale', htmlspecialchars($component['locale_name'] ?? '')) ?>
        <?= field('Posizione', htmlspecialchars($component['location_name'] ?? '')) ?>
        <?= field('Comparto', htmlspecialchars($component['compartment_code'] ?? '')) ?>
        <?= field('Costruttore', htmlspecialchars($component['costruttore'] ?? '')) ?>
        <?= field('Fornitore', htmlspecialchars($fornitore_finale ?? '')) ?>
        <?= field('Codice fornitore', htmlspecialchars($component['codice_fornitore'] ?? '')) ?>
        <?php if (!empty($prezzo_finale)): ?>
        <?= field('Prezzo', '€ ' . number_format($prezzo_finale, 2, ',', '.')) ?>
        <?php endif; ?>
        <?php if (!empty($link_fornitore_finale)): ?>
            <tr>
                <th>Link fornitore</th>
                <td>
                    <a href="<?= htmlspecialchars($link_fornitore_finale) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-link me-1"></i> Vai al sito
                    </a>
                </td>
            </tr>
        <?php endif; ?>
        <?= field('Package', htmlspecialchars($component['package'] ?? '')) ?>
        <?= field('Tensione', !empty($component['tensione']) ? htmlspecialchars($component['tensione']) . ' V' : '') ?>
        <?= field('Corrente', !empty($component['corrente']) ? htmlspecialchars($component['corrente']) . ' A' : '') ?>
        <?= field('Potenza', !empty($component['potenza']) ? htmlspecialchars($component['potenza']) . ' W' : '') ?>
        <?= field('hFE (Guadagno)', htmlspecialchars($component['hfe'] ?? '')) ?>
        <?= field('Equivalenti', $equivalents) ?>
        <?= field('Tags', $tags) ?>
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
                    <a href="<?= BASE_PATH ?>datasheet/<?= htmlspecialchars($component['datasheet_file']) ?>" target="_blank" class="btn btn-sm btn-outline-success mb-2">
                        <i class="fa-solid fa-file-pdf me-1"></i> Visualizza PDF
                    </a>
                </td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<?php if ($hasImage): ?>
<div class="text-center mt-3">
    <img src="<?= BASE_PATH ?>images/components/<?= $id ?>.jpg?<?= time() ?>" alt="Immagine componente" style="max-width: 100%; max-height: 500px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
</div>
<?php endif; ?>