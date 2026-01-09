<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:27:12 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-08 13:56:21
*/
// 2026-01-08: Aggiunto assegnazione di una posizione a un locale

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php'; // Verifica login

// Verifica parametro ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: locations.php");
    exit;
}

$id = intval($_GET['id']);

// Recupero dati esistenti
$stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
$stmt->execute([$id]);
$location = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$location) {
    header("Location: locations.php?notfound=1");
    exit;
}

// Recupero locali per il select
$locali = $pdo->query("SELECT id, name FROM locali ORDER BY name ASC")->fetchAll();

// Aggiornamento dati
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_location'])) {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $description = trim($_POST['description']);
    $locale_id = isset($_POST['locale_id']) && is_numeric($_POST['locale_id']) && $_POST['locale_id'] !== '' ? intval($_POST['locale_id']) : null;

    if ($name !== '') {
        if ($locale_id === null) {
            $error = "Il locale è obbligatorio.";
        } else {
            $stmt = $pdo->prepare("UPDATE locations SET name = ?, type = ?, description = ?, locale_id = ? WHERE id = ?");
            $stmt->execute([$name, $type, $description, $locale_id, $id]);
            header("Location: locations.php?updated=1");
            exit;
        }
    } else {
        $error = "Il nome della posizione è obbligatorio.";
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fa-solid fa-pen-to-square me-2"></i>Modifica posizione</h2>
        <a href="locations.php" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Torna alla lista
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm p-4">
        <div class="mb-3">
            <label class="form-label">Locale *</label>
            <select name="locale_id" class="form-select" required>
                <option value="">-- Seleziona locale --</option>
                <?php foreach ($locali as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= ($location['locale_id'] == $loc['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($loc['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Nome posizione *</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($location['name']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Tipo</label>
            <select name="type" class="form-select">
                <option value="scaffale" <?= $location['type'] === 'scaffale' ? 'selected' : '' ?>>Scaffale</option>
                <option value="cassettiera" <?= $location['type'] === 'cassettiera' ? 'selected' : '' ?>>Cassettiera</option>
                <option value="scatola" <?= $location['type'] === 'scatola' ? 'selected' : '' ?>>Scatola</option>
                <option value="altro" <?= $location['type'] === 'altro' ? 'selected' : '' ?>>Altro</option>
                <option value="valigetta" <?= $location['type'] === 'valigetta' ? 'selected' : '' ?>>Valigetta</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Descrizione</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($location['description']) ?></textarea>
        </div>

        <div class="text-end">
            <button type="submit" name="update_location" class="btn btn-primary">
                <i class="fa-solid fa-save"></i> Salva modifiche
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>