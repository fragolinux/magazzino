<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-13
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-15
 * 
 * Menu di navigazione principale
 */
// aggiunto link alla generazione dei Barcode

?>
<nav class="navbar navbar-expand-lg navbar-<?= $appTheme === 'dark' ? 'dark bg-dark' : 'light bg-light' ?> shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand" href="/magazzino/index.php">
      <img src="/magazzino/assets/img/logo.jpg" alt="logo" class="me-2" style="height:40px;">
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
            <a class="nav-link" href="/magazzino/warehouse/components.php">
              <i class="fa-solid fa-microchip me-1"></i>Componenti
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="/magazzino/warehouse/barcode_scan.php">
              <i class="fa-solid fa-barcode me-1"></i>Barcode
            </a>
          </li>

          <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="magazzinoDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-warehouse me-1"></i>Magazzino
            </a>
            <ul class="dropdown-menu" aria-labelledby="magazzinoDropdown">
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/locali.php">
                  <i class="fa-solid fa-building me-2 text-secondary"></i>Locali
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/locations.php">
                  <i class="fa-solid fa-location-dot me-2 text-secondary"></i>Posizioni
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/compartments.php">
                  <i class="fa-solid fa-boxes-stacked me-2 text-secondary"></i>Comparti
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/categories.php">
                  <i class="fa-solid fa-tags me-2 text-secondary"></i>Categorie
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/hierarchy.php">
                  <i class="fa-solid fa-sitemap me-2 text-primary"></i>Gerarchia completa
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/bulk_move_components.php">
                  <i class="fa-solid fa-right-left me-2 text-secondary"></i>Sposta componenti
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/bulk_swap_components.php">
                  <i class="fa-solid fa-retweet me-2 text-secondary"></i>Scambia componenti
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/low_stock.php">
                  <i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Sotto scorta
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/orphan_files.php">
                  <i class="fa-solid fa-file-circle-question me-2 text-warning"></i>File orfani
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/import_csv.php">
                  <i class="fa-solid fa-file-import me-2 text-success"></i>Import CSV
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/export_csv.php">
                  <i class="fa-solid fa-file-export me-2 text-info"></i>Export CSV
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/qrcodes.php">
                  <i class="fa-solid fa-qrcode me-2 text-secondary"></i>Stampa QR Code
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/barcodes.php">
                  <i class="fa-solid fa-barcode me-2 text-secondary"></i>Stampa Barcode
                </a>
              </li>
            </ul>
          </li>
          <?php endif; ?>

          <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="progettiDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-folder-open me-1"></i>Progetti
            </a>
            <ul class="dropdown-menu" aria-labelledby="progettiDropdown">
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/boms.php">
                  <i class="fa-solid fa-list-check me-2 text-primary"></i>BOM Analyzer
                </a>
              </li>
            </ul>
          </li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav ms-auto">
        <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item">
            <a class="nav-link" href="/magazzino/info.php">
              <i class="fa-solid fa-circle-info me-1"></i>Info
            </a>
          </li>
          <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <li class="nav-item">
              <a class="nav-link" href="/magazzino/settings.php">
                <i class="fa-solid fa-gears me-1"></i>Settaggi
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="/magazzino/update/index.php">
                <i class="fa-solid fa-download me-1"></i>Aggiornamento
              </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
              <a class="nav-link" href="/magazzino/profile.php" title="Profilo">
                <i class="fa-solid fa-user"></i>
              </a>
            </li>
          <li class="nav-item">
            <a class="nav-link" href="/magazzino/logout.php">
              <i class="fa-solid fa-right-from-bracket me-1"></i>Logout (<?= htmlspecialchars($_SESSION['username']) ?>)
            </a>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="/magazzino/login.php">
              <i class="fa-solid fa-right-to-bracket me-1"></i>Login
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-2">
