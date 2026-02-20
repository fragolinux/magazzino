<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-13
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-09 22:48:07
 * 
 * Menu di navigazione principale
 */
// aggiunto link alla generazione dei Barcode
// 2026-02-08: aggiunto link alla gestione progetti e fornitori

?>
<?php
// Determina pagina attiva per highlight menu
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Funzione helper per classe active
function isActive($pages, $dir = null) {
    global $currentPage, $currentDir;
    if ($dir !== null && $currentDir !== $dir) return '';
    if (is_array($pages)) {
        return in_array($currentPage, $pages) ? 'active' : '';
    }
    return $currentPage === $pages ? 'active' : '';
}
?>

<nav class="navbar navbar-expand-lg navbar-<?= $appTheme === 'dark' ? 'dark' : 'light' ?> shadow-sm sticky-top" 
     style="background: <?= $appTheme === 'dark' ? 'rgba(33,37,41,0.95)' : 'rgba(248,249,250,0.95)' ?> !important;">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= BASE_PATH ?>index.php">
      <img src="<?= BASE_PATH ?>assets/img/logo.jpg" alt="logo" class="me-2" style="height:40px;">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
      data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false"
      aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item">
            <a class="nav-link <?= isActive('components.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/components.php">
              <i class="fa-solid fa-microchip me-1"></i>Componenti
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= isActive('barcode_scan.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/barcode_scan.php">
              <i class="fa-solid fa-barcode me-1"></i>Barcode
            </a>
          </li>

          <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= in_array($currentPage, ['locali.php','locations.php','compartments.php','categories.php','hierarchy.php','recent_components.php','bulk_move_components.php','bulk_swap_components.php','low_stock.php','orphan_files.php','import_csv.php','export_csv.php','qrcodes.php','barcodes.php']) && $currentDir === 'warehouse' ? 'active' : '' ?>" href="#" id="magazzinoDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-warehouse me-1"></i>Magazzino
            </a>
            <ul class="dropdown-menu" aria-labelledby="magazzinoDropdown">
              <li>
                <a class="dropdown-item <?= isActive('locali.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/locali.php">
                  <i class="fa-solid fa-building me-2 text-secondary"></i>Locali
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= isActive('locations.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/locations.php">
                  <i class="fa-solid fa-location-dot me-2 text-secondary"></i>Posizioni
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= isActive('compartments.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/compartments.php">
                  <i class="fa-solid fa-boxes-stacked me-2 text-secondary"></i>Comparti
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= isActive('categories.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/categories.php">
                  <i class="fa-solid fa-tags me-2 text-secondary"></i>Categorie
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item <?= isActive('hierarchy.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/hierarchy.php">
                  <i class="fa-solid fa-sitemap me-2 text-primary"></i>Gerarchia completa
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= isActive('recent_components.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/recent_components.php">
                  <i class="fa-solid fa-clock me-2 text-info"></i>Ultimi componenti inseriti
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item <?= isActive('bulk_move_components.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/bulk_move_components.php">
                  <i class="fa-solid fa-right-left me-2 text-secondary"></i>Sposta componenti
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= isActive('bulk_swap_components.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/bulk_swap_components.php">
                  <i class="fa-solid fa-retweet me-2 text-secondary"></i>Scambia componenti
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= isActive('low_stock.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/low_stock.php">
                  <i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Sotto scorta
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item <?= isActive('orphan_files.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/orphan_files.php">
                  <i class="fa-solid fa-file-circle-question me-2 text-warning"></i>File orfani
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item <?= isActive('import_csv.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/import_csv.php">
                  <i class="fa-solid fa-file-import me-2 text-success"></i>Import CSV
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= isActive('export_csv.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/export_csv.php">
                  <i class="fa-solid fa-file-export me-2 text-info"></i>Export CSV
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item <?= isActive('qrcodes.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/qrcodes.php">
                  <i class="fa-solid fa-qrcode me-2 text-secondary"></i>Stampa QR Code
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= isActive('barcodes.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/barcodes.php">
                  <i class="fa-solid fa-barcode me-2 text-secondary"></i>Stampa Barcode
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= isActive('etichette.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/etichette.php">
                  <i class="fa-solid fa-tags me-2 text-secondary"></i>Stampa Etichette
                </a>
              </li>
            </ul>
          </li>
          <?php endif; ?>

          <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= in_array($currentPage, ['progetti.php','fornitori.php','boms.php']) && in_array($currentDir, ['warehouse','progetti','fornitori']) ? 'active' : '' ?>" href="#" id="progettiDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-folder-open me-1"></i>Progetti
            </a>
            <ul class="dropdown-menu" aria-labelledby="progettiDropdown">
              <li>
                <a class="dropdown-item <?= isActive('progetti.php') ?>" href="<?= BASE_PATH ?>warehouse/progetti/progetti.php">
                  <i class="fa-solid fa-clipboard-list me-2 text-primary"></i>Gestione Progetti
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= isActive('fornitori.php') ?>" href="<?= BASE_PATH ?>warehouse/fornitori/fornitori.php">
                  <i class="fa-solid fa-truck-field me-2 text-info"></i>Fornitori
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item <?= isActive('boms.php', 'warehouse') ?>" href="<?= BASE_PATH ?>warehouse/boms.php">
                  <i class="fa-solid fa-list-check me-2 text-secondary"></i>BOM Analyzer
                </a>
              </li>
            </ul>
          </li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav ms-auto align-items-center">
        <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item">
            <a class="nav-link <?= isActive('info.php') ?>" href="<?= BASE_PATH ?>info.php">
              <i class="fa-solid fa-circle-info me-1"></i>Info
            </a>
          </li>
          <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <li class="nav-item">
              <a class="nav-link <?= isActive('settings.php') ?>" href="<?= BASE_PATH ?>settings.php">
                <i class="fa-solid fa-gears me-1"></i>Settaggi
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= isActive('index.php', 'update') ?>" href="<?= BASE_PATH ?>update/index.php">
                <i class="fa-solid fa-download me-1"></i>Aggiornamento
              </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
              <a class="nav-link <?= isActive('profile.php') ?>" href="<?= BASE_PATH ?>profile.php" title="Profilo">
                <i class="fa-solid fa-user"></i>
              </a>
            </li>
          <li class="nav-item">
            <a class="nav-link" href="<?= BASE_PATH ?>logout.php">
              <i class="fa-solid fa-right-from-bracket me-1"></i>Logout (<?= htmlspecialchars($_SESSION['username']) ?>)
            </a>
          </li>
        <?php else: ?>
          <?php if (basename($_SERVER['PHP_SELF']) !== 'login.php'): ?>
          <li class="nav-item">
            <a class="nav-link <?= isActive('login.php') ?>" href="<?= BASE_PATH ?>login.php">
              <i class="fa-solid fa-right-to-bracket me-1"></i>Login
            </a>
          </li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-2">
