<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-02-02 14:16:59 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-02 19:52:12
*/

/*
 * Homepage Personale Pubblica
 * Pagina landing configurabile per presentazione personale/aziendale
 */

require_once 'includes/session_config.php';
session_start();
require_once 'includes/db_connect.php';

// Verifica se il sito personale è attivo
$config = $pdo->query("SELECT * FROM personal_site_config ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$config || !$config['enabled']) {
    // Se non attivo, redirect al login
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

// Carica sezioni
$sections = $pdo->query("SELECT * FROM personal_site_sections WHERE enabled = 1 ORDER BY section_order ASC")->fetchAll(PDO::FETCH_ASSOC);

// Includi header personalizzato
include 'includes/personal_header.php';
?>
<?php if (!empty($config['background_image'])): ?>
<style>
    body.personal-site-container {
        background-image: url('<?= BASE_PATH ?>assets/personal_site/backgrounds/<?= basename($config['background_image']) ?>') !important;
        background-size: cover !important;
        background-position: center !important;
        background-attachment: fixed !important;
        background-color: transparent !important;
    }
    .personal-section {
        background: rgba(255, 255, 255, 0.95) !important;
    }
    .theme-dark-professional .personal-section {
        background: rgba(26, 32, 44, 0.95) !important;
    }
</style>
<?php endif; ?>

<style>
<?php 
$sections_static = $pdo->query("SELECT id, background_image FROM personal_site_sections WHERE enabled = 1 AND background_image IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
foreach ($sections_static as $sec) { 
    echo "#section-{$sec['id']}.with-bg { background-image: url('" . BASE_PATH . "assets/personal_site/backgrounds/" . basename($sec['background_image']) . "') !important; background-size: cover !important; background-position: center !important; }\n";
}
?>
</style>

<main>
    <?php if (empty($sections)): ?>
    <section class="personal-section" id="home" style="min-height: 70vh; display: flex; align-items: center;">
        <div class="container text-center">
            <div class="scroll-animate">
                <h1 class="display-3 fw-bold mb-4">Benvenuto nel mio sito!</h1>
                <p class="lead mb-4">Questo è il tuo spazio personale completamente configurabile.</p>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
                <a href="<?= BASE_PATH ?>personal_home_settings.php" class="btn btn-primary btn-lg">
                    <i class="fa-solid fa-gear me-2"></i>Configura Sezioni
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php else: ?>
    <?php foreach ($sections as $index => $section): 
        $sectionId = 'section-' . $section['id'];
        $bgImage = !empty($section['background_image']) ? '<?= BASE_PATH ?>assets/personal_site/backgrounds/' . basename($section['background_image']) : null;
        $hasBg = !empty($bgImage);
        $isDark = strpos($config['theme_preset'], 'dark') !== false;
    ?>
    <section 
        class="personal-section <?= $hasBg ? 'with-bg' : '' ?> <?= $isDark ? 'dark' : '' ?>" 
        id="<?= $sectionId ?>"
        <?php if ($hasBg): ?>
        style="background-image: url('<?= $bgImage ?>');"
        <?php endif; ?>
    >
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <?php if (!empty($section['section_title'])): ?>
                    <h2 class="text-center scroll-animate mb-4">
                        <?= htmlspecialchars($section['section_title'], ENT_QUOTES, 'UTF-8') ?>
                    </h2>
                    <?php endif; ?>
                    
                    <?php if (!empty($section['section_content'])): ?>
                    <div class="scroll-animate">
                        <?= $section['section_content'] ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php include 'includes/personal_footer.php'; ?>
