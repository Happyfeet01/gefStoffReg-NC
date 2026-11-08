<?php
declare(strict_types=1);
$apiUrl = htmlspecialchars((string)($_['apiUrl'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$filePattern = htmlspecialchars((string)($_['filePattern'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<main id="gsk-external" data-api-url="<?php echo $apiUrl; ?>" data-file-pattern="<?php echo $filePattern; ?>">
  <section class="external-head">
    <p class="external-eyebrow">GEFAHRSTOFFKATASTER · EXTERNE FREIGABE</p>
    <h1>Freigegebene Bestandsdaten</h1>
    <p>Diese Ansicht ist ausschließlich lesend. Es ist kein Nextcloud-Konto erforderlich.</p>
  </section>
  <section class="external-auth">
    <form id="external-unlock">
      <label for="external-password">Freigabepasswort (falls vereinbart)</label>
      <input id="external-password" name="password" type="password" autocomplete="off" placeholder="Passwort eingeben oder leer lassen">
      <button type="submit">Freigabe öffnen</button>
    </form>
    <p id="external-error" role="alert" hidden></p>
  </section>
  <section id="external-content" hidden>
    <div class="external-summary">
      <div><h2 id="external-label"></h2><p id="external-info"></p></div>
      <div id="external-count"></div>
    </div>
    <div id="external-products" class="external-products"></div>
    <p id="external-empty" hidden>Keine Produkte für diese Freigabe vorhanden.</p>
    <p class="external-footnote">Datenstand: bei Öffnung abgerufen. Einstufung und Sicherheitsdatenblätter vor Verwendung anhand des Originalprodukts prüfen.</p>
  </section>
</main>
