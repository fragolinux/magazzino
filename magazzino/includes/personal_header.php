<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-02-02 14:16:14 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-02 19:51:35
*/

/*
 * Header Personalizzato per Sito Personale
 * Completamente configurabile dal pannello admin
 */

// Carica configurazione sito personale (Prendi l'unica configurazione esistente o la più recente)
$config = $pdo->query("SELECT * FROM personal_site_config ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    // Configurazione di default se non esiste nel DB
    $config = [
        'site_title' => 'Il Mio Sito Personale',
        'logo_path' => null,
        'favicon_path' => null,
        'theme_preset' => 'modern_minimal',
        'header_content' => null
    ];
}

$siteTitle = htmlspecialchars($config['site_title'] ?? 'Il Mio Sito Personale', ENT_QUOTES, 'UTF-8');
$logoPath = $config['logo_path'] ? BASE_PATH . 'assets/personal_site/' . basename($config['logo_path']) : BASE_PATH . 'assets/img/logo.jpg';
$faviconPath = $config['favicon_path'] ? BASE_PATH . 'assets/personal_site/' . basename($config['favicon_path']) : BASE_PATH . 'favicon.ico';
$themeName = $config['theme_preset'] ?? 'modern_minimal';
// Correggi discrepanza tra DB (underscore) e CSS (trattino)
$themeClass = 'theme-' . str_replace('_', '-', $themeName);
?>
<!doctype html>
<html lang="it">
<head>  
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    
    <title><?= $siteTitle ?></title>
    <link rel="icon" href="<?= $faviconPath ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?= $faviconPath ?>" type="image/x-icon">
    <link href="<?= BASE_PATH ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_PATH ?>assets/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_PATH ?>assets/css/personal_themes.css?v=<?= time() ?>" rel="stylesheet">
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            transition: background-color 0.5s ease, color 0.5s ease;
        }
        .scroll-animate {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }       
        .scroll-animate.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body class="<?= $themeClass ?> personal-site-container">
    
    <?php include __DIR__ . '/navbar_personal.php'; ?>
    
    <?php if (!empty($config['header_content'])): ?>
    <header class="personal-header">
        <div class="container">
            <?= $config['header_content'] ?>
        </div>
    </header>
    <?php endif; ?>
