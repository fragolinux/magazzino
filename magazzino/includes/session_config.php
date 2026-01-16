<?php
/*
 * Configurazioni di sicurezza per le sessioni
 * Questo file deve essere incluso PRIMA di session_start()
 */

// Configurazioni di sicurezza per le sessioni (solo se headers non ancora inviati)
if (!headers_sent()) {
    // Solo HTTPS (in produzione)
    ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
    // No accesso via JavaScript
    ini_set('session.cookie_httponly', '1');
    // Protezione CSRF avanzata
    ini_set('session.cookie_samesite', 'Strict');
    
    // Headers di sicurezza HTTP
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // HSTS solo se HTTPS
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
}
?>