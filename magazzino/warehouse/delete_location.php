<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-07 13:20:24 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-07 13:24:58
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Solo admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Accesso negato: permessi insufficienti.";
    header("Location: locations.php");
    exit;
}

// Controllo ID valido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: locations.php");
    exit;
}

$id = intval($_GET['id']);

// Recupero la posizione
$stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
$stmt->execute([$id]);
$location = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$location) {
    $_SESSION['error'] = "Posizione non trovata.";
    header("Location: locations.php");
    exit;
}

// Controllo se contiene componenti
$stmt = $pdo->prepare("SELECT COUNT(*) FROM components WHERE location_id = ?");
$stmt->execute([$id]);
$count = $stmt->fetchColumn();

if ($count > 0) {
    $_SESSION['error'] = "Impossibile eliminare la posizione " . htmlspecialchars($location['name']) . ": contiene componenti.";
    header("Location: locations.php");
    exit;
}

// Controllo se contiene comparti
$stmt = $pdo->prepare("SELECT COUNT(*) FROM compartments WHERE location_id = ?");
$stmt->execute([$id]);
$count_compartments = $stmt->fetchColumn();

if ($count_compartments > 0) {
    $_SESSION['error'] = "Impossibile eliminare la posizione " . htmlspecialchars($location['name']) . ": contiene comparti.";
    header("Location: locations.php");
    exit;
}

// Eseguo la cancellazione
$stmt = $pdo->prepare("DELETE FROM locations WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['success'] = "Posizione \"" . htmlspecialchars($location['name']) . "\" eliminata con successo.";

header("Location: locations.php");
exit;
?>