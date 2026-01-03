<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-21 09:15:47 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-23 14:06:39
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$error = '';
$success = '';

// Gestione POST: aggiungi nuova posizione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error = "Il nome della posizione è obbligatorio.";
    } else {
        // Controllo duplicati (nome unico)
        $stmt = $pdo->prepare("SELECT id FROM locations WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $error = "Esiste già una posizione con questo nome.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO locations (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);

            $_SESSION['success'] = "Posizione \"" . htmlspecialchars($name) . "\" aggiunta con successo.";
            header("Location: locations.php");
            exit;
        }
    }
}

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fa-solid fa-map-location-dot me-2"></i>Aggiungi posizione</h2>
        <a href="locations.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Torna alle posizioni</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm p-4">
        <div class="mb-3">
            <label class="form-label">Nome posizione *</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autofocus>
        </div>

        <div class="mb-3">
            <label class="form-label">Tipo</label>
            <select name="type" class="form-select" required>
                <option value=""> -- Seleziona il tipo -- </option>
                <option value="scaffale"> Scaffale</option>
                <option value="cassettiera"> Cassettiera</option>
                <option value="scatola"> Scatola</option>
                <option value="altro"> Altro</option>
                <option value="valigetta"> Valigetta</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Descrizione</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Salva posizione</button>
            <a href="locations.php" class="btn btn-secondary"><i class="fa-solid fa-times me-1"></i> Annulla</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>