<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:49:31 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-03 11:27:00
*/
// 2026-02-01: Aggiunta barra del footer con data/ora, autore e versione

// Recupera versione dal database
$version_info = null;
try {
    $stmt = $pdo->query("SELECT version, applied_at FROM db_version ORDER BY version DESC LIMIT 1");
    $version_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Se la tabella non esiste o errore, mostra versione fittizia
    $version_info = ['version' => 'unknown', 'applied_at' => date('Y-m-d H:i:s')];
}
?>
</div>

<!-- Footer minimale -->
<footer class="mt-5" style="background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%); border-top: 1px solid #dee2e6; padding: 0.5rem 0;">
  <div class="container-fluid">
    <div class="row align-items-center g-1 px-2">
      <!-- Data e ora a sinistra -->
      <div class="col-md-4 text-center text-md-start">
        <small class="text-muted" style="font-size: 0.8rem;">
          <i class="fa-solid fa-clock me-1"></i><?php echo date('d/m/Y H:i'); ?>
        </small>
      </div>
      
      <!-- Autore al centro -->
      <div class="col-md-4 text-center">
        <small class="text-muted" style="font-size: 0.8rem;">
          Autore: <a href="https://www.youtube.com/@rg4tech" target="_blank" class="text-decoration-none" style="color: #667eea; font-weight: 600; transition: color 0.3s;">Gabriele Riva (RG4Tech Youtube Channel)</a>
        </small>
      </div>
      
      <!-- Versione e data a destra -->
      <div class="col-md-4 text-center text-md-end">
        <small class="text-muted" style="font-size: 0.8rem;">
          <i class="fa-solid fa-tag me-1"></i>v<?php echo htmlspecialchars($version_info['version']); ?> • <?php echo date('d/m/Y', strtotime($version_info['applied_at'])); ?>
        </small>
      </div>
    </div>
  </div>
</footer>

<script src="<?= BASE_PATH ?>assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>