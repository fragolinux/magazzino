<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 19:53:17 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-20 23:19:26
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Controllo parametro ID valido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: compartments.php");
    exit;
}

$id = intval($_GET['id']);

// Recupero compartimento
$stmt = $pdo->prepare("SELECT * FROM compartments WHERE id = ?");
$stmt->execute([$id]);
$compartment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compartment) {
    header("Location: compartments.php");
    exit;
}

$location_id = intval($compartment['location_id']);

// Recupero tutte le posizioni per il dropdown
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $new_location_id = intval($_POST['location_id']);
    $description = trim($_POST['description']);

    if ($code === '') {
        $error = "Il codice del compartimento è obbligatorio.";
    } else {
        // Controllo duplicati (stessa posizione, diverso ID)
        $stmtCheck = $pdo->prepare("SELECT id FROM compartments WHERE location_id = ? AND code = ? AND id != ?");
        $stmtCheck->execute([$new_location_id, $code, $id]);
        if ($stmtCheck->fetch()) {
            $error = "Esiste già un compartimento con questo codice nella posizione selezionata.";
        } else {
            // Aggiornamento dati
            $stmt = $pdo->prepare("UPDATE compartments SET code = ?, description = ?, location_id = ? WHERE id = ?");
            $stmt->execute([$code, $description, $new_location_id, $id]);

            $_SESSION['success'] = "Compartimento aggiornato con successo.";
            header("Location: compartments.php?location_id=" . $new_location_id);
            exit;
        }
    }
}

include '../includes/header.php';
?>

<div class="container py-4">
    <h2><i class="fa-solid fa-pen me-2"></i>Modifica compartimento</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm p-4">
        <div class="mb-3">
            <label class="form-label">Codice compartimento *</label>
            <input type="text" name="code" class="form-control" value="<?= htmlspecialchars($compartment['code']) ?>" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Descrizione</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($compartment['description'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Posizione</label>
            <select name="location_id" class="form-select" required>
                <option value="">-- Seleziona posizione --</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= ($compartment['location_id'] == $loc['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($loc['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="text-end">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-save"></i> Salva modifiche
            </button>
            <a href="compartments.php?location_id=<?= $location_id ?>" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Annulla
            </a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>