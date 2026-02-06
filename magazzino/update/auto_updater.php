<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-02-03 11:25:00 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2026-02-03 11:25:00
*/

/*
 * Auto Updater - Gestore Aggiornamenti Automatici
 * 
 * Questo file controlla se ci sono migrazioni del database pendenti e le applica.
 * Viene incluso all'avvio dell'applicazione.
 * 
 * Logica:
 * 1. Verifica se l'utente è amministratore (Security Check)
 * 2. Verifica connessione DB (implicita se incluso dopo db_connect)
 * 3. Usa MigrationManager per controllare e applicare aggiornamenti
 */

require_once __DIR__ . '/migration_manager.php';

// Esegui controllo solo se l'utente è loggato come Admin
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    
    // Inizializza Migration Manager
    // Usa la connessione $pdo globale
    if (isset($pdo)) {
        try {
            $migrationManager = new MigrationManager($pdo);
            
            // Verifica se ci sono migrazioni pendenti PRIMA di applicarle per evitare query inutili
            // Nota: getPendingMigrations non è pubblico in MigrationManager, ma runPendingMigrations fa il check
            // Modifichiamo runPendingMigrations per fare il controllo internamente
            
            $result = $migrationManager->runPendingMigrations();
            
            if (!empty($result['applied'])) {
                // Mostra un report chiaro all'amministratore
                echo '<div class="alert alert-info alert-dismissible fade show m-3" role="alert" style="z-index: 9999; position: relative;">';
                echo '<h5 class="alert-heading"><i class="fas fa-database"></i> Aggiornamento Database Eseguito</h5>';
                echo '<p>Il sistema ha rilevato nuove versioni del database e ha applicato le migrazioni necessarie:</p>';
                
                echo '<ul class="list-group list-group-flush mb-3">';
                foreach ($result['applied'] as $applied) {
                    $ver = htmlspecialchars($applied['version']);
                    $status = $applied['status'] === 'success' ? '<span class="badge bg-success">Successo</span>' : '<span class="badge bg-danger">Errore</span>';
                    
                    echo "<li class=\"list-group-item d-flex justify-content-between align-items-center\">
                            Versione {$ver}
                            {$status}
                          </li>";
                    
                     if (!empty($applied['stats']['details'])) {
                        echo '<li class="list-group-item bg-light"><small>';
                        foreach($applied['stats']['details'] as $detail) {
                            $icon = strpos($detail, '✓') !== false ? '<span class="text-success">✓</span>' : (strpos($detail, '✗') !== false ? '<span class="text-danger">✗</span>' : '<span class="text-muted">⊘</span>');
                            $text = str_replace(['✓', '✗', '⊘'], '', $detail);
                            echo "{$icon} {$text}<br>";
                        }
                        echo '</small></li>';
                    }
                }
                echo '</ul>';
                
                if ($result['message']) {
                    echo '<p class="mb-0">' . htmlspecialchars($result['message']) . '</p>';
                }
                
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            // In caso di errore critico nell'updater, avvisa l'admin
            echo '<div class="alert alert-danger m-3" role="alert">';
            echo '<strong><i class="fas fa-exclamation-triangle"></i> Errore Sistema Aggiornamenti:</strong> ';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        }
    }
}
?>
