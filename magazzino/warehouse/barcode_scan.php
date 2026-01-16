<?php
/*
 * @Author: gabriele.riva
 * @Date: 2026-01-15
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-15
*/
require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Controlla se è stato fornito un ID componente via GET (da barcode scanner)
$component_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;
$component = null;

if ($component_id > 0) {
    // Recupera dettagli componente
    $stmt = $pdo->prepare("SELECT c.id, c.codice_prodotto, c.quantity, c.unita_misura, cat.name AS category_name, l.name AS location_name, cmp.code AS compartment_code
      FROM components c
      LEFT JOIN categories cat ON c.category_id = cat.id
      LEFT JOIN locations l ON c.location_id = l.id
      LEFT JOIN compartments cmp ON c.compartment_id = cmp.id
      WHERE c.id = ?");
    $stmt->execute([$component_id]);
    $component = $stmt->fetch(PDO::FETCH_ASSOC);
}

?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-barcode me-2"></i>Carico/Scarico via Barcode</h2>
        <a href="/magazzino/warehouse/components.php" class="btn btn-secondary">Torna a componenti</a>
    </div>

    <?php if ($component): ?>
        <!-- Mostra pagina carico/scarico per il componente selezionato -->
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fa-solid fa-microchip me-2"></i><?= htmlspecialchars($component['codice_prodotto']) ?></h4>
                    </div>
                    <div class="card-body">
                        <div id="alertContainer"></div>

                        <!-- Info componente -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <div class="text-muted small">Categoria</div>
                                    <div class="fw-bold"><?= htmlspecialchars($component['category_name'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <div class="text-muted small">Posizione</div>
                                    <div class="fw-bold"><?= htmlspecialchars($component['location_name'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <div class="text-muted small">Comparto</div>
                                    <div class="fw-bold"><?= htmlspecialchars($component['compartment_code'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <div class="text-muted small">Quantità attuale</div>
                                    <div class="fw-bold fs-5" id="current-qty"><?= intval($component['quantity']) . ' ' . htmlspecialchars($component['unita_misura'] ?? 'pz') ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Operazione -->
                        <div class="card border-warning mb-4">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0"><i class="fa-solid fa-exchange me-2"></i>Operazione</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Tipo operazione:</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="operation" id="operation-unload" value="unload" checked>
                                            <label class="form-check-label" for="operation-unload">
                                                <i class="fa-solid fa-minus text-danger me-1"></i>Scarico (diminuisci quantità)
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="operation" id="operation-load" value="load">
                                            <label class="form-check-label" for="operation-load">
                                                <i class="fa-solid fa-plus text-success me-1"></i>Carico (aumenta quantità)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="quantity-input" class="form-label fw-bold">Quantità:</label>
                                        <input type="number" class="form-control form-control-lg" id="quantity-input" min="0" placeholder="Inserisci quantità" autofocus>
                                        <div class="form-text">Inserisci la quantità da caricare o scaricare</div>
                                    </div>
                                </div>

                                <div class="d-grid gap-2 mt-3">
                                    <button type="button" class="btn btn-success btn-lg" id="executeBtn">
                                        <i class="fa-solid fa-check me-2"></i>Esegui Operazione
                                    </button>
                                    <a href="/magazzino/warehouse/barcode_scan.php" class="btn btn-outline-secondary">
                                        <i class="fa-solid fa-arrow-left me-2"></i>Nuovo Scan
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="spinner text-center" id="spinner" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Caricamento...</span>
                            </div>
                            <div class="mt-2">Elaborazione operazione...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Mostra form per inserire ID componente -->
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fa-solid fa-barcode me-2"></i>Scansiona Barcode</h4>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="fa-solid fa-barcode fa-4x text-muted mb-3"></i>
                            <p class="lead">Inserisci l'ID del componente scansionato dal barcode</p>
                        </div>

                        <form method="get" action="barcode_scan.php">
                            <div class="mb-3">
                                <label for="component_id" class="form-label fw-bold">ID Componente:</label>
                                <input type="number" class="form-control form-control-lg text-center" id="component_id" name="id" placeholder="Scansiona o inserisci ID" autofocus required min="1">
                                <div class="form-text">Inserisci l'ID numerico del componente (solo numeri)</div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fa-solid fa-search me-2"></i>Cerca Componente
                                </button>
                            </div>
                        </form>

                        <hr class="my-4">

                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fa-solid fa-info-circle me-2"></i>Come utilizzare:</h6>
                            <ul class="mb-0 small">
                                <li>Scansiona il barcode del componente con un lettore barcode</li>
                                <li>Oppure inserisci manualmente l'ID numerico del componente</li>
                                <li>Premi Invio o clicca "Cerca Componente"</li>
                                <li>Verrai portato alla pagina di carico/scarico</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
$(function(){
    <?php if ($component): ?>
        // Pagina carico/scarico - logica simile a mobile_component.php
        $('#executeBtn').on('click', function(){
            const operation = $('input[name="operation"]:checked').val();
            const quantity = parseInt($('#quantity-input').val());

            // Validazioni
            if (!quantity || quantity <= 0) {
                showAlert('Inserisci una quantità valida.', 'warning');
                $('#quantity-input').focus();
                return;
            }

            if (operation === 'unload') {
                const currentQty = parseInt('<?= $component['quantity'] ?>');
                if (quantity > currentQty) {
                    showAlert('Quantità insufficiente in magazzino. Disponibili: ' + currentQty + ' <?= htmlspecialchars($component['unita_misura'] ?? 'pz') ?>', 'danger');
                    return;
                }
            }

            // Esegui operazione
            executeOperation(<?= $component['id'] ?>, operation, quantity);
        });

        function executeOperation(componentId, operation, quantity) {
            $('#spinner').show();
            $('#executeBtn').prop('disabled', true);

            $.post('/magazzino/warehouse/update_component_quantity.php', {
                id: componentId,
                quantity: quantity,
                operation: operation
            })
            .done(function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    // Aggiorna quantità visualizzata
                    $('#current-qty').text(response.new_quantity + ' <?= htmlspecialchars($component['unita_misura'] ?? 'pz') ?>');
                    $('#quantity-input').val('');
                } else {
                    showAlert(response.message, 'danger');
                }
            })
            .fail(function() {
                showAlert('Errore di connessione. Riprova.', 'danger');
            })
            .always(function() {
                $('#spinner').hide();
                $('#executeBtn').prop('disabled', false);
            });
        }

        function showAlert(message, type) {
            const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
            $('#alertContainer').html(alertHtml);
        }

        // Focus automatico sul campo quantità
        $('#quantity-input').focus();
    <?php else: ?>
        // Pagina scan - focus sul campo ID
        $('#component_id').focus();
    <?php endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?>