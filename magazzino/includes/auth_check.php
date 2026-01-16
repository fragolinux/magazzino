<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:45:58 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-15
*/

require 'session_config.php';
session_start();

// If already logged in, nothing to do
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    return;
}

// Try automatic login via remember_token cookie (if present)
if (isset($_COOKIE['remember_token'])) {
    try {
        require_once __DIR__ . '/db_connect.php';
        $token = $_COOKIE['remember_token'];
        $stmt = $pdo->prepare("SELECT u.id, u.username, u.role, rt.id AS token_id FROM remember_tokens rt INNER JOIN users u ON rt.user_id = u.id WHERE rt.token = ? AND rt.expires > NOW()");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // Set session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            // Refresh token to extend validity and rotate token
            $new_token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 year'));
            $upd = $pdo->prepare("UPDATE remember_tokens SET token = ?, expires = ? WHERE id = ?");
            $upd->execute([$new_token, $expires, $row['token_id']]);
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            setcookie('remember_token', $new_token, strtotime('+1 year'), '/', '', $secure, true);
            return;
        }
    } catch (Throwable $e) {
        // If DB/table not available or other error, ignore and fallthrough to redirect
    }
}

// Not authenticated: redirect to login
header('Location: /magazzino/login.php');
exit;
?>