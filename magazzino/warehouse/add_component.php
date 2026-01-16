<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 17:52:20 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-15 20:31:29
*/
// 2026-01-08: Aggiunta quantità minima
// 2026-01-08: aggiunti quick add per posizioni e categorie
// 2026-01-09: Aggiunta gestione upload immagini
// 2026-01-11: Aggiunto controllo duplicati
// 2026-01-12: Aggiunti campi per prezzo, link fornitore, unità di misura, package, tensione, corrente, potenza, hfe e tags; migliorata gestione equivalenti
// 2026-01-14: Sistemati conteggi quantità per unità di misura

require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';
require_once '../includes/secure_upload.php';

$error = '';
$success = '';
$component = [];

// Recupero posizioni, categorie e ultimi componenti
// 2026-01-14: Aggiunta unità di misura
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$lastComponents = $pdo->query("
    SELECT c.id, c.codice_prodotto, c.quantity, c.unita_misura, cat.name AS category_name, l.name AS location_name, cmp.code AS compartment_code
    FROM components c
    LEFT JOIN categories cat ON c.category_id = cat.id
    LEFT JOIN locations l ON c.location_id = l.id
    LEFT JOIN compartments cmp ON c.compartment_id = cmp.id
    ORDER BY c.id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Compartimenti se è selezionata una location
$compartments = [];
$selectedLocationId = $component['location_id'] ?? $_POST['location_id'] ?? null;
if (isset($selectedLocationId) && is_numeric($selectedLocationId) && $selectedLocationId !== '') {
    $stmt = $pdo->prepare("SELECT * FROM compartments WHERE location_id = ? ORDER BY
      REGEXP_REPLACE(code, '[0-9]', '') ASC,
      CAST(REGEXP_REPLACE(code, '[^0-9]', '') AS UNSIGNED) ASC");
    $stmt->execute([intval($selectedLocationId)]);
    $compartments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Gestione form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codice_prodotto']) && !isset($_POST['quick_add_compartment'])) {
    $codice_prodotto = trim($_POST['codice_prodotto'] ?? '');
    $category_id     = isset($_POST['category_id']) && is_numeric($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $costruttore     = trim($_POST['costruttore'] ?? '');
    $fornitore       = trim($_POST['fornitore'] ?? '');
    $codice_fornitore= trim($_POST['codice_fornitore'] ?? '');
    $quantity        = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $quantity_min    = isset($_POST['quantity_min']) && intval($_POST['quantity_min']) > 0 ? intval($_POST['quantity_min']) : null;
    $location_id     = isset($_POST['location_id']) && is_numeric($_POST['location_id']) ? intval($_POST['location_id']) : null;
    $compartment_id  = isset($_POST['compartment_id']) && is_numeric($_POST['compartment_id']) ? intval($_POST['compartment_id']) : null;
    $datasheet_url   = trim($_POST['datasheet_url'] ?? '');
    
    // Normalizza equivalenti: split per virgole E spazi (ogni parola è un equivalente)
    $equivalents_raw = trim($_POST['equivalents'] ?? '');
    if ($equivalents_raw !== '') {
        // Split per virgole e/o spazi, rimuovi elementi vuoti
        $equivalents_array = preg_split('/[\s,]+/', $equivalents_raw, -1, PREG_SPLIT_NO_EMPTY);
        $equivalents = !empty($equivalents_array) ? json_encode($equivalents_array) : null;
    } else {
        $equivalents = null;
    }
    
    $notes           = trim($_POST['notes'] ?? '');
    $confirm_duplicate = isset($_POST['confirm_duplicate']) && $_POST['confirm_duplicate'] === '1';
    
    // Nuovi campi dalla versione 1.7
    $prezzo          = isset($_POST['prezzo']) && $_POST['prezzo'] !== '' ? floatval($_POST['prezzo']) : null;
    $link_fornitore  = trim($_POST['link_fornitore'] ?? '');
    $unita_misura    = trim($_POST['unita_misura'] ?? 'pz');
    $package         = trim($_POST['package'] ?? '');
    $tensione        = trim($_POST['tensione'] ?? '');
    $corrente        = trim($_POST['corrente'] ?? '');
    $potenza         = trim($_POST['potenza'] ?? '');
    $hfe             = trim($_POST['hfe'] ?? '');
    
    // Normalizza tags: split per virgole E spazi (i tag non possono contenere spazi)
    $tags_raw = trim($_POST['tags'] ?? '');
    if ($tags_raw !== '') {
        // Split per virgole e/o spazi, rimuovi elementi vuoti
        $tags_array = preg_split('/[\s,]+/', $tags_raw, -1, PREG_SPLIT_NO_EMPTY);
        $tags = !empty($tags_array) ? json_encode($tags_array) : null;
    } else {
        $tags = null;
    }

    $datasheet_file = null;
    // Gestione upload file datasheet
    if (isset($_FILES['datasheet_file']) && $_FILES['datasheet_file']['error'] === UPLOAD_ERR_OK) {
        $validator = new SecureUploadValidator(__DIR__ . '/../datasheet');
        $validation = $validator->validateUpload($_FILES['datasheet_file'], ['application/pdf']);
        
        if (!$validation['valid']) {
            $error = 'File datasheet non valido: ' . implode(', ', $validation['errors']);
        }
        // Se validazione OK, il file verrà salvato dopo l'INSERT del componente
    }

    if ($codice_prodotto === '') {
        $error = "Il campo codice prodotto è obbligatorio.";
    } else if (empty($error)) {
        // Controllo duplicati
        $checkStmt = $pdo->prepare("
            SELECT c.id, c.codice_prodotto, l.name AS location_name, cmp.code AS compartment_code
            FROM components c
            LEFT JOIN locations l ON c.location_id = l.id
            LEFT JOIN compartments cmp ON c.compartment_id = cmp.id
            WHERE c.codice_prodotto = ?
        ");
        $checkStmt->execute([$codice_prodotto]);
        $existingComponents = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $duplicateInSameLocation = false;
        $duplicatesInOtherLocations = [];
        
        foreach ($existingComponents as $existing) {
            // Controlla se esiste nella stessa posizione E stesso comparto
            $sameLocation = ($existing['location_name'] ?? null) == ($location_id ? $pdo->query("SELECT name FROM locations WHERE id = $location_id")->fetchColumn() : null);
            $sameCompartment = ($existing['compartment_code'] ?? null) == ($compartment_id ? $pdo->query("SELECT code FROM compartments WHERE id = $compartment_id")->fetchColumn() : null);
            
            if ($sameLocation && $sameCompartment) {
                $duplicateInSameLocation = true;
                $error = "ERRORE: Il componente '{$codice_prodotto}' esiste già nella stessa posizione e comparto!";
                break;
            } else {
                // Duplicato in posizione/comparto diverso
                $locationInfo = $existing['location_name'] ?? 'Nessuna posizione';
                $compartmentInfo = $existing['compartment_code'] ? " - Comparto: {$existing['compartment_code']}" : '';
                $duplicatesInOtherLocations[] = "Posizione: {$locationInfo}{$compartmentInfo}";
            }
        }
        
        // Se ci sono duplicati in altre posizioni e non è stata confermata l'operazione
        if (!$duplicateInSameLocation && !empty($duplicatesInOtherLocations) && !$confirm_duplicate) {
            $component = [
                'codice_prodotto' => $codice_prodotto,
                'category_id' => $category_id,
                'costruttore' => $costruttore,
                'fornitore' => $fornitore,
                'codice_fornitore' => $codice_fornitore,
                'quantity' => $quantity,
                'quantity_min' => $quantity_min,
                'location_id' => $location_id,
                'compartment_id' => $compartment_id,
                'datasheet_url' => $datasheet_url,
                'equivalents' => $equivalents ? implode(', ', json_decode($equivalents)) : '',
                'notes' => $notes,
                'tags' => $tags ? implode(', ', json_decode($tags)) : '',
                'duplicate_warning' => true,
                'duplicate_locations' => $duplicatesInOtherLocations
            ];
            $error = ''; // Reset error per mostrare il warning invece
        } else if (!$duplicateInSameLocation && empty($error)) {
            $stmt = $pdo->prepare("INSERT INTO components 
                (codice_prodotto, category_id, costruttore, fornitore, codice_fornitore, quantity, quantity_min, location_id, compartment_id, datasheet_url, datasheet_file, equivalents, notes, prezzo, link_fornitore, unita_misura, package, tensione, corrente, potenza, hfe, tags)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$codice_prodotto, $category_id, $costruttore, $fornitore, $codice_fornitore, $quantity, $quantity_min, $location_id, $compartment_id, $datasheet_url, null, $equivalents, $notes, $prezzo, $link_fornitore, $unita_misura, $package, $tensione, $corrente, $potenza, $hfe, $tags]);
            
            // Recupera l'ID del componente appena inserito
            $component_id = $pdo->lastInsertId();
            
            // Ora elabora il file datasheet se presente e non ci sono errori
            if (isset($_FILES['datasheet_file']) && $_FILES['datasheet_file']['error'] === UPLOAD_ERR_OK && empty($error)) {
                $validator = new SecureUploadValidator(__DIR__ . '/../datasheet');
                $validation = $validator->validateUpload($_FILES['datasheet_file'], ['application/pdf']);
                
                if ($validation['valid']) {
                    // Salva il file direttamente
                    $customFilename = $component_id . '.pdf';
                    $uploadDir = realpath(__DIR__ . '/../datasheet');
                    $filePath = $uploadDir . DIRECTORY_SEPARATOR . $customFilename;
                    
                    if (move_uploaded_file($_FILES['datasheet_file']['tmp_name'], $filePath)) {
                        // Aggiorna il record con il nome del file
                        $upd = $pdo->prepare("UPDATE components SET datasheet_file = ? WHERE id = ?");
                        $upd->execute([$customFilename, $component_id]);
                    } else {
                        $error = "Impossibile salvare il file datasheet.";
                    }
                }
            }
            
            // Ora elabora l'immagine se presente e non ci sono errori
            $resizedImageData = $_POST['resized_image_data'] ?? '';
            $resizedThumbData = $_POST['resized_thumb_data'] ?? '';
            if (!empty($resizedImageData) && !empty($resizedThumbData) && empty($error)) {
                // Decodifica le immagini base64
                $image_data = preg_replace('/^data:image\/(jpeg|webp);base64,/', '', $resizedImageData);
                $image_data = str_replace(' ', '+', $image_data);
                $image_binary = base64_decode($image_data);
                
                $thumb_data = preg_replace('/^data:image\/(jpeg|webp);base64,/', '', $resizedThumbData);
                $thumb_data = str_replace(' ', '+', $thumb_data);
                $thumb_binary = base64_decode($thumb_data);
                
                if ($image_binary && $thumb_binary) {
                    // Percorsi delle cartelle
                    $base_dir = realpath(__DIR__ . '/../images/components');
                    $thumb_dir = $base_dir . '/thumbs';
                    
                    // Verifica che le cartelle esistano
                    if (!is_dir($base_dir)) {
                        $error = "Cartella images/components non trovata";
                    } elseif (!is_dir($thumb_dir)) {
                        $error = "Cartella images/components/thumbs non trovata";
                    } else {
                        // Nomi dei file
                        $filename = $component_id . '.jpg';
                        $image_path = $base_dir . DIRECTORY_SEPARATOR . $filename;
                        $thumb_path = $thumb_dir . DIRECTORY_SEPARATOR . $filename;
                        
                        // Salva le immagini
                        $image_saved = @file_put_contents($image_path, $image_binary);
                        $thumb_saved = @file_put_contents($thumb_path, $thumb_binary);
                        
                        if ($image_saved === false || $thumb_saved === false) {
                            $error = "Impossibile salvare le immagini sul server";
                        }
                    }
                } else {
                    $error = "Errore nella decodifica delle immagini";
                }
            }
            
            if (empty($error)) {
                $success = "Componente aggiunto con successo.";
            }

            $lastComponents = $pdo->query("
                SELECT c.id, c.codice_prodotto, c.quantity, cat.name AS category_name, l.name AS location_name, cmp.code AS compartment_code
                FROM components c
                LEFT JOIN categories cat ON c.category_id = cat.id
                LEFT JOIN locations l ON c.location_id = l.id
                LEFT JOIN compartments cmp ON c.compartment_id = cmp.id
                ORDER BY c.id DESC
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

include '../includes/header.php';
?>

<div class="container pb-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="fa-solid fa-plus me-2"></i>Aggiungi componente</h4>
    <a href="components.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Torna alla lista</a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php elseif (isset($component['duplicate_warning']) && $component['duplicate_warning']): ?>
    <div class="alert alert-warning">
      <h5 class="alert-heading"><i class="fa-solid fa-triangle-exclamation me-2"></i>Attenzione: Componente già esistente!</h5>
      <p><strong>Il componente "<?= htmlspecialchars($component['codice_prodotto']) ?>"</strong> esiste già nelle seguenti posizioni:</p>
      <ul class="mb-3">
        <?php foreach ($component['duplicate_locations'] as $loc): ?>
          <li><?= htmlspecialchars($loc) ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="mb-0"><strong>Vuoi comunque inserire questo componente nella posizione/comparto selezionato?</strong></p>
    </div>
  <?php elseif ($success): ?>
    <div class="alert alert-success py-0"><?= htmlspecialchars($success) ?></div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      const input = document.querySelector('input[name="codice_prodotto"]');
      input.focus();
      input.select();
    });
    </script>
  <?php endif; ?>

  <form method="post" class="card shadow-sm p-3" enctype="multipart/form-data" id="componentForm">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Posizione</label>
        <div class="input-group">
          <select name="location_id" id="locationSelect" class="form-select form-select-sm">
            <option value="">-- Seleziona posizione --</option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>" <?= (isset($component['location_id']) && $component['location_id'] == $loc['id']) || (isset($_POST['location_id']) && $_POST['location_id'] == $loc['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($loc['name']) ?>
                </option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="quickAddLocationBtn" title="Aggiungi posizione"><i class="fa-solid fa-plus"></i></button>
        </div>
      </div>

      <div class="col-md-4">
        <label class="form-label mb-1 d-flex justify-content-between">
          <span>Comparto</span>
        </label>
        <div class="input-group">
          <select name="compartment_id" id="compartmentSelect" class="form-select form-select-sm">
            <option value="">-- Seleziona comparto --</option>
            <?php foreach ($compartments as $cmp): ?>
              <option value="<?= $cmp['id'] ?>" <?= (isset($component['compartment_id']) && $component['compartment_id'] == $cmp['id']) || (isset($_POST['compartment_id']) && $_POST['compartment_id'] == $cmp['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cmp['code']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="quickAddCompBtn" title="Aggiungi comparto"><i class="fa-solid fa-plus"></i></button>
        </div>
      </div>

      <div class="col-md-4">
        <label class="form-label mb-1">Categoria</label>
        <div class="input-group">
          <select name="category_id" id="categorySelect" class="form-select form-select-sm">
            <option value="">-- Seleziona categoria --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= (isset($component['category_id']) && $component['category_id'] == $cat['id']) || (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="quickAddCategoryBtn" title="Aggiungi categoria"><i class="fa-solid fa-plus"></i></button>
        </div>
      </div>

      <div class="col-md-4">
        <label class="form-label mb-1">Codice prodotto *</label>
        <input type="text" name="codice_prodotto" class="form-control form-control-sm" value="<?= htmlspecialchars($component['codice_prodotto'] ?? $_POST['codice_prodotto'] ?? '') ?>" required>
      </div>

      <div class="col-md-2">
        <label class="form-label mb-1">Quantità *</label>
        <input type="number" name="quantity" class="form-control form-control-sm" value="<?= htmlspecialchars($component['quantity'] ?? $_POST['quantity'] ?? 0) ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label mb-1">Q.tà minima</label>
        <input type="number" name="quantity_min" class="form-control form-control-sm" value="<?= htmlspecialchars($component['quantity_min'] ?? $_POST['quantity_min'] ?? 0) ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label mb-1">Unità misura</label>
        <select name="unita_misura" class="form-select form-select-sm">
          <option value="pz" <?= (isset($component['unita_misura']) && $component['unita_misura'] == 'pz') || (isset($_POST['unita_misura']) && $_POST['unita_misura'] == 'pz') || (!isset($component['unita_misura']) && !isset($_POST['unita_misura'])) ? 'selected' : '' ?>>pz</option>
          <option value="m" <?= (isset($component['unita_misura']) && $component['unita_misura'] == 'm') || (isset($_POST['unita_misura']) && $_POST['unita_misura'] == 'm') ? 'selected' : '' ?>>m</option>
          <option value="cm" <?= (isset($component['unita_misura']) && $component['unita_misura'] == 'cm') || (isset($_POST['unita_misura']) && $_POST['unita_misura'] == 'cm') ? 'selected' : '' ?>>cm</option>
          <option value="kg" <?= (isset($component['unita_misura']) && $component['unita_misura'] == 'kg') || (isset($_POST['unita_misura']) && $_POST['unita_misura'] == 'kg') ? 'selected' : '' ?>>kg</option>
          <option value="g" <?= (isset($component['unita_misura']) && $component['unita_misura'] == 'g') || (isset($_POST['unita_misura']) && $_POST['unita_misura'] == 'g') ? 'selected' : '' ?>>g</option>
          <option value="l" <?= (isset($component['unita_misura']) && $component['unita_misura'] == 'l') || (isset($_POST['unita_misura']) && $_POST['unita_misura'] == 'l') ? 'selected' : '' ?>>l</option>
          <option value="ml" <?= (isset($component['unita_misura']) && $component['unita_misura'] == 'ml') || (isset($_POST['unita_misura']) && $_POST['unita_misura'] == 'ml') ? 'selected' : '' ?>>ml</option>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label mb-1">Equivalenti (separati da virgola)</label>
        <input type="text" name="equivalents" id="equivalents" class="form-control form-control-sm" placeholder="Es. UA78M05, LM340T5" value="<?= htmlspecialchars($component['equivalents'] ?? $_POST['equivalents'] ?? '') ?>">
      </div>

      <div class="col-12">
        <label class="form-label mb-1">Note</label>
        <textarea name="notes" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($component['notes'] ?? $_POST['notes'] ?? '') ?></textarea>
      </div>

      <!-- Sezione campi avanzati/facoltativi -->
      <div class="col-12 mt-2">
        <div class="accordion" id="accordionAdvanced">
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingAdvanced">
              <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
                <i class="fa-solid fa-sliders me-2"></i><strong>Campi avanzati / facoltativi</strong>
              </button>
            </h2>
            <div id="collapseAdvanced" class="accordion-collapse collapse" aria-labelledby="headingAdvanced" data-bs-parent="#accordionAdvanced">
              <div class="accordion-body">
                <div class="row g-2">
                  
                  <!-- Campi fornitori e produttore -->
                  <div class="col-md-4">
                    <label class="form-label mb-1">Costruttore</label>
                    <input type="text" name="costruttore" id="costruttore" class="form-control form-control-sm" value="<?= htmlspecialchars($component['costruttore'] ?? '') ?>">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label mb-1">Fornitore</label>
                    <input type="text" name="fornitore" id="fornitore" class="form-control form-control-sm" value="<?= htmlspecialchars($component['fornitore'] ?? '') ?>">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label mb-1">Codice fornitore</label>
                    <input type="text" name="codice_fornitore" class="form-control form-control-sm" value="<?= htmlspecialchars($component['codice_fornitore'] ?? '') ?>">
                  </div>

                  <!-- Prezzo e link fornitore -->
                  <div class="col-md-3">
                    <label class="form-label mb-1">Prezzo (€)</label>
                    <input type="number" step="0.01" name="prezzo" class="form-control form-control-sm" value="<?= htmlspecialchars($component['prezzo'] ?? '') ?>" placeholder="0.00">
                  </div>

                  <div class="col-md-9">
                    <label class="form-label mb-1">Link fornitore</label>
                    <input type="url" name="link_fornitore" class="form-control form-control-sm" value="<?= htmlspecialchars($component['link_fornitore'] ?? '') ?>" placeholder="https://">
                  </div>

                  <!-- Caratteristiche fisiche -->
                  <div class="col-md-4">
                    <label class="form-label mb-1">Package</label>
                    <input type="text" name="package" class="form-control form-control-sm" value="<?= htmlspecialchars($component['package'] ?? '') ?>" placeholder="Es. TO-220, SO-8, DIP-8" list="packageList">
                    <datalist id="packageList">
                      <option value="TO-220">
                      <option value="TO-92">
                      <option value="TO-126">
                      <option value="TO-247">
                      <option value="SO-8">
                      <option value="SO-16">
                      <option value="DIP-8">
                      <option value="DIP-14">
                      <option value="DIP-16">
                      <option value="SMD">
                      <option value="0805">
                      <option value="1206">
                      <option value="SOT-23">
                    </datalist>
                  </div>

                  <!-- Caratteristiche elettriche -->
                  <div class="col-md-2">
                    <label class="form-label mb-1">Tensione V</label>
                    <input type="text" name="tensione" class="form-control form-control-sm" value="<?= htmlspecialchars($component['tensione'] ?? '') ?>" placeholder="Es. 5, 12">
                  </div>

                  <div class="col-md-2">
                    <label class="form-label mb-1">Corrente A</label>
                    <input type="text" name="corrente" class="form-control form-control-sm" value="<?= htmlspecialchars($component['corrente'] ?? '') ?>" placeholder="Es. 1, 0.5">
                  </div>

                  <div class="col-md-2">
                    <label class="form-label mb-1">Potenza W</label>
                    <input type="text" name="potenza" class="form-control form-control-sm" value="<?= htmlspecialchars($component['potenza'] ?? '') ?>" placeholder="Es. 1, 0.25">
                  </div>

                  <div class="col-md-2">
                    <label class="form-label mb-1">hFE (Guadagno)</label>
                    <input type="text" name="hfe" class="form-control form-control-sm" value="<?= htmlspecialchars($component['hfe'] ?? '') ?>" placeholder="Es. 100-300">
                  </div>

                  <!-- Datasheet e immagine -->
                  <div class="col-md-8">
                    <label class="form-label mb-1">Link datasheet Web</label>
                    <input type="url" name="datasheet_url" class="form-control form-control-sm" value="<?= htmlspecialchars($component['datasheet_url'] ?? '') ?>" placeholder="https://">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label mb-1">Datasheet PDF</label>
                    <div class="input-group input-group-sm">
                      <input type="file" name="datasheet_file" id="datasheet_file" class="form-control" accept=".pdf">
                      <button type="button" id="remove-datasheet" class="btn btn-outline-danger" title="Rimuovi datasheet" style="display:none;">
                        <i class="fa-solid fa-times"></i>
                      </button>
                    </div>
                    <small class="text-muted">Max 10MB</small>
                  </div>

                  <div class="col-md-8">
                    <label class="form-label mb-1">Immagine componente</label>
                    <div class="input-group input-group-sm">
                      <input type="file" id="component_image" class="form-control" accept="image/jpeg,image/jpg,image/gif,image/bmp,image/webp">
                      <button type="button" id="remove-image" class="btn btn-outline-danger" title="Rimuovi immagine" style="display:none;">
                        <i class="fa-solid fa-times"></i>
                      </button>
                    </div>
                    <small class="text-muted">JPG, GIF, BMP, WebP - verrà ridimensionata a 500x500px</small>
                    <input type="hidden" name="resized_image_data" id="resized_image_data">
                    <input type="hidden" name="resized_thumb_data" id="resized_thumb_data">
                  </div>

                  <div class="col-md-4" id="image-preview-container" style="display:none;">
                    <label class="form-label mb-1">Anteprima</label>
                    <div>
                      <img id="image-preview" src="" alt="Preview" style="max-width: 100px; max-height: 100px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                  </div>

                  <!-- Tags -->
                  <div class="col-12">
                    <label class="form-label mb-1">Tags (separati da virgola)</label>
                    <input type="text" name="tags" class="form-control form-control-sm" value="<?= htmlspecialchars($component['tags'] ?? '') ?>" placeholder="Es. amplificatore, vintage, audio">
                    <small class="text-muted">Usa le virgole per separare i tag</small>
                  </div>

                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <input type="hidden" name="confirm_duplicate" id="confirm_duplicate" value="0">

    <div class="d-flex justify-content-end mt-3">
      <?php if (isset($component['duplicate_warning']) && $component['duplicate_warning']): ?>
        <button type="button" class="btn btn-secondary btn-sm me-2" onclick="window.location.reload()">
          <i class="fa-solid fa-times me-1"></i> Annulla
        </button>
        <button type="submit" class="btn btn-warning btn-sm" onclick="document.getElementById('confirm_duplicate').value='1'">
          <i class="fa-solid fa-check me-1"></i> Sì, inserisci comunque
        </button>
      <?php else: ?>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-save me-1"></i> Salva</button>
      <?php endif; ?>
    </div>
  </form>

  <!-- Ultimi componenti in basso -->
  <div class="card shadow-sm mt-2">
    <div class="card-header bg-light fw-bold"><i class="fa-solid fa-clock-rotate-left me-2"></i>Ultimi 10 componenti inseriti</div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Codice</th>
            <th>Categoria</th>
            <th>Posizione</th>
            <th>Comparto</th>
            <th class="text-end">Q.tà</th>
            <th class="text-center" style="width:50px;">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$lastComponents): ?>
            <tr><td colspan="6" class="text-center text-muted">Nessun componente ancora inserito.</td></tr>
          <?php else: ?>
            <?php foreach ($lastComponents as $c): ?>
              <tr data-component-id="<?= $c['id'] ?>">
                <td><a target="_blank" href="edit_component.php?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['codice_prodotto']) ?></a></td>
                <td><?= htmlspecialchars($c['category_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($c['location_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($c['compartment_code'] ?? '—') ?></td>
                <td class="text-end"><?= htmlspecialchars($c['quantity']) . ' ' . htmlspecialchars($c['unita_misura'] ?? 'pz') ?></td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-outline-danger btn-delete-component" data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['codice_prodotto']) ?>" title="Elimina componente">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal aggiunta posizione -->
<div class="modal fade" id="modalQuickAddLocation" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form id="formQuickAddLocation">
        <div class="modal-header">
          <h5 class="modal-title">Nuova posizione</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Nome posizione</label>
            <input type="text" name="name" id="quickLocationName" class="form-control form-control-sm" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary btn-sm">Salva</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal aggiunta categoria -->
<div class="modal fade" id="modalQuickAddCategory" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form id="formQuickAddCategory">
        <div class="modal-header">
          <h5 class="modal-title">Nuova categoria</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Nome categoria</label>
            <input type="text" name="name" id="quickCategoryName" class="form-control form-control-sm" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary btn-sm">Salva</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal aggiunta comparto -->
<div class="modal fade" id="modalQuickAddCompartment" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form id="formQuickAddCompartment">
        <div class="modal-header">
          <h5 class="modal-title">Nuovo comparto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Codice comparto</label>
            <input type="text" name="code" id="quickCompCode" class="form-control form-control-sm" required>
          </div>
          <input type="hidden" name="location_id" id="quickCompLocationId">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary btn-sm">Salva</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(function() {
  // Variabili per memorizzare le immagini ridimensionate
  let resizedImageData = null;
  let resizedThumbData = null;

  // Funzione per normalizzare equivalenti e tags (spazi e virgole sono separatori)
  function normalizeCommaSpaceField(inputElement) {
    let value = $(inputElement).val().trim();
    if (value === '') return;
    
    // Split per virgole E spazi, rimuovi elementi vuoti
    let items = value.split(/[\s,]+/).filter(item => item !== '');
    
    // Ricomponi con formato pulito: "aaa, bbb, ccc"
    if (items.length > 0) {
      value = items.join(', ');
      $(inputElement).val(value);
    }
  }

  // Validazione in tempo reale per equivalenti e tags
  $('#equivalents, input[name="tags"]').on('blur', function() {
    normalizeCommaSpaceField(this);
  });

  // Gestione selezione file datasheet PDF
  $('#datasheet_file').on('change', function(e) {
    const file = e.target.files[0];
    if (!file) {
      $('#remove-datasheet').hide();
      return;
    }
    
    // Mostra il bottone X
    $('#remove-datasheet').show();
  });

  // Rimuovi datasheet
  $('#remove-datasheet').on('click', function() {
    $('#datasheet_file').val('');
    $(this).hide();
  });

  // Gestione upload e ridimensionamento immagine
  $('#component_image').on('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    // Verifica tipo file
    const validTypes = ['image/jpeg', 'image/jpg', 'image/gif', 'image/bmp', 'image/webp'];
    if (!validTypes.includes(file.type)) {
      alert('Formato non valido. Usa JPG, GIF, BMP o WebP.');
      $(this).val('');
      return;
    }

    const reader = new FileReader();
    reader.onload = function(event) {
      const img = new Image();
      img.onload = function() {
        // Ridimensiona a 500x500
        const canvas500 = document.createElement('canvas');
        canvas500.width = 500;
        canvas500.height = 500;
        const ctx500 = canvas500.getContext('2d');
        
        // Calcola dimensioni per mantenere proporzioni
        let sourceX = 0, sourceY = 0, sourceSize = Math.min(img.width, img.height);
        if (img.width > img.height) {
          sourceX = (img.width - img.height) / 2;
        } else {
          sourceY = (img.height - img.width) / 2;
        }
        
        // Disegna immagine ridimensionata 500x500
        ctx500.drawImage(img, sourceX, sourceY, sourceSize, sourceSize, 0, 0, 500, 500);
        resizedImageData = canvas500.toDataURL('image/jpeg', 0.9);
        
        // Imposta il campo hidden
        $('#resized_image_data').val(resizedImageData);
        console.log('Image data set, length:', resizedImageData.length);
        
        // Ridimensiona a 80x80 per thumbnail
        const canvas80 = document.createElement('canvas');
        canvas80.width = 80;
        canvas80.height = 80;
        const ctx80 = canvas80.getContext('2d');
        ctx80.drawImage(img, sourceX, sourceY, sourceSize, sourceSize, 0, 0, 80, 80);
        resizedThumbData = canvas80.toDataURL('image/jpeg', 0.85);
        
        // Imposta il campo hidden
        $('#resized_thumb_data').val(resizedThumbData);
        console.log('Thumb data set, length:', resizedThumbData.length);
        
        // Mostra anteprima
        $('#image-preview').attr('src', resizedThumbData);
        $('#image-preview-container').show();
        $('#remove-image').show();
      };
      img.src = event.target.result;
    };
    reader.readAsDataURL(file);
  });

  // Rimuovi immagine
  $('#remove-image').on('click', function() {
    $('#component_image').val('');
    $('#image-preview-container').hide();
    $(this).hide();
    resizedImageData = null;
    resizedThumbData = null;
    $('#resized_image_data').val('');
    $('#resized_thumb_data').val('');
  });

  // Intercetta submit del form per gestire l'immagine
  const $form = $('#componentForm');
  
  $form.on('submit', function(e) {
    // Normalizza i campi
    normalizeCommaSpaceField($('#equivalents')[0]);
    normalizeCommaSpaceField($('input[name="tags"]')[0]);
    
    console.log('Submitting form, image data length:', $('#resized_image_data').val().length);
    console.log('Thumb data length:', $('#resized_thumb_data').val().length);
    
    // Procedi con il submit normale
    return true;
  });

  // Gestione modal aggiunta posizione
  $('#quickAddLocationBtn').on('click', function(e) {
    e.preventDefault();
    $('#quickLocationName').val('');
    var modal = new bootstrap.Modal(document.getElementById('modalQuickAddLocation'));
    modal.show();
  });

  $('#modalQuickAddLocation').on('shown.bs.modal', function () {
    $('#quickLocationName').focus();
  });

  $('#formQuickAddLocation').on('submit', function(e) {
    e.preventDefault();
    const $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true);
    $.post('quick_add_location.php', $(this).serialize(), function(resp) {
      $btn.prop('disabled', false);
      if (resp.success) {
        // Ricarica le posizioni
        $.getJSON('get_locations.php', function(data) {
          let opts = '<option value="">-- Seleziona posizione --</option>';
          data.forEach(function(it) {
            const selected = (it.id == resp.new_id) ? ' selected' : '';
            opts += `<option value="${it.id}"${selected}>${it.name}</option>`;
          });
          $('#locationSelect').html(opts).trigger('change');
        });
        var modalEl = document.getElementById('modalQuickAddLocation');
        var modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
      } else {
        alert(resp.error || 'Errore durante la creazione della posizione.');
      }
    }, 'json').fail(function(){
      $btn.prop('disabled', false);
      alert('Errore di rete.');
    });
  });

  // Gestione modal aggiunta categoria
  $('#quickAddCategoryBtn').on('click', function(e) {
    e.preventDefault();
    $('#quickCategoryName').val('');
    var modal = new bootstrap.Modal(document.getElementById('modalQuickAddCategory'));
    modal.show();
  });

  $('#modalQuickAddCategory').on('shown.bs.modal', function () {
    $('#quickCategoryName').focus();
  });

  $('#formQuickAddCategory').on('submit', function(e) {
    e.preventDefault();
    const $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true);
    $.post('quick_add_category.php', $(this).serialize(), function(resp) {
      $btn.prop('disabled', false);
      if (resp.success) {
        // Ricarica le categorie
        $.getJSON('get_categories.php', function(data) {
          let opts = '<option value="">-- Seleziona categoria --</option>';
          data.forEach(function(it) {
            const selected = (it.id == resp.new_id) ? ' selected' : '';
            opts += `<option value="${it.id}"${selected}>${it.name}</option>`;
          });
          $('#categorySelect').html(opts);
        });
        var modalEl = document.getElementById('modalQuickAddCategory');
        var modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
      } else {
        alert(resp.error || 'Errore durante la creazione della categoria.');
      }
    }, 'json').fail(function(){
      $btn.prop('disabled', false);
      alert('Errore di rete.');
    });
  });

  $('#locationSelect').on('change', function() {
    const locId = $(this).val();
    const $sel = $('#compartmentSelect');
    $sel.html('<option>Caricamento...</option>');
    if (!locId) {
      $sel.html('<option value="">-- Seleziona comparto --</option>');
      return;
    }
    $.getJSON('get_compartments.php', { location_id: locId }, function(data) {
      let opts = '<option value="">-- Seleziona comparto --</option>';
      data.forEach(function(it) {
        opts += `<option value="${it.id}">${it.code}</option>`;
      });
      $sel.html(opts);
    });
  });

  $('#openAddCompartmentModal, #quickAddCompBtn').on('click', function(e) {
    e.preventDefault();
    const locId = $('#locationSelect').val();
    if (!locId) {
      alert('Seleziona prima una posizione.');
      return;
    }
    $('#quickCompLocationId').val(locId);
    $('#quickCompCode').val('');
    var modal = new bootstrap.Modal(document.getElementById('modalQuickAddCompartment'));
    modal.show();
  });

  // evento per gestire il focus
  $('#modalQuickAddCompartment').on('shown.bs.modal', function () {
      $('#quickCompCode').focus();
  });

  $('#formQuickAddCompartment').on('submit', function(e) {
    e.preventDefault();
    const $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true);
    $.post('quick_add_compartment.php', $(this).serialize(), function(resp) {
      $btn.prop('disabled', false);
      if (resp.success) {
        $.getJSON('get_compartments.php', { location_id: resp.location_id }, function(data) {
          let opts = '<option value="">-- Seleziona comparto --</option>';
          data.forEach(function(it) {
            const selected = (it.id == resp.new_id) ? ' selected' : '';
            opts += `<option value="${it.id}"${selected}>${it.code}</option>`;
          });
          $('#compartmentSelect').html(opts);
        });
        var modalEl = document.getElementById('modalQuickAddCompartment');
        var modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
      } else {
        alert(resp.error || 'Errore durante la creazione del comparto.');
      }
    }, 'json').fail(function(){
      $btn.prop('disabled', false);
      alert('Errore di rete.');
    });
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
      error: function() {
        $btn.prop('disabled', false);
        alert('Errore durante l\'eliminazione del componente.');
      }
    });
  });
});
</script>

<?php include '../includes/footer.php'; ?>