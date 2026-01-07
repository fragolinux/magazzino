<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-07 13:30:00 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-07 13:33:49
*/
// Pagina mobile-friendly per carico/scarico componente via QR code

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$component_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;

if ($component_id === 0) {
    http_response_code(404);
    echo "Componente non trovato.";
    exit;
}

// Recupera dettagli componente
$stmt = $pdo->prepare("SELECT c.id, c.codice_prodotto, c.quantity, cat.name AS category_name, l.name AS location_name, cmp.code AS compartment_code
                       FROM components c
                       LEFT JOIN categories cat ON c.category_id = cat.id
                       LEFT JOIN locations l ON c.location_id = l.id
                       LEFT JOIN compartments cmp ON c.compartment_id = cmp.id
                       WHERE c.id = ?");
$stmt->execute([$component_id]);
$component = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$component) {
    http_response_code(404);
    echo "Componente non trovato.";
    exit;
}

?><!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars($component['codice_prodotto']) ?></title>
    <link href="/magazzino/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/magazzino/assets/css/all.min.css" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background-color: #f8f9fa; }
        .container-fluid { padding: 0; }
        .header { background-color: #007bff; color: white; padding: 1rem; text-align: center; }
        .header h1 { font-size: 1.5rem; margin: 0; word-break: break-all; }
        .content { padding: 1.5rem 1rem; }
        .info-group { background-color: white; padding: 1rem; margin-bottom: 1rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .info-label { font-size: 0.9rem; color: #6c757d; margin-bottom: 0.25rem; }
        .info-value { font-size: 1.2rem; font-weight: bold; color: #212529; word-break: break-word; }
        .operation-section { background-color: white; padding: 1rem; margin-bottom: 1rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .operation-section label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-check { margin-bottom: 0.75rem; }
        .form-check-label { margin-left: 0.5rem; }
        input[type="number"] { font-size: 1.1rem; padding: 0.75rem; }
        .btn { font-size: 1.1rem; padding: 0.75rem 1.5rem; width: 100%; margin-bottom: 0.5rem; }
        .btn-success { background-color: #28a745; border-color: #28a745; }
        .btn-secondary { background-color: #6c757d; border-color: #6c757d; }
        .alert { margin-bottom: 1rem; }
        .spinner { display: none; text-align: center; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="header">
        <h1><i class="fa-solid fa-microchip me-2"></i><?= htmlspecialchars($component['codice_prodotto']) ?></h1>
    </div>

    <div class="content">
        <div id="alertContainer"></div>

        <!-- Info componente -->
        <div class="info-group">
            <div class="info-label">Categoria</div>
            <div class="info-value"><?= htmlspecialchars($component['category_name'] ?? '-') ?></div>
        </div>

        <div class="info-group">
            <div class="info-label">Posizione</div>
            <div class="info-value"><?= htmlspecialchars($component['location_name'] ?? '-') ?></div>
        </div>

        <div class="info-group">
            <div class="info-label">Comparto</div>
            <div class="info-value"><?= htmlspecialchars($component['compartment_code'] ?? '-') ?></div>
        </div>

        <div class="info-group">
            <div class="info-label">Quantità attuale</div>
            <div class="info-value" id="current-qty"><?= intval($component['quantity']) ?></div>
        </div>

        <!-- Operazione -->
        <div class="operation-section">
            <label>Operazione:</label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="operation" id="operation-unload" value="unload" checked>
                <label class="form-check-label" for="operation-unload">Scarico (diminuisci quantità)</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="operation" id="operation-load" value="load">
                <label class="form-check-label" for="operation-load">Carico (aumenta quantità)</label>
            </div>
        </div>

        <div class="operation-section">
            <label for="quantity-input">Quantità:</label>
            <input type="number" class="form-control form-control-lg" id="quantity-input" min="0" placeholder="Inserisci quantità" autofocus>
        </div>

        <button class="btn btn-success" id="btn-confirm" onclick="submitOperation()">
            <i class="fa-solid fa-check me-2"></i>Conferma
        </button>
        <button class="btn btn-secondary" onclick="history.back()">
            <i class="fa-solid fa-arrow-left me-2"></i>Indietro
        </button>

        <div class="spinner" id="spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Caricamento...</span>
            </div>
        </div>
    </div>
</div>

<script src="/magazzino/assets/js/jquery-3.6.0.min.js"></script>
<script>
function submitOperation() {
    const quantity = $('#quantity-input').val();
    const operation = $('input[name="operation"]:checked').val();
    
    if (quantity === '' || isNaN(quantity)) {
        showAlert('Inserisci una quantità valida.', 'warning');
        return;
    }
    
    const qty = parseInt(quantity);
    if (qty < 0) {
        showAlert('La quantità non può essere negativa.', 'warning');
        return;
    }
    
    if (qty === 0) {
        showAlert('Inserisci una quantità maggiore di 0.', 'warning');
        return;
    }
    
    $('#btn-confirm').prop('disabled', true);
    $('#spinner').show();
    
    $.ajax({
        url: 'update_component_quantity.php',
        type: 'POST',
        dataType: 'json',
        data: {
            id: <?= intval($component_id) ?>,
            quantity: qty,
            operation: operation
        },
        success: function(data) {
            if (data.success) {
                $('#current-qty').text(data.new_quantity);
                $('#quantity-input').val('');
                showAlert('Operazione completata con successo!', 'success');
                $('#btn-confirm').prop('disabled', false);
                $('#spinner').hide();
            } else {
                showAlert('Errore: ' + data.message, 'danger');
                $('#btn-confirm').prop('disabled', false);
                $('#spinner').hide();
            }
        },
        error: function(xhr, status, error) {
            try {
                const data = JSON.parse(xhr.responseText);
                showAlert('Errore: ' + data.message, 'danger');
            } catch(e) {
                showAlert('Errore di comunicazione con il server', 'danger');
            }
            $('#btn-confirm').prop('disabled', false);
            $('#spinner').hide();
        }
    });
}

function showAlert(message, type) {
    const alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                      message +
                      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>' +
                      '</div>';
    $('#alertContainer').html(alertHtml);
}

// Focus automatico sul campo quantità
$(function() {
    $('#quantity-input').focus();
});
</script>
</body>
</html>