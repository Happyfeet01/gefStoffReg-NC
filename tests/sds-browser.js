// Browser regression with the real template, CSS and JS; API responses are isolated fixtures.
// Requires Playwright + Chromium. Optional: GSK_SCREENSHOT_DIR=/path/to/previews
const {chromium}=require('playwright');
const {readFileSync,mkdirSync}=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..');
const read=file=>readFileSync(path.join(root,file),'utf8');
const template=read('templates/main.php').replace(/^<\?php[\s\S]*?\?>/,'');
const token='1760000000-'+'a'.repeat(32);
const sourceUrl='https://manufacturer.example/sds.pdf';
const product={id:1,name:'Witty Poolreiniger',manufacturer:'Witty',article:'123456',ean:'4001234567890',category:'Reinigung',use_area:'Becken',pack_size:10,unit:'l',hazardous:false,classification:'',ghs:'',signal:'',h_statements:'',checked_at:'',sds_date:'2024-04-02',sds_url:sourceUrl,sds_id:null,stock:[{location_id:1,packs:3}],photos:[]};
const analysis={status:'classified',filename:'SDB-2026-10-15.pdf',sha256:'a'.repeat(64),ocr_pages:[],page_limit:4,draft_token:token,
  notice:'Produkt, Hersteller und SDB-Version im Original prüfen.',suggestions:[{field:'classification',value:'Skin Corr. 1A',evidence:[{page:2,text:'Skin Corr. 1A H314'}]}],evidence:[{page:2,text:'Skin Corr. 1A H314'}]};
(async()=>{
  const browser=await chromium.launch({headless:true});
  try {
    for(const size of [{width:1440,height:1000},{width:390,height:844}]){
      const page=await browser.newPage({viewport:size});
      const errors=[],blocked=[];let analyzed=0,attached=0,saved,toolFailure=false;
      page.on('pageerror',error=>errors.push(error.message));
      page.on('console',message=>{if(/violates.*Content Security Policy|Refused to/.test(message.text()))blocked.push(message.text())});
      await page.route('https://gsk.test/**',async route=>{
        const url=new URL(route.request().url()),pathname=url.pathname;
        const fulfill=(body,contentType='application/json',status=200,headers={})=>route.fulfill({status,contentType,body:typeof body==='string'?body:JSON.stringify(body),headers});
        if(pathname==='/')return fulfill(`<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/theme.css"><link rel="stylesheet" href="/app.css"><script src="/oc.js" defer></script><script src="/app.js" defer></script></head><body><header id="nc-header">◉ Nextcloud <span>Gefahrstoffe</span></header><div id="content">${template}</div></body></html>`,'text/html',200,{'Content-Security-Policy':"default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; img-src 'self' blob:; media-src 'self' blob:; base-uri 'none'; form-action 'self'"});
        if(pathname==='/theme.css')return fulfill(`:root{--color-primary-element:#6554c0;--color-primary-element-text:#fff;--color-main-text:#24292f;--color-main-background:#fff;--color-text-maxcontrast:#616975;--color-border:#dfe3e8;--color-background-hover:#f6f7f9}*{box-sizing:border-box}body{margin:0;background:#e8eaf1;font:15px system-ui}#nc-header{height:56px;background:#302b69;color:white;padding:16px 24px;font-weight:600}#nc-header span{margin-left:28px;font-weight:400}#content{height:calc(100dvh - 72px);margin:0 12px 16px;border-radius:12px;overflow:hidden}input,select{height:34px;max-height:34px;padding:4px}button{height:32px}dialog:not([open]){display:none}`,'text/css');
        if(pathname==='/app.css')return fulfill(read('css/style.css'),'text/css');
        if(pathname==='/app.js')return fulfill(read('js/app.js'),'application/javascript');
        if(pathname==='/oc.js')return fulfill(`window.OC={generateUrl:path=>path,requestToken:'test-token'};`,'application/javascript');
        if(pathname.endsWith('/api/bootstrap'))return fulfill({products:[product],locations:[{id:1,name:'Chemieraum'}]});
        if(pathname.endsWith('/api/sds-analysis')){analyzed++;return fulfill(toolFailure?{...analysis,status:'unclear',suggestions:[],evidence:[],extraction_error:'tools missing',notice:'PDF lokal geladen; Textauswertung nicht verfügbar.'}:analysis)}
        if(pathname.endsWith('/api/products/1/files')){attached++;assert.match(route.request().postData(),/draft_token/);return fulfill({id:19},'application/json',201)}
        if(pathname.endsWith('/api/products/1')){const match=route.request().postData().match(/name="payload"\r\n\r\n([^\r]*)/);saved=JSON.parse(match[1]);return fulfill({id:1})}
        return fulfill({error:'Unexpected URL: '+pathname},'application/json',404);
      });
      await page.goto('https://gsk.test/');
      await page.getByRole('button',{name:'Bearbeiten',exact:true}).waitFor();
      if(process.env.GSK_SCREENSHOT_DIR){mkdirSync(process.env.GSK_SCREENSHOT_DIR,{recursive:true});await page.screenshot({path:path.join(process.env.GSK_SCREENSHOT_DIR,`bestand-${size.width}.png`)});}
      await page.getByRole('button',{name:'Bearbeiten',exact:true}).click();
      assert.equal(await page.locator('#product-dialog').evaluate(dialog=>dialog.matches(':modal')),false,'Editor is still a modal overlay');
      await page.getByRole('link',{name:'04 SDB & Dateien'}).click();
      await page.getByRole('button',{name:'SDB auswerten',exact:true}).click();
      await page.getByText('Als gefährlich eingestuft laut erkanntem SDB-Text',{exact:true}).waitFor();
      assert.equal(analyzed,1,'Link-only product was not analyzed');
      await page.locator('#sds-analysis input[type=checkbox]').check();
      await page.getByRole('button',{name:'Geprüften Vorschlag übernehmen',exact:true}).click();
      assert.equal(await page.locator('[name=classification]').inputValue(),'Skin Corr. 1A');
      assert.equal(await page.locator('[name=sds_url]').inputValue(),sourceUrl);
      const geometry=await page.locator('#gsk-app').evaluate(app=>({width:app.clientWidth,scrollWidth:app.scrollWidth,controls:[...app.querySelectorAll('input:not([hidden]):not([type=checkbox]),select')].filter(input=>input.getClientRects().length).map(input=>({id:input.id||input.name,height:input.clientHeight,min:getComputedStyle(input).minHeight}))}));
      assert.ok(geometry.scrollWidth<=geometry.width+1,JSON.stringify(geometry));
      for(const control of geometry.controls)assert.ok(control.height>=40,JSON.stringify(control));
      if(process.env.GSK_SCREENSHOT_DIR){
        await page.locator('#product-documents').evaluate(section=>section.scrollIntoView({block:'start',behavior:'instant'}));
        await page.locator('#toast').evaluate(toast=>toast.hidden=true);
        await page.screenshot({path:path.join(process.env.GSK_SCREENSHOT_DIR,`sdb-${size.width}.png`)});
        await page.locator('#toast').evaluate(toast=>toast.hidden=false);
      }
      await page.getByRole('button',{name:'Produkt speichern',exact:true}).click();
      await page.getByText('Produkt und Dateien gespeichert.',{exact:true}).waitFor();
      assert.equal(attached,1,'Downloaded local PDF not attached');
      assert.equal(saved.sds_url,sourceUrl,'Source link lost during save');
      assert.equal(saved.sds_review.sha256,analysis.sha256,'Review hash not saved');
      toolFailure=true;
      await page.getByRole('button',{name:'Bearbeiten',exact:true}).click();
      await page.getByRole('link',{name:'04 SDB & Dateien'}).click();
      await page.getByRole('button',{name:'SDB auswerten',exact:true}).click();
      await page.getByText('PDF lokal verfügbar – Textauswertung nicht verfügbar',{exact:true}).waitFor();
      assert.equal(await page.getByRole('button',{name:'Geprüften Vorschlag übernehmen',exact:true}).count(),0);
      const localCopySaved=page.waitForResponse(response=>response.url().endsWith('/api/products/1/files')&&response.request().method()==='POST');
      await page.getByRole('button',{name:'Produkt speichern',exact:true}).click();
      await localCopySaved;
      await page.waitForFunction(()=>!document.getElementById('save-product').disabled);
      await page.getByText('Produkt und Dateien gespeichert.',{exact:true}).waitFor();
      assert.equal(attached,2,'Missing OCR tools prevented local-copy save');
      assert.equal(saved.sds_url,sourceUrl);
      assert.deepEqual(errors,[]);assert.deepEqual(blocked,[]);
      await page.close();
    }
    console.log('Desktop/mobile template, link-only analysis, local-copy save, retained source link and CSP checks passed');
  } finally {await browser.close()}
})().catch(error=>{console.error(error);process.exitCode=1});
