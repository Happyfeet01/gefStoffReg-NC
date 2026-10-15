<?php
declare(strict_types=1);
?>
<div id="gsk-app">
  <div class="inventory-shell">
    <header class="topbar">
      <div class="brand"><span class="app-symbol" aria-hidden="true">◇</span><div><strong>Gefahrstoffkataster</strong><span>Produkte, Sicherheitsdatenblätter und Bestand</span></div></div>
      <div class="toolbar"><a id="export-xlsx" class="secondary">Excel exportieren</a><a id="export-csv" class="text-link">CSV</a></div>
    </header>
    <main>
      <section class="hero">
        <div><p class="eyebrow">INVENTAR</p><h1>Dein Bestand im Überblick</h1><p>Produkte erfassen, Sicherheitsdatenblätter prüfen und Mengen je Lagerort verwalten.</p></div>
        <button id="new-product" type="button" class="primary">+ Produkt erfassen</button>
      </section>
      <div id="load-error" role="alert" hidden><p id="load-error-text"></p><button id="retry-load" type="button">Erneut laden</button></div>
      <section class="metrics" aria-label="Bestandsübersicht">
        <div><span>Produkte</span><strong id="count-products">–</strong></div>
        <div><span>Gefahrstoffe</span><strong id="count-hazards">–</strong></div>
        <div><span>Ohne SDB</span><strong id="count-missing">–</strong></div>
        <div><span>Lagerorte</span><strong id="count-locations">–</strong></div>
      </section>
      <section class="workspace" aria-labelledby="inventory-title">
        <div class="section-head"><div><h2 id="inventory-title">Produkte &amp; Bestand</h2><p class="hint">Suche nach Produkt, Hersteller, Artikelnummer oder EAN.</p></div><button id="add-location" type="button" class="secondary">Lagerort hinzufügen</button></div>
        <div class="filters">
          <label class="search">Produkt suchen<input id="search" type="search" placeholder="Name, Hersteller, Artikelnummer …"></label>
          <label>Lagerort<select id="location-filter"><option value="">Alle Lagerorte</option></select></label>
          <label>Ansicht<select id="hazard-filter"><option value="">Alle Produkte</option><option value="hazard">Gefahrstoffe</option><option value="missing">Gefahrstoffe ohne SDB</option></select></label>
        </div>
        <div id="product-list" class="product-list"></div><p id="empty" class="empty">Bestand wird geladen …</p>
      </section>
      <footer class="below">Ein gemeinsamer Bestand für dein Team. Einstufungen und SDB anhand der konkreten Produktvariante prüfen.</footer>
    </main>
  </div>

  <dialog id="product-dialog" aria-labelledby="dialog-title">
    <form id="product-form">
      <header class="dialog-head">
        <div><button type="button" class="back-link" data-close="product-dialog">← Zur Übersicht</button><h1 id="dialog-title" tabindex="-1">Produkt bearbeiten</h1><p class="hint">Stammdaten, Gefahrstoffangaben und Dokumente an einem Ort.</p></div>
        <button type="button" class="close" data-close="product-dialog" aria-label="Produktbearbeitung schließen">×</button>
      </header>
      <div class="editor-layout">
        <nav class="editor-nav" aria-label="Produktbereiche">
          <a href="#product-basics">01 <span>Stammdaten</span></a>
          <a href="#product-sources">02 <span>Recherche &amp; Quellen</span></a>
          <a href="#product-safety">03 <span>Gefahrstoffangaben</span></a>
          <a href="#product-documents">04 <span>SDB &amp; Dateien</span></a>
          <p class="hint">Änderungen werden erst mit „Produkt speichern“ übernommen.</p>
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
              <label>Kategorie<input name="category" maxlength="80" placeholder="z. B. Reinigung"></label>
              <label>Einsatzbereich<input name="use_area" maxlength="100" placeholder="z. B. Schwimmbecken"></label>
              <label>Gebindegröße<input name="pack_size" type="number" min="0.001" max="1000000" step="any" required></label>
              <label>Einheit<select name="unit" required><option value="l">Liter (l)</option><option value="kg">Kilogramm (kg)</option><option value="ml">Milliliter (ml)</option><option value="g">Gramm (g)</option><option value="Stück">Stück</option></select></label>
            </div>
            <fieldset id="initial-stock"><legend>Anfangsbestand</legend><div class="grid"><label>Lagerort<select id="initial-location" required></select></label><label>Gebindeanzahl<input id="initial-packs" type="number" min="0" step="0.001" value="0" required></label></div></fieldset>
          </section>

          <section id="product-sources" class="form-section">
            <div class="section-heading"><span class="step">02</span><div><h2>Recherche &amp; Quellen</h2><p>Hersteller finden und Stammdaten mit dem Original abgleichen.</p></div></div>
            <div class="lookup-tools"><button id="research-button" type="button" class="secondary">Produkt recherchieren</button><button id="manufacturer-search" type="button" class="secondary">Hersteller im Web suchen ↗</button></div>
            <div class="grid">
              <label class="full">Link zur Herstellerseite<input name="source_url" type="url" maxlength="600" placeholder="https://…"></label>
              <div class="full"><button id="source-preview" type="button" class="secondary">Herstellerseite einlesen</button><p class="hint">Stammdaten-Vorschläge von Witty und Flamingo.</p></div>
              <div id="source-result" class="lookup-result full" role="status" hidden></div>
              <label class="full">Produktmerkblatt-Link<input name="pmb_url" type="url" maxlength="600" placeholder="https://…"></label>
            </div>
          </section>

          <section id="product-safety" class="form-section">
            <div class="section-heading"><span class="step">03</span><div><h2>Gefahrstoffangaben</h2><p>Vorschläge aus dem SDB einzeln prüfen und übernehmen.</p></div></div>
            <div class="grid">
              <label class="checkbox full"><input name="hazardous" type="checkbox">Als Gefahrstoff im Kataster führen</label>
              <label class="full">Einstufung / gefährliche Eigenschaften<textarea name="classification" rows="3" maxlength="600" placeholder="Gefahrenklasse und Kategorie laut SDB"></textarea></label>
              <label>Signalwort<select name="signal"><option value="">Keine Angabe</option><option value="Gefahr">Gefahr</option><option value="Achtung">Achtung</option></select></label>
              <label>GHS-Codes<input name="ghs" maxlength="120" placeholder="z. B. GHS05, GHS07"></label>
              <label>UFI<input name="ufi" maxlength="80"></label>
              <label>Fachlich geprüft am<input name="checked_at" type="date"></label>
              <label class="full">H- / EUH-Sätze<textarea name="h_statements" rows="4" maxlength="1500" placeholder="Nummer und Wortlaut laut SDB"></textarea></label>
            </div>
          </section>

          <section id="product-documents" class="form-section">
            <div class="section-heading"><span class="step">04</span><div><h2>Sicherheitsdatenblatt &amp; Dateien</h2><p>Herstellerlink zum Aktualisieren behalten, geprüfte PDF-Kopie lokal ablegen.</p></div></div>
            <div class="grid">
              <label class="full">SDB-Link beim Hersteller<input name="sds_url" type="url" maxlength="600" placeholder="https://…/sicherheitsdatenblatt.pdf"><small>Der Link bleibt zusätzlich zur lokalen PDF-Kopie gespeichert.</small></label>
              <div class="full"><button id="sds-research-button" type="button" class="secondary">SDB-Link recherchieren</button></div>
              <div id="sds-research-result" class="lookup-result full" role="status" hidden></div>
              <label class="full">SDB als PDF auswählen<input id="sds-file" type="file" accept="application/pdf,.pdf"><small>Optional, wenn ein direkter SDB-Link hinterlegt ist · maximal 15 MB.</small></label>
              <div id="sds-analysis" class="full sds-analysis"></div>
              <label>Datum des SDB<input name="sds_date" type="date"></label>
              <div id="current-files" class="file-links full"></div>
              <label>Bilder aus der Galerie<input id="photo-gallery" type="file" accept="image/*" multiple><small>Mehrere Bilder · jeweils maximal 8 MB.</small></label>
              <label>Direkt fotografieren<input id="photo-camera" type="file" accept="image/*" capture="environment"><small>Kamera öffnen · maximal 8 MB.</small></label>
              <label class="full">Hinweise / Lagerbedingungen<textarea name="storage_note" rows="3" maxlength="800"></textarea></label>
            </div>
          </section>
        </div>
      </div>
      <footer class="dialog-actions"><span class="hint">Erst prüfen, dann speichern.</span><button type="button" class="secondary" data-close="product-dialog">Abbrechen</button><button id="save-product" type="submit" class="primary">Produkt speichern</button></footer>
    </form>
  </dialog>

  <dialog id="stock-dialog" aria-labelledby="stock-title">
    <form id="stock-form">
      <header class="dialog-head"><div><p class="eyebrow">BESTANDSBUCHUNG</p><h2 id="stock-title">Bestand ändern</h2></div><button class="close" type="button" data-close="stock-dialog" aria-label="Schließen">×</button></header>
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
    <header class="dialog-head"><h2 id="scan-title">Barcode scannen</h2><button class="close" type="button" data-close="scan-dialog" aria-label="Schließen">×</button></header>
    <video id="scan-video" autoplay muted playsinline></video><p id="scan-status" role="status">Kamera wird geöffnet …</p>
    <div id="scan-controls"><button id="scan-focus" type="button" class="secondary" hidden>Fokus neu</button><button id="scan-torch" type="button" class="secondary" hidden>Blitz ein</button></div>
    <footer class="dialog-actions scan-actions"><button id="scan-photo-button" type="button" class="secondary">Mit Kamera fotografieren</button><button id="scan-manual" type="button" class="secondary">Nummer eingeben</button></footer>
    <input id="scan-photo-input" type="file" accept="image/*" capture="environment" hidden>
  </dialog>
  <div id="toast" role="status" aria-live="polite"></div>
</div>
