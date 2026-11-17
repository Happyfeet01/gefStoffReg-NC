/**
 * Katalogverwaltung für Gefahrstoffkataster
 * Ermöglicht das Verwalten mehrerer Schwimmbäder/Kataloge
 */

// ==================== STATE & UTILITIES ====================

const catalogState = {
  catalogs: [],
  currentCatalogId: 'legacy',
  loading: false,
  editCatalogId: null,
  saving: false,
  deleting: false
};
let catalogUIInitialized = false;

const catalog$ = id => document.getElementById(id);
const catalogUrl = path => OC.generateUrl('/apps/gefahrstoffkataster' + path);

/**
 * Zeigt eine Benachrichtigung an
 */
function catalogNotice(message, error = false) {
  if (typeof notice === 'function') {notice(message, error); return;}
  const el = catalog$('toast');
  if (el) {
    el.textContent = message;
    el.className = (error ? 'error ' : '') + 'show';
    clearTimeout(window.toastTimer);
    window.toastTimer = setTimeout(() => el.className = '', 4800);
  } else {
    console.log(error ? 'ERROR: ' + message : message);
  }
}

/**
 * Einfache GET-Anfrage
 */
async function catalogGet(path) {
  const response = await fetch(catalogUrl(path), {
    cache: 'no-store',
    headers: { requesttoken: OC.requestToken }
  });
  if (!response.ok) {
    let error = 'Unbekannter Fehler';
    try {
      const data = await response.json();
      error = data.error || `HTTP ${response.status}`;
    } catch {
      error = `HTTP ${response.status}`;
    }
    throw new Error(error);
  }
  return await response.json();
}

/**
 * POST/PUT/DELETE-Anfrage
 */
async function catalogRequest(path, body = {}, method = 'POST') {
  const data = new FormData();
  for (const [key, value] of Object.entries(body)) {
    data.set(key, String(value));
  }
  
  const response = await fetch(catalogUrl(path), {
    method,
    headers: { requesttoken: OC.requestToken },
    body: data
  });
  
  let result;
  try {
    result = await response.json();
  } catch {
    throw new Error(`Serverantwort konnte nicht gelesen werden (HTTP ${response.status}).`);
  }
  
  if (!response.ok) {
    throw new Error(result.error || `HTTP ${response.status}`);
  }
  
  return result;
}

// ==================== KATALOG FUNKTIONEN ====================

/**
 * Lädt alle Kataloge
 */
async function loadCatalogs() {
  catalogState.loading = true;
  try {
    const data = await catalogGet('/api/catalogs');
    catalogState.catalogs = (data.catalogs || []).map(catalog => ({
      ...catalog,
      id: catalog.legacy || catalog.id === 'legacy' ? 'legacy' : Number(catalog.id),
      is_default: [true, 1, '1'].includes(catalog.is_default)
    }));
    
    // Standard-Katalog setzen
    let saved;
    try {saved = sessionStorage.getItem('gsk-current-catalog');} catch {}
    if (saved && catalogState.catalogs.some(c => String(c.id) === saved)) catalogState.currentCatalogId = saved === 'legacy' ? 'legacy' : Number(saved);
    if (!catalogState.catalogs.some(c => c.id === catalogState.currentCatalogId)) {
      catalogState.currentCatalogId = catalogState.catalogs.find(c => c.id === 'legacy')?.id ?? catalogState.catalogs[0]?.id ?? 'legacy';
    }
    
    updateCatalogSelect();
    catalogState.loading = false;
    return catalogState.catalogs;
  } catch (err) {
    catalogState.loading = false;
    catalogNotice(err.message, true);
    throw err;
  }
}

/**
 * Erstellt einen neuen Katalog
 */
async function createCatalog(name, description = '') {
  if (!name || name.trim() === '') {
    throw new Error('Bitte einen Katalognamen angeben.');
  }
  
  const body = { name: name.trim(), description: description.trim() };
  const result = await catalogRequest('/api/catalogs', body, 'POST');
  
  // Katalog zur Liste hinzufügen
  catalogState.catalogs.push(result.catalog);
  
  // Als aktuellen Katalog setzen
  switchCatalog(result.catalog.id);
  updateCatalogSelect();
  catalogNotice('Katalog erstellt: ' + name);
  
  return result.catalog;
}

/**
 * Aktualisiert einen bestehenden Katalog
 */
async function updateCatalog(id, name, description) {
  if (!name || name.trim() === '') {
    throw new Error('Bitte einen Katalognamen angeben.');
  }
  
  const body = { name: name.trim(), description: description?.trim() || '' };
  await catalogRequest('/api/catalogs/' + id, body, 'POST');
  
  // Katalog in der Liste aktualisieren
  const index = catalogState.catalogs.findIndex(c => c.id === id);
  if (index !== -1) {
    catalogState.catalogs[index].name = name.trim();
    catalogState.catalogs[index].description = description?.trim() || '';
  }
  
  updateCatalogSelect();
  catalogNotice('Katalog aktualisiert.');
}

/**
 * Löscht einen Katalog
 */
async function deleteCatalog(id) {
  await catalogRequest('/api/catalogs/' + id, {}, 'DELETE');
  
  // Katalog aus der Liste entfernen
  catalogState.catalogs = catalogState.catalogs.filter(c => c.id !== id);
  
  // Wenn der gelöschte Katalog der aktuelle war, auf Default setzen
  if (catalogState.currentCatalogId === id) {
    const defaultCatalog = catalogState.catalogs.find(c => c.is_default);
    catalogState.currentCatalogId = defaultCatalog ? defaultCatalog.id : (catalogState.catalogs[0]?.id || null);
  }
  
  updateCatalogSelect();
  catalogNotice('Katalog gelöscht.');
}

/**
 * Wechselt den aktuellen Katalog
 */
function switchCatalog(catalogId) {
  const id = catalogId === 'legacy' ? 'legacy' : Number(catalogId);
  const catalog = catalogState.catalogs.find(c => c.id === id);
  if (!catalog) return false;
  if (window.dispatchEvent && !window.dispatchEvent(new CustomEvent('gsk-before-catalog-change', {cancelable: true}))) {
    updateCatalogSelect(); return false;
  }
  catalogState.currentCatalogId = id;
  try {sessionStorage.setItem('gsk-current-catalog', String(id));} catch {}
  updateCatalogSelect();
  renderCatalogList();
  if (catalog$('catalog-list-dialog')?.open) catalog$('catalog-list-dialog').close();
  window.dispatchEvent?.(new CustomEvent('gsk-catalog-change', {detail: {catalogId: id}}));
  catalogNotice('Katalog gewählt: ' + catalog.name);
  return true;
}

/**
 * Aktualisiert das Katalog-Auswahl-Dropdown
 */
function updateCatalogSelect() {
  const select = catalog$('catalog-select');
  if (!select) return;
  
  select.replaceChildren();
  
  if (catalogState.catalogs.length === 0) {
    const option = document.createElement('option');
    option.value = '';
    option.textContent = 'Keine Kataloge geladen';
    select.appendChild(option);
  }

  // Option für jeden Katalog hinzufügen
  catalogState.catalogs.forEach(catalog => {
    const option = document.createElement('option');
    option.value = catalog.id;
    option.textContent = catalog.name + (catalog.description ? ' - ' + catalog.description : '');
    if (catalog.id === catalogState.currentCatalogId) {
      option.selected = true;
    }
    select.appendChild(option);
  });
  
}

// ==================== UI FUNKTIONEN ====================

/**
 * Öffnet den Katalog-Liste-Dialog
 */
async function openCatalogList() {
  try {
    await loadCatalogs();
    renderCatalogList();
    catalog$('catalog-list-dialog').showModal();
  } catch (err) {
    catalogNotice(err.message, true);
  }
}

/**
 * Rendert die Katalog-Liste
 */
function renderCatalogList() {
  const list = catalog$('catalog-list');
  if (!list) return;
  
  list.replaceChildren();
  
  if (catalogState.catalogs.length === 0) {
    list.appendChild(document.createElement('p')).textContent = 'Keine Kataloge vorhanden.';
    return;
  }
  
  // Tabellenheader
  const header = document.createElement('div');
  header.className = 'catalog-list-header';
  header.appendChild(document.createElement('span')).textContent = 'Name';
  header.appendChild(document.createElement('span')).textContent = 'Beschreibung';
  header.appendChild(document.createElement('span')).textContent = 'Standard';
  header.appendChild(document.createElement('span')).textContent = 'Aktionen';
  list.appendChild(header);
  
  // Jeden Katalog rendern
  catalogState.catalogs.forEach(catalog => {
    const row = document.createElement('div');
    row.className = 'catalog-list-row';
    row.dataset.catalogId = catalog.id;
    
    // Name
    const nameCell = document.createElement('span');
    nameCell.textContent = catalog.name;
    if (catalog.id === catalogState.currentCatalogId) {
      nameCell.classList.add('current');
    }
    row.appendChild(nameCell);
    
    // Beschreibung
    const descCell = document.createElement('span');
    descCell.textContent = catalog.description || '-';
    row.appendChild(descCell);
    
    // Standard
    const defaultCell = document.createElement('span');
    defaultCell.textContent = catalog.is_default ? 'Ja' : 'Nein';
    row.appendChild(defaultCell);
    
    // Aktionen
    const actionsCell = document.createElement('span');
    actionsCell.className = 'catalog-actions';
    
    // Bearbeiten-Button
    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'secondary';
    editBtn.textContent = 'Bearbeiten';
    editBtn.disabled = catalog.id === 'legacy' || catalog.permissions?.manage === false;
    editBtn.addEventListener('click', () => openCatalogEdit(catalog.id));
    actionsCell.appendChild(editBtn);
    
    // Löschen-Button (nur für Nicht-Standard-Kataloge)
    if (!catalog.is_default && catalog.id !== 'legacy') {
      const deleteBtn = document.createElement('button');
      deleteBtn.type = 'button';
      deleteBtn.className = 'secondary danger';
      deleteBtn.textContent = 'Löschen';
      deleteBtn.addEventListener('click', () => openCatalogDelete(catalog.id, catalog.name));
      actionsCell.appendChild(deleteBtn);
    }
    
    // Als aktuell auswählen
    const selectBtn = document.createElement('button');
    selectBtn.type = 'button';
    selectBtn.className = 'secondary';
    selectBtn.textContent = catalog.id === catalogState.currentCatalogId ? 'Aktiv' : 'Auswählen';
    selectBtn.disabled = catalog.id === catalogState.currentCatalogId;
    selectBtn.addEventListener('click', () => switchCatalog(catalog.id));
    actionsCell.appendChild(selectBtn);
    
    row.appendChild(actionsCell);
    list.appendChild(row);
  });
}

/**
 * Öffnet den Katalog-Bearbeitungsdialog
 */
function openCatalogEdit(catalogId = null) {
  catalogState.editCatalogId = catalogId;
  catalog$('catalog-edit-error').hidden = true;
  
  const dialog = catalog$('catalog-edit-dialog');
  const title = catalog$('catalog-edit-title');
  const hint = catalog$('catalog-edit-hint');
  const nameInput = catalog$('catalog-name');
  const descInput = catalog$('catalog-description');
  
  if (catalogId) {
    // Bearbeiten
    const catalog = catalogState.catalogs.find(c => c.id === catalogId);
    if (catalog) {
      title.textContent = 'Katalog bearbeiten';
      hint.textContent = 'Bearbeite die Daten des ausgewählten Katalogs.';
      nameInput.value = catalog.name;
      descInput.value = catalog.description || '';
    }
  } else {
    // Neu erstellen
    title.textContent = 'Neuer Katalog';
    hint.textContent = 'Erstellen Sie einen neuen Katalog für ein Schwimmbad oder eine Einrichtung.';
    nameInput.value = '';
    descInput.value = '';
  }
  
  dialog.showModal();
  nameInput.focus();
}

/**
 * Speichert den Katalog (neu oder bearbeiten)
 */
async function saveCatalog() {
  if (catalogState.saving) return;
  const error = catalog$('catalog-edit-error');
  error.hidden = true;
  const nameInput = catalog$('catalog-name');
  const descInput = catalog$('catalog-description');
  const name = nameInput.value.trim();
  const description = descInput.value.trim();
  
  if (!name) {
    catalogNotice('Bitte einen Katalognamen angeben.', true);
    return;
  }
  
  catalogState.saving = true;
  catalog$('save-catalog').disabled = true;
  try {
    if (catalogState.editCatalogId) {
      // Bearbeiten
      await updateCatalog(catalogState.editCatalogId, name, description);
    } else {
      // Neu erstellen
      await createCatalog(name, description);
    }
    
    // Dialog schließen
    catalog$('catalog-edit-dialog').close();
    
    // Liste aktualisieren
    await loadCatalogs();
    renderCatalogList();
    
  } catch (err) {
    error.textContent = err.message;
    error.hidden = false;
    catalogNotice(err.message, true);
  } finally {
    catalogState.saving = false;
    catalog$('save-catalog').disabled = false;
  }
}

/**
 * Öffnet den Lösch-Bestätigungsdialog
 */
function openCatalogDelete(catalogId, catalogName) {
  catalogState.editCatalogId = catalogId;
  catalog$('catalog-delete-error').hidden = true;
  catalog$('delete-catalog-name').textContent = catalogName;
  catalog$('catalog-delete-dialog').showModal();
}

/**
 * Bestätigt das Löschen eines Katalogs
 */
async function confirmDeleteCatalog() {
  if (!catalogState.editCatalogId || catalogState.deleting) return;
  const error = catalog$('catalog-delete-error');
  error.hidden = true;
  catalogState.deleting = true;
  catalog$('confirm-delete-catalog').disabled = true;
  
  try {
    await deleteCatalog(catalogState.editCatalogId);
    catalog$('catalog-delete-dialog').close();
    
    // Liste aktualisieren
    await loadCatalogs();
    renderCatalogList();
    
  } catch (err) {
    error.textContent = err.message;
    error.hidden = false;
    catalogNotice(err.message, true);
  } finally {
    catalogState.deleting = false;
    catalog$('confirm-delete-catalog').disabled = false;
  }
}

// ==================== EVENT HANDLER ====================

// Initialisierung
function initCatalogUI() {
  if (catalogUIInitialized) return;
  catalogUIInitialized = true;
  // Katalog-Auswahl initialisieren
  updateCatalogSelect();
  catalog$('catalog-select')?.addEventListener('change', e => switchCatalog(e.target.value));
  catalog$('add-catalog')?.addEventListener('click', () => openCatalogEdit());
  
  // Button: Kataloge verwalten
  const manageBtn = catalog$('manage-catalogs');
  if (manageBtn) {
    manageBtn.addEventListener('click', openCatalogList);
  }
  
  // Dialog: Katalog-Liste
  const listDialog = catalog$('catalog-list-dialog');
  if (listDialog) {
    listDialog.querySelector('.close')?.addEventListener('click', () => listDialog.close());
    listDialog.addEventListener('close', () => {
      catalogState.editCatalogId = null;
    });
  }
  
  // Dialog: Katalog bearbeiten
  const editDialog = catalog$('catalog-edit-dialog');
  if (editDialog) {
    editDialog.querySelector('.close')?.addEventListener('click', () => editDialog.close());
    editDialog.addEventListener('close', () => {
      catalogState.editCatalogId = null;
    });
    
    // Formular-Submit
    const editForm = catalog$('catalog-edit-form');
    if (editForm) {
      editForm.addEventListener('submit', (e) => {
        e.preventDefault();
        saveCatalog();
      });
    }
  }
  
  // Dialog: Katalog löschen
  const deleteDialog = catalog$('catalog-delete-dialog');
  if (deleteDialog) {
    deleteDialog.querySelector('.close')?.addEventListener('click', () => deleteDialog.close());
    deleteDialog.addEventListener('close', () => {
      catalogState.editCatalogId = null;
    });
    
    const deleteForm = catalog$('catalog-delete-form');
    if (deleteForm) {
      deleteForm.addEventListener('submit', (e) => {
        e.preventDefault();
        confirmDeleteCatalog();
      });
    }
  }
  
  // Dialoge mit data-close Attribut
  document.querySelectorAll('[data-close]')?.forEach(btn => {
    btn.addEventListener('click', () => {
      const dialogId = btn.dataset.close;
      const dialog = catalog$(dialogId);
      if (dialog) dialog.close();
    });
  });
}

// Initialisierung beim Laden der Seite
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCatalogUI);
} else {
  initCatalogUI();
}

// Export für andere Module
window.CatalogManager = {
  loadCatalogs,
  createCatalog,
  updateCatalog,
  deleteCatalog,
  switchCatalog,
  getCurrentCatalogId: () => catalogState.currentCatalogId,
  getCurrentCatalog: () => catalogState.catalogs.find(c => c.id === catalogState.currentCatalogId),
  getCatalogs: () => catalogState.catalogs
};


