<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 18:36:21 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-21 09:50:55
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$error = '';
$success = '';

// Cancellazione
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Categoria eliminata con successo.";
}

// Recupero categorie
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fa-solid fa-tags me-2"></i>Categorie</h2>
        <a href="add_category.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Aggiungi categoria</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>Nome</th>
                    <th class="text-end">Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$categories): ?>
                    <tr><td colspan="2" class="text-center text-muted">Nessuna categoria presente.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td class="text-end">
                                <a href="edit_category.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-pen"></i></a>
                                <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Sei sicuro di eliminare <?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>?')) window.location='categories.php?delete=<?= $cat['id'] ?>';"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>