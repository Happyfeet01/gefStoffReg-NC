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
const adminHtml = read('templates/main.php');
const adminJs = read('js/app.js');
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
assert.match(html, /id="external-password-toggle"/);
assert.match(adminHtml, /id="external-share-password-toggle"/);
assert.match(js, /new URLSearchParams\(\{password\}\)/);
assert.match(js, /credentials:'same-origin'/);
assert.doesNotMatch(js, /credentials:'omit'/);
assert.match(js, /external-password-toggle.*addEventListener/);
assert.match(js, /querySelector\('button\[type="submit"\]'\)/);
assert.match(adminJs, /external-share-password-toggle.*addEventListener/);
assert.match(controller, /logDeniedShare\(\$token, 'data'\)/);
// Password inputs share the same Nextcloud-style eye-in-input pattern.
assert.match(html, /class="external-password-control"/);
assert.match(html, /id="external-password-toggle"[^>]*>\s*<svg/);
assert.match(adminHtml, /id="external-share-password-toggle"[^>]*>\s*<svg/);
assert.doesNotMatch(html, /id="external-password-toggle"[^>]*>Anzeigen<\/button>/);
assert.match(css, /--image-background/);
assert.match(css, /--color-main-background-blur/);
assert.match(css, /external-password-control #external-password/);
assert.match(css, /position:absolute/);
assert.match(js, /button\.title=visible/);
assert.doesNotMatch(js, /button\.textContent=visible/);
assert.match(adminJs, /toggle\.title=visible/);
assert.doesNotMatch(adminJs, /toggle\.textContent=visible/);
// Nextcloud 35 guest shell may vertically center the body and fix all footers.
// The public product list must restore actual document scroll and normal footer flow.
assert.match(css, /body#body-public:has\(#gsk-external\)\s*\{[^}]*display:block !important/s);
assert.match(css, /html:has\(#gsk-external\)\s*\{[^}]*overflow-y:auto !important/s);
assert.match(css, /body#body-public #content\.app-gefahrstoffkataster:has\(#gsk-external\)/);
assert.match(css, /body#body-public #gsk-external > footer\.external-page-footer\s*\{[^}]*position:static !important/s);
assert.match(css, /body#body-public #gsk-external \.external-page\s*\{[^}]*overflow:visible/s);
new Function(adminJs);
new Function(js);
console.log('External public page layout, controls and responsive CSS guards passed');
