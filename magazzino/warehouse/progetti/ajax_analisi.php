<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: AJAX Analisi Progetto
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger">Permessi insufficienti</div>';
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo '<div class="alert alert-danger">ID mancante</div>';
    exit;
}

// Recupera progetto
$stmt = $pdo->prepare("SELECT * FROM progetti WHERE id = ?");
$stmt->execute([$id]);
$progetto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$progetto) {
    echo '<div class="alert alert-danger">Progetto non trovato</div>';
    exit;
}

// Recupera componenti
$sql = "SELECT pc.*, c.codice_prodotto, c.costruttore, c.quantity as magazzino_qty, 
               c.prezzo as comp_prezzo, c.equivalents, f.nome as fornitore_nome
        FROM progetti_componenti pc
        LEFT JOIN components c ON pc.ks_componente = c.id
        LEFT JOIN fornitori f ON pc.ks_fornitore = f.id
        WHERE pc.ks_progetto = ?
        ORDER BY c.codice_prodotto ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$componenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Analisi
$totale_componenti = count($componenti);
$costo_totale = 0;
$disponibili = [];
$non_disponibili = [];
$parzialmente_disponibili = [];

foreach ($componenti as $comp) {
    $prezzo = $comp['prezzo'] ?? $comp['comp_prezzo'] ?? 0;
    $costo_totale += $prezzo * $comp['quantita'];
    
    if (!$comp['ks_componente']) {
        $non_disponibili[] = $comp;
    } elseif ($comp['magazzino_qty'] >= $comp['quantita']) {
        $disponibili[] = $comp;
    } elseif ($comp['magazzino_qty'] > 0) {
        $parzialmente_disponibili[] = $comp;
    } else {
        $non_disponibili[] = $comp;
    }
}
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center bg-light">
            <div class="card-body">
                <h4><?= $totale_componenti ?></h4>
                <small>Componenti Totali</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-success text-white">
            <div class="card-body">
                <h4><?= count($disponibili) ?></h4>
                <small>Disponibili</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-warning">
            <div class="card-body">
                <h4><?= count($parzialmente_disponibili) ?></h4>
                <small>Parzialmente Disp.</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-danger text-white">
            <div class="card-body">
                <h4><?= count($non_disponibili) ?></h4>
                <small>Non Disponibili</small>
            </div>
        </div>
    </div>
</div>

<h5 class="mb-3"><i class="fa-solid fa-euro-sign me-2"></i>Costo Totale Stimato: <strong>€ <?= number_format($costo_totale, 2, ',', '.') ?></strong></h5>

<?php if (!empty($non_disponibili) || !empty($parzialmente_disponibili)): ?>
<div class="alert alert-warning">
    <h6><i class="fa-solid fa-exclamation-triangle me-2"></i>Componenti da Acquistare</h6>
    <div class="table-responsive">
        <table class="table table-sm table-warning mb-0">
            <thead>
                <tr>
                    <th>Codice</th>
                    <th>Q.tà Richiesta</th>
                    <th>Disponibile</th>
                    <th>Mancante</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($non_disponibili as $c): 
                    $mancante = $c['quantita'];
                ?>
                    <tr>
                        <td><?= htmlspecialchars($c['codice_prodotto'] ?? $c['codice_componente']) ?></td>
                        <td><?= $c['quantita'] ?></td>
                        <td>0</td>
                        <td><strong><?= $mancante ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($parzialmente_disponibili as $c): 
                    $mancante = $c['quantita'] - $c['magazzino_qty'];
                ?>
                    <tr>
                        <td><?= htmlspecialchars($c['codice_prodotto']) ?></td>
                        <td><?= $c['quantita'] ?></td>
                        <td><?= $c['magazzino_qty'] ?></td>
                        <td><strong><?= $mancante ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="alert alert-success">
    <i class="fa-solid fa-check-circle me-2"></i>Tutti i componenti sono disponibili in magazzino!
</div>
<?php endif; ?>

<?php if (!empty($disponibili)): ?>
<h6 class="mt-3">Componenti Disponibili</h6>
<div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
    <table class="table table-sm table-success">
        <thead>
            <tr>
                <th>Codice</th>
                <th>Q.tà</th>
                <th>Magazzino</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($disponibili as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['codice_prodotto']) ?></td>
                    <td><?= $c['quantita'] ?></td>
                    <td><?= $c['magazzino_qty'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
