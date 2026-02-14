<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: Gestione Progetti - Lista
 */

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
                                <th class="text-center" style="width: 180px;">Azioni</th>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
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
});
</script>

<?php include '../../includes/footer.php'; ?>
