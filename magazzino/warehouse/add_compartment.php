<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:29:25 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-23 13:50:53
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Controllo e recupero location_id
if (!isset($_GET['location_id']) || !is_numeric($_GET['location_id'])) {
    header("Location: locations.php");
    exit;
}
$location_id = intval($_GET['location_id']);

// Recupero dati posizione
$stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
$stmt->execute([$location_id]);
$location = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$location) {
    header("Location: locations.php?notfound=1");
    exit;
}

$error = '';

// Aggiunta comparto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $description = trim($_POST['description']);

    if ($code === '') {
        $error = "Il codice del comparto è obbligatorio.";
    } else {
        // Controllo duplicati per la stessa posizione
        $stmtCheck = $pdo->prepare("SELECT id FROM compartments WHERE location_id = ? AND code = ?");
        $stmtCheck->execute([$location_id, $code]);
        if ($stmtCheck->fetch()) {
            $error = "Esiste già un comparto con questo codice in questa posizione.";
        } else {
            // Inserimento nuovo comparto
            $stmtInsert = $pdo->prepare("INSERT INTO compartments (location_id, code, description) VALUES (?, ?, ?)");
            $stmtInsert->execute([$location_id, $code, $description]);

            // Salvataggio messaggio e redirect
            $_SESSION['success'] = "Comparto aggiunto con successo.";
            header("Location: compartments.php?location_id=" . $location_id);
            exit;
        }
    }
}

include '../includes/header.php';
?>

<div class="container py-4">
    <h2><i class="fa-solid fa-plus me-2"></i>Aggiungi comparto a <?= htmlspecialchars($location['name']) ?></h2>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm p-4">
        <div class="mb-3">
            <label class="form-label">Codice comparto *</label>
            <input type="text" name="code" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Descrizione</label>
            <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
        <div class="text-end">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-save"></i> Salva comparto
            </button>
        </div>
    </form>

    <a href="compartments.php?location_id=<?= $location_id ?>" class="btn btn-secondary mt-3">
        <i class="fa-solid fa-arrow-left"></i> Torna alla lista comparti
    </a>
</div>

<?php include '../includes/footer.php'; ?>