<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-08
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-08
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Controllo ID valido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: locali.php");
    exit;
}

$id = intval($_GET['id']);

// Recupero il locale
$stmt = $pdo->prepare("SELECT * FROM locali WHERE id = ?");
$stmt->execute([$id]);
$locale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$locale) {
    $_SESSION['error'] = "Locale non trovato.";
    header("Location: locali.php");
    exit;
}

// Controllo se ci sono posizioni collegate
$stmt = $pdo->prepare("SELECT COUNT(*) FROM locations WHERE locale_id = ?");
$stmt->execute([$id]);
$locationCount = $stmt->fetchColumn();

if ($locationCount > 0) {
    $_SESSION['error'] = "Impossibile eliminare il locale \"{$locale['name']}\": ci sono {$locationCount} posizioni collegate. Rimuovi prima le posizioni o assegnale ad altro locale.";
    header("Location: locali.php");
    exit;
}

// Eliminazione locale
$stmt = $pdo->prepare("DELETE FROM locali WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['success'] = "Locale \"{$locale['name']}\" eliminato con successo.";
header("Location: locali.php");
exit;
