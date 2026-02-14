<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-02-09 18:35:41 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2026-02-09 18:35:41
*/

/*
 * Modal Dettagli Componente - Include sia HTML che JavaScript
 * Da includere in pagine che devono mostrare i dettagli di un componente
 * Dipendenze: view_component.php per caricare i dettagli via AJAX
 */
?>

<!-- Modal Dettagli Componente -->
<div class="modal fade" id="componentModal" tabindex="-1" aria-labelledby="componentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="componentModalLabel"><i class="fa-solid fa-eye me-2"></i>Dettagli componente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        <div id="component-details" class="text-muted">Caricamento...</div>
      </div>
    </div>
  </div>
</div>

<script>
// Gestione modal dettagli componente
$(document).on('click', '.btn-view', function(){
    const id = $(this).data('id');
    $('#component-details').html('Caricamento...');
    $('#componentModal').modal('show');
    $.get('<?= BASE_PATH ?>warehouse/view_component.php', {id: id}, function(data){
        $('#component-details').html(`
            <div class="alert alert-secondary py-1 mb-2">
                <small><strong>ID Componente:</strong> ${id}</small>
            </div>
            ${data}
        `);
    });
});
</script>
