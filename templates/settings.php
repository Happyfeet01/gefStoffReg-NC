<?php /** @var array $_ */ ?>
<section class="section" id="gsk-api-settings">
<h2>Gefahrstoffkataster – KI-Produktsuche</h2>
<p>Die Websuche läuft über deinen lokalen SearXNG-Server. Die KI wertet Titel und Textauszüge aus; PDFs werden dabei nicht gelesen. Treffer vor der Übernahme prüfen.</p>
<form id="gsk-key-form" data-openai="<?php p($_['openai'] ? '1' : '0'); ?>" data-mistral="<?php p($_['mistral'] ? '1' : '0'); ?>">
<p><label for="gsk-provider">KI-Anbieter</label></p>
<select id="gsk-provider">
<option value="openai" <?php if ($_['provider'] === 'openai') p('selected'); ?>>OpenAI</option>
<option value="mistral" <?php if ($_['provider'] === 'mistral') p('selected'); ?>>Mistral</option>
</select>
<button type="button" id="gsk-provider-save">Anbieter verwenden</button>
<p id="gsk-key-status"></p>
<p><label for="gsk-api-key">Neuer API-Schlüssel für den ausgewählten Anbieter</label></p>
<p><input type="password" id="gsk-api-key" autocomplete="new-password" spellcheck="false" maxlength="512" /></p>
<p>Schlüssel werden pro Benutzer verschlüsselt gespeichert und nicht wieder angezeigt. Speichern aktiviert den ausgewählten Anbieter. Kontingente und Kosten richten sich nach deinem API-Konto. Es gibt keinen automatischen Anbieterwechsel. Nur OpenAI kann auf einen vorhandenen Server-Schlüssel zurückgreifen.</p>
<button type="submit" class="primary">Schlüssel speichern</button>
<button type="button" id="gsk-key-delete">Persönlichen Schlüssel löschen</button>
<p id="gsk-key-message" role="status" aria-live="polite"></p>
</form>
</section>
