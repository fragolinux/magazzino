<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:03:48 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-03 20:21:04
*/
// 2026-01-08: Aggiunta card sotto scorta
// 2026-01-11: Aggiunto link su card Tipi di componenti
// 2026-01-14: Sistemati conteggi quantità per unità di misura
// 2026-01-15: Miglioramenti grafici - gradienti, animazioni hover, welcome message, modal ridisegnato
// 2026-02-01: modificata card Quantità
// 2026-02-02: Redirect a homepage personale se attivata
// 2026-02-03: Integrato sistema di installazione/aggiornamento automatico

/*
 * Magazzino Componenti – Software di gestione magazzino di componenti elettronici
 * Copyright (C) 2026  Gabriele Riva (RG4Tech Youtube Channel)
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

// Conteggio componenti per unità di misura (con quantità > 0)
$stmt = $pdo->query("
    SELECT 
        COALESCE(unita_misura, 'pz') as unit,
        COUNT(*) as count,
        SUM(quantity) as total_qty
    FROM components 
    WHERE quantity > 0
    GROUP BY COALESCE(unita_misura, 'pz')
    ORDER BY count DESC
");
$quantities_by_unit = $stmt->fetchAll(PDO::FETCH_ASSOC);

$low_stock_components = $pdo->query("SELECT COUNT(*) FROM components WHERE quantity_min IS NOT NULL AND quantity_min != 0 AND quantity < quantity_min")->fetchColumn();

include 'includes/header.php';
require_once 'update/auto_updater.php';
?>

<div class="container py-4">
  <h1 class="mb-4 text-muted"><i class="fa-solid fa-tachometer-alt me-2"></i>Dashboard Magazzino</h1>

  <div class="row g-4">
    <div class="col-md-6 col-lg-3">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="warehouse/locali.php" class="text-decoration-none">
          <div class="card h-100 border-0 shadow-sm hover-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; transition: all 0.3s ease;">
            <div class="card-body text-center py-4">
              <div class="mb-3">
                <i class="fa-solid fa-building fa-2x"></i>
              </div>
              <h5 class="card-title mb-2">Locali</h5>
              <p class="card-text fs-2 fw-bold mb-0"><?= $total_locali ?></p>
              <small class="opacity-75">Gestisci locali</small>
            </div>
          </div>
        </a>
      <?php else: ?>
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; transition: all 0.3s ease; opacity: 0.6; cursor: not-allowed;" title="Solo admin">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <i class="fa-solid fa-building fa-2x"></i>
            </div>
            <h5 class="card-title mb-2">Locali</h5>
            <p class="card-text fs-2 fw-bold mb-0"><?= $total_locali ?></p>
            <small class="opacity-75">Solo admin</small>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-md-6 col-lg-3">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="warehouse/locations.php" class="text-decoration-none">
          <div class="card h-100 border-0 shadow-sm hover-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; transition: all 0.3s ease;">
            <div class="card-body text-center py-4">
              <div class="mb-3">
                <i class="fa-solid fa-map-location-dot fa-2x"></i>
              </div>
              <h5 class="card-title mb-2">Posizioni</h5>
              <p class="card-text fs-2 fw-bold mb-0"><?= $total_locations ?></p>
              <small class="opacity-75">Organizza posizioni</small>
            </div>
          </div>
        </a>
      <?php else: ?>
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; transition: all 0.3s ease; opacity: 0.6; cursor: not-allowed;" title="Solo admin">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <i class="fa-solid fa-map-location-dot fa-2x"></i>
            </div>
            <h5 class="card-title mb-2">Posizioni</h5>
            <p class="card-text fs-2 fw-bold mb-0"><?= $total_locations ?></p>
            <small class="opacity-75">Solo admin</small>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-md-6 col-lg-3">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="warehouse/compartments.php" class="text-decoration-none">
          <div class="card h-100 border-0 shadow-sm hover-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; transition: all 0.3s ease;">
            <div class="card-body text-center py-4">
              <div class="mb-3">
                <i class="fa-solid fa-boxes-stacked fa-2x"></i>
              </div>
              <h5 class="card-title mb-2">Comparti</h5>
              <p class="card-text fs-2 fw-bold mb-0"><?= $total_compartments ?></p>
              <small class="opacity-75">Gestisci comparti</small>
            </div>
          </div>
        </a>
      <?php else: ?>
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; transition: all 0.3s ease; opacity: 0.6; cursor: not-allowed;" title="Solo admin">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <i class="fa-solid fa-boxes-stacked fa-2x"></i>
            </div>
            <h5 class="card-title mb-2">Comparti</h5>
            <p class="card-text fs-2 fw-bold mb-0"><?= $total_compartments ?></p>
            <small class="opacity-75">Solo admin</small>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-md-6 col-lg-3">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="warehouse/components.php" class="text-decoration-none">
          <div class="card h-100 border-0 shadow-sm hover-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; transition: all 0.3s ease;">
            <div class="card-body text-center py-4">
              <div class="mb-3">
                <i class="fa-solid fa-microchip fa-2x"></i>
              </div>
              <h5 class="card-title mb-2">Componenti</h5>
              <p class="card-text fs-2 fw-bold mb-0"><?= $total_components ?></p>
              <small class="opacity-75">Catalogo componenti</small>
            </div>
          </div>
        </a>
      <?php else: ?>
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; transition: all 0.3s ease; opacity: 0.6; cursor: not-allowed;" title="Solo admin">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <i class="fa-solid fa-microchip fa-2x"></i>
            </div>
            <h5 class="card-title mb-2">Componenti</h5>
            <p class="card-text fs-2 fw-bold mb-0"><?= $total_components ?></p>
            <small class="opacity-75">Solo admin</small>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-md-6 col-lg-3">
      <div class="card h-100 border-0 shadow-sm hover-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; transition: all 0.3s ease; cursor: pointer;" data-bs-toggle="modal" data-bs-target="#quantityModal">
        <div class="card-body text-center py-4">
          <div class="mb-3">
            <i class="fa-solid fa-layer-group fa-2x"></i>
          </div>
          <h5 class="card-title mb-2">Quantità</h5>
          <p class="card-text fs-4 fw-bold mb-1">Visualizza dettagli</p>
          <small class="opacity-75">Dettagli quantità per unità di misura</small>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-lg-3">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="warehouse/low_stock.php" class="text-decoration-none">
          <div class="card h-100 border-0 shadow-sm hover-card" style="background: linear-gradient(135deg, <?= $low_stock_components > 0 ? '#ff6b6b 0%, #ee5a52 100%' : '#51cf66 0%, #40c057 100%' ?>); color: white; transition: all 0.3s ease;">
            <div class="card-body text-center py-4">
              <div class="mb-3">
                <i class="fa-solid fa-triangle-exclamation fa-2x"></i>
              </div>
              <h5 class="card-title mb-2">Sotto Scorta</h5>
              <p class="card-text fs-2 fw-bold mb-0"><?= $low_stock_components ?? 0 ?></p>
              <small class="opacity-75">Componenti da riordinare</small>
            </div>
          </div>
        </a>
      <?php else: ?>
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, <?= $low_stock_components > 0 ? '#ff6b6b 0%, #ee5a52 100%' : '#51cf66 0%, #40c057 100%' ?>); color: white; transition: all 0.3s ease; opacity: 0.6; cursor: not-allowed;" title="Solo admin">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <i class="fa-solid fa-triangle-exclamation fa-2x"></i>
            </div>
            <h5 class="card-title mb-2">Sotto Scorta</h5>
            <p class="card-text fs-2 fw-bold mb-0"><?= $low_stock_components ?? 0 ?></p>
            <small class="opacity-75">Solo admin</small>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($_SESSION['role'] === 'admin'): ?>
  <div class="row g-4 mt-5">
    <div class="col-12">
      <h3 class="mb-3 text-muted"><i class="fa-solid fa-cog me-2"></i>Pannello Amministrazione</h3>
    </div>
    <div class="col-md-6 col-lg-3">
      <a href="admin/users.php" class="text-decoration-none">
        <div class="card h-100 border-0 shadow-sm hover-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; transition: all 0.3s ease;">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <i class="fa-solid fa-users fa-2x text-primary"></i>
            </div>
            <h5 class="card-title mb-2">Gestione Utenti</h5>
            <p class="card-text text-muted mb-0">Amministra utenti e permessi</p>
          </div>
        </div>
      </a>
    </div>
  </div>
  <?php endif; ?>
</div>

<style>
.hover-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.card {
  border-radius: 15px !important;
}

.welcome-gradient {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>

<!-- Modal per dettaglio quantità per unità di misura -->
<div class="modal fade" id="quantityModal" tabindex="-1" aria-labelledby="quantityModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
      <div class="modal-header bg-gradient-primary text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px 15px 0 0;">
        <h5 class="modal-title" id="quantityModalLabel">
          <i class="fa-solid fa-layer-group me-2"></i>Dettaglio Quantità per Unità di Misura
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="row">
          <?php foreach ($quantities_by_unit as $item): ?>
          <div class="col-md-6 col-lg-4 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 10px;">
              <div class="card-body text-center">
                <div class="mb-2">
                  <span class="badge bg-primary fs-6 px-3 py-2" style="border-radius: 20px;"><?= htmlspecialchars($item['unit']) ?></span>
                </div>
                <h4 class="text-primary mb-1"><?= number_format($item['total_qty'], 0, ',', '.') ?></h4>
                <small class="text-muted">totale componenti</small>
                <br>
                <small class="text-muted"><?= number_format($item['count'], 0, ',', '.') ?> tipi</small>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (empty($quantities_by_unit)): ?>
          <div class="alert alert-info mb-0 border-0" style="border-radius: 10px;">
            <i class="fa-solid fa-info-circle me-2"></i>Nessun componente con quantità disponibile.
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary px-4" style="border-radius: 25px;" data-bs-dismiss="modal">
          <i class="fa-solid fa-times me-2"></i>Chiudi
        </button>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>