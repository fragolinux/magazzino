<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-02-01 18:18:38 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-01 20:00:37
*/

/*
 * Pagina per visualizzare gli ultimi componenti inseriti
 */

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Validazione e default per il numero di componenti da mostrare
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) && intval($_GET['limit']) > 0 ? intval($_GET['limit']) : 20;
// Limitare il massimo a 500 per evitare query troppo pesanti
if ($limit > 500) $limit = 500;

// Recupero ultimi componenti
$lastComponents = $pdo->query("
    SELECT c.id, c.codice_prodotto, c.quantity, c.unita_misura, cat.name AS category_name, l.name AS location_name, cmp.code AS compartment_code
    FROM components c
    LEFT JOIN categories cat ON c.category_id = cat.id
    LEFT JOIN locations l ON c.location_id = l.id
    LEFT JOIN compartments cmp ON c.compartment_id = cmp.id
    ORDER BY c.id DESC
    LIMIT $limit
")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';

?>
<div class="container-fluid mt-4">
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
      <div class="row align-items-center">
        <div class="col">
          <h5 class="mb-0"><i class="fa-solid fa-clock me-2"></i>Ultimi componenti inseriti</h5>
        </div>
        <div class="col-auto">
          <div class="input-group input-group-sm" style="width: 200px;">
            <label class="input-group-text" for="limitInput">Mostra</label>
            <input type="number" class="form-control" id="limitInput" min="1" max="500"
              value="<?= htmlspecialchars($limit) ?>" placeholder="Numero componenti">
            <button class="btn btn-primary" type="button" id="applyLimitBtn" title="Applica filtro">
              <i class="fa-solid fa-check"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Codice</th>
            <th>Categoria</th>
            <th>Posizione</th>
            <th>Comparto</th>
            <th class="text-end">Q.tà</th>
            <th class="text-center" style="width:110px;">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$lastComponents): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-4">Nessun componente ancora inserito.</td>
          </tr>
          <?php else: ?>
          <?php foreach ($lastComponents as $c): ?>
          <tr class="component-row" data-component-id="<?= $c['id'] ?>">
            <td><a target="_blank" href="edit_component.php?id=<?= $c['id'] ?>"
                class="text-decoration-none"><?= htmlspecialchars($c['codice_prodotto']) ?></a></td>
            <td><?= htmlspecialchars($c['category_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($c['location_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($c['compartment_code'] ?? '—') ?></td>
            <td class="text-end">
              <?= htmlspecialchars($c['quantity']) . ' ' . htmlspecialchars($c['unita_misura'] ?? 'pz') ?></td>
            <td class="text-center">
              <div class="btn-group btn-group-sm" role="group">
                <a href="edit_component.php?id=<?= $c['id'] ?>&return_url=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline-primary"
                  title="Modifica componente">
                  <i class="fa-solid fa-pen-to-square"></i>
                </a>
                <button type="button" class="btn btn-outline-danger btn-delete-component" data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['codice_prodotto']) ?>" title="Elimina componente">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="alert alert-info small">
    <i class="fa-solid fa-info-circle me-1"></i>
    Ultimi <strong><?= count($lastComponents) ?></strong> componenti inseriti (limite massimo: 500)
  </div>
</div>

<script>
document.getElementById('applyLimitBtn').addEventListener('click', function() {
  const limit = document.getElementById('limitInput').value;
  if (limit && limit > 0) {
    window.location.href = '?limit=' + encodeURIComponent(limit);
  }
});

// Permettere di applicare il filtro premendo Invio
document.getElementById('limitInput').addEventListener('keypress', function(e) {
  if (e.key === 'Enter') {
    document.getElementById('applyLimitBtn').click();
  }
});

// Gestione cancellazione componente dalla tabella ultimi inseriti
$(document).on('click', '.btn-delete-component', function() {
  const componentId = $(this).data('id');
  const componentName = $(this).data('name');
  
  if (!confirm(`Sei sicuro di voler eliminare il componente "${componentName}"?`)) {
    return;
  }
  
  const $btn = $(this);
  $btn.prop('disabled', true);
  
  $.ajax({
    url: 'delete_component.php?id=' + componentId,
    method: 'GET',
    success: function() {
      // Rimuovi la riga dalla tabella con animazione
      $btn.closest('tr').fadeOut(400, function() {
        $(this).remove();
        // Verifica se non ci sono più righe
        const rowCount = $('tbody tr').length;
        if (rowCount === 0) {
          $('tbody').html('<tr><td colspan="6" class="text-center text-muted">Nessun componente ancora inserito.</td></tr>');
        }
      });
    },
    error: function(xhr) {
      $btn.prop('disabled', false);
      const errorMsg = xhr.responseText || 'Errore durante la cancellazione del componente.';
      alert('Errore: ' + errorMsg);
    }
  });
});
</script>

<?php include '../includes/footer.php'; ?>