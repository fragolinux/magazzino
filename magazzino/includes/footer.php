<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:49:31 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-03-03
*/
// 2026-02-01: Aggiunta barra del footer con data/ora, autore e versione
// 2026-02-09: Footer sticky, migliorato responsive e design
// 2025-03-03: aggiunto modale di avviso per componenti sotto scorta, con possibilità di nascondere singoli avvisi tramite cookie

// Recupera versione dal database
$version_info = null;
try {
    $stmt = $pdo->query("SELECT version, applied_at FROM db_version ORDER BY CAST(SUBSTRING_INDEX(version, '.', 1) AS UNSIGNED) DESC, CAST(SUBSTRING_INDEX(version, '.', -1) AS UNSIGNED) DESC LIMIT 1");
    $version_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $version_info = ['version' => 'unknown', 'applied_at' => date('Y-m-d H:i:s')];
}

// Anno corrente per copyright
$currentYear = date('Y');
?>
</div>

<footer class="app-footer mt-auto">
  <div class="container-fluid">
    <div class="row align-items-center g-1 py-2">
      <!-- Data/ora a sinistra -->
      <div class="col-12 col-md-4 text-center text-md-start">
        <span class="footer-badge">
          <i class="fa-solid fa-clock me-1"></i><?php echo date('d/m/Y H:i'); ?>
        </span>
      </div>
      
      <div class="col-12 col-md-4 text-center">
        <small class="footer-author">
          Autore: <a href="https://www.youtube.com/@rg4tech" target="_blank" class="footer-link">Gabriele Riva (RG4Tech Youtube Channel)</a>
        </small>
      </div>

      <div class="col-12 col-md-4 text-center text-md-end">
        <span class="footer-version" title="Versione <?php echo htmlspecialchars($version_info['version']); ?>">
          <i class="fa-solid fa-tag me-1"></i>v<?php echo htmlspecialchars($version_info['version']); ?>
          <span class="d-none d-md-inline">• <?php echo date('d/m/Y', strtotime($version_info['applied_at'])); ?></span>
        </span>
      </div>
    </div>
  </div>
</footer>

<script src="<?= BASE_PATH ?>assets/js/bootstrap.bundle.min.js"></script>

<!-- Low Stock Alert Modal -->
<div class="modal fade" id="lowStockAlertModal" tabindex="-1" aria-labelledby="lowStockAlertModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-warning shadow">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="lowStockAlertModalLabel">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>Avviso Sotto Scorta
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>I seguenti componenti hanno una quantità inferiore alla scorta minima:</p>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Codice Prodotto</th>
                <th>Quantità Attuale</th>
                <th>Scorta Minima</th>
                <th class="text-end">Azione</th>
              </tr>
            </thead>
            <tbody id="lowStockAlertBody">
              <!-- Caricato via JS -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    // Funzione per leggere cookie
    function getCookie(name) {
        let matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : null;
    }

    // Funzione per impostare cookie
    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            let date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }

    // Carica alert via AJAX
    $.getJSON('<?= BASE_PATH ?>warehouse/ajax_get_low_stock_alerts.php', function(data) {
        if (data.length > 0) {
            let html = '';
            data.forEach(function(item) {
                html += `<tr>
                    <td><strong>${item.codice_prodotto}</strong></td>
                    <td class="text-danger">${item.quantity} ${item.unita_misura}</td>
                    <td>${item.quantity_min} ${item.unita_misura}</td>
                    <td class="text-end">
                        <button class="btn btn-xs btn-outline-info hide-alert" data-id="${item.id}" data-days="1" title="Nascondi solo oggi">
                            <i class="fa-solid fa-calendar-day me-1"></i>Nascondi oggi
                        </button>
                        <button class="btn btn-xs btn-outline-secondary hide-alert" data-id="${item.id}" data-days="365" title="Nascondi per sempre">
                            <i class="fa-solid fa-eye-slash me-1"></i>Nascondi
                        </button>
                    </td>
                </tr>`;
            });
            $('#lowStockAlertBody').html(html);
            $('#lowStockAlertModal').modal('show');
        }
    });

    // Gestione pulsante Nascondi
    $(document).on('click', '.hide-alert', function() {
        const id = $(this).data('id');
        const days = $(this).data('days') || 365;
        let hidden = getCookie('hide_low_stock');
        let hiddenList = hidden ? hidden.split(',') : [];
        
        if (!hiddenList.includes(id.toString())) {
            hiddenList.push(id);
            setCookie('hide_low_stock', hiddenList.join(','), days);
        }
        
        $(this).closest('tr').fadeOut(function() {
            if ($('#lowStockAlertBody tr:visible').length === 0) {
                $('#lowStockAlertModal').modal('hide');
            }
        });
    });
});
</script>
</body>
</html>