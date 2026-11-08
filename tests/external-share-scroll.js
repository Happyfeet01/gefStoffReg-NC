// Browser regression for external read-only links with many long products.
// Run: node tests/external-share-scroll.js (requires Playwright + Chromium).
'use strict';
const {chromium} = require('playwright');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root=path.resolve(__dirname,'..');
const read=name=>readFileSync(path.join(root,name),'utf8');
const appCss=read('css/external-share.css');
const appJs=read('js/external-share.js');
const markup=read('templates/external-share.php')
  .replace(/^<\?php[\s\S]*?\?>\s*/,'')
  .replace('data-api-url="<?php echo $apiUrl; ?>"','data-api-url="/data"')
  .replace('data-file-pattern="<?php echo $filePattern; ?>"','data-file-pattern="/file/__FILE__"');
assert.ok(markup.includes('data-api-url="/data"'),'Static route fixture missing');

// Reproduce the relevant Nextcloud 35 guest/public shell defaults.
// The fixed footer rule must be overridden by the app, not removed here.
const nextcloudShell=[
  'html {height:100%}',
  'body#body-public {display:flex;flex-direction:column;justify-content:center;',
  'align-items:center;min-height:100%;height:auto;overflow:auto;position:static;background:#3074ad}',
  '#body-public #content {min-height:calc(100% - 50px);padding-block-end:50px}',
  '#body-public footer {position:fixed;left:50%;bottom:20px;transform:translateX(-50%);width:90vw}',
].join('\n');
const products=Array.from({length:36},(_,i)=>({
  id:i+1,name:'Gefahrstoff '+String(i+1).padStart(2,'0'),
  manufacturer:'Beispiel GmbH',category:'Wasseraufbereitung',
  hazardous:true,material_kind:'chemical',classification:'Skin Corr. 1A',
  h_statements:('H314 Verursacht schwere Verätzungen der Haut und schwere Augenschäden. ').repeat(8),
  ghs:'GHS05',signal:'Gefahr',sds_id:null,pack_size:10,unit:'l',
  article:'A-2026',ean:'4001234567890',use_area:'Wasserpflege',
  stock:[{location_id:1,location:'Chemieraum',packs:2.5}],photos:[],
}));
async function check(browser,viewport){
  const page=await browser.newPage({
    viewport,deviceScaleFactor:2,isMobile:viewport.width<600,hasTouch:viewport.width<600,
  });
  const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  await page.route('https://gsk.test/**',async route=>{
    const target=new URL(route.request().url());
    const reply=(body,contentType)=>route.fulfill({status:200,contentType,body});
    if(target.pathname==='/')return reply(
      '<!doctype html><html lang="de"><head><meta charset="utf-8">'
      +'<meta name="viewport" content="width=device-width,initial-scale=1">'
      +'<style>'+nextcloudShell+'</style><link rel="stylesheet" href="/app.css">'
      +'<script src="/app.js" defer></script></head>'
      +'<body id="body-public"><div id="content" class="app-gefahrstoffkataster">'
      +markup+'</div></body></html>','text/html');
    if(target.pathname==='/app.css')return reply(appCss,'text/css');
    if(target.pathname==='/app.js')return reply(appJs,'text/javascript');
    if(target.pathname==='/data')return reply(JSON.stringify({
      scope:'kataster',label:'Test Flieden',expires_at:1799999999,products,
      locations:[{id:1,name:'Chemieraum'}],
    }),'application/json');
    return route.fulfill({status:404,body:'not found'});
  });
  try{
    await page.goto('https://gsk.test/',{waitUntil:'networkidle'});
    await page.getByRole('button',{name:/Freigabe anzeigen/}).click();
    await page.locator('#external-products .external-card').first().waitFor();
    assert.equal(await page.locator('#external-products .external-card').count(),36,'Not all products rendered');
    assert.match(await page.locator('#external-products .external-card').first().innerText(),/Bestand: 2,5 Gebinde/, 'Stock missing in Kataster scope');
    assert.match(await page.locator('#external-locations').innerText(),/Chemieraum/, 'Visible storage locations missing');
    const first=page.locator('#external-products .external-card').first();
    await first.locator('summary').click();
    assert.match(await first.innerText(),/Lagerorte und Bestände/, 'Full detail section missing');
    assert.match(await first.innerText(),/Artikelnummer/, 'Read-only product master data missing');
    assert.equal(await first.locator('input,textarea,button[type="submit"]').count(),0,'Guest can edit an inventory product');
    await first.locator('summary').click();
    const state=await page.evaluate(()=>{
      const last=document.querySelector('#external-products .external-card:last-child');
      const footer=document.querySelector('#gsk-external > footer');
      return {
        scrollHeight:document.scrollingElement.scrollHeight,
        clientHeight:document.scrollingElement.clientHeight,
        footerPosition:getComputedStyle(footer).position,
        contentBottom:last.getBoundingClientRect().bottom+window.scrollY,
        footerTop:footer.getBoundingClientRect().top+window.scrollY,
      };
    });
    assert.ok(state.scrollHeight>state.clientHeight+1200,'Document did not become scrollable');
    assert.equal(state.footerPosition,'static','Footer must flow after products');
    assert.ok(state.footerTop>=state.contentBottom,'Footer overlaps product cards');
    await page.evaluate(()=>window.scrollTo(0,document.scrollingElement.scrollHeight));
    await page.waitForTimeout(150);
    const bottom=await page.evaluate(()=>{
      const last=document.querySelector('#external-products .external-card:last-child').getBoundingClientRect();
      return {scrollY:window.scrollY,visible:last.bottom>0&&last.top<innerHeight};
    });
    assert.ok(bottom.scrollY>500,'Mobile page remains scroll locked');
    assert.ok(bottom.visible,'Last product is unreachable');
    assert.deepEqual(errors,[],'Unhandled browser errors');
    console.log('PASS '+viewport.width+'x'+viewport.height+' – 36 products, scroll to last, footer in document flow');
  }finally{await page.close()}
}
(async()=>{
  const browser=await chromium.launch({headless:true});
  try{
    for(const viewport of [{width:390,height:844},{width:375,height:667},{width:1280,height:900}]){
      await check(browser,viewport);
    }
  }finally{await browser.close()}
})().catch(err=>{console.error(err);process.exitCode=1});
