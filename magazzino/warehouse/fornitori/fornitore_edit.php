<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-10
 * @Description: Modifica Fornitore
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
    header('Location: fornitori.php');
    exit;
}

// Recupera fornitore
$stmt = $pdo->prepare("SELECT * FROM fornitori WHERE id = ?");
$stmt->execute([$id]);
$fornitore = $stmt->fetch(PDO::FETCH_ASSOC);

// Mappa pattern fornitore → file termini (solo per developer, non modificabile da admin)
$termini_fornitori = [
    'mouser' => 'termini_mouser.txt',
    // Aggiungi altri fornitori qui es: 'digikey' => 'termini_digikey.txt',
];

if (!$fornitore) {
    $_SESSION['error'] = 'Fornitore non trovato.';
    header('Location: fornitori.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $apikey = trim($_POST['apikey'] ?? '');
    $note = trim($_POST['note'] ?? '');
    
    if ($nome === '') {
        $error = 'Il nome del fornitore è obbligatorio.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE fornitori SET nome = ?, link = ?, apikey = ?, note = ? WHERE id = ?");
            $stmt->execute([$nome, $link ?: null, $apikey ?: null, $note ?: null, $id]);
            
            $_SESSION['success'] = 'Fornitore aggiornato con successo.';
            header('Location: fornitori.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Errore durante l\'aggiornamento: ' . $e->getMessage();
        }
    }
    
    $fornitore = ['nome' => $nome, 'link' => $link, 'apikey' => $apikey, 'note' => $note];
}

include '../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-truck-field me-2"></i>Modifica Fornitore</h2>
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
                        <input type="url" name="link" class="form-control" value="<?= htmlspecialchars($fornitore['link'] ?? '') ?>" placeholder="https://">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">API Key</label>
                        <input type="text" name="apikey" id="apikey" class="form-control" value="<?= htmlspecialchars($fornitore['apikey'] ?? '') ?>" data-terms-accepted="<?= !empty($fornitore['apikey']) ? 'true' : 'false' ?>" data-terms-required="false">
                        <div class="form-text">Chiave API per integrazioni (opzionale). <span id="termsHint" style="display:none;">Clicca sul campo per accettare i termini specifici del fornitore.</span></div>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <textarea name="note" class="form-control" rows="3"><?= htmlspecialchars($fornitore['note'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="fornitori.php" class="btn btn-secondary">Annulla</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-1"></i>Salva Modifiche
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
// Mappa pattern → file termini (deve corrispondere a quella PHP)
const terminiFornitori = {
    'mouser': 'termini_mouser.txt',
    // Aggiungi altri fornitori qui
};

document.addEventListener('DOMContentLoaded', function() {
    const nomeInput = document.getElementById('nome');
    const apikeyInput = document.getElementById('apikey');
    const termsModal = new bootstrap.Modal(document.getElementById('termsModal'));
    const btnAccept = document.getElementById('btnAccept');
    const btnDecline = document.getElementById('btnDecline');
    const termsContent = document.getElementById('termsContent');
    const termsHint = document.getElementById('termsHint');
    
    let currentTermsFile = null;
    
    // Controlla se il nome fornitore matcha un pattern
    function checkSupplierTerms() {
        const nome = nomeInput.value.toLowerCase();
        currentTermsFile = null;
        
        for (const [pattern, file] of Object.entries(terminiFornitori)) {
            if (nome.includes(pattern.toLowerCase())) {
                currentTermsFile = file;
                break;
            }
        }
        
        // Aggiorna UI
        if (currentTermsFile) {
            apikeyInput.dataset.termsRequired = 'true';
            termsHint.style.display = 'inline';
            // Se cambia il fornitore e il campo è vuoto, resetta l'accettazione
            if (apikeyInput.value.trim() === '') {
                apikeyInput.dataset.termsAccepted = 'false';
            }
        } else {
            apikeyInput.dataset.termsRequired = 'false';
            termsHint.style.display = 'none';
            apikeyInput.dataset.termsAccepted = 'true'; // Nessun termine richiesto
        }
    }
    
    // Monitora cambiamenti nel nome
    nomeInput.addEventListener('input', checkSupplierTerms);
    checkSupplierTerms(); // Controlla subito (per il valore iniziale)
    
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
