<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: Gestione Progetti - Lista
 */

// 2026-02-19: Aggiunto bottone info progetto con modal e visualizzazione file locali

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

// Verifica permessi admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

// Messaggi
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Recupera progetti con conteggio componenti e costo totale
$sql = "SELECT p.*, 
               COUNT(pc.id) as num_componenti,
               COALESCE(SUM(pc.quantita * COALESCE(pc.prezzo, c.prezzo, 0)), 0) as costo_totale
        FROM progetti p 
        LEFT JOIN progetti_componenti pc ON p.id = pc.ks_progetto 
        LEFT JOIN components c ON pc.ks_componente = c.id
        GROUP BY p.id 
        ORDER BY p.created_at DESC";
$progetti = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-clipboard-list me-2"></i>Gestione Progetti</h2>
        <a href="progetto_nuovo.php" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>Nuovo Progetto
        </a>
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

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($progetti)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-clipboard-list fa-3x mb-3"></i>
                    <p>Nessun progetto registrato.</p>
                    <a href="progetto_nuovo.php" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-plus me-1"></i>Crea il primo progetto
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="progettiTable">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>Num. Ordine</th>
                                <th>Stato</th>
                                <th class="text-center">Componenti</th>
                                <th class="text-end">Costo Totale</th>
                                <th>Data Creazione</th>
                                <th class="text-center" style="width: 200px;">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($progetti as $p): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($p['nome']) ?></strong>
                                        <?php if ($p['descrizione']): ?>
                                            <br><small class="text-muted text-truncate d-inline-block" style="max-width: 250px;">
                                                <?= htmlspecialchars($p['descrizione']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($p['numero_ordine']): ?>
                                            <code><?= htmlspecialchars($p['numero_ordine']) ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= getStatoBadge($p['stato']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?= $p['num_componenti'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($p['costo_totale'] > 0): ?>
                                            <strong>€ <?= number_format($p['costo_totale'], 2, ',', '.') ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-info text-white btn-info-progetto" 
                                                data-id="<?= $p['id'] ?>" title="Info progetto">
                                            <i class="fa-solid fa-circle-info"></i>
                                        </button>
                                        <a href="progetto_view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Visualizza/Modifica componenti">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="progetto_edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Modifica dati progetto">
                                            <i class="fa-solid fa-edit"></i>
                                        </a>
                                        <?php if ($p['stato'] === 'bozza'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete" 
                                                    data-id="<?= $p['id'] ?>" data-nome="<?= htmlspecialchars($p['nome']) ?>" title="Elimina">
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
</div>

<!-- Modal Info Progetto -->
<div class="modal fade" id="infoProgettoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-circle-info me-2 text-info"></i>Info Progetto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Contenuto caricato dinamicamente -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Caricamento...</span>
                    </div>
                    <p class="mt-2">Caricamento informazioni...</p>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="btnGestisciComponenti" class="btn btn-outline-info">
                    <i class="fa-solid fa-boxes-stacked me-1"></i>Gestisci Componenti
                </a>
                <a href="#" id="btnModifica" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-edit me-1"></i>Modifica
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const infoModal = new bootstrap.Modal(document.getElementById('infoProgettoModal'));
    
    // Gestione eliminazione
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const nome = this.dataset.nome;
            
            if (confirm(`Sei sicuro di voler eliminare il progetto "${nome}"?\n\nQuesta azione non può essere annullata.`)) {
                window.location.href = `progetto_delete.php?id=${id}`;
            }
        });
    });
    
    // Gestione bottone info
    document.querySelectorAll('.btn-info-progetto').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            const modalBody = document.getElementById('modalBody');
            
            // Mostra loading
            modalBody.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Caricamento...</span>
                    </div>
                    <p class="mt-2">Caricamento informazioni...</p>
                </div>
            `;
            
            // Aggiorna link bottoni
            document.getElementById('btnGestisciComponenti').href = `progetto_view.php?id=${id}`;
            document.getElementById('btnModifica').href = `progetto_edit.php?id=${id}`;
            
            // Apri modal
            infoModal.show();
            
            // Carica dati
            try {
                const response = await fetch(`ajax_progetto_info.php?id=${id}`);
                const data = await response.json();
                
                if (data.error) {
                    modalBody.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                    return;
                }
                
                const p = data.progetto;
                
                // Costruisci HTML
                let html = `
                    <div class="row g-3">
                        <div class="col-md-8">
                            <h4 class="mb-3">${escapeHtml(p.nome)}</h4>
                        </div>
                        <div class="col-md-4 text-md-end">
                            ${getStatoBadge(p.stato)}
                        </div>
                        
                        ${p.numero_ordine ? `
                        <div class="col-12">
                            <label class="text-muted small">Numero Ordine</label>
                            <p class="mb-0"><code>${escapeHtml(p.numero_ordine)}</code></p>
                        </div>` : ''}
                        
                        ${p.descrizione ? `
                        <div class="col-12">
                            <label class="text-muted small">Descrizione</label>
                            <p class="mb-0">${escapeHtml(p.descrizione).replace(/\n/g, '<br>')}</p>
                        </div>` : ''}
                        
                        ${p.note ? `
                        <div class="col-12">
                            <label class="text-muted small">Note</label>
                            <div class="alert alert-light border">
                                ${escapeHtml(p.note).replace(/\n/g, '<br>')}
                            </div>
                        </div>` : ''}
                        
                        <div class="col-md-6">
                            <label class="text-muted small">Componenti</label>
                            <p class="mb-0"><span class="badge bg-info">${data.num_componenti}</span></p>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="text-muted small">Costo Totale</label>
                            <p class="mb-0"><strong>${data.costo_totale > 0 ? '€ ' + formatCurrency(data.costo_totale) : '—'}</strong></p>
                        </div>
                        
                        ${(data.link_web && data.link_web.length > 0) || (data.link_locali && data.link_locali.length > 0) ? `
                        <div class="col-12 mt-3">
                            <div class="row g-3">
                                ${data.link_web && data.link_web.length > 0 ? `
                                <div class="col-md-6">
                                    <div class="card border-primary h-100">
                                        <div class="card-header bg-primary text-white py-2">
                                            <i class="fa-solid fa-globe me-2"></i>Link Web
                                        </div>
                                        <ul class="list-group list-group-flush">
                                            ${data.link_web.map(link => `
                                                <li class="list-group-item py-2">
                                                    <a href="${escapeHtml(link.url)}" target="_blank" class="text-decoration-none">
                                                        <i class="fa-solid fa-external-link-alt me-2 text-primary"></i>
                                                        ${link.descrizione ? escapeHtml(link.descrizione) : escapeHtml(link.url)}
                                                    </a>
                                                </li>
                                            `).join('')}
                                        </ul>
                                    </div>
                                </div>` : ''}
                                
                                ${data.link_locali && data.link_locali.length > 0 ? `
                                <div class="col-md-6">
                                    <div class="card border-success h-100">
                                        <div class="card-header bg-success text-white py-2">
                                            <i class="fa-solid fa-folder-open me-2"></i>Cartelle Locali
                                        </div>
                                        <ul class="list-group list-group-flush">
                                            ${data.link_locali.map(link => `
                                                <li class="list-group-item py-2">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <code class="small d-block">${escapeHtml(link.path)}</code>
                                                            ${link.descrizione ? `<small class="text-muted">${escapeHtml(link.descrizione)}</small>` : ''}
                                                        </div>
                                                        <a href="progetto_cartella.php?path=${encodeURIComponent(link.path)}&progetto_id=${p.id}" 
                                                           class="btn btn-sm btn-outline-success ms-2" title="Apri cartella"
                                                           target="_blank">
                                                            <i class="fa-solid fa-folder-open"></i>
                                                        </a>
                                                    </div>
                                                </li>
                                            `).join('')}
                                        </ul>
                                    </div>
                                </div>` : ''}
                            </div>
                        </div>` : ''}
                        
                        <div class="col-12 text-muted small">
                            <hr class="my-2">
                            <span>Creato: ${formatDate(p.created_at)}</span>
                            ${p.updated_at && p.updated_at !== p.created_at ? `<span class="ms-3">Aggiornato: ${formatDate(p.updated_at)}</span>` : ''}
                        </div>
                    </div>
                `;
                
                modalBody.innerHTML = html;
                
            } catch (err) {
                modalBody.innerHTML = `<div class="alert alert-danger">Errore nel caricamento: ${err.message}</div>`;
            }
        });
    });
    
    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function getStatoBadge(stato) {
        const badges = {
            'bozza': '<span class="badge bg-secondary">Bozza</span>',
            'confermato': '<span class="badge bg-warning text-dark">Confermato</span>',
            'completato': '<span class="badge bg-success">Completato</span>'
        };
        return badges[stato] || `<span class="badge bg-light text-dark">${stato}</span>`;
    }
    
    function formatCurrency(val) {
        return parseFloat(val).toLocaleString('it-IT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    function formatDate(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleString('it-IT');
    }
    
});
</script>

<?php include '../../includes/footer.php'; ?>
