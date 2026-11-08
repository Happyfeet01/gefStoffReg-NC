// Public browser regression: a reader must never see stock-edit controls,
// while a named stock editor can book a movement through the dedicated route.
// Run: node tests/external-stock-browser.js (requires Playwright & Chromium).
'use strict';
const {chromium}=require('playwright');
const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const appJs=read('js/external-share.js');
const appCss=read('css/external-share.css');
const markup=read('templates/external-share.php')
  .replace(/^<\?php[\s\S]*?\?>\s*/,'')
  .replace('data-api-url="<?php echo $apiUrl; ?>"','data-api-url="/data"')
  .replace('data-file-pattern="<?php echo $filePattern; ?>"','data-file-pattern="/file/__FILE__"')
  .replace('data-stock-url="<?php echo $stockUrl; ?>"','data-stock-url="/stock"');
assert.ok(markup.includes('data-stock-url="/stock"'));
const product={
 id:1,name:'Witty-Pool Flocal 5',manufacturer:'Witty',hazardous:true,
 material_kind:'chemical',pack_size:35,unit:'kg',category:'Wasserpflege',
 stock:[{location_id:2,location:'Chemieraum',packs:2}],photos:[],
};
(async()=>{
const browser=await chromium.launch({headless:true});
try{
for(const access of ['read','stock_editor']){
 let current=2,bookings=0;
 const page=await browser.newPage({viewport:{width:390,height:844},isMobile:true,hasTouch:true});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://gsk-stock.test/**',async route=>{
   const req=route.request(),url=new URL(req.url());
   const fulfill=(body,type='application/json',status=200)=>route.fulfill({status,contentType:type,body});
   if(url.pathname==='/')return fulfill(
     '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">'
     +'<link rel="stylesheet" href="/app.css"><script src="/app.js" defer></script></head>'
     +'<body id="body-public"><div id="content" class="app-gefahrstoffkataster">'
     +markup+'</div></body></html>','text/html');
   if(url.pathname==='/app.css')return fulfill(appCss,'text/css');
   if(url.pathname==='/app.js')return fulfill(appJs,'text/javascript');
   if(url.pathname==='/data')return fulfill(JSON.stringify({
     label:'Test Mitarbeitende',scope:'both',expires_at:1799999999,
     full_details:true,access,recipient:access==='stock_editor'?'Lena Beispiel':'',
     products:[{...product,stock:[{location_id:2,location:'Chemieraum',packs:current}]}],
     locations:[{id:2,name:'Chemieraum'}],
   }));
   if(url.pathname==='/stock'){
     bookings++;
     assert.equal(req.method(),'POST');
     assert.equal(req.headers()['x-gsk-stock-action'],'1');
     const params=new URLSearchParams(req.postData());
     assert.equal(params.get('password'),'Test-Passwort-1234');
     if(access!=='stock_editor')return fulfill(JSON.stringify({error:'Forbidden'}),'application/json',403);
     const payload=JSON.parse(params.get('booking'));
     assert.equal(payload.product_id,1);
     assert.equal(payload.location_id,2);
     assert.equal(payload.action,'add');
     assert.equal(payload.expected_milli,2000);
     assert.equal(payload.packs,1);
     current=3;
     return fulfill(JSON.stringify({before:2,after:3}));
   }
   return route.fulfill({status:404,body:'not found'});
 });
 try{
  await page.goto('https://gsk-stock.test/',{waitUntil:'networkidle'});
  await page.locator('#external-password').fill('Test-Passwort-1234');
  await page.getByRole('button',{name:/Freigabe anzeigen/}).click();
  await page.locator('.external-card').first().waitFor();
  assert.match(await page.locator('.external-card').first().innerText(),/2 Gebinde/);
  if(access==='stock_editor'){
   assert.match(await page.locator('#external-access-badge').innerText(),/Bestände bearbeiten/);
   await page.locator('.external-card .external-quick-booking > summary').click();
   await page.locator('.external-booking-quantity').fill('1');
   await page.getByRole('button',{name:'Änderung speichern'}).click();
   await page.getByText(/Gespeichert! Neuer Bestand/).waitFor();
   assert.match(await page.locator('.external-card').first().innerText(),/3 Gebinde/);
   assert.equal(bookings,1,'Unexpected booking count');
  }else{
   assert.equal(await page.locator('.external-booking-form').count(),0,'Reader saw booking controls');
   assert.equal(await page.locator('.external-quick-booking').count(),0,'Reader saw the stock change action');
   assert.equal(bookings,0,'Reader made stock write request');
  }
  assert.deepEqual(errors,[],'Guest script errors');
  console.log('PASS guest '+access+' access controls and stock booking');
 }finally{await page.close()}
}
}finally{await browser.close()}
})().catch(error=>{console.error(error);process.exitCode=1});
