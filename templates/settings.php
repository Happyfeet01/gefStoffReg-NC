<?php
/** @var array $_ */
?>
<section class="section" id="gsk-api-settings">
    <h2>Gefahrstoffkataster – KI-Produktsuche</h2>
    <p>Hinterlege deinen persönlichen OpenAI-API-Schlüssel für die Produkt- und SDB-Suche. Die API-Nutzung wird separat bei OpenAI abgerechnet.</p>
    <p id="gsk-key-status"><?php p($_['configured'] ? 'Persönlicher API-Schlüssel gespeichert.' : 'Kein persönlicher API-Schlüssel gespeichert.'); ?></p>
    <form id="gsk-key-form">
        <p><label for="gsk-api-key">Neuer OpenAI-API-Schlüssel</label></p>
        <p><input type="password" id="gsk-api-key" autocomplete="new-password" spellcheck="false" maxlength="512" placeholder="sk-…" aria-describedby="gsk-key-help" /></p>
        <p id="gsk-key-help">Der Schlüssel wird verschlüsselt gespeichert und nicht wieder angezeigt. Er gilt nur für dein Benutzerkonto. Ohne persönlichen Schlüssel wird eine gegebenenfalls vorhandene Server-Konfiguration verwendet.</p>
        <button type="submit" class="primary">Schlüssel speichern</button>
        <button type="button" id="gsk-key-delete" <?php if (!$_['configured']) p('disabled'); ?>>Persönlichen Schlüssel löschen</button>
        <p id="gsk-key-message" role="status" aria-live="polite"></p>
    </form>
</section>
