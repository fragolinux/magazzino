<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-09
 * @Description: Visualizzazione e Gestione Componenti Progetto
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
    $_SESSION['error'] = 'Progetto non trovato.';
    header('Location: progetti.php');
    exit;
}

// Messaggi
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Recupera fornitori per select
$fornitori = $pdo->query("SELECT * FROM fornitori ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// Recupera componenti del progetto con dettagli
$sql = "SELECT pc.*, c.codice_prodotto, c.costruttore, c.quantity as magazzino_qty, 
               c.prezzo as comp_prezzo, c.link_fornitore as comp_link, c.equivalents,
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

// Badge colori per stato
function getStatoBadge($stato) {
    switch ($stato) {
        case 'bozza': return '<span class="badge bg-secondary">Bozza</span>';
        case 'confermato': return '<span class="badge bg-warning text-dark">Confermato</span>';
        case 'completato': return '<span class="badge bg-success">Completato</span>';
        default: return '<span class="badge bg-light text-dark">' . ucfirst($stato) . '</span>';
    }
}

include '../../includes/header.php';
?>

<div class="container py-4">
    <!-- Header Progetto -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h2><i class="fa-solid fa-clipboard-list me-2"></i><?= htmlspecialchars($progetto['nome']) ?></h2>
            <?php if ($progetto['descrizione']): ?>
                <p class="text-muted mb-1"><?= htmlspecialchars($progetto['descrizione']) ?></p>
            <?php endif; ?>
            <div class="d-flex gap-3 align-items-center">
                <?= getStatoBadge($progetto['stato']) ?>
                <?php if ($progetto['numero_ordine']): ?>
                    <span class="text-muted"><i class="fa-solid fa-hashtag me-1"></i><?= htmlspecialchars($progetto['numero_ordine']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="progetti.php" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i>Torna alla lista
            </a>
            <a href="progetto_edit.php?id=<?= $id ?>" class="btn btn-outline-primary">
                <i class="fa-solid fa-edit me-1"></i>Modifica Dati
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistiche Compatte -->
    <div class="row mb-3 g-2">
        <div class="col-6 col-md-3">
            <div class="card text-center border-primary py-2">
                <div class="card-body py-1">
                    <small class="text-muted d-block mb-1"><i class="fa-solid fa-microchip text-primary me-1"></i>Componenti</small>
                    <h4 class="mb-0"><?= $totale_componenti ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-success py-2">
                <div class="card-body py-1">
                    <small class="text-muted d-block mb-1"><i class="fa-solid fa-circle-check text-success me-1"></i>Disponibili</small>
                    <h4 class="mb-0 text-success"><?= $disponibili ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-warning py-2">
                <div class="card-body py-1">
                    <small class="text-muted d-block mb-1"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Non/Parz.</small>
                    <h4 class="mb-0 text-warning"><?= $parzialmente_disponibili + $non_disponibili ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-info py-2">
                <div class="card-body py-1">
                    <small class="text-muted d-block mb-1"><i class="fa-solid fa-euro-sign text-info me-1"></i>Costo Tot.</small>
                    <h4 class="mb-0 text-info">€ <?= number_format($costo_totale, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
    </div>

    <?php if ($progetto['stato'] === 'bozza'): ?>
    <!-- Sezione Aggiunta Componenti (solo se bozza) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fa-solid fa-plus-circle me-2"></i>Aggiungi Componente</h5>
        </div>
        <div class="card-body">
            <form id="formAggiungiComponente">
                <input type="hidden" name="progetto_id" value="<?= $id ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Codice Componente *</label>
                        <div class="input-group">
                            <input type="text" id="codice_ricerca" class="form-control" placeholder="Cerca codice..." autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="btnCerca">
                                <i class="fa-solid fa-search"></i>
                            </button>
                        </div>
                        <div id="risultati_ricerca" class="list-group position-absolute w-100 shadow" style="z-index: 1000; display: none;"></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Quantità *</label>
                        <input type="number" name="quantita" class="form-control" value="1" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fornitore</label>
                        <select name="ks_fornitore" class="form-select">
                            <option value="">-- Seleziona --</option>
                            <?php foreach ($fornitori as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Prezzo (€)</label>
                        <input type="number" name="prezzo" class="form-control" step="0.01" placeholder="0.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Link Fornitore</label>
                        <input type="url" name="link_fornitore" class="form-control" placeholder="https://...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Note</label>
                        <input type="text" name="note" class="form-control" placeholder="Note opzionali...">
                    </div>
                    <div class="col-12">
                        <div id="info_componente" class="alert alert-info d-none">
                            <strong>Componente selezionato:</strong> <span id="comp_selezionato"></span>
                            <input type="hidden" name="ks_componente" id="ks_componente">
                        </div>
                        <div id="comp_non_trovato" class="alert alert-warning d-none">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>Componente non trovato in magazzino.
                            <button type="button" class="btn btn-sm btn-warning ms-2" data-bs-toggle="modal" data-bs-target="#modalNuovoComponente">
                                <i class="fa-solid fa-plus me-1"></i>Aggiungi a magazzino
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" id="btnAggiungi" disabled>
                            <i class="fa-solid fa-plus me-1"></i>Aggiungi al Progetto
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Import CSV (Collassabile) -->
    <div class="accordion mb-4" id="importCSVAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingImport">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseImport" aria-expanded="false" aria-controls="collapseImport">
                    <i class="fa-solid fa-file-csv me-2"></i><strong>Import Componenti da CSV</strong>
                </button>
            </h2>
            <div id="collapseImport" class="accordion-collapse collapse" aria-labelledby="headingImport" data-bs-parent="#importCSVAccordion">
                <div class="card-body border-top">
            <form id="formImportCSV" enctype="multipart/form-data">
                <input type="hidden" name="progetto_id" value="<?= $id ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">File CSV</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fa-solid fa-upload me-1"></i>Importa CSV
                        </button>
                        <a href="data:text/csv;charset=utf-8,codice_componente,quantita,prezzo,fornitore,note,link_fornitore%0ARes1k,10,0.50,RS%20Components,Resistenza%201k,https://example.com/r1%0ALM358,5,2.30,Mouser,Opamp,https://example.com/lm358%0ABC547,20,0.15,Digikey,Transistor%20NPN,https://example.com/bc547" download="esempio_import.csv" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-download me-1"></i>Scarica esempio
                        </a>
                    </div>
                    <div class="col-12">
                        <div class="form-text">
                            <strong>Formato CSV:</strong> codice_componente, quantità, prezzo, fornitore, note, link_fornitore<br>
                            <small class="text-muted">I campi prezzo, fornitore, note e link_fornitore sono facoltativi.</small>
                        </div>
                    </div>
                </div>
            </form>
            <div id="import_result" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Lista Componenti -->
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa-solid fa-boxes-stacked me-2"></i>Componenti del Progetto</h5>
            <?php if (!empty($componenti)): ?>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="analizzaProgetto()">
                        <i class="fa-solid fa-chart-pie me-1"></i>Analizza
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportCSV()">
                        <i class="fa-solid fa-file-export me-1"></i>Export CSV
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($componenti)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-box-open fa-3x mb-3"></i>
                    <p>Nessun componente inserito.</p>
                    <?php if ($progetto['stato'] === 'bozza'): ?>
                        <p class="small">Usa il form sopra per aggiungere componenti manualmente o importa da CSV.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover" id="componentiTable">
                        <thead class="table-light">
                            <tr>
                                <th>Codice</th>
                                <th>Costruttore</th>
                                <th class="text-center">Q.tà Richiesta</th>
                                <?php if ($progetto['stato'] === 'confermato' || $progetto['stato'] === 'completato'): ?>
                                <th class="text-center">Q.tà Scaricata</th>
                                <?php endif; ?>
                                <?php if ($progetto['stato'] === 'bozza'): ?>
                                <th class="text-center">Disponibilità</th>
                                <?php endif; ?>
                                <th class="text-end">Prezzo Unit.</th>
                                <th class="text-end">Totale</th>
                                <th>Fornitore</th>
                                <th class="text-center" style="width: 130px;">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($componenti as $comp): 
                                $prezzo = $comp['prezzo'] ?? $comp['comp_prezzo'] ?? 0;
                                $totale_riga = $prezzo * $comp['quantita'];
                                
                                // Stato disponibilità (in base allo stato del progetto)
                                if (!$comp['ks_componente']) {
                                    // Componente non più in magazzino (scaricato completamente)
                                    $disp_class = '';
                                    $disp_text = '<span class="badge bg-secondary">Scaricato</span>';
                                } elseif ($progetto['stato'] === 'confermato' || $progetto['stato'] === 'completato') {
                                    // In stato confermato/completato: basa il colore sulla quantità scaricata
                                    $qta_scaricata = $comp['quantita_scaricata'] ?? 0;
                                    if ($qta_scaricata >= $comp['quantita']) {
                                        $disp_class = '';
                                        $disp_text = '<span class="badge bg-success">OK</span>';
                                    } elseif ($qta_scaricata > 0) {
                                        $disp_class = 'table-warning';
                                        $disp_text = '<span class="badge bg-warning text-dark">Parziale</span>';
                                    } else {
                                        $disp_class = 'table-danger';
                                        $disp_text = '<span class="badge bg-danger">Non scaricato</span>';
                                    }
                                } else {
                                    // In stato bozza: basa il colore sulla disponibilità magazzino
                                    if ($comp['magazzino_qty'] >= $comp['quantita']) {
                                        $disp_class = '';
                                        $disp_text = '<span class="badge bg-success">' . $comp['magazzino_qty'] . ' disp.</span>';
                                    } elseif ($comp['magazzino_qty'] > 0) {
                                        $disp_class = 'table-warning';
                                        $disp_text = '<span class="badge bg-warning text-dark">' . $comp['magazzino_qty'] . '/' . $comp['quantita'] . '</span>';
                                    } else {
                                        $disp_class = 'table-danger';
                                        $disp_text = '<span class="badge bg-danger">Esaurito</span>';
                                    }
                                }
                            ?>
                                <tr class="<?= $disp_class ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($comp['codice_prodotto'] ?? $comp['codice_componente'] ?? 'N/A') ?></strong>
                                        <?php if ($comp['equivalents']): 
                                            $equiv = json_decode($comp['equivalents'], true);
                                            if ($equiv): ?>
                                                <br><small class="text-muted">Eq: <?= htmlspecialchars(implode(', ', $equiv)) ?></small>
                                            <?php endif; 
                                        endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($comp['costruttore'] ?? '—') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?= $comp['quantita'] ?></span>
                                    </td>
                                    <?php if ($progetto['stato'] === 'confermato' || $progetto['stato'] === 'completato'): ?>
                                    <td class="text-center">
                                        <?php if ($comp['quantita_scaricata'] !== null): 
                                            $isSufficiente = $comp['quantita_scaricata'] >= $comp['quantita'];
                                            $badgeClass = $isSufficiente ? 'bg-success' : 'bg-warning text-dark';
                                        ?>
                                            <span class="badge <?= $badgeClass ?>"><?= $comp['quantita_scaricata'] ?></span>
                                            <?php if ($progetto['stato'] === 'confermato'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary ms-1" 
                                                    onclick="modificaQuantitaScaricata(<?= $comp['id'] ?>, <?= $comp['quantita_scaricata'] ?>, <?= $comp['quantita'] ?>)"
                                                    title="Modifica quantità scaricata">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <?php if ($progetto['stato'] === 'bozza'): ?>
                                    <td class="text-center"><?= $disp_text ?></td>
                                    <?php endif; ?>
                                    <td class="text-end">
                                        <?php if ($prezzo > 0): ?>
                                            € <?= number_format($prezzo, 2, ',', '.') ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($totale_riga > 0): ?>
                                            <strong>€ <?= number_format($totale_riga, 2, ',', '.') ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($comp['fornitore_nome']): ?>
                                            <?= htmlspecialchars($comp['fornitore_nome']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                onclick="visualizzaComponente(<?= $comp['ks_componente'] ?: 'null' ?>, '<?= htmlspecialchars($comp['codice_prodotto'] ?? $comp['codice_componente'] ?? '') ?>', <?= $comp['id'] ?>)"
                                                title="Visualizza">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <?php if ($progetto['stato'] === 'bozza'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-modifica" 
                                                    data-id="<?= $comp['id'] ?>"
                                                    data-quantita="<?= $comp['quantita'] ?>"
                                                    data-prezzo="<?= $comp['prezzo'] ?? $comp['comp_prezzo'] ?? '' ?>"
                                                    data-link="<?= htmlspecialchars($comp['link_fornitore'] ?? '') ?>"
                                                    data-note="<?= htmlspecialchars($comp['note'] ?? '') ?>"
                                                    data-fornitore="<?= $comp['ks_fornitore'] ?? '' ?>"
                                                    title="Modifica">
                                                <i class="fa-solid fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-rimuovi" 
                                                    data-id="<?= $comp['id'] ?>" title="Rimuovi">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($progetto['stato'] === 'bozza' && !empty($componenti)): ?>
    <!-- Azioni Finali - Conferma -->
    <div class="card shadow-sm mt-4 border-warning">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1"><i class="fa-solid fa-check-circle me-2 text-warning"></i>Conferma Progetto</h5>
                    <p class="text-muted mb-0 small">Una volta confermato, verrà effettuato lo scarico dal magazzino e non sarà più possibile modificare i componenti.</p>
                </div>
                <button type="button" class="btn btn-warning btn-lg" onclick="confermaProgetto()">
                    <i class="fa-solid fa-check me-1"></i>Conferma e Scarica
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($progetto['stato'] === 'confermato'): ?>
    <!-- Azioni Finali - Completa -->
    <div class="card shadow-sm mt-4 border-success">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1"><i class="fa-solid fa-flag-checkered me-2 text-success"></i>Completa Progetto</h5>
                    <p class="text-muted mb-0 small">Segna il progetto come completato quando tutti i componenti sono stati utilizzati.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger" onclick="annullaConferma()">
                        <i class="fa-solid fa-rotate-left me-1"></i>Annulla (→ Bozza)
                    </button>
                    <button type="button" class="btn btn-success btn-lg" onclick="completaProgetto()">
                        <i class="fa-solid fa-check-double me-1"></i>Completa
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($progetto['stato'] === 'completato'): ?>
    <!-- Azioni Finali - Torna a Confermato -->
    <div class="card shadow-sm mt-4 border-info">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1"><i class="fa-solid fa-rotate-left me-2 text-info"></i>Modifica Stato</h5>
                    <p class="text-muted mb-0 small">Se necessario, puoi tornare allo stato precedente.</p>
                </div>
                <button type="button" class="btn btn-info" onclick="tornaAConfermato()">
                    <i class="fa-solid fa-backward-step me-1"></i>Torna a Confermato
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Barra di Avanzamento Stati -->
    <div class="card shadow-sm mt-4">
        <div class="card-body py-4">
            <div class="position-relative">
                <!-- Linea di connessione -->
                <div class="progress" style="height: 4px; position: absolute; top: 20px; left: 15%; width: 70%; z-index: 0;">
                    <?php if ($progetto['stato'] === 'bozza'): ?>
                        <div class="progress-bar bg-secondary" style="width: 0%"></div>
                    <?php elseif ($progetto['stato'] === 'confermato'): ?>
                        <div class="progress-bar bg-warning" style="width: 50%"></div>
                    <?php else: ?>
                        <div class="progress-bar bg-success" style="width: 100%"></div>
                    <?php endif; ?>
                </div>
                
                <!-- Step indicators -->
                <div class="d-flex justify-content-between position-relative" style="z-index: 1;">
                    <!-- Step 1: Bozza -->
                    <div class="text-center" style="width: 30%;">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" 
                             style="width: 44px; height: 44px; border: 3px solid <?= $progetto['stato'] === 'bozza' ? '#0d6efd' : ($progetto['stato'] === 'confermato' || $progetto['stato'] === 'completato' ? '#198754' : '#6c757d') ?>; 
                                    background: <?= $progetto['stato'] === 'bozza' ? '#e7f1ff' : ($progetto['stato'] === 'confermato' || $progetto['stato'] === 'completato' ? '#d1e7dd' : '#f8f9fa') ?>;">
                            <i class="fa-solid fa-pen-to-square <?= $progetto['stato'] === 'bozza' ? 'text-primary' : ($progetto['stato'] === 'confermato' || $progetto['stato'] === 'completato' ? 'text-success' : 'text-muted') ?>"></i>
                        </div>
                        <div class="small <?= $progetto['stato'] === 'bozza' ? 'fw-bold text-primary' : ($progetto['stato'] === 'confermato' || $progetto['stato'] === 'completato' ? 'text-success fw-bold' : 'text-muted') ?>">
                            Bozza
                            <?php if ($progetto['stato'] === 'bozza'): ?><br><span class="badge bg-primary mt-1">Attuale</span><?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Step 2: Confermato -->
                    <div class="text-center" style="width: 30%;">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" 
                             style="width: 44px; height: 44px; border: 3px solid <?= $progetto['stato'] === 'confermato' ? '#ffc107' : ($progetto['stato'] === 'completato' ? '#198754' : '#dee2e6') ?>; 
                                    background: <?= $progetto['stato'] === 'confermato' ? '#fff3cd' : ($progetto['stato'] === 'completato' ? '#d1e7dd' : '#f8f9fa') ?>;">
                            <i class="fa-solid fa-check <?= $progetto['stato'] === 'confermato' ? 'text-warning' : ($progetto['stato'] === 'completato' ? 'text-success' : 'text-muted') ?>"></i>
                        </div>
                        <div class="small <?= $progetto['stato'] === 'confermato' ? 'fw-bold text-warning' : ($progetto['stato'] === 'completato' ? 'text-success fw-bold' : 'text-muted') ?>">
                            Confermato
                            <?php if ($progetto['stato'] === 'confermato'): ?><br><span class="badge bg-warning text-dark mt-1">Attuale</span><?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Step 3: Completato -->
                    <div class="text-center" style="width: 30%;">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" 
                             style="width: 44px; height: 44px; border: 3px solid <?= $progetto['stato'] === 'completato' ? '#198754' : '#dee2e6' ?>; 
                                    background: <?= $progetto['stato'] === 'completato' ? '#d1e7dd' : '#f8f9fa' ?>;">
                            <i class="fa-solid fa-flag-checkered <?= $progetto['stato'] === 'completato' ? 'text-success' : 'text-muted' ?>"></i>
                        </div>
                        <div class="small <?= $progetto['stato'] === 'completato' ? 'fw-bold text-success' : 'text-muted' ?>">
                            Completato
                            <?php if ($progetto['stato'] === 'completato'): ?><br><span class="badge bg-success mt-1">Attuale</span><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuovo Componente -->
<div class="modal fade" id="modalNuovoComponente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-plus me-2"></i>Aggiungi Componente a Magazzino</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body">
                <p>Il componente "<strong id="codice_da_aggiungere"></strong>" non esiste in magazzino.</p>
                <p>Vuoi aggiungerlo ora? Verrai reindirizzato alla pagina di inserimento componenti.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <a href="#" id="linkNuovoComponente" class="btn btn-primary" target="_blank" onclick="aggiornaLinkNuovoComponente()">
                    <i class="fa-solid fa-plus me-1"></i>Aggiungi a Magazzino
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Modifica Componente -->
<div class="modal fade" id="modalModifica" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-edit me-2"></i>Modifica Componente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <form id="formModificaComponente">
                <div class="modal-body">
                    <input type="hidden" name="id" id="mod_id">
                    <div class="mb-3">
                        <label class="form-label">Quantità *</label>
                        <input type="number" name="quantita" id="mod_quantita" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fornitore</label>
                        <select name="ks_fornitore" id="mod_fornitore" class="form-select">
                            <option value="">-- Seleziona --</option>
                            <?php foreach ($fornitori as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prezzo (€)</label>
                        <input type="number" name="prezzo" id="mod_prezzo" class="form-control" step="0.01" placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Link Fornitore</label>
                        <input type="url" name="link_fornitore" id="mod_link" class="form-control" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <textarea name="note" id="mod_note" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-1"></i>Salva Modifiche
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Visualizza Componente -->
<div class="modal fade" id="modalVisualizza" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-eye me-2"></i>Dettagli Componente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body" id="contenutoVisualizza">
                Caricamento...
            </div>
        </div>
    </div>
</div>

<!-- Modal Analisi -->
<div class="modal fade" id="modalAnalisi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-chart-pie me-2"></i>Analisi Progetto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body" id="contenutoAnalisi">
                <!-- Contenuto caricato via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                <a href="report_progetto.php?id=<?= $id ?>" target="_blank" class="btn btn-primary">
                    <i class="fa-solid fa-file-pdf me-1"></i>Salva Report (PDF)
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Ricerca componenti
let timeoutRicerca;
document.getElementById('codice_ricerca').addEventListener('input', function() {
    clearTimeout(timeoutRicerca);
    const codice = this.value.trim();
    
    if (codice.length < 2) {
        document.getElementById('risultati_ricerca').style.display = 'none';
        return;
    }
    
    timeoutRicerca = setTimeout(() => {
        fetch(`ajax_cerca_componente.php?q=${encodeURIComponent(codice)}`)
            .then(r => r.json())
            .then(data => {
                const div = document.getElementById('risultati_ricerca');
                div.innerHTML = '';
                
                if (data.length === 0) {
                    div.style.display = 'none';
                    document.getElementById('comp_non_trovato').classList.remove('d-none');
                    document.getElementById('info_componente').classList.add('d-none');
                    document.getElementById('btnAggiungi').disabled = true;
                    document.getElementById('codice_da_aggiungere').textContent = codice;
                    
                    // Costruisci URL con tutti i parametri del form
                    const params = new URLSearchParams();
                    params.append('codice', codice);
                    
                    // Recupera i valori dal form
                    const prezzo = document.querySelector('input[name="prezzo"]').value;
                    const fornitore = document.querySelector('select[name="ks_fornitore"]').value;
                    const note = document.querySelector('input[name="note"]').value;
                    const link_fornitore = document.querySelector('input[name="link_fornitore"]').value;
                    
                    if (prezzo) params.append('prezzo', prezzo);
                    if (fornitore) params.append('fornitore', fornitore);
                    if (note) params.append('note', note);
                    if (link_fornitore) params.append('link_fornitore', link_fornitore);
                    
                    const finalUrl = `../add_component.php?${params.toString()}`;
                    document.getElementById('linkNuovoComponente').href = finalUrl;
                } else {
                    document.getElementById('comp_non_trovato').classList.add('d-none');
                    data.forEach(comp => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action';
                        item.innerHTML = `<strong>${comp.codice_prodotto}</strong> ${comp.costruttore ? '- ' + comp.costruttore : ''} <span class="badge bg-secondary float-end">${comp.quantity} disp.</span>`;
                        item.onclick = () => selezionaComponente(comp);
                        div.appendChild(item);
                    });
                    div.style.display = 'block';
                }
            });
    }, 300);
});

function selezionaComponente(comp) {
    document.getElementById('codice_ricerca').value = comp.codice_prodotto;
    document.getElementById('ks_componente').value = comp.id;
    document.getElementById('comp_selezionato').textContent = `${comp.codice_prodotto} (${comp.quantity} disponibili)`;
    document.getElementById('risultati_ricerca').style.display = 'none';
    document.getElementById('info_componente').classList.remove('d-none');
    document.getElementById('comp_non_trovato').classList.add('d-none');
    document.getElementById('btnAggiungi').disabled = false;
    
    // Precompila il prezzo se disponibile
    if (comp.prezzo && comp.prezzo > 0) {
        document.querySelector('input[name="prezzo"]').value = comp.prezzo;
    }
    // Seleziona il fornitore se presente
    if (comp.fornitore) {
        const fornitoreSelect = document.querySelector('select[name="ks_fornitore"]');
        const fornitoreNome = comp.fornitore.toLowerCase().trim();
        
        // Cerca tra le opzioni della select
        for (let i = 0; i < fornitoreSelect.options.length; i++) {
            const optionText = fornitoreSelect.options[i].text.toLowerCase().trim();
            if (optionText === fornitoreNome || 
                optionText.includes(fornitoreNome) || 
                fornitoreNome.includes(optionText)) {
                fornitoreSelect.selectedIndex = i;
                break;
            }
        }
    }

}

// Funzione per visualizzare componente (chiamata da onclick inline)
function visualizzaComponente(id, codice, progettoComponenteId = null) {
    $('#contenutoVisualizza').html('Caricamento...');
    new bootstrap.Modal(document.getElementById('modalVisualizza')).show();
    
    if (!id) {
        // Componente non più in magazzino, mostra info base
        $('#contenutoVisualizza').html(`
            <div class="alert alert-warning">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <strong>Componente non più disponibile in magazzino</strong>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <tr><th>Codice</th><td>${codice || 'N/A'}</td></tr>
                    <tr><th>Stato</th><td>Scaricato dal magazzino</td></tr>
                </table>
            </div>`);
        return;
    }
    
    // Costruisci URL con progetto_id se disponibile
    let url = `../view_component.php?id=${id}`;
    if (progettoComponenteId) {
        url += `&progetto_componente_id=${progettoComponenteId}`;
    }
    
    $.get(url)
        .done(function(html) {
            // Aggiungi ID del componente all'inizio
            $('#contenutoVisualizza').html(`
                <div class="alert alert-secondary py-1 mb-2">
                    <small><strong>ID Componente:</strong> ${id}</small>
                </div>
                ${html}
            `);
        })
        .fail(function() {
            $('#contenutoVisualizza').html('<div class="alert alert-danger">Errore durante il caricamento</div>');
        });
}

// Import CSV
document.getElementById('formImportCSV').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const div = document.getElementById('import_result');
    
    div.innerHTML = '<div class="alert alert-info"><i class="fa-solid fa-spinner fa-spin me-2"></i>Import in corso...</div>';
    
    fetch('ajax_import_csv.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            let html = `<div class="alert alert-success alert-dismissible fade show" id="importReportAlert">
                <button type="button" class="btn-close" data-bs-dismiss="alert" onclick="location.reload()"></button>
                <strong>Import completato!</strong><br>
                <i class="fa-solid fa-check-circle text-success me-2"></i>Aggiunti: ${data.aggiunti}`;
            
            // Mostra elenco componenti aggiunti
            if (data.aggiunti_list && data.aggiunti_list.length > 0) {
                html += `<div class="mt-2"><strong>Componenti aggiunti:</strong><ul class="mb-0 mt-1">`;
                data.aggiunti_list.forEach(comp => {
                    html += `<li><strong>${comp.codice}</strong> (q.tà ${comp.quantita})</li>`;
                });
                html += `</ul></div>`;
            }
            
            // Mostra componenti duplicati
            if (data.duplicati && data.duplicati.length > 0) {
                html += `<hr><div class="alert alert-info mb-0">
                    <strong><i class="fa-solid fa-info-circle me-2"></i>Componenti già presenti nel progetto (ignorati):</strong><br>
                    <ul class="mb-0 mt-2">`;
                data.duplicati.forEach(dup => {
                    html += `<li><strong>${dup.codice}</strong> (q.tà ${dup.quantita})</li>`;
                });
                html += `</ul></div>`;
            }
            
            // Mostra componenti non trovati con bottoni
            if (data.non_trovati.length > 0) {
                html += `<hr><div class="alert alert-warning mb-0">
                    <strong><i class="fa-solid fa-triangle-exclamation me-2"></i>Componenti non trovati in magazzino:</strong><br>
                    <ul class="mb-0 mt-2">`;
                data.non_trovati.forEach(comp => {
                    const codice = comp.codice || comp;
                    const params = new URLSearchParams();
                    params.append('codice', codice);
                    if (comp.prezzo) params.append('prezzo', comp.prezzo);
                    if (comp.fornitore) params.append('fornitore', comp.fornitore);
                    if (comp.note) params.append('note', comp.note);
                    if (comp.link_fornitore) params.append('link_fornitore', comp.link_fornitore);
                    
                    html += `<li class="mb-2" id="comp-${codice}">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <strong>${codice}</strong> <span class="text-muted">(q.tà ${comp.quantita})</span>
                            <a href="../add_component.php?${params.toString()}" target="_blank" class="btn btn-sm btn-warning">
                                <i class="fa-solid fa-plus me-1"></i>Aggiungi a magazzino
                            </a>
                            <button type="button" class="btn btn-sm btn-success btn-aggiungi-progetto" 
                                    data-codice="${codice}" 
                                    data-quantita="${comp.quantita}"
                                    data-prezzo="${comp.prezzo || ''}"
                                    data-fornitore="${comp.fornitore || ''}"
                                    data-note="${comp.note || ''}"
                                    data-link="${comp.link_fornitore || ''}">
                                <i class="fa-solid fa-check me-1"></i>Aggiungi al progetto
                            </button>
                        </div>
                    </li>`;
                });
                html += `</ul></div>`;
            }
            html += `</div>`;
            div.innerHTML = html;
            
            // Aggiungi gestore click per bottoni "Aggiungi al progetto"
            document.querySelectorAll('.btn-aggiungi-progetto').forEach(btn => {
                btn.addEventListener('click', function() {
                    const codice = this.dataset.codice;
                    const btnElement = this;
                    
                    const formData = new FormData();
                    formData.append('progetto_id', <?= $id ?>);
                    formData.append('codice', codice);
                    formData.append('quantita', this.dataset.quantita);
                    formData.append('prezzo', this.dataset.prezzo);
                    formData.append('fornitore', this.dataset.fornitore);
                    formData.append('note', this.dataset.note);
                    formData.append('link_fornitore', this.dataset.link);
                    
                    btnElement.disabled = true;
                    btnElement.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Aggiungendo...';
                    
                    fetch('ajax_aggiungi_al_progetto.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            btnElement.className = 'btn btn-sm btn-secondary';
                            btnElement.innerHTML = '<i class="fa-solid fa-check me-1"></i>Aggiunto!';
                            // Rimuovi dopo 1 secondo
                            setTimeout(() => {
                                const li = document.getElementById(`comp-${codice}`);
                                if (li) li.style.display = 'none';
                                
                                // Conta quanti componenti non trovati rimangono visibili
                                const listaNonTrovati = document.querySelectorAll('[id^="comp-"]');
                                const visibili = Array.from(listaNonTrovati).filter(el => el.style.display !== 'none');
                                
                                // Se non ce ne sono più, ricarica la pagina
                                if (visibili.length === 0) {
                                    location.reload();
                                }
                            }, 1000);
                        } else {
                            alert(data.error);
                            btnElement.disabled = false;
                            btnElement.innerHTML = '<i class="fa-solid fa-check me-1"></i>Aggiungi al progetto';
                        }
                    })
                    .catch(err => {
                        alert('Errore: ' + err.message);
                        btnElement.disabled = false;
                        btnElement.innerHTML = '<i class="fa-solid fa-check me-1"></i>Aggiungi al progetto';
                    });
                });
            });
        } else {
            div.innerHTML = `<div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                ${data.error}
            </div>`;
        }
    })
    .catch(err => {
        div.innerHTML = `<div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            Errore: ${err.message}
        </div>`;
    });
});

// Aggiungi componente al progetto
document.getElementById('formAggiungiComponente').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('ajax_aggiungi_componente.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Errore durante l\'aggiunta');
        }
    });
});

// Modifica componente
document.querySelectorAll('.btn-modifica').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('mod_id').value = this.dataset.id;
        document.getElementById('mod_quantita').value = this.dataset.quantita;
        document.getElementById('mod_prezzo').value = this.dataset.prezzo;
        document.getElementById('mod_link').value = this.dataset.link;
        document.getElementById('mod_note').value = this.dataset.note;
        document.getElementById('mod_fornitore').value = this.dataset.fornitore;
        
        new bootstrap.Modal(document.getElementById('modalModifica')).show();
    });
});

// Salva modifiche componente
document.getElementById('formModificaComponente').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('ajax_modifica_componente.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Errore durante la modifica');
        }
    });
});

// Rimuovi componente
document.querySelectorAll('.btn-rimuovi').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!confirm('Sei sicuro di voler rimuovere questo componente dal progetto?')) return;
        
        const id = this.dataset.id;
        fetch(`ajax_rimuovi_componente.php?id=${id}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Errore durante la rimozione');
                }
            });
    });
});

// Analisi progetto
function analizzaProgetto() {
    fetch(`ajax_analisi.php?id=<?= $id ?>`)
        .then(r => r.text())
        .then(html => {
            document.getElementById('contenutoAnalisi').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalAnalisi')).show();
        });
}

// Export CSV
function exportCSV() {
    window.location.href = `ajax_export_csv.php?id=<?= $id ?>`;
}

// Conferma progetto
function confermaProgetto() {
    if (!confirm('ATTENZIONE: Confermando il progetto verrà effettuato lo scarico dal magazzino.\n\nContinuare?')) {
        return;
    }
    
    // Aggiungi timestamp per evitare cache
    const timestamp = new Date().getTime();
    fetch(`ajax_conferma.php?id=<?= $id ?>&_=${timestamp}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Progetto confermato!\n\n' + data.report);
                location.reload();
            } else {
                alert(data.error || 'Errore durante la conferma');
            }
        });
}

// Completa progetto
function completaProgetto() {
    if (!confirm('Confermi di voler completare il progetto?\n\nQuesta azione segnerà il progetto come terminato.')) {
        return;
    }
    
    fetch(`ajax_completa.php?id=<?= $id ?>`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Progetto completato con successo!');
                location.reload();
            } else {
                alert(data.error || 'Errore durante il completamento');
            }
        });
}

// Torna a Confermato (da Completato)
function tornaAConfermato() {
    if (!confirm('Vuoi tornare allo stato "Confermato"?\n\nIl progetto non sarà più marcato come terminato.')) {
        return;
    }
    
    fetch(`ajax_torna_confermato.php?id=<?= $id ?>`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Stato aggiornato a "Confermato"');
                location.reload();
            } else {
                alert(data.error || 'Errore durante l\'aggiornamento');
            }
        });
}

// Annulla conferma (torna a Bozza e ricarica magazzino)
function annullaConferma() {
    if (!confirm('ATTENZIONE: Vuoi tornare allo stato "Bozza"?\n\nLe quantità verranno ricaricate in magazzino e potrai modificare nuovamente il progetto.')) {
        return;
    }
    
    fetch(`ajax_annulla_conferma.php?id=<?= $id ?>`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let messaggio = 'Progetto tornato in "Bozza"\n\n';
                messaggio += 'Componenti caricati: ' + data.componenti_ricaricati + '\n';
                messaggio += 'Dettaglio carico:\n';
                
                if (data.dettaglio_carico && data.dettaglio_carico.length > 0) {
                    data.dettaglio_carico.forEach(item => {
                        messaggio += item.codice + ' (+' + item.quantita + ')\n';
                    });
                }
                
                alert(messaggio);
                location.reload();
            } else {
                alert(data.error || 'Errore durante l\'annullamento');
            }
        });
}

// Modifica quantità scaricata
function modificaQuantitaScaricata(id, attuale, max) {
    const nuovaQuantita = prompt(`Modifica quantità scaricata:\n\nAttuale: ${attuale}\nMassimo consentito: ${max}\n\nInserisci la nuova quantità (0-${max}):`, attuale);
    
    if (nuovaQuantita === null) return; // Utente ha premuto Annulla
    
    const qta = parseInt(nuovaQuantita);
    if (isNaN(qta) || qta < 0 || qta > max) {
        alert('Quantità non valida. Inserisci un numero tra 0 e ' + max);
        return;
    }
    
    fetch('ajax_modifica_qta_scaricata.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${id}&quantita=${qta}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Errore durante la modifica');
        }
    })
    .catch(err => {
        alert('Errore: ' + err.message);
    });
}

// Aggiorna link "Aggiungi a Magazzino" nel modal con i valori attuali del form
function aggiornaLinkNuovoComponente() {
    const codice = document.getElementById('codice_ricerca').value.trim();
    if (!codice) return;
    
    // Costruisci URL con tutti i parametri del form
    const params = new URLSearchParams();
    params.append('codice', codice);
    
    // Recupera i valori attuali dal form
    const prezzo = document.querySelector('input[name="prezzo"]').value;
    const fornitoreSelect = document.querySelector('select[name="ks_fornitore"]');
    const fornitoreId = fornitoreSelect.value;
    // Trova il nome del fornitore dall'option selezionata
    const fornitoreNome = fornitoreId ? fornitoreSelect.options[fornitoreSelect.selectedIndex].text : '';
    const note = document.querySelector('input[name="note"]').value;
    const link_fornitore = document.querySelector('input[name="link_fornitore"]').value;
    
    if (prezzo) params.append('prezzo', prezzo);
    if (fornitoreNome && fornitoreNome !== '-- Seleziona --') params.append('fornitore', fornitoreNome);
    if (note) params.append('note', note);
    if (link_fornitore) params.append('link_fornitore', link_fornitore);
    
    const finalUrl = `../add_component.php?${params.toString()}`;
    
    // Aggiorna l'href e permetti la navigazione
    const link = document.getElementById('linkNuovoComponente');
    link.href = finalUrl;
}

// Stampa report
function stampaReport() {
    window.print();
}

// Chiudi risultati ricerca quando si clicca fuori
document.addEventListener('click', function(e) {
    if (!e.target.closest('#codice_ricerca') && !e.target.closest('#risultati_ricerca')) {
        document.getElementById('risultati_ricerca').style.display = 'none';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
