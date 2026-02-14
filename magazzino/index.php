<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:03:48 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-09 22:50:23
*/
// 2026-01-08: Aggiunta card sotto scorta
// 2026-01-11: Aggiunto link su card Tipi di componenti
// 2026-01-14: Sistemati conteggi quantità per unità di misura
// 2026-01-15: Miglioramenti grafici - gradienti, animazioni hover, welcome message, modal ridisegnato
// 2026-02-01: modificata card Quantità
// 2026-02-02: Redirect a homepage personale se attivata
// 2026-02-03: Integrato sistema di installazione/aggiornamento automatico
// 2026-02-09: Eseguite migliorie grafiche e ottimizzazioni varie

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

// Calcola il totale generale dei componenti
$total_quantity_all = 0;
foreach ($quantities_by_unit as $item) {
    $total_quantity_all += $item['total_qty'];
}

$low_stock_components = $pdo->query("SELECT COUNT(*) FROM components WHERE quantity_min IS NOT NULL AND quantity_min != 0 AND quantity < quantity_min")->fetchColumn();

// Conteggio progetti
$total_progetti = $pdo->query("SELECT COUNT(*) FROM progetti")->fetchColumn();

// Recupera nome utente per benvenuto
$user_name = $_SESSION['username'] ?? 'Utente';

include 'includes/header.php';
require_once 'update/auto_updater.php';
?>

<div class="container py-4">
  <div class="row mb-4">
    <div class="col-12">
      <div class="welcome-header p-4 rounded-4 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <div class="row align-items-center">
          <div class="col-md-8">
            <h1 class="mb-2 fw-bold" style="text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
              <i class="fa-solid fa-tachometer-alt me-3"></i>Dashboard Magazzino
            </h1>
            <p class="mb-0 opacity-90 fs-5">
              <i class="fa-solid fa-hand-sparkles me-2"></i>Ciao <strong><?= htmlspecialchars($user_name) ?></strong>, bentornato!
            </p>
          </div>
          <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="warehouse/add_component.php" class="btn btn-light btn-lg rounded-pill shadow-sm">
              <i class="fa-solid fa-plus me-2"></i>Nuovo Componente
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-md-6 col-lg-3">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="warehouse/locali.php" class="text-decoration-none">
          <div class="card h-100 border-0 shadow-sm hover-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; transition: all 0.3s ease;">
            <div class="card-body text-center py-4">
              <div class="mb-3">
                <i class="fa-solid fa-building fa-2x" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"></i>
              </div>
              <h5 class="card-title mb-2" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Locali</h5>
              <p class="card-text fs-2 fw-bold mb-0" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?= $total_locali ?></p>
              <small class="opacity-75" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);">Gestisci locali</small>
            </div>
          </div>
        </a>
      <?php else: ?>
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; transition: all 0.3s ease; opacity: 0.6; cursor: not-allowed;" title="Solo admin">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <i class="fa-solid fa-building fa-2x" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"></i>
            </div>
            <h5 class="card-title mb-2" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Locali</h5>
            <p class="card-text fs-2 fw-bold mb-0" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?= $total_locali ?></p>
            <small class="opacity-75" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);">Solo admin</small>
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
                <i class="fa-solid fa-map-location-dot fa-2x" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"></i>
              </div>
              <h5 class="card-title mb-2" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Posizioni</h5>
              <p class="card-text fs-2 fw-bold mb-0" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?= $total_locations ?></p>
              <small class="opacity-75" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);">Organizza posizioni</small>
            </div>
          </div>
        </a>
      <?php else: ?>
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; transition: all 0.3s ease; opacity: 0.6; cursor: not-allowed;" title="Solo admin">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <i class="fa-solid fa-map-location-dot fa-2x" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"></i>
            </div>
            <h5 class="card-title mb-2" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Posizioni</h5>
            <p class="card-text fs-2 fw-bold mb-0" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?= $total_locations ?></p>
            <small class="opacity-75" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);">Solo admin</small>
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
                <i class="fa-solid fa-boxes-stacked fa-2x" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"></i>
              </div>
              <h5 class="card-title mb-2" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Comparti</h5>
              <p class="card-text fs-2 fw-bold mb-0" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?= $total_compartments ?></p>
              <small class="opacity-75" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);">Gestisci comparti</small>
            </div>
          </div>
        </a>
      <?php else: ?>
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; transition: all 0.3s ease; opacity: 0.6; cursor: not-allowed;" title="Solo admin">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <i class="fa-solid fa-boxes-stacked fa-2x" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"></i>
            </div>
            <h5 class="card-title mb-2" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Comparti</h5>
            <p class="card-text fs-2 fw-bold mb-0" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?= $total_compartments ?></p>
            <small class="opacity-75" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);">Solo admin</small>
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
                <i class="fa-solid fa-microchip fa-2x" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"></i>
              </div>
              <h5 class="card-title mb-2" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Componenti</h5>
              <p class="card-text fs-2 fw-bold mb-0" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?= $total_components ?></p>
              <small class="opacity-75" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);">Catalogo componenti</small>
            </div>
          </div>
        </a>
      <?php else: ?>
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; transition: all 0.3s ease; opacity: 0.6; cursor: not-allowed;" title="Solo admin">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <i class="fa-solid fa-microchip fa-2x" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"></i>
            </div>
            <h5 class="card-title mb-2" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Componenti</h5>
            <p class="card-text fs-2 fw-bold mb-0" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?= $total_components ?></p>
            <small class="opacity-75" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);">Solo admin</small>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-md-6 col-lg-3">
      <div class="card h-100 border-0 shadow-sm hover-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; transition: all 0.3s ease; cursor: pointer;" data-bs-toggle="modal" data-bs-target="#quantityModal">
        <div class="card-body text-center py-4">
          <div class="mb-3">
            <i class="fa-solid fa-layer-group fa-2x" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"></i>
          </div>
          <h5 class="card-title mb-2" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Quantità</h5>
          <p class="card-text fs-4 fw-bold mb-0" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);">Dettagli per unità</p>
          <small class="opacity-75" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);"><i class="fa-solid fa-eye me-1"></i>Clicca per vedere</small>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-lg-3">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="warehouse/low_stock.php" class="text-decoration-none">
          <div class="card h-100 border-0 shadow-sm hover-card" style="background: linear-gradient(135deg, <?= $low_stock_components > 0 ? '#ff6b6b 0%, #ee5a52 100%' : '#51cf66 0%, #40c057 100%' ?>); color: white; transition: all 0.3s ease;">
            <div class="card-body text-center py-4">
              <div class="mb-3">
                <i class="fa-solid fa-triangle-exclamation fa-2x" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"></i>
              </div>
              <h5 class="card-title mb-2" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Sotto Scorta</h5>
              <p class="card-text fs-2 fw-bold mb-0" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?= $low_stock_components ?? 0 ?></p>
              <small class="opacity-75" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);">Componenti da riordinare</small>
            </div>
          </div>
        </a>
      <?php else: ?>
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, <?= $low_stock_components > 0 ? '#ff6b6b 0%, #ee5a52 100%' : '#51cf66 0%, #40c057 100%' ?>); color: white; transition: all 0.3s ease; opacity: 0.6; cursor: not-allowed;" title="Solo admin">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <i class="fa-solid fa-triangle-exclamation fa-2x" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"></i>
            </div>
            <h5 class="card-title mb-2" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Sotto Scorta</h5>
            <p class="card-text fs-2 fw-bold mb-0" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?= $low_stock_components ?? 0 ?></p>
            <small class="opacity-75" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);">Solo admin</small>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-md-6 col-lg-3">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="warehouse/categories.php" class="text-decoration-none">
          <div class="card h-100 border-0 shadow-sm hover-card" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333; transition: all 0.3s ease;">
            <div class="card-body text-center py-4">
              <div class="mb-3">
                <i class="fa-solid fa-tags fa-2x text-warning"></i>
              </div>
              <h5 class="card-title mb-2">Categorie</h5>
              <p class="card-text text-muted mb-0"><i class="fa-solid fa-arrow-right me-1"></i>Gestisci categorie</p>
            </div>
          </div>
        </a>
      <?php else: ?>
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333; transition: all 0.3s ease; opacity: 0.6; cursor: not-allowed;" title="Solo admin">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <i class="fa-solid fa-tags fa-2x text-warning"></i>
            </div>
            <h5 class="card-title mb-2">Categorie</h5>
            <p class="card-text text-muted mb-0">Solo admin</p>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-md-6 col-lg-3">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="warehouse/progetti/progetti.php" class="text-decoration-none">
          <div class="card h-100 border-0 shadow-sm hover-card" style="background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%); color: #333; transition: all 0.3s ease;">
            <div class="card-body text-center py-4">
              <div class="mb-3">
                <i class="fa-solid fa-folder-open fa-2x text-info"></i>
              </div>
              <h5 class="card-title mb-2">Progetti</h5>
              <p class="card-text fs-2 fw-bold mb-0 text-info"><?= $total_progetti ?></p>
              <small class="text-muted">Gestisci progetti</small>
            </div>
          </div>
        </a>
      <?php else: ?>
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%); color: #333; transition: all 0.3s ease; opacity: 0.6; cursor: not-allowed;" title="Solo admin">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <i class="fa-solid fa-folder-open fa-2x text-info"></i>
            </div>
            <h5 class="card-title mb-2">Progetti</h5>
            <p class="card-text fs-2 fw-bold mb-0 text-info"><?= $total_progetti ?></p>
            <small class="text-muted">Solo admin</small>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($_SESSION['role'] === 'admin'): ?>
  <hr class="my-5 opacity-25">
  
  <div class="row g-4">
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

<?php
$unit_colors = [
  'pz' => ['bg' => 'bg-primary', 'text' => 'text-primary', 'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'],
  'kg' => ['bg' => 'bg-success', 'text' => 'text-success', 'gradient' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)'],
  'g' => ['bg' => 'bg-info', 'text' => 'text-info', 'gradient' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'],
  'm' => ['bg' => 'bg-warning', 'text' => 'text-warning', 'gradient' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)'],
  'cm' => ['bg' => 'bg-danger', 'text' => 'text-danger', 'gradient' => 'linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%)'],
  'mm' => ['bg' => 'bg-secondary', 'text' => 'text-secondary', 'gradient' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)'],
  'l' => ['bg' => 'bg-dark', 'text' => 'text-dark', 'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'],
  'ml' => ['bg' => 'bg-primary', 'text' => 'text-primary', 'gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'],
];
?>

<div class="modal fade" id="quantityModal" tabindex="-1" aria-labelledby="quantityModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
      <div class="modal-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px 15px 0 0;">
        <h5 class="modal-title" id="quantityModalLabel">
          <i class="fa-solid fa-layer-group me-2"></i>Dettaglio Quantità per Unità di Misura
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="row">
          <?php foreach ($quantities_by_unit as $index => $item): 
            $unit = strtolower($item['unit']);
            $colors = $unit_colors[$unit] ?? $unit_colors['pz'];
          ?>
          <div class="col-md-6 col-lg-4 mb-3">
            <div class="card border-0 shadow-sm h-100 overflow-hidden" style="border-radius: 15px;">
              <div class="card-header border-0 py-3" style="background: <?= $colors['gradient'] ?>;">
                <h5 class="mb-0 text-center text-white fw-bold" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">
                  <?= htmlspecialchars(strtoupper($item['unit'])) ?>
                </h5>
              </div>
              <div class="card-body text-center py-4">
                <h3 class="fw-bold mb-1" style="color: #333;">
                  <?= number_format($item['total_qty'], 0, ',', '.') ?>
                </h3>
                <small class="text-muted">quantità totale</small>
                <hr class="my-3 opacity-25">
                <span class="badge bg-light text-dark border">
                  <?= number_format($item['count'], 0, ',', '.') ?> tipi diversi
                </span>
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