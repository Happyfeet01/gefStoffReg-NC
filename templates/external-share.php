<?php
declare(strict_types=1);
$apiUrl = htmlspecialchars((string)($_['apiUrl'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$filePattern = htmlspecialchars((string)($_['filePattern'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<main id="gsk-external" data-api-url="<?php echo $apiUrl; ?>" data-file-pattern="<?php echo $filePattern; ?>">
  <header class="external-topbar">
    <div class="external-brand">
      <span class="external-brand-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5z"/>
          <path d="M8 12h8M12 8v8"/>
        </svg>
      </span>
      <span><strong>Lager &amp; Gefahrstoffe</strong><small>Freigegebene Bestandsdaten</small></span>
    </div>
    <span class="external-readonly-pill">Nur Lesezugriff</span>
  </header>

  <div class="external-page">
    <section id="external-auth" class="external-auth" aria-labelledby="external-login-title">
      <div class="external-auth-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="5" y="10" width="14" height="11" rx="2.5"/>
          <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
          <path d="M12 14.5v2"/>
        </svg>
      </div>
      <p class="external-eyebrow">EXTERNE FREIGABE</p>
      <h1 id="external-login-title">Freigabe öffnen</h1>
      <p class="external-intro">Hier kannst du freigegebene Lager- und Gefahrstoffdaten sicher einsehen – ganz ohne Nextcloud-Konto.</p>

      <form id="external-unlock">
        <label for="external-password">Freigabepasswort <span class="external-optional">(falls vorhanden)</span></label>
        <input id="external-password" name="password" type="password" autocomplete="off" placeholder="Passwort eingeben">
        <p class="external-field-hint">Kein Passwort erhalten? Lass das Feld einfach leer.</p>
        <button type="submit">Freigabe anzeigen <span aria-hidden="true">→</span></button>
      </form>
      <div class="external-privacy-note">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
          <path d="m12 22 7-4V7l-7-4-7 4v11z"/>
          <path d="m9 12 2 2 4-4"/>
        </svg>
        <span>Geschützter Zugriff · Nur ansehen, nichts verändern</span>
      </div>
    </section>

    <section id="external-content" aria-labelledby="external-label" hidden>
      <div class="external-summary">
        <div>
          <p class="external-eyebrow">FREIGEGEBENER BEREICH</p>
          <h1 id="external-label"></h1>
          <p id="external-info"></p>
        </div>
        <div id="external-count" class="external-count"></div>
      </div>
      <div id="external-products" class="external-products"></div>
      <p id="external-empty" class="external-empty" hidden>Keine Produkte für diese Freigabe vorhanden.</p>
      <p class="external-footnote">Die Angaben entsprechen dem zuletzt abgerufenen Datenstand. Einstufung und Sicherheitsdatenblätter vor der Verwendung anhand des Originalprodukts prüfen.</p>
    </section>
    <p id="external-error" role="alert" hidden></p>
  </div>

  <footer class="external-page-footer">
    <span>Externe Ansicht · Lager &amp; Gefahrstoffe</span>
    <span>Nur Lesezugriff</span>
  </footer>
</main>
