<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: Gestione Fornitori - Lista
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

// Recupera fornitori
$fornitori = $pdo->query("SELECT * FROM fornitori ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-truck-field me-2"></i>Gestione Fornitori</h2>
        <a href="fornitore_nuovo.php" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>Nuovo Fornitore
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
            <?php if (empty($fornitori)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-truck-field fa-3x mb-3"></i>
                    <p>Nessun fornitore registrato.</p>
                    <a href="fornitore_nuovo.php" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-plus me-1"></i>Aggiungi il primo fornitore
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="fornitoriTable">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>Link</th>
                                <th>Note</th>
                                <th class="text-center" style="width: 150px;">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fornitori as $f): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($f['nome']) ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($f['link']): ?>
                                            <a href="<?= htmlspecialchars($f['link']) ?>" target="_blank" class="text-truncate d-inline-block" style="max-width: 250px;">
                                                <i class="fa-solid fa-external-link-alt me-1"></i><?= htmlspecialchars($f['link']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($f['note']): ?>
                                            <span class="text-truncate d-inline-block" style="max-width: 300px;" title="<?= htmlspecialchars($f['note']) ?>">
                                                <?= htmlspecialchars($f['note']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="fornitore_edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifica">
                                            <i class="fa-solid fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete" 
                                                data-id="<?= $f['id'] ?>" data-nome="<?= htmlspecialchars($f['nome']) ?>" title="Elimina">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
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
            
            if (confirm(`Sei sicuro di voler eliminare il fornitore "${nome}"?\n\nAttenzione: questo potrebbe influire sui progetti esistenti.`)) {
                window.location.href = `fornitore_delete.php?id=${id}`;
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
