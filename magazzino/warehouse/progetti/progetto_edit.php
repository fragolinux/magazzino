<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: Modifica Dati Progetto
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

// Verifica permessi admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header('Location: progetti.php');
    exit;
}

// Recupera progetto
$stmt = $pdo->prepare("SELECT * FROM progetti WHERE id = ?");
$stmt->execute([$id]);
$progetto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$progetto) {
    $_SESSION['error'] = 'Progetto non trovato.';
    header('Location: progetti.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descrizione = trim($_POST['descrizione'] ?? '');
    $numero_ordine = trim($_POST['numero_ordine'] ?? '');
    
    if ($nome === '') {
        $error = 'Il nome del progetto è obbligatorio.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE progetti SET nome = ?, descrizione = ?, numero_ordine = ? WHERE id = ?");
            $stmt->execute([$nome, $descrizione ?: null, $numero_ordine ?: null, $id]);
            
            $_SESSION['success'] = 'Progetto aggiornato con successo.';
            header('Location: progetti.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Errore durante l\'aggiornamento: ' . $e->getMessage();
        }
    }
    
    $progetto = ['nome' => $nome, 'descrizione' => $descrizione, 'numero_ordine' => $numero_ordine];
}

include '../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-clipboard-list me-2"></i>Modifica Progetto</h2>
        <a href="progetti.php" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>Torna alla lista
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome Progetto *</label>
                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($progetto['nome']) ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Numero Ordine</label>
                        <input type="text" name="numero_ordine" class="form-control" value="<?= htmlspecialchars($progetto['numero_ordine'] ?? '') ?>" placeholder="Es. ORD-2024-001">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Descrizione</label>
                        <textarea name="descrizione" class="form-control" rows="4"><?= htmlspecialchars($progetto['descrizione'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="progetto_view.php?id=<?= $id ?>" class="btn btn-outline-info">
                        <i class="fa-solid fa-boxes-stacked me-1"></i>Gestisci Componenti
                    </a>
                    <a href="progetti.php" class="btn btn-secondary">Annulla</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-1"></i>Salva Modifiche
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
