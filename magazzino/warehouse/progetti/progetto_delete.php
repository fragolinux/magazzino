<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: Elimina Progetto
 */

require_once '../../config/base_path.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';

// Verifica permessi admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header('Location: progetti.php');
    exit;
}

try {
    // Elimina progetto (i componenti associati verranno eliminati automaticamente grazie a ON DELETE CASCADE)
    $stmt = $pdo->prepare("DELETE FROM progetti WHERE id = ? AND stato = 'bozza'");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = 'Progetto eliminato con successo.';
    } else {
        $_SESSION['error'] = 'Impossibile eliminare il progetto. Verifica che sia in stato "bozza".';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Errore durante l\'eliminazione: ' . $e->getMessage();
}

header('Location: progetti.php');
exit;
