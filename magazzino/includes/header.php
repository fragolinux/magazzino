<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:48:46 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-02 19:50:59
*/

// 2026-01-03: Aggiunto link Info nel menu di navigazione
// 2026-01-04: Aggiunto link per installazione degli aggiornamenti
// 2026-01-08: Aggiunto supporto tema dark/light e locali
// 2026-01-09: Aggiunta pagina file orfani
// 2026-01-12: Aggiunta pagina profilo utente
// 2026-01-13: Diviso header in due file (header.php e navbar.php)
// 2026-02-01: Rimossi i riferimenti a Google Fonts e Cloudflare

require_once __DIR__ . '/../config/base_path.php';

// Leggi il tema dal DB o da file config
$appTheme = 'light'; // default
try {
    if (isset($pdo)) {
        $stmtTheme = $pdo->prepare("SELECT setting_value FROM setting WHERE setting_name = ? LIMIT 1");
        $stmtTheme->execute(['app_theme']);
        $rowTheme = $stmtTheme->fetch(PDO::FETCH_ASSOC);
        if ($rowTheme && in_array($rowTheme['setting_value'], ['light', 'dark'])) {
            $appTheme = $rowTheme['setting_value'];
        }
    }
} catch (Exception $e) {
    // Se la tabella non esiste o errore DB, prova a leggere da file
    $settingsConfig = @include __DIR__ . '/../config/settings.php';
    if ($settingsConfig && isset($settingsConfig['app_theme'])) {
        $appTheme = $settingsConfig['app_theme'];
    }
}
?>
<!doctype html>
<html lang="it" data-bs-theme="<?= $appTheme ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <?php if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'): ?>
    <meta http-equiv="Strict-Transport-Security" content="max-age=31536000; includeSubDomains">
    <?php endif; ?>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self';">
    
    <title>Magazzino Componenti</title>
    <link rel="icon" href="<?= BASE_PATH ?>favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="<?= BASE_PATH ?>favicon.ico" type="image/x-icon">
    <link href="<?= BASE_PATH ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_PATH ?>assets/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/jquery-ui.css">
    <script src="<?= BASE_PATH ?>assets/js/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_PATH ?>assets/js/jquery-ui.min.js"></script>
    <script>
        function applyTheme(theme) {
            document.documentElement.setAttribute('data-bs-theme', theme);
        }
    </script>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
