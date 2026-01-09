<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:03:48 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-08 16:41:01
*/
// 2026-01-08: Aggiunta card sotto scorta

/*
 * Magazzino Componenti – Software di gestione magazzino di componenti elettronici
 * Copyright (C) 2025  Gabriele Riva (RG4Tech Youtube Channel)
 *
 * Questo programma è software libero: puoi redistribuirlo e/o modificarlo
 * secondo i termini della GNU General Public License come pubblicata
 * dalla Free Software Foundation, sia la versione 3 della Licenza, sia
 * (a tua scelta) qualsiasi versione successiva.
 *
 * Questo programma è distribuito nella speranza che sia utile,
 * ma SENZA ALCUNA GARANZIA; senza neppure la garanzia implicita
 * di COMMERCIABILITÀ o di IDONEITÀ PER UNO SCOPO PARTICOLARE.
 * Vedi la GNU General Public License per ulteriori dettagli.
 *
 * Puoi trovare la licenza completa qui:
 * https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once 'includes/db_connect.php';
require_once 'includes/auth_check.php';

// Conteggi
$total_locali = $pdo->query("SELECT COUNT(*) FROM locali")->fetchColumn();
$total_locations = $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();
$total_compartments = $pdo->query("SELECT COUNT(*) FROM compartments")->fetchColumn();
$total_components = $pdo->query("SELECT COUNT(*) FROM components")->fetchColumn();
$total_quantity = $pdo->query("SELECT SUM(quantity) FROM components")->fetchColumn();
$low_stock_components = $pdo->query("SELECT COUNT(*) FROM components WHERE quantity_min IS NOT NULL AND quantity_min != 0 AND quantity < quantity_min")->fetchColumn();

include 'includes/header.php';
?>

<div class="container py-4">
  <h1 class="mb-4"><i class="fa-solid fa-warehouse me-2"></i>Dashboard Magazzino</h1>

  <div class="row g-3">
    <div class="col-md-3">
      <a href="warehouse/locali.php" class="text-decoration-none">
        <div class="card text-white bg-secondary shadow-sm">
          <div class="card-body">
            <h5 class="card-title"><i class="fa-solid fa-building me-2"></i>Locali</h5>
            <p class="card-text fs-3"><?= $total_locali ?></p>
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-3">
      <a href="warehouse/locations.php" class="text-decoration-none">
        <div class="card text-white bg-primary shadow-sm">
          <div class="card-body">
            <h5 class="card-title"><i class="fa-solid fa-map-location-dot me-2"></i>Posizioni</h5>
            <p class="card-text fs-3"><?= $total_locations ?></p>
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-3">
      <a href="warehouse/compartments.php" class="text-decoration-none">
        <div class="card text-white bg-success shadow-sm">
          <div class="card-body">
            <h5 class="card-title"><i class="fa-solid fa-boxes-stacked me-2"></i>Comparti</h5>
            <p class="card-text fs-3"><?= $total_compartments ?></p>
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-3">
        <div class="card text-white bg-warning shadow-sm">
          <div class="card-body">
            <h5 class="card-title"><i class="fa-solid fa-microchip me-2"></i>Tipi di componenti</h5>
            <p class="card-text fs-3"><?= $total_components ?></p>
          </div>
        </div>
    </div>

    <div class="col-md-3">
      <div class="card text-white bg-info shadow-sm">
        <div class="card-body">
          <h5 class="card-title"><i class="fa-solid fa-layer-group me-2"></i>Totale quantità</h5>
          <p class="card-text fs-3"><?= $total_quantity ?? 0 ?></p>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <a href="warehouse/low_stock.php" class="text-decoration-none">
        <div class="card text-white <?= $low_stock_components > 0 ? 'bg-danger' : 'bg-success' ?> shadow-sm">
          <div class="card-body">
            <h5 class="card-title"><i class="fa-solid fa-triangle-exclamation me-2"></i>Sotto scorta</h5>
            <p class="card-text fs-3"><?= $low_stock_components ?? 0 ?></p>
          </div>
        </div>
      </a>
    </div>
  </div>

  <?php if ($_SESSION['role'] === 'admin'): ?>
  <div class="row g-3 mt-4">
    <div class="col-md-3">
      <a href="admin/users.php" class="text-decoration-none">
        <div class="card text-white bg-secondary shadow-sm">
          <div class="card-body">
            <h5 class="card-title"><i class="fa-solid fa-users me-2"></i>Utenti</h5>
            <p class="card-text fs-3">Gestione</p>
          </div>
        </div>
      </a>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>