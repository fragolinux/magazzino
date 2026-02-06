<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:45:58 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-02 20:21:13
*/

require 'session_config.php';
session_start();

// Assicura BASE_PATH sempre disponibile
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../config/base_path.php';
}

// Controlla se il database ha le tabelle necessarie
try {
    require_once __DIR__ . '/db_connect.php';
    
    // Verifica se la tabella users esiste
    $checkTables = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersTableExists = $checkTables && $checkTables->rowCount() > 0;
    
    if (!$usersTableExists) {
        // Database esiste ma è vuoto, reindirizza a inizializzazione
        if (!defined('BASE_PATH')) {
            require_once __DIR__ . '/../config/base_path.php';
        }
        header('Location: ' . BASE_PATH . 'update/init_database.php');
        exit;
    }
} catch (PDOException $e) {
    // Errore di connessione, lascia che db_connect.php gestisca il redirect
    if (!defined('BASE_PATH')) {
        require_once __DIR__ . '/../config/base_path.php';
    }
    header('Location: ' . BASE_PATH . 'update/setup_db_wizard.php');
    exit;
}

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
            setcookie('remember_token', $new_token, strtotime('+1 year'), BASE_PATH, '', $secure, true);
            return;
        }
    } catch (Throwable $e) {
        // If DB/table not available or other error, ignore and fallthrough to redirect
    }
}

// Verifica se il sito personale è attivo e se NON sei loggato
// Se attivo, redirect al sito personale INVECE di richiedere il login
try {
    require_once __DIR__ . '/db_connect.php';
    $personalSiteCheck = $pdo->query("SELECT enabled FROM personal_site_config WHERE id = 1 LIMIT 1");
    $personalSiteConfig = $personalSiteCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($personalSiteConfig && $personalSiteConfig['enabled'] == 1) {
        // Se non loggato E sito personale attivo → redirect al sito personale
        header('Location: ' . BASE_PATH . 'personal_home.php');
        exit;
    }
} catch (Exception $e) {
    // Se la tabella non esiste, continua normalmente
}

// Not authenticated: redirect to login
header('Location: ' . BASE_PATH . 'login.php');
exit;
?>
