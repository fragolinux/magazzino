<?php
/*
 * CSRF Protection Utilities
 * Genera e verifica token CSRF per proteggere le form
 */

if (session_status() === PHP_SESSION_NONE) {
    require 'session_config.php';
    session_start();
}

/**
 * Genera un token CSRF e lo salva in sessione
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica un token CSRF
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rigenera il token CSRF (per sicurezza dopo login/logout)
 */
function regenerate_csrf_token() {
    unset($_SESSION['csrf_token']);
    return generate_csrf_token();
}
?>