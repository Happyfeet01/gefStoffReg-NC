// Execute the complete catalog script against a small DOM/HTTP fixture; no browser required.
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const read = file => readFileSync(path.join(__dirname, '..', file), 'utf8');
class Element {
  constructor() {this.listeners = {}; this.children = []; this.value = ''; this.dataset = {}; this.classList = {add() {}};}
  addEventListener(type, callback) {(this.listeners[type] ||= []).push(callback);}
  dispatch(type) {for (const callback of this.listeners[type] || []) callback({target: this, preventDefault() {}});}
  appendChild(child) {this.children.push(child); return child;}
  replaceChildren() {this.children = [];}
  querySelector() {return null;}
  showModal() {this.open = true;}
  close() {this.open = false; this.dispatch('close');}
  focus() {this.focused = true;}
  click() {
    if (this.disabled) return;
    this.dispatch('click');
    if (this.submitForm) this.submitForm.dispatch('submit');
  }
}
const html = read('templates/main.php');
const elements = Object.fromEntries([...html.matchAll(/\bid="([^"]+)"/g)].map(match => [match[1], new Element()]));
elements['save-catalog'].submitForm = elements['catalog-edit-form'];
elements['confirm-delete-catalog'].submitForm = elements['catalog-delete-form'];
const calls = [];
let catalogs = [];
let rejectNextSave = false;
let releaseSave;
let delaySave = false;
const context = vm.createContext({
  document: {readyState: 'complete', getElementById: id => elements[id], createElement: () => new Element(), querySelectorAll: () => []},
  window: {}, OC: {generateUrl: value => '/nc/index.php' + value, requestToken: 'test-token'},
  FormData, console, setTimeout: () => 1, clearTimeout() {},
  fetch: async (url, options = {}) => {
    const method = options.method || 'GET';
    calls.push({url, method, fields: options.body ? Object.fromEntries(options.body) : {}});
    if (method === 'GET') return {ok: true, json: async () => ({catalogs: catalogs.map(c => ({...c, id: String(c.id)}))})};
    if (delaySave) await new Promise(resolve => {releaseSave = resolve;});
    if (rejectNextSave) {rejectNextSave = false; return {ok: false, status: 500, json: async () => ({error: 'Speichern fehlgeschlagen'})};}
    const fields = calls.at(-1).fields;
    const id = Number(url.split('/').at(-1));
    if (method === 'DELETE') catalogs = catalogs.filter(c => c.id !== id);
    else {
      assert.ok(fields.name, 'Controller expects name as a top-level form field');
      assert.ok(!('payload' in fields), 'Catalog endpoints do not decode payload JSON');
      if (id) Object.assign(catalogs.find(c => c.id === id), fields);
      else catalogs.push({id: catalogs.length + 1, ...fields, is_default: catalogs.length ? '0' : '1'});
    }
    return {ok: true, json: async () => ({catalog: catalogs.at(-1), success: true})};
  },
});
vm.runInContext(read('js/catalog.js'), context);
const settle = () => new Promise(resolve => setImmediate(resolve));
const mutations = () => calls.filter(call => call.method !== 'GET');
const fill = (name, description = '') => {elements['catalog-name'].value = name; elements['catalog-description'].value = description;};

(async () => {
  context.initCatalogUI(); // Reinitialization must not register a second click/submit handler.
  elements['manage-catalogs'].click();
  await settle();
  assert.equal(elements['catalog-list-dialog'].open, true);
  elements['add-catalog'].click();
  assert.equal(elements['catalog-edit-dialog'].open, true, 'New catalog button must open editor');
  assert.equal(elements['catalog-name'].focused, true);
  fill('  Freibad Ä & Süd  ', '  Testbeschreibung  ');
  delaySave = true;
  elements['save-catalog'].click();
  elements['save-catalog'].click();
  elements['catalog-edit-form'].dispatch('submit');
  assert.equal(mutations().length, 1, 'One submission, with repeated clicks blocked while saving');
  assert.equal(elements['save-catalog'].disabled, true);
  releaseSave(); delaySave = false;
  await settle();
  assert.equal(elements['catalog-edit-dialog'].open, false);
  assert.equal(elements['save-catalog'].disabled, false);
  assert.equal(catalogs[0].name, 'Freibad Ä & Süd');
  assert.equal(catalogs[0].description, 'Testbeschreibung');
  assert.equal(context.window.CatalogManager.getCatalogs()[0].id, 1);
  assert.equal(context.window.CatalogManager.getCatalogs()[0].is_default, true);
  assert.ok(elements['catalog-list'].children.some(row => row.children.some(cell => cell.textContent === 'Freibad Ä & Süd')));

  context.openCatalogEdit(1); fill('Hallenbad', 'Geändert');
  elements['catalog-edit-form'].dispatch('submit'); // Enter-key submission.
  await settle();
  assert.equal(mutations().length, 2);
  assert.ok(mutations()[1].url.endsWith('/api/catalogs/1'));
  assert.equal(catalogs[0].name, 'Hallenbad');

  elements['add-catalog'].click(); fill('Zweiter Katalog');
  rejectNextSave = true;
  elements['save-catalog'].click(); await settle();
  assert.equal(elements['catalog-edit-dialog'].open, true, 'Failed save must preserve editor');
  assert.equal(elements['catalog-name'].value, 'Zweiter Katalog');
  assert.equal(elements.toast.textContent, 'Speichern fehlgeschlagen');
  assert.equal(elements['catalog-edit-error'].hidden, false, 'Save error must appear inside the modal');
  assert.equal(elements['catalog-edit-error'].textContent, 'Speichern fehlgeschlagen');
  assert.equal(elements['save-catalog'].disabled, false);
  elements['save-catalog'].click(); await settle();
  assert.equal(elements['catalog-edit-error'].hidden, true);
  assert.equal(context.window.CatalogManager.getCatalogs()[1].is_default, false, 'DB boolean string 0 must not mark every catalog as default');

  context.openCatalogDelete(2, 'Zweiter Katalog');
  delaySave = true;
  elements['confirm-delete-catalog'].click();
  elements['catalog-delete-form'].dispatch('submit');
  assert.equal(mutations().filter(call => call.method === 'DELETE').length, 1);
  releaseSave(); delaySave = false; await settle();
  assert.equal(elements['catalog-delete-dialog'].open, false);
  assert.equal(elements['confirm-delete-catalog'].disabled, false);
  assert.equal(catalogs.length, 1);
  assert.equal(elements['catalog-select'].listeners.change.length, 1, 'List refresh must not multiply selection listeners');
  assert.ok(calls.every(call => call.url.startsWith('/nc/index.php/apps/gefahrstoffkataster/')));
  console.log('Catalog UI fixture passed: new button, focus, create, edit, error/retry, delete, form contract, repeated submits, DB types and subdirectory URLs');
})().catch(error => {console.error(error); process.exitCode = 1;});
