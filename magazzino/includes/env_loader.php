<?php
/*
 * Simple Environment Variables Loader
 * Carica variabili d'ambiente da file .env
 * In produzione, usa una soluzione più robusta come vlucas/phpdotenv
 */

if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Salta commenti
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse chiave=valore
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Rimuovi virgolette se presenti
                $value = trim($value, '"\'');

                // Imposta variabile d'ambiente se non già impostata
                if (!getenv($key)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }

        return true;
    }
}

// Carica file .env se esiste
loadEnv(__DIR__ . '/../.env');
?>