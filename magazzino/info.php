<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-03 10:15:23 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-13 23:20:15
*/
// 2026-01-13: Invertito ordine visualizzazione delle versioni

require_once 'includes/db_connect.php';
require_once 'includes/auth_check.php';

// Leggi la versione (usa l'ultima riga il cui primo carattere NON è uno spazio/tab)
$versione = 'N/A';
$versionFile = file_get_contents('versioni.txt');
$lines = preg_split('/\r\n|\r|\n/', $versionFile);
if (!empty($lines)) {
    // Scorri le righe dal fondo per trovare l'ultima riga che inizia con un carattere (non whitespace)
    $lastLine = null;
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $lnRaw = $lines[$i];
        if ($lnRaw === null || $lnRaw === '') {
            continue;
        }
        // Se il primo carattere non è uno spazio/tab, considero la riga
        if (preg_match('/^\S/', $lnRaw)) {
            $lastLine = trim($lnRaw);
            break;
        }
    }
    $versionDate = '';
    if ($lastLine !== null) {
        if (preg_match('/^(\d+\.\d+)/', $lastLine, $matches)) {
            $versione = $matches[1];
        }
        // Estrai la data in formato DD/MM/YYYY o YYYY-MM-DD, se presente
        if (preg_match('/\b(\d{2}\/\d{2}\/\d{4})\b/', $lastLine, $mdate)) {
            $versionDate = $mdate[1];
        } elseif (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $lastLine, $mdate)) {
            $versionDate = $mdate[1];
        }
    }
}

// Leggi la licenza
$licenza = file_get_contents('LICENSE.txt');
// Leggi il file versioni completo e invertilo per visualizzazione (più recente in alto)
$versioniContent = file_get_contents('versioni.txt');
$versioniLines = preg_split('/\r\n|\r|\n/', $versioniContent);

// Raggruppa le righe per blocco di versione
$blocks = [];
$currentBlock = [];
foreach ($versioniLines as $line) {
    // Una riga che inizia con un numero è l'inizio di una nuova versione
    if (preg_match('/^\d+\.\d+/', $line)) {
        // Salva il blocco precedente se non è vuoto
        if (!empty($currentBlock)) {
            $blocks[] = implode("\n", $currentBlock);
        }
        // Inizia un nuovo blocco
        $currentBlock = [$line];
    } else {
        // Aggiungi la riga al blocco corrente
        $currentBlock[] = $line;
    }
}
// Aggiungi l'ultimo blocco
if (!empty($currentBlock)) {
    $blocks[] = implode("\n", $currentBlock);
}

// Inverti i blocchi
$blocks = array_reverse($blocks);
$versioni = implode("\n", $blocks);

?>

<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-10 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="fa-solid fa-circle-info me-2"></i>Informazioni</h3>
                </div>
                <div class="card-body">
                    <!-- Versione -->
                    <div class="mb-4">
                        <div class="border-bottom pb-2 mb-3">
                            <span class="fs-5">
                                <i class="fa-solid fa-tag me-2 text-primary"></i><strong>Versione:</strong>
                                <span class="ms-2"><?= htmlspecialchars($versione) ?>
                                <?php if (!empty($versionDate)): ?>
                                    <span class="text-muted ms-2 small">(<?= htmlspecialchars($versionDate) ?>)</span>
                                <?php endif; ?>
                                </span>
                            </span>
                        </div>
                    </div>

                    <!-- Autore -->
                    <div class="mb-4">
                        <div class="border-bottom pb-2 mb-3">
                            <span class="fs-5">
                                <i class="fa-solid fa-user me-2 text-primary"></i><strong>Autore:</strong>
                                <span class="ms-2">Gabriele Riva (<a href="https://www.youtube.com/@rg4tech" target="_blank" rel="noopener noreferrer">RG4Tech Youtube Channel</a>)</span>
                            </span>
                        </div>
                    </div>

                    <!-- Cronologia Versioni -->
                    <div class="mb-4">
                        <h5 class="border-bottom pb-2 mb-3">
                            <i class="fa-solid fa-history me-2 text-primary"></i>Cronologia Versioni
                        </h5>
                        <div class="bg-light p-4 rounded border" style="max-height: 600px; overflow-y: auto;">
                            <pre style="white-space: pre-wrap; word-wrap: break-word; font-size: 0.9rem;"><?= htmlspecialchars($versioni) ?></pre>
                        </div>
                    </div>

                    <!-- Licenza -->
                    <div class="mb-4">
                        <h5 class="border-bottom pb-2 mb-3">
                            <i class="fa-solid fa-scale-balanced me-2 text-primary"></i>Licenza GNU GPL v3
                        </h5>
                        <div class="bg-light p-4 rounded border" style="max-height: 400px; overflow-y: auto;">
                            <pre style="white-space: pre-wrap; word-wrap: break-word; font-size: 0.9rem;"><?= htmlspecialchars($licenza) ?></pre>
                        </div>
                        <p class="mt-3 text-muted small">
                            <a href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank" rel="noopener noreferrer">
                                <i class="fa-solid fa-external-link me-1"></i>Visualizza la licenza completa su GNU.org
                            </a>
                        </p>
                    </div>

                    <!-- Back Button -->
                    <div class="mt-4">
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="fa-solid fa-arrow-left me-2"></i>Indietro
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>