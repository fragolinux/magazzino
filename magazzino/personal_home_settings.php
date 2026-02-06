<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-02-02 14:17:26 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-02 21:59:55
*/

/*
 * Pagina Configurazione Sito Personale (solo admin)
 * Gestione completa di: attivazione, identità, header/footer, sezioni, temi
 */

require_once __DIR__ . '/includes/auth_check.php';

// Solo admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Accesso negato: permessi insufficienti.";
    exit;
}

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';

$message = null;
$error = null;

// Carica configurazione corrente o crea se non esiste
$stmt = $pdo->query("SELECT * FROM personal_site_config ORDER BY id ASC LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    // Crea configurazione di default
    $pdo->exec("INSERT INTO personal_site_config (enabled, site_title, theme_preset) VALUES (0, 'Il Mio Sito Personale', 'modern_minimal')");
    $config = $pdo->query("SELECT * FROM personal_site_config ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

// ID effettivo della configurazione
$configId = $config['id'];

// === GESTIONE FORM GENERALE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF non valido.";
    } else {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'update_general':
                    $enabled = isset($_POST['enabled']) ? 1 : 0;
                    $siteTitle = trim($_POST['site_title'] ?? '');
                    $themePreset = $_POST['theme_preset'] ?? 'modern_minimal';
                    
                    if (empty($siteTitle)) {
                        $error = "Il titolo del sito è obbligatorio.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE personal_site_config SET enabled = ?, site_title = ?, theme_preset = ? WHERE id = ?");
                        $stmt->execute([$enabled, $siteTitle, $themePreset, $configId]);
                        $message = "Impostazioni generali aggiornate con successo!";
                        $config['enabled'] = $enabled;
                        $config['site_title'] = $siteTitle;
                        $config['theme_preset'] = $themePreset;
                    }
                    break;
                    
                case 'upload_logo':
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
                        $filename = $_FILES['logo']['name'];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        
                        if (!in_array($ext, $allowed)) {
                            $error = "Formato file non consentito. Usa: " . implode(', ', $allowed);
                        } elseif ($_FILES['logo']['size'] > 5 * 1024 * 1024) {
                            $error = "File troppo grande (max 5MB).";
                        } else {
                            $newFilename = 'logo_' . time() . '.' . $ext;
                            $uploadPath = __DIR__ . '/assets/personal_site/' . $newFilename;
                            
                            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                                // Elimina vecchio logo
                                if (!empty($config['logo_path']) && file_exists(__DIR__ . '/assets/personal_site/' . basename($config['logo_path']))) {
                                    @unlink(__DIR__ . '/assets/personal_site/' . basename($config['logo_path']));
                                }
                                
                                $stmt = $pdo->prepare("UPDATE personal_site_config SET logo_path = ? WHERE id = ?");
                                $stmt->execute([$newFilename, $configId]);
                                $config['logo_path'] = $newFilename;
                                $message = "Logo caricato con successo!";
                            } else {
                                $error = "Errore durante l'upload del logo.";
                            }
                        }
                    } else {
                        $error = "Nessun file caricato o errore nell'upload.";
                    }
                    break;
                    
                case 'upload_favicon':
                    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                        $allowed = ['ico', 'png'];
                        $filename = $_FILES['favicon']['name'];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        
                        if (!in_array($ext, $allowed)) {
                            $error = "Formato favicon non valido. Usa: ico o png";
                        } elseif ($_FILES['favicon']['size'] > 1024 * 1024) {
                            $error = "Favicon troppo grande (max 1MB).";
                        } else {
                            $newFilename = 'favicon_' . time() . '.' . $ext;
                            $uploadPath = __DIR__ . '/assets/personal_site/' . $newFilename;
                            
                            if (move_uploaded_file($_FILES['favicon']['tmp_name'], $uploadPath)) {
                                if (!empty($config['favicon_path']) && file_exists(__DIR__ . '/assets/personal_site/' . basename($config['favicon_path']))) {
                                    @unlink(__DIR__ . '/assets/personal_site/' . basename($config['favicon_path']));
                                }
                                
                                $stmt = $pdo->prepare("UPDATE personal_site_config SET favicon_path = ? WHERE id = ?");
                                $stmt->execute([$newFilename, $configId]);
                                $config['favicon_path'] = $newFilename;
                                $message = "Favicon caricata con successo!";
                            } else {
                                $error = "Errore durante l'upload della favicon.";
                            }
                        }
                    } else {
                        $error = "Nessun file caricato o errore nell'upload.";
                    }
                    break;
                    
                case 'upload_background':
                    if (isset($_FILES['background']) && $_FILES['background']['error'] === UPLOAD_ERR_OK) {
                        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                        $filename = $_FILES['background']['name'];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        
                        if (!in_array($ext, $allowed)) {
                            $error = "Formato non consentito. Usa: jpg, png, webp";
                        } elseif ($_FILES['background']['size'] > 10 * 1024 * 1024) {
                            $error = "Immagine troppo grande (max 10MB).";
                        } else {
                            $newFilename = 'bg_global_' . time() . '.' . $ext;
                            $uploadPath = __DIR__ . '/assets/personal_site/backgrounds/' . $newFilename;
                            
                            if (move_uploaded_file($_FILES['background']['tmp_name'], $uploadPath)) {
                                if (!empty($config['background_image']) && file_exists(__DIR__ . '/assets/personal_site/backgrounds/' . basename($config['background_image']))) {
                                    @unlink(__DIR__ . '/assets/personal_site/backgrounds/' . basename($config['background_image']));
                                }
                                
                                $stmt = $pdo->prepare("UPDATE personal_site_config SET background_image = ? WHERE id = ?");
                                $stmt->execute([$newFilename, $configId]);
                                $config['background_image'] = $newFilename;
                                $message = "Sfondo globale caricato con successo!";
                            } else {
                                $error = "Errore durante l'upload dello sfondo.";
                            }
                        }
                    } else {
                        $error = "Nessun file caricato o errore nell'upload.";
                    }
                    break;
                    
                case 'delete_background':
                    if (!empty($config['background_image']) && file_exists(__DIR__ . '/assets/personal_site/backgrounds/' . basename($config['background_image']))) {
                        @unlink(__DIR__ . '/assets/personal_site/backgrounds/' . basename($config['background_image']));
                    }
                    $stmt = $pdo->prepare("UPDATE personal_site_config SET background_image = NULL WHERE id = ?");
                    $stmt->execute([$configId]);
                    $config['background_image'] = null;
                    $message = "Sfondo globale eliminato con successo!";
                    break;
                    
                case 'update_header':
                    $headerContent = $_POST['header_content'] ?? '';
                    $stmt = $pdo->prepare("UPDATE personal_site_config SET header_content = ? WHERE id = ?");
                    $stmt->execute([$headerContent, $configId]);
                    $config['header_content'] = $headerContent;
                    $message = "Header aggiornato con successo!";
                    break;
                    
                case 'update_footer':
                    $footerContent = $_POST['footer_content'] ?? '';
                    $stmt = $pdo->prepare("UPDATE personal_site_config SET footer_content = ? WHERE id = ?");
                    $stmt->execute([$footerContent, $configId]);
                    $config['footer_content'] = $footerContent;
                    $message = "Footer aggiornato con successo!";
                    break;
                    
                case 'add_section':
                    $menuLabel = trim($_POST['menu_label'] ?? '');
                    $sectionTitle = trim($_POST['section_title'] ?? '');
                    $sectionContent = $_POST['section_content'] ?? '';
                    
                    if (empty($menuLabel)) {
                        $error = "L'etichetta del menu è obbligatoria.";
                    } else {
                        // Determina ordine
                        $maxOrder = $pdo->query("SELECT MAX(section_order) FROM personal_site_sections")->fetchColumn();
                        $newOrder = ($maxOrder ?? 0) + 1;
                        
                        $stmt = $pdo->prepare("INSERT INTO personal_site_sections (menu_label, section_title, section_content, section_order) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$menuLabel, $sectionTitle, $sectionContent, $newOrder]);
                        $message = "Sezione aggiunta con successo!";
                    }
                    break;
                    
                case 'edit_section':
                    $sectionId = intval($_POST['section_id'] ?? 0);
                    $menuLabel = trim($_POST['menu_label'] ?? '');
                    $sectionTitle = trim($_POST['section_title'] ?? '');
                    $sectionContent = $_POST['section_content'] ?? '';
                    $enabled = isset($_POST['enabled']) ? 1 : 0;
                    
                    if (empty($menuLabel)) {
                        $error = "L'etichetta del menu è obbligatoria.";
                    } elseif ($sectionId > 0) {
                        $stmt = $pdo->prepare("UPDATE personal_site_sections SET menu_label = ?, section_title = ?, section_content = ?, enabled = ? WHERE id = ?");
                        $stmt->execute([$menuLabel, $sectionTitle, $sectionContent, $enabled, $sectionId]);
                        $message = "Sezione aggiornata con successo!";
                    }
                    break;
                    
                case 'delete_section':
                    $sectionId = intval($_POST['section_id'] ?? 0);
                    if ($sectionId > 0) {
                        // Elimina immagine di sfondo se presente
                        $section = $pdo->prepare("SELECT background_image FROM personal_site_sections WHERE id = ?");
                        $section->execute([$sectionId]);
                        $sectionData = $section->fetch();
                        if ($sectionData && !empty($sectionData['background_image'])) {
                            $bgPath = __DIR__ . '/assets/personal_site/backgrounds/' . basename($sectionData['background_image']);
                            if (file_exists($bgPath)) {
                                @unlink($bgPath);
                            }
                        }
                        
                        $stmt = $pdo->prepare("DELETE FROM personal_site_sections WHERE id = ?");
                        $stmt->execute([$sectionId]);
                        $message = "Sezione eliminata con successo!";
                    }
                    break;
                    
                case 'reorder_sections':
                    $orders = json_decode($_POST['section_orders'] ?? '[]', true);
                    if (is_array($orders)) {
                        foreach ($orders as $id => $order) {
                            $stmt = $pdo->prepare("UPDATE personal_site_sections SET section_order = ? WHERE id = ?");
                            $stmt->execute([$order, $id]);
                        }
                        $message = "Ordine sezioni aggiornato!";
                    }
                    break;
                    
                case 'delete_section_bg':
                    $sectionId = intval($_POST['section_id'] ?? 0);
                    if ($sectionId > 0) {
                        $oldBg = $pdo->prepare("SELECT background_image FROM personal_site_sections WHERE id = ?");
                        $oldBg->execute([$sectionId]);
                        $oldData = $oldBg->fetch();
                        if ($oldData && !empty($oldData['background_image'])) {
                            $oldPath = __DIR__ . '/assets/personal_site/backgrounds/' . basename($oldData['background_image']);
                            if (file_exists($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        $stmt = $pdo->prepare("UPDATE personal_site_sections SET background_image = NULL WHERE id = ?");
                        $stmt->execute([$sectionId]);
                        $message = "Sfondo sezione eliminato con successo!";
                    }
                    break;
                    
                case 'upload_section_bg':
                    $sectionId = intval($_POST['section_id'] ?? 0);
                    if ($sectionId > 0 && isset($_FILES['section_bg']) && $_FILES['section_bg']['error'] === UPLOAD_ERR_OK) {
                        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                        $filename = $_FILES['section_bg']['name'];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        
                        if (!in_array($ext, $allowed)) {
                            $error = "Formato non consentito.";
                        } elseif ($_FILES['section_bg']['size'] > 10 * 1024 * 1024) {
                            $error = "Immagine troppo grande (max 10MB).";
                        } else {
                            $newFilename = 'section_' . $sectionId . '_' . time() . '.' . $ext;
                            $uploadPath = __DIR__ . '/assets/personal_site/backgrounds/' . $newFilename;
                            
                            if (move_uploaded_file($_FILES['section_bg']['tmp_name'], $uploadPath)) {
                                // Elimina vecchio sfondo
                                $oldBg = $pdo->prepare("SELECT background_image FROM personal_site_sections WHERE id = ?");
                                $oldBg->execute([$sectionId]);
                                $oldData = $oldBg->fetch();
                                if ($oldData && !empty($oldData['background_image'])) {
                                    $oldPath = __DIR__ . '/assets/personal_site/backgrounds/' . basename($oldData['background_image']);
                                    if (file_exists($oldPath)) {
                                        @unlink($oldPath);
                                    }
                                }
                                
                                $stmt = $pdo->prepare("UPDATE personal_site_sections SET background_image = ? WHERE id = ?");
                                $stmt->execute([$newFilename, $sectionId]);
                                $message = "Sfondo sezione caricato con successo!";
                            } else {
                                $error = "Errore durante l'upload.";
                            }
                        }
                    }
                    break;
            }
            
            // Ricarica config
            $config = $pdo->query("SELECT * FROM personal_site_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = "Errore: " . $e->getMessage();
        }
    }
}

// Carica sezioni
$sections = $pdo->query("SELECT * FROM personal_site_sections ORDER BY section_order ASC")->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="text-muted">
            <i class="fa-solid fa-palette me-2"></i>Configurazione Sito Personale
        </h1>
        <div>
            <?php if ($config['enabled']): ?>
            <a href="<?= BASE_PATH ?>personal_home.php" class="btn btn-primary" target="_blank">
                <i class="fa-solid fa-eye me-2"></i>Visualizza Sito
            </a>
            <?php endif; ?>
            <a href="<?= BASE_PATH ?>settings.php" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left me-2"></i>Torna a Impostazioni
            </a>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fa-solid fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tab-general">
                <i class="fa-solid fa-toggle-on me-2"></i>Generale
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-identity">
                <i class="fa-solid fa-id-card me-2"></i>Identità
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-header-footer">
                <i class="fa-solid fa-window-maximize me-2"></i>Header & Footer
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-sections">
                <i class="fa-solid fa-layer-group me-2"></i>Sezioni (<?= count($sections) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-theme">
                <i class="fa-solid fa-brush me-2"></i>Tema
            </a>
        </li>
    </ul>
    
    <div class="tab-content">
        <!-- TAB GENERALE -->
        <div class="tab-pane fade show active" id="tab-general">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i class="fa-solid fa-toggle-on me-2"></i>Attivazione Sito Personale</h5>
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="update_general">
                        
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="enabled" name="enabled" <?= $config['enabled'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="enabled">
                                    Attiva Homepage Personale
                                </label>
                            </div>
                            <small class="text-muted">
                                Quando attivo, la homepage del magazzino (index.php) reindirizzerà al sito personale.
                            </small>
                        </div>
                        
                        <div class="mb-4">
                            <label for="site_title" class="form-label fw-bold">Titolo Sito</label>
                            <input type="text" class="form-control" id="site_title" name="site_title" 
                                   value="<?= htmlspecialchars($config['site_title'], ENT_QUOTES, 'UTF-8') ?>" required>
                            <small class="text-muted">Apparirà nella navbar e nel tag &lt;title&gt;</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-save me-2"></i>Salva Impostazioni Generali
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- TAB IDENTITÀ -->
        <div class="tab-pane fade" id="tab-identity">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title mb-4"><i class="fa-solid fa-image me-2"></i>Logo Sito</h5>
                            <?php if (!empty($config['logo_path'])): ?>
                            <div class="mb-3 text-center">
                                <img src="<?= BASE_PATH ?>assets/personal_site/<?= basename($config['logo_path']) ?>" 
                                     alt="Logo" class="img-thumbnail" style="max-height: 150px;">
                            </div>
                            <?php endif; ?>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="action" value="upload_logo">
                                <div class="mb-3">
                                    <input type="file" class="form-control" name="logo" accept=".jpg,.jpeg,.png,.gif,.svg,.webp" required>
                                    <small class="text-muted">Formati: JPG, PNG, GIF, SVG, WebP (max 5MB)</small>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fa-solid fa-upload me-2"></i>Carica Logo
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title mb-4"><i class="fa-solid fa-star me-2"></i>Favicon</h5>
                            <?php if (!empty($config['favicon_path'])): ?>
                            <div class="mb-3 text-center">
                                <img src="<?= BASE_PATH ?>assets/personal_site/<?= basename($config['favicon_path']) ?>" 
                                     alt="Favicon" class="img-thumbnail" style="max-height: 64px;">
                            </div>
                            <?php endif; ?>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="action" value="upload_favicon">
                                <div class="mb-3">
                                    <input type="file" class="form-control" name="favicon" accept=".ico,.png" required>
                                    <small class="text-muted">Formati: ICO, PNG (max 1MB, consigliato 32x32px)</small>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fa-solid fa-upload me-2"></i>Carica Favicon
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-4"><i class="fa-solid fa-images me-2"></i>Sfondo Globale</h5>
                            <?php if (!empty($config['background_image'])): ?>
                            <div class="mb-3 text-center">
                                <img src="<?= BASE_PATH ?>assets/personal_site/backgrounds/<?= basename($config['background_image']) ?>" 
                                     alt="Background" class="img-thumbnail" style="max-height: 200px;">
                                <div class="mt-2">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_background">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare lo sfondo globale?')">
                                            <i class="fa-solid fa-trash me-1"></i>Elimina Sfondo
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="action" value="upload_background">
                                <div class="mb-3">
                                    <input type="file" class="form-control" name="background" accept=".jpg,.jpeg,.png,.webp" required>
                                    <small class="text-muted">Immagine di sfondo per l'intero sito (consigliato 1920x1080px)</small>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-upload me-2"></i>Carica Sfondo Globale
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- TAB HEADER & FOOTER -->
        <div class="tab-pane fade" id="tab-header-footer">
            <div class="row g-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-4"><i class="fa-solid fa-heading me-2"></i>Header Personalizzato</h5>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="action" value="update_header">
                                <div class="mb-3">
                                    <label for="header_content" class="form-label">Contenuto Header HTML</label>
                                    <textarea class="form-control font-monospace" id="header_content" name="header_content" rows="10"><?= htmlspecialchars($config['header_content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                    <small class="text-muted">Puoi usare HTML. Lascia vuoto per header di default.</small>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-save me-2"></i>Salva Header
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-4"><i class="fa-solid fa-shoe-prints me-2"></i>Footer Personalizzato</h5>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="action" value="update_footer">
                                <div class="mb-3">
                                    <label for="footer_content" class="form-label">Contenuto Footer HTML</label>
                                    <textarea class="form-control font-monospace" id="footer_content" name="footer_content" rows="10"><?= htmlspecialchars($config['footer_content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                    <small class="text-muted">Puoi usare HTML. Lascia vuoto per footer di default.</small>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-save me-2"></i>Salva Footer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- TAB SEZIONI -->
        <div class="tab-pane fade" id="tab-sections">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i class="fa-solid fa-plus-circle me-2"></i>Aggiungi Nuova Sezione</h5>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="add_section">
                        <div class="col-md-4">
                            <label for="menu_label" class="form-label">Etichetta Menu *</label>
                            <input type="text" class="form-control" id="menu_label" name="menu_label" placeholder="es: Chi Sono" required>
                        </div>
                        <div class="col-md-8">
                            <label for="section_title" class="form-label">Titolo Sezione</label>
                            <input type="text" class="form-control" id="section_title" name="section_title" placeholder="es: Benvenuto nel mio spazio">
                        </div>
                        <div class="col-12">
                            <label for="section_content" class="form-label">Contenuto Sezione</label>
                            <textarea class="form-control" id="section_content" name="section_content" rows="4" placeholder="Inserisci HTML o testo..."></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success">
                                <i class="fa-solid fa-plus me-2"></i>Aggiungi Sezione
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (empty($sections)): ?>
            <div class="alert alert-info">
                <i class="fa-solid fa-info-circle me-2"></i>Nessuna sezione presente. Aggiungi la prima sezione sopra!
            </div>
            <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i class="fa-solid fa-list me-2"></i>Sezioni Esistenti</h5>
                    <div class="alert alert-info alert-sm mb-3" style="font-size: 0.9rem;">
                        <i class="fa-solid fa-arrows-up-down me-2"></i>
                        <strong>Suggerimento:</strong> Puoi trascinare le righe per riordinare le sezioni. L'ordine si salverà automaticamente.
                    </div>
                    <style>
                        #sections-tbody tr {
                            transition: opacity 0.2s ease;
                        }
                        #sections-tbody tr:hover {
                            background-color: rgba(0, 123, 255, 0.05) !important;
                        }
                    </style>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">Ordine</th>
                                    <th>Menu</th>
                                    <th>Titolo</th>
                                    <th>Stato</th>
                                    <th style="width: 200px;">Azioni</th>
                                </tr>
                            </thead>
                            <tbody id="sections-tbody">
                                <?php foreach ($sections as $section): ?>
                                <tr data-section-id="<?= $section['id'] ?>">
                                    <td class="text-center"><?= $section['section_order'] ?></td>
                                    <td><strong><?= htmlspecialchars($section['menu_label'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= htmlspecialchars($section['section_title'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if ($section['enabled']): ?>
                                        <span class="badge bg-success">Attiva</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Disattiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editSection(<?= $section['id'] ?>)">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-info" onclick="uploadSectionBg(<?= $section['id'] ?>)">
                                            <i class="fa-solid fa-image"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteSection(<?= $section['id'] ?>)">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- TAB TEMA -->
        <div class="tab-pane fade" id="tab-theme">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i class="fa-solid fa-palette me-2"></i>Seleziona Tema Predefinito</h5>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="update_general">
                        <input type="hidden" name="site_title" value="<?= htmlspecialchars($config['site_title'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="<?= $config['enabled'] ? 'enabled' : 'disabled' ?>" value="1">
                        
                        <div class="row g-4">
                            <?php
                            $themes = [
                                'modern_minimal' => ['nome' => 'Modern Minimal', 'desc' => 'Design pulito e minimalista con spazi bianchi', 'colori' => ['#2d3748', '#4a5568', '#3182ce']],
                                'dark_professional' => ['nome' => 'Dark Professional', 'desc' => 'Sfondo scuro con accenti turchese', 'colori' => ['#1a202c', '#2d3748', '#4fd1c5']],
                                'creative_portfolio' => ['nome' => 'Creative Portfolio', 'desc' => 'Gradienti arancioni per portfolio creativi', 'colori' => ['#f56565', '#ed8936', '#f6ad55']],
                                'business_classic' => ['nome' => 'Business Classic', 'desc' => 'Stile corporate blu formale', 'colori' => ['#1e40af', '#3b82f6', '#60a5fa']],
                                'tech_gradient' => ['nome' => 'Tech Gradient', 'desc' => 'Gradienti viola moderni e tech', 'colori' => ['#667eea', '#764ba2', '#f093fb']]
                            ];
                            
                            foreach ($themes as $key => $theme):
                            ?>
                            <div class="col-md-6">
                                <div class="card h-100 <?= $config['theme_preset'] === $key ? 'border-primary' : '' ?>">
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="theme_preset" 
                                                   id="theme_<?= $key ?>" value="<?= $key ?>" 
                                                   <?= $config['theme_preset'] === $key ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold" for="theme_<?= $key ?>">
                                                <?= $theme['nome'] ?>
                                            </label>
                                        </div>
                                        <p class="text-muted small mb-2"><?= $theme['desc'] ?></p>
                                        <div class="d-flex gap-2">
                                            <?php foreach ($theme['colori'] as $colore): ?>
                                            <div style="width: 30px; height: 30px; background: <?= $colore ?>; border-radius: 50%;"></div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fa-solid fa-check me-2"></i>Applica Tema Selezionato
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Section -->
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" id="editSectionForm">
                <div class="modal-header">
                    <h5 class="modal-title">Modifica Sezione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="edit_section">
                    <input type="hidden" name="section_id" id="edit_section_id">
                    
                    <div class="mb-3">
                        <label for="edit_menu_label" class="form-label">Etichetta Menu *</label>
                        <input type="text" class="form-control" id="edit_menu_label" name="menu_label" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_section_title" class="form-label">Titolo Sezione</label>
                        <input type="text" class="form-control" id="edit_section_title" name="section_title">
                    </div>
                    <div class="mb-3">
                        <label for="edit_section_content" class="form-label">Contenuto</label>
                        <textarea class="form-control" id="edit_section_content" name="section_content" rows="8"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="edit_enabled" name="enabled" checked>
                            <label class="form-check-label" for="edit_enabled">Sezione Attiva</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva Modifiche</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Upload Section Background -->
<div class="modal fade" id="uploadSectionBgModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Gestisci Sfondo Sezione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="upload_section_bg">
                    <input type="hidden" name="section_id" id="upload_section_id">
                    
                    <!-- Anteprima sfondo attuale -->
                    <div id="bg-preview-container" class="mb-3" style="display: none;">
                        <label class="form-label">Sfondo Attuale</label>
                        <div style="border: 1px solid #dee2e6; border-radius: 4px; overflow: hidden; height: 150px;">
                            <img id="bg-preview" src="" alt="Anteprima" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <button type="button" class="btn btn-sm btn-danger mt-2 w-100" id="delete-bg-btn" onclick="deleteSectionBg()">
                            <i class="fa-solid fa-trash me-1"></i>Elimina Sfondo
                        </button>
                    </div>
                    
                    <!-- Form upload -->
                    <div class="mb-3">
                        <label for="section_bg" class="form-label">Carica Nuova Immagine</label>
                        <input type="file" class="form-control" id="section_bg" name="section_bg" accept=".jpg,.jpeg,.png,.webp">
                        <small class="text-muted">Formati: JPG, PNG, WebP</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                    <button type="submit" class="btn btn-primary" id="upload-btn">Carica</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const sections = <?= json_encode($sections) ?>;

function editSection(id) {
    const section = sections.find(s => s.id == id);
    if (!section) return;
    
    document.getElementById('edit_section_id').value = section.id;
    document.getElementById('edit_menu_label').value = section.menu_label;
    document.getElementById('edit_section_title').value = section.section_title || '';
    document.getElementById('edit_section_content').value = section.section_content || '';
    document.getElementById('edit_enabled').checked = section.enabled == 1;
    
    new bootstrap.Modal(document.getElementById('editSectionModal')).show();
}

function uploadSectionBg(id) {
    const section = sections.find(s => s.id == id);
    document.getElementById('upload_section_id').value = id;
    document.getElementById('section_bg').value = '';
    document.getElementById('upload-btn').textContent = 'Carica';
    document.getElementById('upload-btn').disabled = false;
    
    const previewContainer = document.getElementById('bg-preview-container');
    if (section && section.background_image) {
        const bgPath = '<?= BASE_PATH ?>assets/personal_site/backgrounds/' + section.background_image;
        document.getElementById('bg-preview').src = bgPath;
        previewContainer.style.display = 'block';
    } else {
        previewContainer.style.display = 'none';
    }
    
    new bootstrap.Modal(document.getElementById('uploadSectionBgModal')).show();
}

function deleteSectionBg() {
    if (!confirm('Sei sicuro di voler eliminare lo sfondo di questa sezione?')) return;
    
    const sectionId = document.getElementById('upload_section_id').value;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="action" value="delete_section_bg">
        <input type="hidden" name="section_id" value="${sectionId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function deleteSection(id) {
    if (!confirm('Sei sicuro di voler eliminare questa sezione?')) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="action" value="delete_section">
        <input type="hidden" name="section_id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// === GESTIONE TAB PERSISTENZA ===
// Salva il tab attivo quando viene cliccato
document.querySelectorAll('[role="tablist"] .nav-link').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function (e) {
        const tabId = e.target.getAttribute('href');
        sessionStorage.setItem('activeSettingsTab', tabId);
    });
});

// Ripristina il tab salvato al caricamento della pagina
window.addEventListener('load', function() {
    const savedTab = sessionStorage.getItem('activeSettingsTab');
    if (savedTab) {
        const tabElement = document.querySelector(`[data-bs-toggle="tab"][href="${savedTab}"]`);
        if (tabElement) {
            new bootstrap.Tab(tabElement).show();
        }
    }
});

// === DRAG & DROP SEZIONI ===
const tbody = document.getElementById('sections-tbody');
let draggedRow = null;

if (tbody) {
    // Abilita drag su ogni riga
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(row => {
        row.draggable = true;
        row.style.cursor = 'move';
        
        row.addEventListener('dragstart', function(e) {
            draggedRow = this;
            this.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
        });
        
        row.addEventListener('dragend', function(e) {
            this.style.opacity = '1';
            draggedRow = null;
        });
        
        row.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            if (draggedRow && draggedRow !== this) {
                const rect = this.getBoundingClientRect();
                const midpoint = rect.top + rect.height / 2;
                
                if (e.clientY < midpoint) {
                    this.parentNode.insertBefore(draggedRow, this);
                } else {
                    this.parentNode.insertBefore(draggedRow, this.nextSibling);
                }
            }
        });
        
        row.addEventListener('drop', function(e) {
            e.preventDefault();
        });
    });
    
    // Salva il nuovo ordine
    function saveNewOrder() {
        const rows = tbody.querySelectorAll('tr');
        const sectionOrders = {};
        let order = 1;
        
        rows.forEach(row => {
            const sectionId = row.getAttribute('data-section-id');
            sectionOrders[sectionId] = order;
            row.querySelector('td:first-child').textContent = order;
            order++;
        });
        
        // Invia al server
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="reorder_sections">
            <input type="hidden" name="section_orders" value='${JSON.stringify(sectionOrders)}'>
        `;
        document.body.appendChild(form);
        form.submit();
    }
    
    // Salva ordine con un piccolo ritardo dopo l'ultimo drag
    let saveTimeout;
    rows.forEach(row => {
        row.addEventListener('dragend', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(saveNewOrder, 500);
        });
    });
}
</script>

<?php include 'includes/footer.php'; ?>
