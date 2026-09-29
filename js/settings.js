(() => {
  const init = () => {
    const form = document.getElementById('gsk-key-form');
    if (!form || form.dataset.ready) return;
    form.dataset.ready = 'true';
    const input = document.getElementById('gsk-api-key');
    const provider = document.getElementById('gsk-provider');
    const remove = document.getElementById('gsk-key-delete');
    const select = document.getElementById('gsk-provider-save');
    const message = document.getElementById('gsk-key-message');
    const status = document.getElementById('gsk-key-status');
    const save = form.querySelector('[type="submit"]');
    const configured = {openai: form.dataset.openai === '1', mistral: form.dataset.mistral === '1'};
    const refresh = () => {
      status.textContent = configured[provider.value] ? 'Persönlicher Schlüssel für diesen Anbieter gespeichert.' : 'Kein persönlicher Schlüssel für diesen Anbieter gespeichert.';
      remove.disabled = !configured[provider.value];
    };
    provider.addEventListener('change', () => {input.value = ''; refresh(); message.textContent = 'Zum Aktivieren „Anbieter verwenden“ oder einen neuen Schlüssel speichern.';});
    const update = async action => {
      if (action === 'save' && !input.value.trim()) {message.textContent = 'Bitte einen API-Schlüssel eingeben.'; return;}
      const chosen = provider.value;
      save.disabled = remove.disabled = select.disabled = provider.disabled = true;
      message.textContent = 'Wird gespeichert …';
      try {
        const data = new FormData();
        data.set('action', action); data.set('provider', chosen);
        if (action === 'save') data.set('key', input.value.trim());
        const response = await fetch(OC.generateUrl('/apps/gefahrstoffkataster/api/settings/key'), {
          method: 'POST', headers: {requesttoken: OC.requestToken}, body: data,
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Speichern fehlgeschlagen.');
        configured[chosen] = result.configured;
        input.value = '';
        message.textContent = action === 'delete' ? 'Persönlicher Schlüssel gelöscht.' : 'Gespeichert. Anbieter: ' + (chosen === 'mistral' ? 'Mistral' : 'OpenAI') + '.';
      } catch (error) {message.textContent = error.message || 'Speichern fehlgeschlagen.';}
      finally {save.disabled = select.disabled = provider.disabled = false; refresh();}
    };
    form.addEventListener('submit', event => {event.preventDefault(); update('save');});
    remove.addEventListener('click', () => update('delete'));
    select.addEventListener('click', () => update('select'));
    refresh();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
