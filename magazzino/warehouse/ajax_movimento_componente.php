<?php
/*
 * @Author: Andrea Gonzo 
 * @Date: 2026-03-07 
*/

require_once '../config/base_path.php';
require_once '../includes/db_connect.php';
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
$limit = (isset($_GET['from']) && $_GET['from'] === 'unload_modal') ? "LIMIT 10" : "";

$stmt = $pdo->prepare("
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
    WHERE m.component_id = ?
    ORDER BY m.data_ora DESC
    $limit
");

$stmt->execute([$component_id]);
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
        </tr>
    </thead>
    <tbody>
        <?php if ($movimenti): ?>
                   <?php foreach ($movimenti as $mov): ?>
                   <?php 
                    $classe_riga = '';
                    if ($mov['movimento'] === 'Scarico') $classe_riga = 'table-danger';
                    elseif ($mov['movimento'] === 'Carico' && str_starts_with($mov['commento'], 'Storno')) $classe_riga = 'table-warning';
                    elseif ($mov['movimento'] === 'Carico') $classe_riga = 'table-success'; 
                ?>
                <tr class="<?= $classe_riga ?>">
                    <td><?= htmlspecialchars($mov['movimento']) ?></td>
                    <td><?= htmlspecialchars($mov['quantity']) ?> <?= htmlspecialchars($mov['unita_misura']) ?></td>
                    <td><?= htmlspecialchars($mov['commento']) ?></td>
                    <td><?= htmlspecialchars($mov['username'] ?? '-') ?></td>
                    <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($mov['data_ora']))) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5" class="text-center text-muted">Nessun movimento trovato</td></tr>
        <?php endif; ?>
    </tbody>
</table>