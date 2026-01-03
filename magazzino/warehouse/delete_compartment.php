<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 19:54:10 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-20 23:24:11
*/

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Controllo ID valido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: compartments.php");
    exit;
}

$id = intval($_GET['id']);

// Recupero la posizione del compartimento prima di cancellarlo
$stmt = $pdo->prepare("SELECT location_id, code FROM compartments WHERE id = ?");
$stmt->execute([$id]);
$compartment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compartment) {
    $_SESSION['error'] = "Compartimento non trovato.";
    header("Location: compartments.php");
    exit;
}

$location_id = intval($compartment['location_id']);

// Controllo se contiene componenti
$stmt = $pdo->prepare("SELECT COUNT(*) FROM components WHERE compartment_id = ?");
$stmt->execute([$id]);
$count = $stmt->fetchColumn();

if ($count > 0) {
    $_SESSION['error'] = "Impossibile eliminare il compartimento <strong>" . htmlspecialchars($compartment['code']) . "</strong>: contiene componenti.";
    header("Location: compartments.php?location_id=" . $location_id);
    exit;
}

// Eseguo la cancellazione
$stmt = $pdo->prepare("DELETE FROM compartments WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['success'] = "Compartimento \"" . htmlspecialchars($compartment['code']) . "\" eliminato con successo.";

header("Location: compartments.php?location_id=" . $location_id);
exit;