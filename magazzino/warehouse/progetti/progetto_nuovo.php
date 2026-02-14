<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: Nuovo Progetto
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

// Verifica permessi admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$error = '';
$progetto = ['nome' => '', 'descrizione' => '', 'numero_ordine' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descrizione = trim($_POST['descrizione'] ?? '');
    $numero_ordine = trim($_POST['numero_ordine'] ?? '');
    
    if ($nome === '') {
        $error = 'Il nome del progetto è obbligatorio.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO progetti (nome, descrizione, numero_ordine) VALUES (?, ?, ?)");
            $stmt->execute([$nome, $descrizione ?: null, $numero_ordine ?: null]);
            
            $newId = $pdo->lastInsertId();
            $_SESSION['success'] = 'Progetto creato con successo.';
            
            // Redirect alla pagina di gestione componenti
            header('Location: progetto_view.php?id=' . $newId);
            exit;
        } catch (PDOException $e) {
            $error = 'Errore durante il salvataggio: ' . $e->getMessage();
        }
    }
    
    $progetto = ['nome' => $nome, 'descrizione' => $descrizione, 'numero_ordine' => $numero_ordine];
}

include '../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-clipboard-list me-2"></i>Nuovo Progetto</h2>
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
                        <input type="text" name="numero_ordine" class="form-control" value="<?= htmlspecialchars($progetto['numero_ordine']) ?>" placeholder="Es. ORD-2024-001">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Descrizione</label>
                        <textarea name="descrizione" class="form-control" rows="4"><?= htmlspecialchars($progetto['descrizione']) ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="progetti.php" class="btn btn-secondary">Annulla</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-1"></i>Crea Progetto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
