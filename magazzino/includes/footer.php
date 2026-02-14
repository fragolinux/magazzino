<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:49:31 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-09 23:39:17
*/
// 2026-02-01: Aggiunta barra del footer con data/ora, autore e versione
// 2026-02-09: Footer sticky, migliorato responsive e design

// Recupera versione dal database
$version_info = null;
try {
    $stmt = $pdo->query("SELECT version, applied_at FROM db_version ORDER BY CAST(SUBSTRING_INDEX(version, '.', 1) AS UNSIGNED) DESC, CAST(SUBSTRING_INDEX(version, '.', -1) AS UNSIGNED) DESC LIMIT 1");
    $version_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $version_info = ['version' => 'unknown', 'applied_at' => date('Y-m-d H:i:s')];
}

// Anno corrente per copyright
$currentYear = date('Y');
?>
</div>

<footer class="app-footer mt-auto">
  <div class="container-fluid">
    <div class="row align-items-center g-1 py-2">
      <!-- Data/ora a sinistra -->
      <div class="col-12 col-md-4 text-center text-md-start">
        <span class="footer-badge">
          <i class="fa-solid fa-clock me-1"></i><?php echo date('d/m/Y H:i'); ?>
        </span>
      </div>
      
      <div class="col-12 col-md-4 text-center">
        <small class="footer-author">
          Autore: <a href="https://www.youtube.com/@rg4tech" target="_blank" class="footer-link">Gabriele Riva (RG4Tech Youtube Channel)</a>
        </small>
      </div>

      <div class="col-12 col-md-4 text-center text-md-end">
        <span class="footer-version" title="Versione <?php echo htmlspecialchars($version_info['version']); ?>">
          <i class="fa-solid fa-tag me-1"></i>v<?php echo htmlspecialchars($version_info['version']); ?>
          <span class="d-none d-md-inline">• <?php echo date('d/m/Y', strtotime($version_info['applied_at'])); ?></span>
        </span>
      </div>
    </div>
  </div>
</footer>

<script src="<?= BASE_PATH ?>assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>