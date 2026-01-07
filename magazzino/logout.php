<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:50:47 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-07 14:32:15
*/

session_start();
require 'includes/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;

// Determina se siamo su HTTPS per impostare il flag `secure` del cookie
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

// Elimina il cookie di ricordo
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    // Elimina il token dal database (rimuovi per token per sicurezza anche se sessione non valida)
    $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?");
    $stmt->execute([$token]);

    // Elimina il cookie dal browser
    setcookie('remember_token', '', time() - 3600, '/', '', $secure, true);
    unset($_COOKIE['remember_token']);
}

// Distruggi la sessione
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>