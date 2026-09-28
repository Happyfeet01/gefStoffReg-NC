<div id="gsk-app">

  <header class="topbar">
    <div class="brand"><img src="<?php print_unescaped(image_path('gefahrstoffkataster', 'app.png')); ?>" alt="Wappen Flieden"><div><strong>Freibad Landrücken</strong><span>Betriebsstoffe & Gefahrstoffe</span></div></div>
    <a class="export" id="export-xlsx" href="#" download>Excel exportieren ↗</a>
  </header>
  <main>
    <div id="load-error" role="alert" hidden><span id="load-error-text"></span><button type="button" id="retry-load">Erneut laden</button></div>
    <section class="hero">
      <div><p class="eyebrow">INVENTAR · FREIBAD FLIEDEN</p><h1>Alles am richtigen Ort.</h1><p>Produkte, Lagerorte und Sicherheitsdatenblätter an einer Stelle erfassen.</p></div>
      <button class="primary" id="new-product">＋ Produkt erfassen</button>
    </section>
    <section class="metrics" aria-label="Übersicht">
      <div><span>Produkte</span><strong id="count-products">–</strong></div>
      <div><span>Gefahrstoffe</span><strong id="count-hazards">–</strong></div>
      <div><span>SDB fehlt</span><strong id="count-missing">–</strong></div>
      <div><span>Lagerorte</span><strong id="count-locations">–</strong></div>
    </section>
    <section class="workspace">
      <div class="section-head"><div><p class="eyebrow">PRODUKTÜBERSICHT</p><h2>Bestand im Blick</h2></div><button class="secondary" id="add-location">＋ Lagerort</button></div>
      <div class="filters"><label class="search"><span class="sr-only">Produkte suchen</span><input id="search" type="search" placeholder="Produkt, Hersteller oder Barcode suchen …"></label><select id="location-filter" aria-label="Nach Lagerort filtern"><option value="">Alle Lagerorte</option></select><select id="hazard-filter" aria-label="Nach Einstufung filtern"><option value="">Alle Produkte</option><option value="hazard">Nur Gefahrstoffe</option><option value="missing">Ohne SDB</option></select></div>
      <div id="product-list" class="product-list"></div>
      <p id="empty" class="empty" hidden>Hier ist noch kein Produkt erfasst. Starte mit dem ersten Gebinde.</p>
      <div class="below"><a id="export-csv" href="#" download>CSV herunterladen</a><span>Änderungen werden in Nextcloud gespeichert.</span></div>
    </section>
  </main>
  <div id="toast" role="status" aria-live="polite"></div>

  <dialog id="product-dialog">
    <form id="product-form">
      <div class="dialog-head"><div><p class="eyebrow">PRODUKTKARTE</p><h2 id="dialog-title">Produkt erfassen</h2></div><button class="close" type="button" data-close="product-dialog" aria-label="Schließen">×</button></div>
      <div class="dialog-scroll">
        <div class="form-section"><h3>1 · Produkt</h3><div class="grid"><label class="full">Produktname *<input name="name" required maxlength="160" autocomplete="off" placeholder="z. B. Chlorbleichlauge"></label><label>Hersteller<input name="manufacturer" maxlength="120"></label><label>Artikelnummer<input name="article" maxlength="100"></label><label>EAN / Barcode <span class="input-row"><input name="ean" maxlength="50" inputmode="numeric"><button type="button" class="secondary scan" id="scan-button">Scannen</button></span></label><label>UFI<input name="ufi" maxlength="80"></label><label>Kategorie<select name="category"><option>Wasseraufbereitung</option><option>Reinigung</option><option>Technik</option><option>Grünpflege</option><option>Sonstige</option></select></label><label>Einsatzbereich<input name="use_area" maxlength="100" placeholder="z. B. SB / NSB"></label></div></div>
        <div class="form-section"><h3>2 · Gebinde & Einstufung</h3><div class="grid"><label>Gebindegröße *<input name="pack_size" type="number" min="0.000001" max="1000000" step="any" required placeholder="20"></label><label>Einheit *<select name="unit" required><option value="l">l</option><option value="kg">kg</option><option value="ml">ml</option><option value="g">g</option><option value="Stück">Stück</option></select></label><label class="checkbox full"><input name="hazardous" type="checkbox"><span>Als Gefahrstoff im Verzeichnis führen</span></label><label class="full">Einstufung / gefährliche Eigenschaften<textarea name="classification" maxlength="600" rows="2" placeholder="Angaben aus dem geprüften SDB"></textarea></label><label>GHS-Piktogramme<input name="ghs" maxlength="120" placeholder="z. B. GHS05, GHS09"></label><label>Signalwort<input name="signal" maxlength="40" placeholder="Gefahr / Achtung"></label><label class="full">H-Sätze<textarea name="h_statements" maxlength="1500" rows="2"></textarea></label></div></div>
        <div class="form-section" id="initial-stock"><h3>3 · Anfangsbestand</h3><div class="grid"><label>Lagerort *<select id="initial-location" required></select></label><label>Gebindeanzahl *<input id="initial-packs" type="number" min="0" step="any" required value="0"></label></div><p class="hint">Beispiel: 3 Kanister à 20 l ergeben 60 l Gesamtbestand.</p></div>
        <div class="form-section"><h3>4 · Foto & Sicherheitsdatenblatt</h3><div class="grid"><label>Bilder aus der Galerie <input id="photo-gallery" type="file" accept="image/*" multiple><small>Mehrere Bilder auswählen · je max. 8 MB</small></label><label>Direkt fotografieren <input id="photo-camera" type="file" accept="image/*" capture="environment"><small>Kamera öffnen · max. 8 MB</small></label><label>SDB als PDF <input id="sds-file" type="file" accept="application/pdf,.pdf"><small>Passendes Dokument manuell prüfen · max. 15 MB</small></label><label>Datum des SDB<input name="sds_date" type="date"></label><label>Fachlich geprüft am<input name="checked_at" type="date"></label><label class="full">Hinweise / Lagerbedingungen<textarea name="storage_note" maxlength="800" rows="2"></textarea></label></div><p class="hint">Die App übernimmt Angaben aus Fotos oder PDFs nicht ungeprüft. Produkt, Einstufung und SDB-Version bitte abgleichen.</p><div id="current-files" class="file-links"></div></div>
      </div>
      <div class="dialog-actions"><button type="button" class="secondary" data-close="product-dialog">Abbrechen</button><button class="primary" id="save-product">Produkt speichern</button></div>
    </form>
  </dialog>

  <dialog id="stock-dialog">
    <form id="stock-form">
      <div class="dialog-head"><div><p class="eyebrow">BESTAND BUCHEN</p><h2 id="stock-title">Bestand ändern</h2></div><button class="close" type="button" data-close="stock-dialog" aria-label="Schließen">×</button></div>
      <div class="dialog-scroll"><div class="grid"><label class="full">Lagerort<select id="stock-location" required></select></label><label>Aktion<select id="stock-action"><option value="add">＋ Zugang</option><option value="remove">− Entnahme</option><option value="set">Bestand korrigieren</option></select></label><label>Gebindeanzahl<input id="stock-packs" type="number" min="0" step="any" required placeholder="z. B. 2"></label><label class="full">Notiz (optional)<input id="stock-note" maxlength="200" placeholder="z. B. Lieferung / Inventur"></label></div><p id="stock-context" class="hint"></p></div>
      <div class="dialog-actions"><button type="button" class="secondary" data-close="stock-dialog">Abbrechen</button><button class="primary" id="save-stock">Buchen</button></div>
    </form>
  </dialog>

  <dialog id="scan-dialog"><div class="dialog-head"><h2>Barcode scannen</h2><button class="close" type="button" data-close="scan-dialog" aria-label="Schließen">×</button></div><video id="scan-video" autoplay playsinline muted></video><p id="scan-status" role="status" aria-live="polite">Kamera wird geöffnet …</p><p class="hint">Richte die Rückkamera auf den Strichcode. Die erkannte Nummer wird ins EAN-Feld übernommen.</p><div class="dialog-actions"><button type="button" class="secondary" id="scan-manual">Nummer selbst eingeben</button></div></dialog>
  
</div>
