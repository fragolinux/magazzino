<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 18:37:30 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2025-10-20 18:37:30
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: categories.php");
    exit;
}

$id = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    header("Location: categories.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    if ($name === '') {
        $error = "Il nome della categoria è obbligatorio.";
    } else {
        $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        header("Location: categories.php");
        exit;
    }
}

include '../includes/header.php';
?>

<div class="container py-4">
    <h2><i class="fa-solid fa-pen me-2"></i>Modifica Categoria</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm p-4">
        <div class="mb-3">
            <label class="form-label">Nome categoria</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($category['name']) ?>" required>
        </div>
        <div class="text-end">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Salva modifiche</button>
            <a href="categories.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Annulla</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>