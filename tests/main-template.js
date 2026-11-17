// Run with Node; add --browser for a Playwright shell fixture (not a Nextcloud integration test).
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const read = file => readFileSync(path.join(__dirname, '..', file), 'utf8');
const template = read('templates/main.php');
const html = template.replace(/^<\?php\s+declare\(strict_types=1\);\s*\?>/, '');
assert.ok(!html.includes('<?php'), 'The static main template must render without PHP URL helpers');
assert.ok(!/<script\b/i.test(html), 'Scripts must be registered through Nextcloud, not emitted by the template');
assert.ok(!/\\u[0-9a-f]{4}/i.test(html), 'HTML text must contain real Unicode characters');
assert.ok(html.includes('Sicherheitsdatenblätter') && html.includes('Schließen'));
const controller = read('lib/Controller/InventoryController.php');
const index = controller.slice(controller.indexOf('public function index()'), controller.indexOf('public function bootstrap()'));
const scripts = [...index.matchAll(/Util::addScript\('gefahrstoffkataster', '(catalog|app)'\)/g)].map(match => match[1]);
assert.deepEqual(scripts, ['catalog', 'app'], 'Register catalog before app so its state is available on first render');
console.log('Main-template source checks passed: Unicode, static PHP body, Nextcloud script registration');
if (!process.argv.includes('--browser')) process.exit(0);
const {chromium} = require('playwright');

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROMIUM_PATH || undefined});
  try {
    for (const viewport of [{width: 390, height: 844}, {width: 1280, height: 900}]) {
      const context = await browser.newContext({viewport});
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      const base = '/nextcloud/index.php/apps/gefahrstoffkataster';
      const assets = {
        '/assets/style.css': ['text/css', read('css/style.css')],
        '/assets/core.js': ['text/javascript', `window.OC = {requestToken: 'fixture-token', generateUrl: path => '/nextcloud/index.php' + path};`],
        '/assets/catalog.js': ['text/javascript', read('js/catalog.js')],
        '/assets/app.js': ['text/javascript', read('js/app.js')],
      };
      let failed = false;
      const product = {id: 1, name: 'Testprodukt', manufacturer: 'Test', pack_size: 1, unit: 'l', stock: [], photos: [], hazardous: false};
      await page.route('https://nextcloud.test/**', async route => {
        const pathname = new URL(route.request().url()).pathname;
        if (assets[pathname]) {
          const [contentType, body] = assets[pathname];
          return route.fulfill({contentType, body});
        }
        if (pathname === base + '/api/bootstrap') {
          return route.fulfill({status: failed ? 404 : 200, contentType: 'application/json', body: JSON.stringify(failed ? {error: 'Fixture unavailable'} : {products: [product], locations: [], permissions: {manage: true, editBasic: true}})});
        }
        if (pathname === base + '/api/catalogs') {
          return route.fulfill({contentType: 'application/json', body: JSON.stringify({catalogs: []})});
        }
        if (pathname === base + '/') {
          return route.fulfill({contentType: 'text/html', headers: {'Content-Security-Policy': "default-src 'self'; script-src 'self'; style-src 'self'"}, body: `<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/style.css"><script defer src="/assets/core.js"></script>${scripts.map(name => `<script defer src="/assets/${name}.js"></script>`).join('')}</head><body><div id="content">${html}</div></body></html>`});
        }
        return route.fulfill({status: 404, body: 'Unexpected fixture request'});
      });
      await page.goto('https://nextcloud.test' + base + '/');
      await page.waitForFunction(() => document.getElementById('count-products').textContent === '1');
      assert.equal(await page.locator('#load-error').isVisible(), false);
      assert.ok((await page.locator('#gsk-app').evaluate(el => getComputedStyle(el).fontFamily)).includes('system-ui'), 'App stylesheet must be applied');
      assert.equal(await page.getByText('Testprodukt', {exact: true}).count(), 1);
      assert.equal(await page.locator('#view-description').textContent(), 'Produkte erfassen, Sicherheitsdatenblätter prüfen und Mengen je Lagerort verwalten.');
      await page.locator('#manage-catalogs').click();
      await page.waitForFunction(() => document.getElementById('catalog-list-dialog').open);
      await page.locator('[data-close="catalog-list-dialog"]').click();
      assert.equal(await page.locator('#catalog-list-dialog').evaluate(el => el.open), false);
      failed = true;
      await page.reload();
      await page.locator('#load-error').waitFor({state: 'visible'});
      assert.match(await page.locator('#load-error-text').textContent(), /HTTP 404/);
      failed = false;
      await page.locator('#retry-load').click();
      await page.waitForFunction(() => document.getElementById('load-error').hidden);
      assert.equal(await page.locator('#count-products').textContent(), '1');
      assert.deepEqual(errors, [], 'No uncaught browser errors under a strict CSP');
      console.log(`Main-template fixture passed: ${viewport.width}x${viewport.height}, Unicode, CSS, CSP, catalog dialog, 404 and retry`);
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch(error => {console.error(error); process.exitCode = 1;});
