<?php
/*
 * @Author: RG4Tech
 * @Date: 2026-02-08
 * @Description: Elimina Fornitore
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
    header('Location: fornitori.php');
    exit;
}

try {
    // Elimina fornitore (i progetti associati avranno ks_fornitore = NULL grazie a ON DELETE SET NULL)
    $stmt = $pdo->prepare("DELETE FROM fornitori WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['success'] = 'Fornitore eliminato con successo.';
} catch (PDOException $e) {
    $_SESSION['error'] = 'Errore durante l\'eliminazione: ' . $e->getMessage();
}

header('Location: fornitori.php');
exit;
