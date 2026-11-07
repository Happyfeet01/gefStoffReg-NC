// Lightweight regression for the two inventory views (no Nextcloud or browser needed).
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const source = readFileSync(__dirname + '/../js/app.js', 'utf8');
const start = source.indexOf('// Navigation is a view');
const end = source.indexOf('function render(){', start);
assert.ok(start >= 0 && end > start, 'Navigation function not found');
const elements = {};
for (const id of ['view-lager', 'view-gefahr', 'view-title', 'view-description', 'inventory-title', 'inventory-hint', 'hazard-filter']) {
  elements[id] = {
    attributes: {},
    classList: {toggle() {}},
    setAttribute(name, value) {this.attributes[name] = value;},
    replaceChildren(...options) {this.options = options;},
  };
}
const state = {view: 'lager'};
let renders = 0;
function Option(label, value) {this.label = label;this.value = value;}
const switchView = new Function('$', 'state', 'Option', 'render',
  source.slice(start, end) + '\nreturn setView;')(
  id => elements[id], state, Option, () => {renders++;});
switchView('gefahr');
assert.equal(state.view, 'gefahr');
assert.equal(elements['view-gefahr'].attributes['aria-pressed'], 'true');
assert.deepEqual(elements['hazard-filter'].options.map(x => x.value), ['', 'missing']);
switchView('lager');
assert.equal(elements['view-lager'].attributes['aria-pressed'], 'true');
assert.deepEqual(elements['hazard-filter'].options.map(x => x.value), ['', 'hazard', 'missing']);
assert.equal(renders, 2);
const matchingLine = source.split('\n').find(line => line.includes('const matching=state.products.filter'));
assert.ok(matchingLine, 'Product filter not found');
const matching = new Function('state', 'q', 'loc', 'hazard', matchingLine + '\nreturn matching;');
const makeProduct = (name, hazardous) => ({
  name, hazardous, manufacturer:'', article:'', ean:'', ufi:'', stock:[],
});
const products = [makeProduct('Papierhandtücher', false), makeProduct('Chlorbleichlauge', true)];
state.products = products;
assert.equal(matching(state, '', '', '').length, 2, 'Lager must show all products');
state.view = 'gefahr';
assert.deepEqual(matching(state, '', '', '').map(p => p.name), ['Chlorbleichlauge'], 'Kataster must show only explicitly flagged hazards');
assert.equal(products.length, 2, 'Changing the view must not modify products');
console.log('Inventory navigation and shared-product filtering passed');
