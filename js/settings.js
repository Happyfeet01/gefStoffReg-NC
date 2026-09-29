(() => {
  const init = () => {
    const form = document.getElementById('gsk-key-form');
    if (!form || form.dataset.ready) return;
    form.dataset.ready = 'true';
    const input = document.getElementById('gsk-api-key');
    const remove = document.getElementById('gsk-key-delete');
    const message = document.getElementById('gsk-key-message');
    const status = document.getElementById('gsk-key-status');
    const save = form.querySelector('[type="submit"]');
    let configured = !remove.disabled;
    const update = async (action) => {
      if (action === 'save' && !input.value.trim()) {
        message.textContent = 'Bitte einen API-Schlüssel eingeben.';
        return;
      }
      save.disabled = remove.disabled = true;
      message.textContent = 'Wird gespeichert …';
      try {
        const data = new FormData();
        data.set('action', action);
        if (action === 'save') data.set('key', input.value.trim());
        const response = await fetch(OC.generateUrl('/apps/gefahrstoffkataster/api/settings/key'), {
          method: 'POST', headers: { requesttoken: OC.requestToken }, body: data,
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Speichern fehlgeschlagen.');
        configured = result.configured;
        input.value = '';
        status.textContent = configured ? 'Persönlicher API-Schlüssel gespeichert.' : 'Kein persönlicher API-Schlüssel gespeichert.';
        message.textContent = configured ? 'Gespeichert. Du kannst die KI-Suche jetzt verwenden.' : 'Persönlicher Schlüssel gelöscht. Eine vorhandene Server-Konfiguration bleibt verfügbar.';
      } catch (error) {
        message.textContent = error.message || 'Speichern fehlgeschlagen.';
      } finally {
        save.disabled = false;
        remove.disabled = !configured;
      }
    };
    form.addEventListener('submit', event => { event.preventDefault(); update('save'); });
    remove.addEventListener('click', () => update('delete'));
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
