<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-19
 * @Description: Nuovo Fornitore
 */

// 2026-02-19 il campo API Key si visualizza solo se esiste un file termini_*.txt

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

// Verifica permessi admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$error = '';
$fornitore = ['nome' => '', 'link' => '', 'apikey' => '', 'note' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $apikey = trim($_POST['apikey'] ?? '');
    $note = trim($_POST['note'] ?? '');
    
    if ($nome === '') {
        $error = 'Il nome del fornitore è obbligatorio.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO fornitori (nome, link, apikey, note) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nome, $link ?: null, $apikey ?: null, $note ?: null]);
            
            $_SESSION['success'] = 'Fornitore aggiunto con successo.';
            header('Location: fornitori.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Errore durante il salvataggio: ' . $e->getMessage();
        }
    }
    
    $fornitore = ['nome' => $nome, 'link' => $link, 'apikey' => $apikey, 'note' => $note];
}

include '../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-truck-field me-2"></i>Nuovo Fornitore</h2>
        <a href="fornitori.php" class="btn btn-secondary">
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
                        <label class="form-label">Nome Fornitore *</label>
                        <input type="text" name="nome" id="nome" class="form-control" value="<?= htmlspecialchars($fornitore['nome']) ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Link Sito/Prodotti</label>
                        <input type="url" name="link" class="form-control" value="<?= htmlspecialchars($fornitore['link']) ?>" placeholder="https://">
                    </div>
                    
                    <!-- Container API Key - mostrato solo se esiste file termini -->
                    <div class="col-12" id="apikey-container" style="display: none;">
                        <label class="form-label">API Key</label>
                        <input type="text" name="apikey" id="apikey" class="form-control" value="<?= htmlspecialchars($fornitore['apikey']) ?>" data-terms-accepted="<?= !empty($fornitore['apikey']) ? 'true' : 'false' ?>" data-terms-file="">
                        <div class="form-text">Chiave API per integrazioni (opzionale). <span id="termsHint" style="display:none;">Clicca sul campo per accettare i termini specifici del fornitore.</span></div>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <textarea name="note" class="form-control" rows="3"><?= htmlspecialchars($fornitore['note']) ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="fornitori.php" class="btn btn-secondary">Annulla</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-1"></i>Salva Fornitore
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Termini di Servizio API Key -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="termsModalLabel">
                    <i class="fa-solid fa-file-contract me-2"></i>Termini di Servizio - API Key
                </h5>
            </div>
            <div class="modal-body">
                <pre id="termsContent" style="white-space: pre-wrap; font-family: inherit; font-size: 0.9rem; max-height: 400px; overflow-y: auto;">Caricamento termini...</pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="btnDecline">
                    <i class="fa-solid fa-xmark me-1"></i>Nega
                </button>
                <button type="button" class="btn btn-success" id="btnAccept">
                    <i class="fa-solid fa-check me-1"></i>Accetta i termini
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const nomeInput = document.getElementById('nome');
    const apikeyContainer = document.getElementById('apikey-container');
    const apikeyInput = document.getElementById('apikey');
    const termsModal = new bootstrap.Modal(document.getElementById('termsModal'));
    const btnAccept = document.getElementById('btnAccept');
    const btnDecline = document.getElementById('btnDecline');
    const termsContent = document.getElementById('termsContent');
    const termsHint = document.getElementById('termsHint');
    
    let currentTermsFile = null;
    let checkTimeout = null;
    
    // Controlla via AJAX se esiste un file termini per il nome fornitore
    function checkSupplierTerms() {
        const nome = nomeInput.value.trim();
        
        // Clear previous timeout
        if (checkTimeout) {
            clearTimeout(checkTimeout);
        }
        
        if (!nome) {
            apikeyContainer.style.display = 'none';
            currentTermsFile = null;
            return;
        }
        
        // Debounce: attendi 300ms dopo l'ultimo carattere
        checkTimeout = setTimeout(() => {
            fetch('check_termini.php?nome=' + encodeURIComponent(nome))
                .then(response => response.json())
                .then(data => {
                    if (data.exists && data.file) {
                        currentTermsFile = data.file;
                        apikeyInput.dataset.termsFile = data.file;
                        apikeyInput.dataset.termsRequired = 'true';
                        apikeyContainer.style.display = 'block';
                        termsHint.style.display = 'inline';
                        // Reset acceptance if empty
                        if (apikeyInput.value.trim() === '') {
                            apikeyInput.dataset.termsAccepted = 'false';
                        }
                    } else {
                        currentTermsFile = null;
                        apikeyInput.dataset.termsFile = '';
                        apikeyInput.dataset.termsRequired = 'false';
                        apikeyContainer.style.display = 'none';
                        termsHint.style.display = 'none';
                        apikeyInput.dataset.termsAccepted = 'true';
                    }
                })
                .catch(() => {
                    // In caso di errore, nascondi il campo
                    apikeyContainer.style.display = 'none';
                    currentTermsFile = null;
                });
        }, 300);
    }
    
    // Monitora cambiamenti nel nome
    nomeInput.addEventListener('input', checkSupplierTerms);
    
    // Se il campo ha già un valore, considera i termini già accettati
    if (apikeyInput.value.trim() !== '') {
        apikeyInput.dataset.termsAccepted = 'true';
    }
    
    // Quando si clicca sul campo input
    apikeyInput.addEventListener('focus', function(e) {
        // Se non ci sono termini richiesti, permetti scrittura
        if (apikeyInput.dataset.termsRequired !== 'true') {
            return;
        }
        
        // Se i termini non sono ancora stati accettati e il campo è vuoto
        if (apikeyInput.dataset.termsAccepted !== 'true' && apikeyInput.value.trim() === '') {
            e.preventDefault();
            apikeyInput.blur();
            
            // Carica i termini
            if (currentTermsFile) {
                fetch(currentTermsFile)
                    .then(response => response.text())
                    .then(text => {
                        termsContent.textContent = text;
                        termsModal.show();
                    })
                    .catch(() => {
                        termsContent.textContent = 'Errore nel caricamento dei termini.';
                        termsModal.show();
                    });
            }
        }
    });
    
    // Quando si clicca su "Accetta i termini"
    btnAccept.addEventListener('click', function() {
        apikeyInput.dataset.termsAccepted = 'true';
        termsModal.hide();
        apikeyInput.focus();
    });
    
    // Quando si clicca su "Nega"
    btnDecline.addEventListener('click', function() {
        termsModal.hide();
        apikeyInput.blur();
        apikeyInput.value = '';
        apikeyInput.dataset.termsAccepted = 'false';
    });
    
    // Impedisce la scrittura se i termini non sono accettati (solo se richiesti)
    apikeyInput.addEventListener('keydown', function(e) {
        if (apikeyInput.dataset.termsRequired === 'true' && apikeyInput.dataset.termsAccepted !== 'true') {
            e.preventDefault();
            return false;
        }
    });
    
    apikeyInput.addEventListener('input', function(e) {
        if (apikeyInput.dataset.termsRequired === 'true' && apikeyInput.dataset.termsAccepted !== 'true') {
            e.preventDefault();
            apikeyInput.value = '';
            return false;
        }
    });
    
    apikeyInput.addEventListener('paste', function(e) {
        if (apikeyInput.dataset.termsRequired === 'true' && apikeyInput.dataset.termsAccepted !== 'true') {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
