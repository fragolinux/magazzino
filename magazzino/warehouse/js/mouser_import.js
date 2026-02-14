/*
 * @Author: Andrea Gonzo
 * @Date: 20206-02-08
 */  

document.addEventListener('DOMContentLoaded', function () {

  /* ==========================================================
   *  RIFERIMENTI DOM PRINCIPALI
   * ========================================================== */
  const btnImport = document.getElementById('btnMouserImport');
  const codiceInput = document.getElementById('codice_prodotto');


  /* ==========================================================
 *  OVERLAY DI ATTESA IN CORNICE
 * ========================================================== */
function showLoadingOverlay() {
  // Rimuove eventuale overlay esistente
  const existing = document.getElementById('loadingOverlay');
  if (existing) existing.remove();

  const overlay = document.createElement('div');
  overlay.id = 'loadingOverlay';
  overlay.style.cssText = `
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    border: 2px solid #0d6efd;
    border-radius: 10px;
    padding: 30px 50px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    font-size: 18px;
    color: #131212;
    font-family: Arial, sans-serif;
    z-index: 2000;
    text-align: center;
    min-width: 300px;
  `;

  // aggiungo un piccolo spinner sopra il testo
  const spinner = document.createElement('div');
  spinner.style.cssText = `
    border: 4px solid #f3f3f3;
    border-top: 4px solid #0d6efd;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    margin: 0 auto 15px;
    animation: spin 1s linear infinite;
  `;
  overlay.appendChild(spinner);

  const text = document.createElement('div');
  text.textContent = 'Attendere, caricamento dati Mouser…';
  overlay.appendChild(text);

  // animazione spinner 
  if (!document.getElementById('spinnerStyle')) {
    const style = document.createElement('style');
    style.id = 'spinnerStyle';
    style.textContent = `
      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }
    `;
    document.head.appendChild(style);
  }

  document.body.appendChild(overlay);
}

function hideLoadingOverlay() {
  const overlay = document.getElementById('loadingOverlay');
  if (overlay) overlay.remove();
}



  /* ==========================================================
   *  MAPPE CAMPI (Mouser → UI → Form add_component)
   * ========================================================== */

  // Etichette visualizzate nel popup finale
  const fieldNamesMap = {
    partNumber: "Codice prodotto",
    manufacturer: "Costruttore",
    mouserSku: "Codice Fornitore",
    datasheet: "Url Datasheet",
    description: "Descrizione/Note",
    fornitore: "Fornitore",
    mouser_number: "Codice Fornitore",
    mouser_product_url: "Link Fornitore",
    mouser_image: "Immagine",
    mouser_datasheet: "Datasheet",
    price: "Prezzo"
  };

  // Mapping campo Mouser → name input HTML
  const fieldToInputName = {
    partNumber: "codice_prodotto",
    manufacturer: "costruttore",
    mouserSku: "codice_fornitore",
    datasheet: "datasheet_url",
    description: "notes",
    fornitore: "fornitore",
    mouser_number: "codice_fornitore",
    mouser_product_url: "link_fornitore",
    price: "prezzo"
  };


/* ==========================================================
   *  POPUP FINALE – IMPORT DATI SELEZIONATI
   *  Ora con pulsante "Indietro" per tornare al popup intermedio
   * ========================================================== */
function createImportPopup(data, originalParts) {
  // Rimuove popup esistente
  let existing = document.getElementById('mouserModal');
  if (existing) existing.remove();

  // ---------- OVERLAY ----------
  const overlay = document.createElement('div');
  overlay.id = 'mouserModal';
  overlay.style.cssText = `
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex; justify-content: center; align-items: center;
    z-index: 1000;
  `;

  // ---------- CONTENITORE POPUP ----------
  const popup = document.createElement('div');
  popup.style.cssText = `
    background: #fff; border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    padding: 20px; width: 800px;
    max-height: 80vh; overflow-x: auto;
    position: relative; font-family: Arial, sans-serif;
  `;

  // ---------- PULSANTE CHIUSURA ----------
  const btnClose = document.createElement('button');
  btnClose.textContent = '×';
  btnClose.style.cssText = `
    position: absolute; top: 10px; right: 15px;
    background: transparent; border: none;
    font-size: 22px; cursor: pointer;
  `;
  btnClose.addEventListener('click', () => overlay.remove());
  popup.appendChild(btnClose);

  // ---------- TITOLO ----------
  const title = document.createElement('h4');
  title.textContent = 'Importa dati da Mouser';
  title.style.textAlign = 'center';
  popup.appendChild(title);

  // ---------- NOTA INFORMATIVA ----------
  const guide = document.createElement('p');
  guide.textContent = 'Per immagine e datasheet, scarica i file e poi usa "Scegli file" in Aggiungi componente.';
  guide.style.fontSize = '12px';
  guide.style.color = '#555';
  popup.appendChild(guide);

  // ---------- TABELLA ----------
  const table = document.createElement('table');
  table.style.width = '100%';
  table.style.borderCollapse = 'collapse';

  table.innerHTML = `
    <thead>
      <tr>
        <th style="text-align:center;">Importa</th>
        <th>Campo</th>
        <th>Valore</th>
      </tr>
    </thead>
  `;

  const tbody = document.createElement('tbody');

  // ---------- RIGHE TABELLA ----------
  for (const key in data) {
    if (!data.hasOwnProperty(key)) continue;

    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid #ddd';

    const tdCheck = document.createElement('td');
    tdCheck.style.textAlign = 'center';

    if (key !== 'mouser_image' && key !== 'mouser_datasheet') {
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.checked = true;
      cb.dataset.field = key;
      tdCheck.appendChild(cb);
    }

    const tdLabel = document.createElement('td');
    tdLabel.textContent = fieldNamesMap[key] || key;

    const tdValue = document.createElement('td');
    if ((key === 'mouser_image' || key === 'mouser_datasheet') && data[key]) {
      const link = document.createElement('a');
      link.href = data[key];
      link.target = '_blank';
      link.textContent = key === 'mouser_image' ? 'Scarica immagine' : 'Scarica datasheet';
      tdValue.appendChild(link);
    } else {
      tdValue.textContent = data[key] ?? '';
    }

    tr.append(tdCheck, tdLabel, tdValue);
    tbody.appendChild(tr);
  }

  table.appendChild(tbody);
  popup.appendChild(table);

  // ---------- PULSANTI ----------

  const buttonBar = document.createElement('div');
  buttonBar.style.cssText = `
   display: flex;
   justify-content: flex-start;   /* tutto a sinistra */
   gap: 15px;                     /* spazio tra i pulsanti */
   margin-top: 15px;
`;

  // Crea il pulsante torna alla lista
const btnBack = document.createElement('button');
btnBack.type = 'button'; // importante per evitare submit in un form
btnBack.style.cssText = `
  --bs-btn-padding-y: 0.25rem;
  --bs-btn-padding-x: 0.5rem;
  --bs-btn-font-size: 0.875rem;
  --bs-btn-border-radius: 0.25rem;

  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.25rem; /* spazio tra icona e testo */
  
  color: #fff;
  background-color: #6c757d;
  border: 1px solid #6c757d;
  padding: var(--bs-btn-padding-y) var(--bs-btn-padding-x);
  font-size: var(--bs-btn-font-size);
  border-radius: var(--bs-btn-border-radius);
  cursor: pointer;
  line-height: 1.5;
  text-decoration: none;
  user-select: none;
  transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out,
              border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
`;

// Hover / focus
btnBack.onmouseover = () => btnBack.style.backgroundColor = '#5c636a';
btnBack.onmouseout = () => btnBack.style.backgroundColor = '#6c757d';
btnBack.onfocus = () => btnBack.style.boxShadow = '0 0 0 0.25rem rgba(130,138,145,.5)';
btnBack.onblur = () => btnBack.style.boxShadow = 'none';
const icon = document.createElement('i');
icon.className = 'fa-solid fa-arrow-left';
btnBack.appendChild(icon);
const spanText = document.createElement('span');
spanText.textContent = 'Torna alla lista';
btnBack.appendChild(spanText);
document.body.appendChild(btnBack);


// Crea il pulsante importa
  const btnConfirm = document.createElement('button');
  btnConfirm.textContent = 'Importa selezionati';
  btnConfirm.style.cssText = `
   background: #0d6efd;
   color: #fff;
   border: none;
   border-radius: 5px;
   padding: 3px 15px;
   cursor: pointer;
`;

buttonBar.appendChild(btnBack);
buttonBar.appendChild(btnConfirm);
popup.appendChild(buttonBar);

  

  // ---------- EVENTI PULSANTI ----------
  btnConfirm.addEventListener('click', () => {
    tbody.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      if (!cb.checked) return;

      const field = cb.dataset.field;
      let value = data[field];

      if (field === 'price' && value) {
    value = parseFloat(
        value.toString().replace('€', '').replace(',', '.').trim()
    ) || 0;

    // Forza 2 cifre decimali
    value = parseFloat(value.toFixed(2));
    }


      const inputName = fieldToInputName[field];
      if (inputName) {
        const input = document.querySelector(`[name="${inputName}"]`);
        if (input) input.value = value;
      }
    });

    overlay.remove();
  });

  btnBack.addEventListener('click', () => {
    overlay.remove();
    createSelectionPopup(originalParts); // torna al popup intermedio
  });

  
  // ---------- AGGIUNTA AL DOM ----------
  overlay.appendChild(popup);
  document.body.appendChild(overlay);
}

  // ---------- POPUP INTERMEDIO (SELEZIONE COMPONENTE) ----------
function createSelectionPopup(parts) {
  // Rimuove eventuale popup esistente
  let existing = document.getElementById('mouserSelectionModal');
  if (existing) existing.remove();

  // ---------- OVERLAY ----------
  const overlay = document.createElement('div');
  overlay.id = 'mouserSelectionModal';
  overlay.style.cssText = `
    position: fixed;
    top:0; left:0;
    width:100%; height:100%;
    background: rgba(0,0,0,0.5);
    display:flex; justify-content:center; align-items:center;
    z-index:1000;
  `;

  // ---------- POPUP PRINCIPALE ----------
  const popup = document.createElement('div');
  popup.style.cssText = `
    background:#fff;
    border-radius:12px;
    width:1500px;
    height:80vh;
    display:flex;
    flex-direction:column;
    position:relative;
  `;

  
  // ---------- HEADER STICKY (TITOLO + BOTTONE AVANTI + CHIUDI) ----------
const header = document.createElement('div');
header.style.cssText = `
  position: sticky;
  top: 0;
  z-index: 20;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  border-bottom: 1px solid #ddd;
  padding: 15px 20px;
  height: 60px;
  border-radius:12px;
`;

// Titolo centrato
const title = document.createElement('h4');
title.textContent = 'Seleziona componente Mouser';
title.style.cssText = `
  margin: 0 auto;
  text-align: center;
  font-size: 18px;
`;
header.appendChild(title);

// Pulsante Avanti a sinistra
const btnNext = document.createElement('button');
btnNext.textContent = 'Avanti';
btnNext.style.cssText = `
  position: absolute;
  left: 20px;
  padding:3px 15px;
  background:#2a52f2; color:#fff;
  border:none; border-radius:5px; cursor:pointer;
  font-size:14px;
`;
header.appendChild(btnNext);

// Pulsante Chiudi a destra
const btnClose = document.createElement('button');
btnClose.textContent = '×';
btnClose.style.cssText = `
  position: absolute;
  right: 20px;
  top: 40%;
  transform: translateY(-50%);
  font-size:22px;
  background:none;
  border:none;
  cursor:pointer;
`;
btnClose.addEventListener('click', () => overlay.remove());
header.appendChild(btnClose);

popup.appendChild(header);


  // ---------- CONTAINER TABELLA SCROLLABILE ----------
  const tableWrapper = document.createElement('div');
  tableWrapper.style.cssText = `
    flex: 1;
    overflow-y: auto;
  `;

  // ---------- TABELLA ----------
  const table = document.createElement('table');
  table.style.cssText = `
    width:100%;
    border-collapse:collapse;
  `;

  // Intestazione ferma (sticky)
  const thead = document.createElement('thead');
  thead.style.cssText = `
    position: sticky;
    top: 0;
    background: #f8f8f8;
    z-index: 10;
  `;
  thead.innerHTML = `
    <tr>
      <th style="padding:8px;">Seleziona</th>
      <th style="padding:8px;">Codice</th>
      <th style="padding:8px;">Costruttore</th>
      <th style="padding:8px;">Descrizione</th>
    </tr>
  `;
  table.appendChild(thead);

  // ---------- CORPO TABELLA ----------
  const tbody = document.createElement('tbody');
  parts.forEach((p, index) => {
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid #ddd';
    tr.innerHTML = `
      <td style="text-align:center;">
        <input type="radio" name="selectedPart" value="${index}" ${index === 0 ? 'checked' : ''}>
      </td>
      <td>${p.ManufacturerPartNumber || ''}</td>
      <td>${p.Manufacturer || ''}</td>
      <td>${p.Description || ''}</td>
    `;
    tbody.appendChild(tr);
  });
  table.appendChild(tbody);

  // ---------- ASSEMBLAGGIO ----------
  tableWrapper.appendChild(table);
  popup.appendChild(tableWrapper);
  overlay.appendChild(popup);
  document.body.appendChild(overlay);

  // ---------- LOGICA BOTTONE AVANTI ----------
  btnNext.addEventListener('click', () => {
    const selectedIndex = parseInt(document.querySelector('input[name="selectedPart"]:checked').value, 10);
    const selectedPart = parts[selectedIndex];

    // Estrazione prezzo quantità 1
    let price = '';
    if (selectedPart.PriceBreaks && selectedPart.PriceBreaks.length) {
      const pb = selectedPart.PriceBreaks.find(p => p.Quantity === 1);
      if (pb) price = pb.Price;
    }

    // Mappatura finale
    const mapped = {
      partNumber: selectedPart.ManufacturerPartNumber ?? '',
      manufacturer: selectedPart.Manufacturer ?? '',
      description: selectedPart.Description ?? '',
      fornitore: "Mouser Electronics",
      mouser_number: selectedPart.MouserPartNumber ??'',
      mouser_product_url: selectedPart.ProductDetailUrl ??'',
      price: price,
      datasheet: selectedPart.DataSheetUrl ?? '',
      mouser_image: selectedPart.ImagePath ?? '',
      mouser_datasheet: selectedPart.DataSheetUrl ?? ''
    };

    createImportPopup(mapped, parts);
    overlay.remove();
  });
}




  /* ==========================================================
   *  EVENTO – BOTTONE "IMPORTA DA MOUSER"
   * ========================================================== */
  btnImport.addEventListener('click', () => {
  const codice = codiceInput.value.trim();
  if (!codice) return alert('Inserisci un codice prodotto!');

  // Mostra overlay di attesa
  showLoadingOverlay();

  const fd = new FormData();
  fd.append('codice_prodotto', codice);

  fetch('mouser_import.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      hideLoadingOverlay(); // togli overlay appena arriva la risposta

      if (d.success && d.dataParts?.length) {
        createSelectionPopup(d.dataParts); // apre popup selezione
      } else {
        alert(d.error || 'Nessun componente trovato');
      }
    })
    .catch(() => {
      hideLoadingOverlay();
      alert('Errore fetch');
    });
});
});
