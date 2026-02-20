<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: Modifica Dati Progetto
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

// Recupera link web esistenti
$stmt_web = $pdo->prepare("SELECT * FROM progetti_link_web WHERE progetto_id = ? ORDER BY id");
$stmt_web->execute([$id]);
$link_web = $stmt_web->fetchAll(PDO::FETCH_ASSOC);

// Recupera link locali esistenti
$stmt_local = $pdo->prepare("SELECT * FROM progetti_link_locali WHERE progetto_id = ? ORDER BY id");
$stmt_local->execute([$id]);
$link_locali = $stmt_local->fetchAll(PDO::FETCH_ASSOC);

$error = '';

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
            
            // Aggiorna il progetto
            $stmt = $pdo->prepare("UPDATE progetti SET nome = ?, descrizione = ?, numero_ordine = ?, note = ? WHERE id = ?");
            $stmt->execute([$nome, $descrizione ?: null, $numero_ordine ?: null, $note ?: null, $id]);
            
            // Aggiorna i link web: elimina quelli vecchi e inserisce quelli nuovi
            $pdo->prepare("DELETE FROM progetti_link_web WHERE progetto_id = ?")->execute([$id]);
            if (!empty($link_web_urls)) {
                $stmt_web = $pdo->prepare("INSERT INTO progetti_link_web (progetto_id, url, descrizione) VALUES (?, ?, ?)");
                foreach ($link_web_urls as $i => $url) {
                    $url = trim($url);
                    if ($url !== '') {
                        $stmt_web->execute([$id, $url, trim($link_web_desc[$i] ?? '') ?: null]);
                    }
                }
            }
            
            // Aggiorna i link locali: elimina quelli vecchi e inserisce quelli nuovi
            $pdo->prepare("DELETE FROM progetti_link_locali WHERE progetto_id = ?")->execute([$id]);
            if (!empty($link_locali_paths)) {
                $stmt_local = $pdo->prepare("INSERT INTO progetti_link_locali (progetto_id, path, descrizione) VALUES (?, ?, ?)");
                foreach ($link_locali_paths as $i => $path) {
                    $path = trim($path);
                    if ($path !== '') {
                        $stmt_local->execute([$id, $path, trim($link_locali_desc[$i] ?? '') ?: null]);
                    }
                }
            }
            
            $pdo->commit();
            $_SESSION['success'] = 'Progetto aggiornato con successo.';
            header('Location: progetti.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Errore durante l\'aggiornamento: ' . $e->getMessage();
        }
    }
    
    // Aggiorna i dati per la visualizzazione in caso di errore
    $progetto = ['nome' => $nome, 'descrizione' => $descrizione, 'numero_ordine' => $numero_ordine, 'note' => $note];
    
    // Ricostruisci array link per la visualizzazione
    $link_web = [];
    foreach ($link_web_urls as $i => $url) {
        if (trim($url) !== '') {
            $link_web[] = ['url' => $url, 'descrizione' => $link_web_desc[$i] ?? ''];
        }
    }
    $link_locali = [];
    foreach ($link_locali_paths as $i => $path) {
        if (trim($path) !== '') {
            $link_locali[] = ['path' => $path, 'descrizione' => $link_locali_desc[$i] ?? ''];
        }
    }
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
            <form method="POST" id="formProgetto">
                <div class="row g-3">
                    <!-- Nome e Numero Ordine -->
                    <div class="col-md-6">
                        <label class="form-label">Nome Progetto *</label>
                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($progetto['nome']) ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Numero Ordine</label>
                        <input type="text" name="numero_ordine" class="form-control" value="<?= htmlspecialchars($progetto['numero_ordine'] ?? '') ?>" placeholder="Es. ORD-2024-001">
                    </div>
                    
                    <!-- Descrizione -->
                    <div class="col-12">
                        <label class="form-label">Descrizione</label>
                        <textarea name="descrizione" class="form-control" rows="3"><?= htmlspecialchars($progetto['descrizione'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- Note -->
                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Note aggiuntive sul progetto..."><?= htmlspecialchars($progetto['note'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- Link Web -->
                    <div class="col-12">
                        <label class="form-label">
                            <i class="fa-solid fa-globe me-1 text-primary"></i>Link Web
                        </label>
                        <div id="containerLinkWeb">
                            <!-- I link web verranno caricati qui -->
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
                            <!-- I link locali verranno caricati qui -->
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

<script>
// Template per link web
function aggiungiLinkWeb(url = '', descrizione = '') {
    const container = document.getElementById('containerLinkWeb');
    
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

// Carica i link esistenti al caricamento della pagina
document.addEventListener('DOMContentLoaded', function() {
    // Carica link web esistenti
    <?php foreach ($link_web as $link): ?>
        aggiungiLinkWeb('<?= htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($link['descrizione'] ?? '', ENT_QUOTES, 'UTF-8') ?>');
    <?php endforeach; ?>
    
    // Carica link locali esistenti
    <?php foreach ($link_locali as $link): ?>
        aggiungiLinkLocale(<?= json_encode($link['path']) ?>, <?= json_encode($link['descrizione'] ?? '') ?>);
    <?php endforeach; ?>
    
    // Se non ci sono link, aggiungi almeno un campo vuoto per comodità
    if (document.getElementById('containerLinkWeb').children.length === 0) {
        aggiungiLinkWeb();
    }
    if (document.getElementById('containerLinkLocali').children.length === 0) {
        aggiungiLinkLocale();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
