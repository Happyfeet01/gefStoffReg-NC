<?php
declare(strict_types=1);
?>
<div id="gsk-app">
  <div class="inventory-shell">
    <header class="topbar">
      <div class="brand"><span class="app-symbol" aria-hidden="true">\u25c7</span><div><strong>Gefahrstoffkataster</strong><span>Produkte, Sicherheitsdatenbl\u00e4tter und Bestand</span></div></div>
      <div class="toolbar">
        <div class="catalog-selector">
          <label for="catalog-select">Katalog:</label>
          <select id="catalog-select"><option value="">Lade Kataloge...</option></select>
        </div>
        <button id="manage-catalogs" type="button" class="secondary">Kataloge verwalten</button>
        <button id="manage-shares" type="button" class="secondary" hidden>Freigaben</button>
        <a id="export-xlsx" class="secondary">Excel exportieren</a>
        <a id="export-csv" class="text-link">CSV</a>
      </div>
    </header>
    <nav class="mode-switch" aria-label="Bereich ausw\u00e4hlen">
      <button id="view-lager" type="button" class="is-active" aria-pressed="true">Lager</button>
      <button id="view-gefahr" type="button" aria-pressed="false">Gefahrstoffkataster</button>
    </nav>
    <main>
      <section class="hero">
        <div><p class="eyebrow">INVENTAR</p><h1 id="view-title">Dein Bestand im \u00dcberblick</h1><p id="view-description">Produkte erfassen, Sicherheitsdatenbl\u00e4tter pr\u00fcfen und Mengen je Lagerort verwalten.</p></div>
        <button id="new-product" type="button" class="primary">+ Produkt erfassen</button>
      </section>
      <div id="load-error" role="alert" hidden><p id="load-error-text"></p><button id="retry-load" type="button">Erneut laden</button></div>
      <section class="metrics" aria-label="Bestands\u00fcbersicht">
        <div><span>Produkte</span><strong id="count-products">\u2013</strong></div>
        <div><span>Gefahrstoffe</span><strong id="count-hazards">\u2013</strong></div>
        <div><span>Ohne SDB</span><strong id="count-missing">\u2013</strong></div>
        <div><span>Lagerorte</span><strong id="count-locations">\u2013</strong></div>
      </section>
      <section class="workspace" aria-labelledby="inventory-title">
        <div class="section-head"><div><h2 id="inventory-title">Produkte &amp; Bestand</h2><p id="inventory-hint" class="hint">Suche nach Produkt, Hersteller, Artikelnummer oder EAN.</p></div><button id="add-location" type="button" class="secondary">Lagerort hinzuf\u00fcgen</button></div>
        <div class="filters">
          <label class="search">Produkt suchen<input id="search" type="search" placeholder="Name, Hersteller, Artikelnummer \u2026"></label>
          <label>Lagerort<select id="location-filter"><option value="">Alle Lagerorte</option></select></label>
          <label id="material-filter-wrap">Materialart<select id="material-filter"><option value="">Alle Materialarten</option><option value="chemical">Chemikalien</option><option value="consumable">Verbrauchsmaterial</option><option value="other">Sonstiges</option><option value="unclassified">Noch nicht zugeordnet</option></select></label>
          <label>Kategorie<select id="category-filter"><option value="">Alle Kategorien</option></select></label>
          <label>Ansicht<select id="hazard-filter"><option value="">Alle Produkte</option><option value="hazard">Gefahrstoffe</option><option value="missing">Gefahrstoffe ohne SDB</option></select></label>
        </div>
        <div id="product-list" class="product-list"></div><p id="empty" class="empty">Bestand wird geladen \u2026</p>
      </section>
      <footer class="below">Ein gemeinsamer Bestand f\u00fcr dein Team. Einstufungen und SDB anhand der konkreten Produktvariante pr\u00fcfen.</footer>
    </main>
  </div>

  <!-- ==================== KATALOG-VERWALTUNG DIALOGE ==================== -->

  <dialog id="catalog-list-dialog" aria-labelledby="catalog-list-title">
    <header class="dialog-head">
      <div><h2 id="catalog-list-title">Kataloge verwalten</h2><p class="hint">Neue Schwimmb\u00e4der/Kataloge anlegen und bestehende verwalten.</p></div>
      <button class="close" type="button" data-close="catalog-list-dialog" aria-label="Schlie\u00dfen">\u00d7</button>
    </header>
    <div class="dialog-scroll">
      <div class="catalog-actions">
        <button id="add-catalog" type="button" class="primary">+ Neuer Katalog</button>
      </div>
      <div id="catalog-list" class="catalog-list" aria-live="polite"></div>
    </div>
  </dialog>

  <dialog id="catalog-edit-dialog" aria-labelledby="catalog-edit-title">
    <form id="catalog-edit-form">
      <header class="dialog-head">
        <div><h2 id="catalog-edit-title">Katalog bearbeiten</h2><p class="hint" id="catalog-edit-hint">Neuen Katalog anlegen oder bestehenden bearbeiten.</p></div>
        <button class="close" type="button" data-close="catalog-edit-dialog" aria-label="Schlie\u00dfen">\u00d7</button>
      </header>
      <div class="dialog-scroll">
        <div class="grid">
          <label class="full">Katalognamen *<input id="catalog-name" name="name" type="text" maxlength="255" required placeholder="z. B. Freibad Landr\u00fccken"></label>
          <label class="full">Beschreibung<textarea id="catalog-description" name="description" rows="3" maxlength="1000" placeholder="z. B. Hauptkataster f\u00fcr Schwimmbad XYZ"></textarea></label>
        </div>
      </div>
      <footer class="dialog-actions">
        <button type="button" class="secondary" data-close="catalog-edit-dialog">Abbrechen</button>
        <button id="save-catalog" type="submit" class="primary">Speichern</button>
      </footer>
    </form>
  </dialog>

  <dialog id="catalog-delete-dialog" aria-labelledby="catalog-delete-title">
    <form id="catalog-delete-form">
      <header class="dialog-head">
        <div><h2 id="catalog-delete-title">Katalog l\u00f6schen</h2><p class="hint">Dieser Vorgang kann nicht r\u00fcckg\u00e4ngig gemacht werden.</p></div>
        <button class="close" type="button" data-close="catalog-delete-dialog" aria-label="Schlie\u00dfen">\u00d7</button>
      </header>
      <div class="dialog-scroll">
        <p>M\u00f6chten Sie den Katalog <strong id="delete-catalog-name"></strong> wirklich l\u00f6schen?</p>
        <p class="warning">\u26a0\ufe0f <strong>Warnung:</strong> Der Katalog kann nur gel\u00f6scht werden, wenn er leer ist (keine Produkte, Lagerorte, Best\u00e4nde).</p>
      </div>
      <footer class="dialog-actions">
        <button type="button" class="secondary" data-close="catalog-delete-dialog">Abbrechen</button>
        <button id="confirm-delete-catalog" type="submit" class="primary danger">L\u00f6schen</button>
      </footer>
    </form>
  </dialog>

  <!-- ==================== PRODUKT-DIALOG (bestehend) ==================== -->

  <dialog id="product-dialog" aria-labelledby="dialog-title">
    <form id="product-form">
      <header class="dialog-head">
        <div><button type="button" class="back-link" data-close="product-dialog">\u2190 Zur \u00dcbersicht</button><h1 id="dialog-title" tabindex="-1">Produkt bearbeiten</h1><p class="hint">Stammdaten, Gefahrstoffangaben und Dokumente an einem Ort.</p></div>
        <button type="button" class="close" data-close="product-dialog" aria-label="Produktbearbeitung schlie\u00dfen">\u00d7</button>
      </header>
      <div class="editor-layout">
        <nav class="editor-nav" aria-label="Produktbereiche">
          <a href="#product-basics">01 <span>Stammdaten</span></a>
          <a href="#product-sources">02 <span>Recherche &amp; Quellen</span></a>
          <a href="#product-safety">03 <span>Gefahrstoffangaben</span></a>
          <a href="#product-documents">04 <span>SDB &amp; Dateien</span></a>
          <p class="hint">\u00c4nderungen werden erst mit \u201eProdukt speichern\u201c \u00fcbernommen.</p>
        </nav>
        <div class="dialog-scroll">
          <section id="product-basics" class="form-section">
            <div class="section-heading"><span class="step">01</span><div><h2>Stammdaten</h2><p>Produkt und genaue Gebindevariante identifizieren.</p></div></div>
            <div class="grid">
              <label class="full">Produktname<input name="name" required maxlength="160" autocomplete="off"></label>
              <label>Hersteller<input name="manufacturer" maxlength="120"></label>
              <label>Artikelnummer<input name="article" maxlength="100"></label>
              <label class="full">EAN / GTIN<div class="input-row"><input name="ean" maxlength="50" inputmode="numeric"><button id="scan-button" type="button" class="secondary">Scannen</button><button id="lookup-button" type="button" class="secondary">Im Bestand suchen</button></div></label>
              <div class="full lookup-tools"><button id="label-button" type="button" class="secondary">Etikett fotografieren</button><input id="label-input" type="file" accept="image/*" capture="environment" hidden></div>
              <div id="lookup-result" class="lookup-result full" role="status" hidden></div>
              <label>Materialart<select name="material_kind"><option value="unclassified">Noch nicht zugeordnet</option><option value="chemical">Chemikalien</option><option value="consumable">Verbrauchsmaterial</option><option value="other">Sonstiges Material</option></select><small>Unabh\u00e4ngig von der Gefahrstoffkennzeichnung.</small></label>
              <label>Kategorie<input name="category" maxlength="80" placeholder="z. B. Reinigung"></label>
              <label>Einsatzbereich<input name="use_area" maxlength="100" placeholder="z. B. Schwimmbecken"></label>
              <label>Gebindegr\u00f6\u00dfe<input name="pack_size" type="number" min="0.001" max="1000000" step="any" required></label>
              <label>Einheit<select name="unit" required><option value="l">Liter (l)</option><option value="kg">Kilogramm (kg)</option><option value="ml">Milliliter (ml)</option><option value="g">Gramm (g)</option><option value="St\u00fcck">St\u00fcck</option></select></label>
            </div>
            <fieldset id="initial-stock"><legend>Anfangsbestand</legend><div class="grid"><label>Lagerort<select id="initial-location" required></select></label><label>Gebindeanzahl<input id="initial-packs" type="number" min="0" step="0.001" value="0" required></label></div></fieldset>
          </section>

          <section id="product-sources" class="form-section">
            <div class="section-heading"><span class="step">02</span><div><h2>Recherche &amp; Quellen</h2><p>Hersteller finden und Stammdaten mit dem Original abgleichen.</p></div></div>
            <div class="lookup-tools"><button id="research-button" type="button" class="secondary">Produkt recherchieren</button><button id="manufacturer-search" type="button" class="secondary">Hersteller im Web suchen \u2197</button></div>
            <div class="grid">
              <label class="full">Link zur Herstellerseite<input name="source_url" type="url" maxlength="600" placeholder="https://\u2026"></label>
              <div class="full"><button id="source-preview" type="button" class="secondary">Herstellerseite einlesen</button><p class="hint">Stammdaten-Vorschl\u00e4ge von Witty und Flamingo.</p></div>
              <div id="source-result" class="lookup-result full" role="status" hidden></div>
              <label class="full">Produktmerkblatt-Link<input name="pmb_url" type="url" maxlength="600" placeholder="https://\u2026"></label>
            </div>
          </section>

          <section id="product-safety" class="form-section">
            <div class="section-heading"><span class="step">03</span><div><h2>Gefahrstoffangaben</h2><p>Vorschl\u00e4ge aus dem SDB einzeln pr\u00fcfen und \u00fcbernehmen.</p></div></div>
            <div class="grid">
              <label class="checkbox full"><input name="hazardous" type="checkbox">Als Gefahrstoff im Kataster f\u00fchren</label>
              <label class="full">Einstufung / gef\u00e4hrliche Eigenschaften<textarea name="classification" rows="3" maxlength="600" placeholder="Gefahrenklasse und Kategorie laut SDB"></textarea></label>
              <label>Signalwort<select name="signal"><option value="">Keine Angabe</option><option value="Gefahr">Gefahr</option><option value="Achtung">Achtung</option></select></label>
              <label>GHS-Codes<input name="ghs" maxlength="120" placeholder="z. B. GHS05, GHS07"></label>
              <label>UFI<input name="ufi" maxlength="80"></label>
              <label>Fachlich gepr\u00fcf am<input name="checked_at" type="date"></label>
              <label class="full">H- / EUH-S\u00e4tze<textarea name="h_statements" rows="4" maxlength="1500" placeholder="Nummer und Wortlaut laut SDB"></textarea></label>
            </div>
          </section>

          <section id="product-documents" class="form-section">
            <div class="section-heading"><span class="step">04</span><div><h2>Sicherheitsdatenblatt &amp; Dateien</h2><p>Herstellerlink zum Aktualisieren behalten, gepr\u00fcfte PDF-Kopie lokal ablegen.</p></div></div>
            <div class="grid">
              <label class="full">SDB-Link beim Hersteller<input name="sds_url" type="url" maxlength="600" placeholder="https://\u2026/sicherheitsdatenblatt.pdf"><small>Der Link bleibt zus\u00e4tzlich zur lokalen PDF-Kopie gespeichert.</small></label>
              <div class="full"><button id="sds-research-button" type="button" class="secondary">SDB-Link recherchieren</button></div>
              <div id="sds-research-result" class="lookup-result full" role="status" hidden></div>
              <label class="full">SDB als PDF ausw\u00e4hlen<input id="sds-file" type="file" accept="application/pdf,.pdf"><small>Optional, wenn ein direkter SDB-Link hinterlegt ist \u00b7 maximal 15 MB.</small></label>
              <div id="sds-analysis" class="full sds-analysis"></div>
              <label>Datum des SDB<input name="sds_date" type="date"></label>
              <div id="current-files" class="file-links full"></div>
              <label>Bilder aus der Galerie<input id="photo-gallery" type="file" accept="image/*" multiple><small>Mehrere Bilder \u00b7 jeweils maximal 8 MB.</small></label>
              <label>Direkt fotografieren<input id="photo-camera" type="file" accept="image/*" capture="environment"><small>Kamera \u00f6ffnen \u00b7 maximal 8 MB.</small></label>
              <label class="full">Hinweise / Lagerbedingungen<textarea name="storage_note" rows="3" maxlength="800"></textarea></label>
            </div>
          </section>
        </div>
      </div>
      <footer class="dialog-actions"><span class="hint">Erst pr\u00fcfen, dann speichern.</span><button type="button" class="secondary" data-close="product-dialog">Abbrechen</button><button id="save-product" type="submit" class="primary">Produkt speichern</button></footer>
    </form>
  </dialog>

  <dialog id="sharing-dialog" aria-labelledby="sharing-title">
    <header class="dialog-head"><div><h2 id="sharing-title">Freigaben verwalten</h2><p class="hint">Nextcloud-Benutzer oder -Gruppen mit passenden Rechten einladen.</p></div><button class="close" type="button" data-close="sharing-dialog" aria-label="Freigaben schlie\u00dfen">\u00d7</button></header>
    <div class="dialog-scroll">
      <div class="share-tabs" role="tablist" aria-label="Freigabeart">
        <button id="share-tab-internal" role="tab" type="button" aria-selected="true" aria-controls="share-internal-panel">Nextcloud-Nutzer</button>
        <button id="share-tab-external" role="tab" type="button" aria-selected="false" aria-controls="share-external-panel" tabindex="-1">Externe Links</button>
      </div>
      <section id="share-internal-panel" role="tabpanel" aria-labelledby="share-tab-internal" class="share-panel">
      <form id="sharing-form">
        <div class="grid">
          <label>Freigeben an<select id="share-type" required><option value="user">Benutzer</option><option value="group">Gruppe</option></select></label>
          <label>Nextcloud-ID<input id="share-id" required maxlength="128" placeholder="Benutzer- oder Gruppen-ID"></label>
          <label class="full">Berechtigung<select id="share-role" required><option value="reader">Lesen</option><option value="stock_editor">Lager bearbeiten</option><option value="hazard_editor">Gefahrstoffdaten bearbeiten</option><option value="manager">Verwalten</option></select></label>
        </div>
        <p class="hint">Lagerbearbeiter d\u00fcrfen Best\u00e4nde buchen und ungef\u00e4hrliche Verbrauchsmaterialien pflegen. Gefahrstoffangaben und Freigaben sind gesondert gesch\u00fctzt.</p>
        <div class="share-form-actions"><button type="submit" class="primary">Freigabe speichern</button></div>
      </form>
      <h3 class="share-heading">Aktuelle Freigaben</h3>
      <div id="share-list" aria-live="polite"></div>
      <p class="hint">Die bisherige Nextcloud-Gruppe freibad-gefahrstoffe beh\u00e4lt vorerst ihre bisherigen Rechte. Ihre Mitgliedschaft wird weiterhin in Nextcloud verwaltet. Die Freigaben gelten derzeit f\u00fcr den gesamten App-Bestand, noch nicht pro Standort.</p>
      </section>
      <section id="share-external-panel" class="external-share-manager share-panel" role="tabpanel" aria-labelledby="share-tab-external" hidden>
        <h3 id="external-share-title">Externe Links (ohne Nextcloud-Konto)</h3>
        <p class="hint">Externe Links k\u00f6nnen nur lesen oder zus\u00e4tzlich Best\u00e4nde buchen. Vergib Schreibrechte pers\u00f6nlich, mit einem eigenen Link und verpflichtendem Passwort. Gefahrstoffdaten und Stammdaten bleiben gesch\u00fctzt.</p>
        <form id="external-share-form">
          <div class="grid">
            <label class="full">Bezeichnung<input id="external-share-label" required maxlength="100" placeholder="z. B. Hygieneinstitut \u2013 November"></label>
            <label>Freigabebereich<select id="external-share-scope"><option value="kataster">Nur Gefahrstoffkataster</option><option value="lager">Nur Lager</option><option value="both">Lager und Gefahrstoffkataster</option></select></label>
            <label>G\u00fcltigkeit<select id="external-share-days"><option value="1">1 Tag</option><option value="7" selected>7 Tage</option><option value="30">30 Tage</option></select></label>
            <label class="full">Berechtigung
              <select id="external-share-access"><option value="read">Nur lesen</option><option value="stock_editor">Best\u00e4nde bearbeiten (Zugang, Entnahme, Korrektur)</option></select>
            </label>
            <label class="full" id="external-share-recipient-row" hidden>Person mit Schreibzugriff
              <input id="external-share-recipient" type="text" maxlength="60" autocomplete="off" placeholder="Vor- und Nachname des Mitarbeiters">
            </label>
            <div class="full"><label for="external-share-password">Zus\u00e4tzliches Passwort (empfohlen)</label>
              <div class="external-password-control">
                <input id="external-share-password" type="password" minlength="12" maxlength="256" autocomplete="new-password" placeholder="Mindestens 12 Zeichen, wenn gesetzt">
                <button id="external-share-password-toggle" type="button" class="secondary external-password-toggle" aria-label="Passwort anzeigen" aria-pressed="false" aria-controls="external-share-password" title="Passwort anzeigen"><svg viewBox="0 0 24 24" class="gsk-eye-icon" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="gsk-eye-slash" d="M4 20 20 4"/></svg></button>
              </div>
            </div>
          </div>
          <div class="share-form-actions"><button id="external-share-submit" class="primary" type="submit">Externe Freigabe erstellen</button></div>
        </form>
        <div id="external-share-created" class="external-share-created" hidden>
          <p><strong>Freigabelink erstellt</strong> \u2013 nur jetzt vollst\u00e4ndig sichtbar. Link und Passwort am besten getrennt \u00fcbermitteln.</p>
          <div class="external-share-copy">
            <input id="external-share-created-url" type="text" readonly aria-label="Neu erstellter Freigabelink">
            <button id="external-share-copy" type="button" class="secondary">Link kopieren</button>
          </div>
        </div>
        <h4>Aktive und abgelaufene Links</h4>
        <div id="external-share-list" aria-live="polite"></div>
      </section>
    </div>
  </dialog>
  <dialog id="stock-dialog" aria-labelledby="stock-title">
    <form id="stock-form">
      <header class="dialog-head"><div><p class="eyebrow">BESTANDSBUCHUNG</p><h2 id="stock-title">Bestand \u00e4ndern</h2></div><button class="close" type="button" data-close="stock-dialog" aria-label="Schlie\u00dfen">\u00d7</button></header>
      <div class="dialog-scroll"><div class="grid">
        <label class="full">Lagerort<select id="stock-location" required></select></label>
        <p id="stock-context" class="full hint"></p>
        <label>Buchung<select id="stock-action"><option value="add">Zugang</option><option value="remove">Entnahme</option><option value="set">Bestand korrigieren</option></select></label>
        <label>Gebindeanzahl<input id="stock-packs" type="number" min="0" step="0.001" required></label>
        <label class="full">Notiz<input id="stock-note" maxlength="200"></label>
      </div></div>
      <footer class="dialog-actions"><button type="button" class="secondary" data-close="stock-dialog">Abbrechen</button><button id="save-stock" type="submit" class="primary">Bestand buchen</button></footer>
    </form>
  </dialog>
  <dialog id="scan-dialog" aria-labelledby="scan-title">
    <header class="dialog-head"><h2 id="scan-title">Barcode scannen</h2><button class="close" type="button" data-close="scan-dialog" aria-label="Schlie\u00dfen">\u00d7</button></header>
    <video id="scan-video" autoplay muted playsinline></video><p id="scan-status" role="status">Kamera wird ge\u00f6ffnet \u2026</p>
    <div id="scan-controls"><button id="scan-focus" type="button" class="secondary" hidden>Fokus neu</button><button id="scan-torch" type="button" class="secondary" hidden>Blitz ein</button></div>
    <footer class="dialog-actions scan-actions"><button id="scan-photo-button" type="button" class="secondary">Mit Kamera fotografieren</button><button id="scan-manual" type="button" class="secondary">Nummer eingeben</button></footer>
    <input id="scan-photo-input" type="file" accept="image/*" capture="environment" hidden>
  </dialog>
  <div id="toast" role="status" aria-live="polite"></div>
</div>

<!-- Katalogverwaltung Scripts -->
<script src="<?php print_unescaped_url(OC::$WEBROOT); ?>/apps/gefahrstoffkataster/js/catalog.js"></script>
<script>
// Initialisiere Katalog-UI beim Laden
if (typeof initCatalogUI === 'function') {
    document.addEventListener('DOMContentLoaded', initCatalogUI);
}
</script>
