<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: Nuovo Progetto
 */

// 2026-02-19 aggiunti link web, link a cartelle locali e note

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

// Verifica permessi admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$error = '';
$progetto = ['nome' => '', 'descrizione' => '', 'numero_ordine' => '', 'note' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descrizione = trim($_POST['descrizione'] ?? '');
    $numero_ordine = trim($_POST['numero_ordine'] ?? '');
    $note = trim($_POST['note'] ?? '');
    
    // Recupera link web
    $link_web_urls = $_POST['link_web_url'] ?? [];
    $link_web_desc = $_POST['link_web_desc'] ?? [];
    
    // Recupera link locali
    $link_locali_paths = $_POST['link_locale_path'] ?? [];
    $link_locali_desc = $_POST['link_locale_desc'] ?? [];
    
    if ($nome === '') {
        $error = 'Il nome del progetto è obbligatorio.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Inserisce il progetto
            $stmt = $pdo->prepare("INSERT INTO progetti (nome, descrizione, numero_ordine, note) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nome, $descrizione ?: null, $numero_ordine ?: null, $note ?: null]);
            
            $newId = $pdo->lastInsertId();
            
            // Inserisce i link web
            if (!empty($link_web_urls)) {
                $stmt_web = $pdo->prepare("INSERT INTO progetti_link_web (progetto_id, url, descrizione) VALUES (?, ?, ?)");
                foreach ($link_web_urls as $i => $url) {
                    $url = trim($url);
                    if ($url !== '') {
                        $stmt_web->execute([$newId, $url, trim($link_web_desc[$i] ?? '') ?: null]);
                    }
                }
            }
            
            // Inserisce i link locali
            if (!empty($link_locali_paths)) {
                $stmt_local = $pdo->prepare("INSERT INTO progetti_link_locali (progetto_id, path, descrizione) VALUES (?, ?, ?)");
                foreach ($link_locali_paths as $i => $path) {
                    $path = trim($path);
                    if ($path !== '') {
                        $stmt_local->execute([$newId, $path, trim($link_locali_desc[$i] ?? '') ?: null]);
                    }
                }
            }
            
            $pdo->commit();
            $_SESSION['success'] = 'Progetto creato con successo.';
            
            // Redirect alla pagina di gestione componenti
            header('Location: progetto_view.php?id=' . $newId);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Errore durante il salvataggio: ' . $e->getMessage();
        }
    }
    
    $progetto = ['nome' => $nome, 'descrizione' => $descrizione, 'numero_ordine' => $numero_ordine, 'note' => $note];
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
            <form method="POST" id="formProgetto">
                <div class="row g-3">
                    <!-- Nome e Numero Ordine -->
                    <div class="col-md-6">
                        <label class="form-label">Nome Progetto *</label>
                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($progetto['nome']) ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Numero Ordine</label>
                        <input type="text" name="numero_ordine" class="form-control" value="<?= htmlspecialchars($progetto['numero_ordine']) ?>" placeholder="Es. ORD-2024-001">
                    </div>
                    
                    <!-- Descrizione -->
                    <div class="col-12">
                        <label class="form-label">Descrizione</label>
                        <textarea name="descrizione" class="form-control" rows="3"><?= htmlspecialchars($progetto['descrizione']) ?></textarea>
                    </div>
                    
                    <!-- Note -->
                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Note aggiuntive sul progetto..."><?= htmlspecialchars($progetto['note']) ?></textarea>
                    </div>
                    
                    <!-- Link Web -->
                    <div class="col-12">
                        <label class="form-label">
                            <i class="fa-solid fa-globe me-1 text-primary"></i>Link Web
                        </label>
                        <div id="containerLinkWeb">
                            <!-- I link web verranno aggiunti qui dinamicamente -->
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="aggiungiLinkWeb()">
                            <i class="fa-solid fa-plus me-1"></i>Aggiungi Link Web
                        </button>
                    </div>
                    
                    <!-- Link Cartelle Locali -->
                    <div class="col-12">
                        <label class="form-label">
                            <i class="fa-solid fa-folder-open me-1 text-success"></i>Link Cartelle Locali
                        </label>
                        <div id="containerLinkLocali">
                            <!-- I link locali verranno aggiunti qui dinamicamente -->
                        </div>
                        <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="aggiungiLinkLocale()">
                            <i class="fa-solid fa-plus me-1"></i>Aggiungi Cartella Locale
                        </button>
                        <div class="form-text text-muted">
                            <i class="fa-solid fa-info-circle me-1"></i>
                            Inserisci il percorso completo della cartella (es. C:\Progetti\MioProgetto o /home/user/progetti/mioprogetto)
                        </div>
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

<script>
// Template per link web
function aggiungiLinkWeb(url = '', descrizione = '') {
    const container = document.getElementById('containerLinkWeb');
    const index = container.children.length;
    
    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 link-web-row align-items-end';
    div.innerHTML = `
        <div class="col-md-5">
            <input type="url" name="link_web_url[]" class="form-control" placeholder="https://..." value="${url}">
        </div>
        <div class="col-md-6">
            <input type="text" name="link_web_desc[]" class="form-control" placeholder="Descrizione link (opzionale)" value="${descrizione}">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="rimuoviLink(this)" title="Rimuovi link">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(div);
}

// Template per link locali
function aggiungiLinkLocale(path = '', descrizione = '') {
    const container = document.getElementById('containerLinkLocali');
    
    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 link-locale-row align-items-end';
    div.innerHTML = `
        <div class="col-md-5">
            <div class="input-group">
                <input type="text" name="link_locale_path[]" class="form-control" placeholder="Percorso cartella..." value="${path}">
                <span class="input-group-text" title="Per inserire il percorso completo:&#10;1. Naviga nella cartella con Esplora Risorse/Finder&#10;2. Clicca sulla barra del percorso e copia (Ctrl+C)&#10;3. Incolla nel campo di testo (Ctrl+V)" style="cursor: help;">
                    <i class="fa-solid fa-circle-info text-info"></i>
                </span>
            </div>
        </div>
        <div class="col-md-6">
            <input type="text" name="link_locale_desc[]" class="form-control" placeholder="Descrizione cartella (opzionale)" value="${descrizione}">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="rimuoviLink(this)" title="Rimuovi cartella">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(div);
}

// Rimuovi link (funzione condivisa)
function rimuoviLink(button) {
    button.closest('.row').remove();
}
</script>

<?php include '../../includes/footer.php'; ?>
