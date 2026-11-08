// Regression guard for a clean mobile public-share page. No Nextcloud server required.
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = name => fs.readFileSync(path.join(__dirname, '..', name), 'utf8');
const html = read('templates/external-share.php');
const css = read('css/external-share.css');
const js = read('js/external-share.js');
const controller = read('lib/Controller/ExternalShareController.php');
for (const id of ['gsk-external', 'external-auth', 'external-unlock',
                  'external-password', 'external-error', 'external-content',
                  'external-label', 'external-info', 'external-count',
                  'external-products', 'external-empty']) {
  assert.match(html, new RegExp('id="' + id + '"'), 'Missing UI element ' + id);
}
assert.equal((html.match(/id="external-error"/g) || []).length, 1);
assert.match(html, /external-topbar/);
assert.match(html, /external-readonly-pill/);
assert.match(css, /body#body-public > #header/);
assert.match(css, /body#body-public #content\.app-gefahrstoffkataster/);
assert.match(css, /#gsk-external \[hidden\] \{display:none !important\}/);
assert.match(css, /@media \(max-width:600px\)/);
assert.match(controller, /setFooterVisible\(false\)/);
assert.match(js, /\$\('external-auth'\)\.hidden=true/);
new Function(js);
console.log('External public page layout, controls and responsive CSS guards passed');
