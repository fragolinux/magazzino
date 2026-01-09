<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-08
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-09
*/
// Vista gerarchica: Locali -> Posizioni -> Comparti -> Componenti

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Recupera tutti i locali con conteggi
$stmt = $pdo->query("
    SELECT 
        loc.id,
        loc.name,
        loc.description,
        COUNT(DISTINCT l.id) as locations_count,
        COUNT(DISTINCT c.id) as compartments_count,
        COUNT(DISTINCT comp.id) as components_count
    FROM locali loc
    LEFT JOIN locations l ON l.locale_id = loc.id
    LEFT JOIN compartments c ON c.location_id = l.id
    LEFT JOIN components comp ON comp.location_id = l.id OR comp.compartment_id = c.id
    GROUP BY loc.id
    ORDER BY loc.name ASC
");
$locali = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fa-solid fa-sitemap me-2"></i>Gerarchia Magazzino</h2>
    </div>

    <div class="alert alert-info">
        <i class="fa-solid fa-info-circle me-2"></i>
        Questa pagina mostra la struttura gerarchica completa del magazzino: <strong>Locali → Posizioni → Comparti → Componenti</strong>
    </div>

    <?php if (empty($locali)): ?>
        <div class="alert alert-warning">
            <i class="fa-solid fa-exclamation-triangle me-2"></i>
            Nessun locale trovato. <a href="add_locale.php">Aggiungi il primo locale</a>.
        </div>
    <?php else: ?>
        <div class="accordion" id="hierarchyAccordion">
            <?php foreach ($locali as $locale): ?>
                <?php
                // Recupera posizioni per questo locale
                $stmt_loc = $pdo->prepare("
                    SELECT 
                        l.id,
                        l.name,
                        COUNT(DISTINCT c.id) as compartments_count,
                        COUNT(DISTINCT comp.id) as components_count
                    FROM locations l
                    LEFT JOIN compartments c ON c.location_id = l.id
                    LEFT JOIN components comp ON comp.location_id = l.id OR comp.compartment_id = c.id
                    WHERE l.locale_id = ?
                    GROUP BY l.id
                    ORDER BY l.name ASC
                ");
                $stmt_loc->execute([$locale['id']]);
                $locations = $stmt_loc->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-locale-<?= $locale['id'] ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-locale-<?= $locale['id'] ?>" aria-expanded="false">
                            <i class="fa-solid fa-building me-2"></i>
                            <strong><?= htmlspecialchars($locale['name']) ?></strong>
                            <span class="ms-3 text-muted small">
                                <span class="badge bg-primary"><?= $locale['locations_count'] ?> posizioni</span>
                                <span class="badge bg-success"><?= $locale['compartments_count'] ?> comparti</span>
                                <span class="badge bg-info"><?= $locale['components_count'] ?> componenti</span>
                            </span>
                        </button>
                    </h2>
                    <div id="collapse-locale-<?= $locale['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#hierarchyAccordion">
                        <div class="accordion-body">
                            <?php if (!empty($locale['description'])): ?>
                                <p class="text-muted mb-3"><em><?= htmlspecialchars($locale['description']) ?></em></p>
                            <?php endif; ?>
                            
                            <?php if (empty($locations)): ?>
                                <p class="text-muted">Nessuna posizione in questo locale.</p>
                            <?php else: ?>
                                <div class="accordion" id="accordion-locale-<?= $locale['id'] ?>">
                                    <?php foreach ($locations as $location): ?>
                                        <?php
                                        // Recupera comparti per questa posizione
                                        $stmt_comp = $pdo->prepare("
                                            SELECT 
                                                c.id,
                                                c.code,
                                                c.description,
                                                COUNT(comp.id) as components_count
                                            FROM compartments c
                                            LEFT JOIN components comp ON comp.compartment_id = c.id
                                            WHERE c.location_id = ?
                                            GROUP BY c.id
                                            ORDER BY c.code ASC
                                        ");
                                        $stmt_comp->execute([$location['id']]);
                                        $compartments = $stmt_comp->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        // Recupera componenti diretti (senza comparto)
                                        $stmt_direct = $pdo->prepare("
                                            SELECT COUNT(*) as count
                                            FROM components
                                            WHERE location_id = ? AND compartment_id IS NULL
                                        ");
                                        $stmt_direct->execute([$location['id']]);
                                        $direct_components = $stmt_direct->fetch()['count'];
                                        ?>
                                        
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading-location-<?= $location['id'] ?>">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-location-<?= $location['id'] ?>" aria-expanded="false">
                                                    <i class="fa-solid fa-map-location-dot me-2"></i>
                                                    <?= htmlspecialchars($location['name']) ?>
                                                    <span class="ms-3 text-muted small">
                                                        <span class="badge bg-success"><?= count($compartments) ?> comparti</span>
                                                        <span class="badge bg-info"><?= $location['components_count'] ?> componenti</span>
                                                    </span>
                                                </button>
                                            </h2>
                                            <div id="collapse-location-<?= $location['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#accordion-locale-<?= $locale['id'] ?>">
                                                <div class="accordion-body">
                                                    <?php if ($direct_components > 0): ?>
                                                        <div class="alert alert-light mb-3">
                                                            <i class="fa-solid fa-microchip me-2"></i>
                                                            <strong><?= $direct_components ?> componenti</strong> senza comparto specifico
                                                            <a href="components.php?location_id=<?= $location['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-2">
                                                                <i class="fa-solid fa-eye"></i> Visualizza
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (empty($compartments)): ?>
                                                        <p class="text-muted">Nessun comparto in questa posizione.</p>
                                                    <?php else: ?>
                                                        <div class="list-group">
                                                            <?php foreach ($compartments as $compartment): ?>
                                                                <div class="list-group-item">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <div>
                                                                            <i class="fa-solid fa-boxes-stacked me-2"></i>
                                                                            <strong><?= htmlspecialchars($compartment['code']) ?></strong>
                                                                            <?php if (!empty($compartment['description'])): ?>
                                                                                <span class="text-muted ms-2"><?= htmlspecialchars($compartment['description']) ?></span>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <div>
                                                                            <span class="badge bg-info me-2"><?= $compartment['components_count'] ?> componenti</span>
                                                                            <?php if ($compartment['components_count'] > 0): ?>
                                                                                <a href="components.php?compartment_id=<?= $compartment['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                                    <i class="fa-solid fa-eye"></i> Visualizza
                                                                                </a>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
