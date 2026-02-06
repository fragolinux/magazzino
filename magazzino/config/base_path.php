<?php
/**
 * Configurazione Percorso Base
 * Rileva automaticamente se il sito è su localhost/magazzino o su dominio custom
 * 
 * Su localhost: define BASE_PATH = '/magazzino/'
 * Su dominio: define BASE_PATH = '/'
 */

if (!defined('BASE_PATH')) {
    // Rileva il percorso della cartella corrente rispetto alla root del web
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Normalizza gli slash: converti backslash a forward slash (Windows compatibility)
    $scriptDir = str_replace('\\', '/', $scriptDir);
    
    // Logica intelligente: se il percorso contiene /magazzino/, la base è /magazzino/
    // Altrimenti è / (siamo su production domain o subdirectory)
    if (strpos($scriptDir, '/magazzino') !== false) {
        // Siamo su localhost/magazzino/warehouse o localhost/magazzino/includes ecc
        $basePath = '/magazzino/';
    } else {
        // Siamo su production domain root (/warehouse/..., /includes/..., ecc)
        $basePath = '/';
    }
    
    define('BASE_PATH', $basePath);
}

// Variabile globale per i template (retrocompatibilità)
$basePath = BASE_PATH;
?>