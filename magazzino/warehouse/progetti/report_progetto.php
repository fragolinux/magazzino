<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: Report Progetto per Stampa/PDF
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

// Verifica permessi admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header('Location: progetti.php');
    exit;
}

// Recupera progetto
$stmt = $pdo->prepare("SELECT * FROM progetti WHERE id = ?");
$stmt->execute([$id]);
$progetto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$progetto) {
    die('Progetto non trovato');
}

// Recupera componenti del progetto con dettagli
$sql = "SELECT pc.*, c.codice_prodotto, c.costruttore, c.quantity as magazzino_qty, 
               c.prezzo as comp_prezzo, c.link_fornitore as comp_link,
               f.nome as fornitore_nome
        FROM progetti_componenti pc
        LEFT JOIN components c ON pc.ks_componente = c.id
        LEFT JOIN fornitori f ON pc.ks_fornitore = f.id
        WHERE pc.ks_progetto = ?
        ORDER BY c.codice_prodotto ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$componenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcola statistiche
$totale_componenti = count($componenti);
$costo_totale = 0;
$disponibili = 0;
$non_disponibili = 0;
$parzialmente_disponibili = 0;

foreach ($componenti as $comp) {
    $prezzo = $comp['prezzo'] ?? $comp['comp_prezzo'] ?? 0;
    $costo_totale += $prezzo * $comp['quantita'];
    
    if ($comp['ks_componente']) {
        if ($comp['magazzino_qty'] >= $comp['quantita']) {
            $disponibili++;
        } elseif ($comp['magazzino_qty'] > 0) {
            $parzialmente_disponibili++;
        } else {
            $non_disponibili++;
        }
    } else {
        $non_disponibili++;
    }
}

function getStatoBadge($stato) {
    switch ($stato) {
        case 'bozza': return '<span class="badge bg-secondary">Bozza</span>';
        case 'confermato': return '<span class="badge bg-warning text-dark">Confermato</span>';
        case 'completato': return '<span class="badge bg-success">Completato</span>';
        default: return '<span class="badge bg-light text-dark">' . ucfirst($stato) . '</span>';
    }
}

// Stato disponibilità
function getDispBadge($comp) {
    if (!$comp['ks_componente']) {
        return '<span class="badge bg-danger">Non in magazzino</span>';
    } elseif ($comp['magazzino_qty'] >= $comp['quantita']) {
        return '<span class="badge bg-success">Disponibile</span>';
    } elseif ($comp['magazzino_qty'] > 0) {
        return '<span class="badge bg-warning text-dark">Parziale</span>';
    } else {
        return '<span class="badge bg-danger">Esaurito</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Progetto - <?= htmlspecialchars($progetto['nome']) ?></title>
    <link href="<?= BASE_PATH ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_PATH ?>assets/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            .container { max-width: 100% !important; }
            body { font-size: 12pt; }
            .card { border: 1px solid #ddd !important; break-inside: avoid; }
            .table { font-size: 10pt; }
            h1, h2, h3 { page-break-after: avoid; }
            tr { page-break-inside: avoid; }
        }
        .report-header {
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin-bottom: 15px;
        }
        .total-box {
            background: #e7f3ff;
            border: 2px solid #0d6efd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .total-box h2 {
            color: #0d6efd;
            margin: 0;
            font-size: 2.5rem;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header Report -->
        <div class="report-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fa-solid fa-clipboard-list text-primary me-2"></i>Report Progetto</h1>
                    <h3 class="text-muted"><?= htmlspecialchars($progetto['nome']) ?></h3>
                    <?php if ($progetto['descrizione']): ?>
                        <p class="text-muted mb-1"><?= htmlspecialchars($progetto['descrizione']) ?></p>
                    <?php endif; ?>
                    <div class="d-flex gap-3 mt-2">
                        <?= getStatoBadge($progetto['stato']) ?>
                        <?php if ($progetto['numero_ordine']): ?>
                            <span class="text-muted"><i class="fa-solid fa-hashtag me-1"></i><?= htmlspecialchars($progetto['numero_ordine']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <p class="text-muted mb-1"><strong>Data report:</strong></p>
                    <p><?= date('d/m/Y H:i') ?></p>
                    <button class="btn btn-primary no-print" onclick="window.print()">
                        <i class="fa-solid fa-print me-2"></i>Stampa / Salva PDF
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistiche -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h5 class="text-primary mb-1"><i class="fa-solid fa-microchip me-2"></i>Componenti totali</h5>
                    <h3 class="mb-0"><?= $totale_componenti ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #198754;">
                    <h5 class="text-success mb-1"><i class="fa-solid fa-circle-check me-2"></i>Disponibili</h5>
                    <h3 class="mb-0"><?= $disponibili ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #ffc107;">
                    <h5 class="text-warning mb-1"><i class="fa-solid fa-triangle-exclamation me-2"></i>Non/Parz.</h5>
                    <h3 class="mb-0"><?= $parzialmente_disponibili + $non_disponibili ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #0dcaf0;">
                    <h5 class="text-info mb-1"><i class="fa-solid fa-euro-sign me-2"></i>Costo Totale</h5>
                    <h3 class="mb-0">€ <?= number_format($costo_totale, 2, ',', '.') ?></h3>
                </div>
            </div>
        </div>

        <!-- Costo Totale Evidenziato -->
        <div class="total-box">
            <p class="text-muted mb-1">COSTO TOTALE STIMATO</p>
            <h2>€ <?= number_format($costo_totale, 2, ',', '.') ?></h2>
        </div>

        <!-- Tabella Componenti -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fa-solid fa-boxes-stacked me-2"></i>Elenco Componenti</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Codice</th>
                                <th>Costruttore</th>
                                <th class="text-center">Q.tà</th>
                                <th class="text-center">Stato</th>
                                <th class="text-end">Prezzo Unit.</th>
                                <th class="text-end">Totale</th>
                                <th>Fornitore</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($componenti as $comp): 
                                $prezzo = $comp['prezzo'] ?? $comp['comp_prezzo'] ?? 0;
                                $totale_riga = $prezzo * $comp['quantita'];
                            ?>
                                <tr>
                                    <td class="text-muted"><?= $i++ ?></td>
                                    <td><strong><?= htmlspecialchars($comp['codice_prodotto'] ?? $comp['codice_componente'] ?? 'N/A') ?></strong></td>
                                    <td><?= htmlspecialchars($comp['costruttore'] ?? '—') ?></td>
                                    <td class="text-center"><?= $comp['quantita'] ?></td>
                                    <td class="text-center"><?= getDispBadge($comp) ?></td>
                                    <td class="text-end">
                                        <?= $prezzo > 0 ? '€ ' . number_format($prezzo, 2, ',', '.') : '—' ?>
                                    </td>
                                    <td class="text-end">
                                        <strong><?= $totale_riga > 0 ? '€ ' . number_format($totale_riga, 2, ',', '.') : '—' ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($comp['fornitore_nome'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-group-divider">
                            <tr class="table-primary">
                                <td colspan="6" class="text-end"><strong>TOTALE:</strong></td>
                                <td class="text-end"><strong>€ <?= number_format($costo_totale, 2, ',', '.') ?></strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-4 text-center text-muted">
            <hr>
            <p><small>Report generato il <?= date('d/m/Y') ?> alle <?= date('H:i') ?> | Gestione Magazzino By RG4Tech</small></p>
        </div>
    </div>

    <script>
        // Stampa automatica all'apertura (opzionale)
        // window.print();
    </script>
</body>
</html>
