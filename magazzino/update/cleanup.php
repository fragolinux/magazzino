<?php
/**
 * Script di pulizia finale per l'aggiornamento
 * Questo file non viene mai sovrascritto durante gli aggiornamenti
 */

// Percorso alla cartella update
$updateDir = __DIR__;

// Ottieni tmpDir e altri parametri dalla sessione o da GET
$tmpDir = isset($_GET['tmpDir']) ? $_GET['tmpDir'] : null;
$cleanupZips = isset($_GET['cleanup_zips']) && $_GET['cleanup_zips'] === '1';

// Elimina cartella temporanea se specificata e esiste
if ($tmpDir && is_dir($tmpDir)) {
    rrmdir($tmpDir);
}

// Elimina tutte le cartelle temporanee _temp_*
$tempDirs = glob($updateDir . DIRECTORY_SEPARATOR . '_temp_*', GLOB_ONLYDIR);
foreach ($tempDirs as $dir) {
    if (is_dir($dir)) {
        rrmdir($dir);
    }
}

// Elimina tutti i file ZIP se richiesto
if ($cleanupZips) {
    $zipFiles = glob($updateDir . DIRECTORY_SEPARATOR . '*.zip');
    foreach ($zipFiles as $zf) {
        if (!is_file($zf)) continue;
        if (!str_ends_with(strtolower($zf), '.zip')) continue;
        if (!is_writable($zf)) {
            @chmod($zf, 0666);
        }
        @unlink($zf);
    }
}
?>