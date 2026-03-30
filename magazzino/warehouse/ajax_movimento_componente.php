<?php
/*
 * @Author: Andrea Gonzo 
 * @Date: 2026-03-07 
 * * @Last Modified time: 2026-03-29
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
$date_from    = $_GET['date_from'] ?? null;
$date_to      = $_GET['date_to'] ?? null;
$show_actions = isset($_GET['show_actions']) && $_GET['show_actions'] == 1;

// Limite per modal unload
$limit_sql = (isset($_GET['from']) && $_GET['from'] === 'unload_modal') ? "LIMIT 10" : "";

// Costruzione WHERE con filtri
$where  = "WHERE m.component_id = :component_id";
$params = ['component_id' => $component_id];

if ($date_from) {
    $where .= " AND DATE(m.data_ora) >= :date_from";
    $params['date_from'] = $date_from;
}

if ($date_to) {
    $where .= " AND DATE(m.data_ora) <= :date_to";
    $params['date_to'] = $date_to;
}

// Query finale
$sql = "
    SELECT 
        m.id,  
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
    $limit_sql
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<table class="table table-sm table-striped">
    <thead>
        <tr>
            <th>Movimento</th>
            <th>Quantità</th>
            <th>Commento</th>
            <th>Utente</th>
            <th>Data/Ora</th>
            <?php if ($show_actions): ?>
                <th>Azioni</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php if ($movimenti): ?>
            <?php foreach ($movimenti as $mov): ?>
                <?php
                $classe_riga = '';
                if (isset($settings['mov_theme']) && $settings['mov_theme'] === 'color') {
                    if ($mov['movimento'] === 'Scarico') {
                        $classe_riga = 'table-danger';
                    } elseif ($mov['movimento'] === 'Carico' && str_starts_with($mov['commento'], 'Storno')) {
                        $classe_riga = 'table-warning';
                    } elseif ($mov['movimento'] === 'Carico') {
                        $classe_riga = 'table-success';
                    }
                }
                ?>
                <tr class="<?= $classe_riga ?>">
                    <td><?= htmlspecialchars($mov['movimento']) ?></td>
                    <td><?= htmlspecialchars($mov['quantity']) ?> <?= htmlspecialchars($mov['unita_misura']) ?></td>
                    <td class="mov-comment"><?= htmlspecialchars($mov['commento']) ?></td>
                    <td><?= htmlspecialchars($mov['username'] ?? '-') ?></td>
                    <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($mov['data_ora']))) ?></td>
                    <?php if ($show_actions): ?>
                        <td>
                            <button class="btn btn-outline-secondary" title="Modifica commento"
                                data-action="edit-comment"
                                data-movimento-id="<?= $mov['id'] ?>"
                                data-comment="<?= htmlspecialchars($mov['commento']) ?>"
                                style="padding: 0.15rem 0.3rem; font-size: 0.7rem; margin-right: 0.3rem;">
                                <i class="fa-solid fa-pen" style="font-size: 0.7rem;"></i>
                            </button>
                            <button class="btn btn-outline-danger" title="Elimina movimento"
                                data-action="delete-movement"
                                data-movimento-id="<?= $mov['id'] ?>"
                                style="padding: 0.15rem 0.3rem; font-size: 0.7rem;">
                                <i class="fa-solid fa-trash" style="font-size: 0.7rem;"></i>
                            </button>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="<?= $show_actions ? 6 : 5 ?>" class="text-center text-muted">Nessun movimento trovato</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>