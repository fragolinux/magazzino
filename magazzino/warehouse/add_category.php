<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 18:36:55 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-23 14:15:26
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    if ($name === '') {
        $error = "Il nome della categoria è obbligatorio.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);
        header("Location: categories.php");
        exit;
    }
}

include '../includes/header.php';
?>

<div class="container py-4">
    <h2><i class="fa-solid fa-plus me-2"></i>Aggiungi Categoria</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm p-4">
        <div class="mb-3">
            <label class="form-label">Nome categoria</label>
            <input type="text" name="name" class="form-control" autofocus required>
        </div>
        <div class="text-end">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Salva</button>
            <a href="categories.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Annulla</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>