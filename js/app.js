const state = {products:[],locations:[],current:null,stockProduct:null,stream:null,scanning:false};
const $ = id => document.getElementById(id);
const url = path => OC.generateUrl('/apps/gefahrstoffkataster'+path);
const format = n => new Intl.NumberFormat('de-DE',{maximumFractionDigits:3}).format(n);
let toastTimer;
function notice(message,error=false){const el=$('toast');el.textContent=message;el.className=(error?'error ':'')+'show';clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.className='',4800)}
async function request(path,body,method='POST'){
  const data=new FormData();data.set('payload',JSON.stringify(body));
  const response=await fetch(url(path),{method,headers:{requesttoken:OC.requestToken},body:data});
  let result;try{result=await response.json()}catch{throw Error('Serverantwort konnte nicht gelesen werden.')}
  if(!response.ok)throw Error(result.error||`HTTP ${response.status}`);
  return result;
}
async function load(){
  try{const r=await fetch(url('/api/bootstrap'));if(!r.ok)throw Error('Laden fehlgeschlagen.');const data=await r.json();state.products=data.products;state.locations=data.locations;render()}
  catch(err){notice(err.message,true)}
}
function optionize(select,selected,allLabel){
  select.replaceChildren();if(allLabel)select.add(new Option(allLabel,''));
  for(const l of state.locations)select.add(new Option(l.name,String(l.id)));
  select.value=selected||'';
}
function el(tag,cls,text){const node=document.createElement(tag);if(cls)node.className=cls;if(text!==undefined)node.textContent=text;return node}
function button(label,action){const b=el('button','',label);b.type='button';b.addEventListener('click',action);return b}
function render(){
  $('count-products').textContent=state.products.length;
  $('count-hazards').textContent=state.products.filter(x=>x.hazardous).length;
  $('count-missing').textContent=state.products.filter(x=>x.hazardous&&!x.sds_id).length;
  $('count-locations').textContent=state.locations.length;
  optionize($('location-filter'),$('location-filter').value,'Alle Lagerorte');
  const q=$('search').value.trim().toLocaleLowerCase('de');const loc=$('location-filter').value;const hazard=$('hazard-filter').value;
  const matching=state.products.filter(p=>(!q||[p.name,p.manufacturer,p.article,p.ean,p.ufi].join(' ').toLocaleLowerCase('de').includes(q))&&(!loc||p.stock.some(s=>String(s.location_id)===loc&&s.packs>0))&&(!hazard||hazard==='hazard'&&p.hazardous||hazard==='missing'&&p.hazardous&&!p.sds_id));
  const list=$('product-list');list.replaceChildren();$('empty').hidden=matching.length>0;
  if(!matching.length)$('empty').textContent=state.products.length?'Keine Produkte für diese Filter gefunden.':'Hier ist noch kein Produkt erfasst. Starte mit dem ersten Gebinde.';
  for(const p of matching){
    const card=el('article','card');const top=el('div','card-top');top.append(el('div','product-icon',p.hazardous?'!':'●'));
    const details=el('div');details.append(el('h3','',p.name),el('small','muted',[p.manufacturer,p.category].filter(Boolean).join(' · ')||'Noch keine weiteren Angaben'));
    if(p.hazardous)details.append(el('span','badge hazard','Gefahrstoff'));
    if(p.hazardous&&!p.sds_id)details.append(el('span','badge warn','SDB fehlt'));
    if(p.hazardous&&!p.checked_at)details.append(el('span','badge warn','Prüfung offen'));
    top.append(details);card.append(top);
    const total=p.stock.reduce((a,s)=>a+s.packs,0);const quantity=el('div','card-stock');quantity.append(el('strong','',`${format(total*p.pack_size)} ${p.unit}`),el('small','',`${format(total)} × ${format(p.pack_size)} ${p.unit}`));card.append(quantity);
    const places=el('div','card-locations');for(const s of p.stock.filter(s=>s.packs>0)){const place=state.locations.find(l=>l.id===s.location_id);places.append(el('span','place',`${place?.name||'?'}: ${format(s.packs)} Gebinde`))}if(!places.childElementCount)places.append(el('span','', 'Noch kein Bestand'));card.append(places);
    const actions=el('div','actions');actions.append(button('Bestand ändern',()=>openStock(p)),button('Bearbeiten',()=>openProduct(p)));
    if(p.sds_id){const link=el('a','', 'SDB herunterladen ↗');link.href=url(`/api/files/${p.sds_id}`);link.target='_blank';link.rel='noopener';actions.append(link)}
    for(const photo of p.photos||[]){const link=el('a','', 'Foto herunterladen ↗');link.href=url(`/api/files/${photo.id}`);link.target='_blank';link.rel='noopener';actions.append(link)}
    card.append(actions);list.append(card);
  }
}
function show(id){$(id).showModal()}
function close(id){$(id).close();if(id==='scan-dialog')stopScanner()}
function openProduct(p=null){
  state.current=p;
  const f=$('product-form');f.reset();$('photo-gallery').value='';$('photo-camera').value='';$('sds-file').value='';
  $('dialog-title').textContent=p?'Produkt bearbeiten':'Produkt erfassen';$('initial-stock').hidden=!!p;
  $('initial-packs').required=!p;$('initial-location').required=!p;
  optionize($('initial-location'),String(state.locations[0]?.id||''));
  if(p){for(const name of ['name','manufacturer','article','ean','ufi','category','use_area','pack_size','unit','classification','ghs','signal','h_statements','storage_note','sds_date','checked_at'])f.elements[name].value=p[name]??'';f.elements.hazardous.checked=!!p.hazardous}
  const links=$('current-files');links.replaceChildren();
  if(p?.sds_id){const a=el('a','', 'Vorhandenes SDB ↗');a.href=url(`/api/files/${p.sds_id}`);a.target='_blank';a.rel='noopener';links.append(a)}
  for(const photo of p?.photos||[]){const a=el('a','',photo.filename+' ↗');a.href=url(`/api/files/${photo.id}`);a.target='_blank';a.rel='noopener';links.append(a)}
  show('product-dialog');
}
function openStock(p){
  state.stockProduct=p;$('stock-form').reset();$('stock-title').textContent=p.name;
  optionize($('stock-location'),String(p.stock.find(s=>s.packs>0)?.location_id||state.locations[0]?.id||''));
  updateStockContext();show('stock-dialog');
}
function updateStockContext(){const p=state.stockProduct;if(!p)return;const s=p.stock.find(s=>s.location_id===Number($('stock-location').value));$('stock-context').textContent=`Aktuell an diesem Ort: ${format(s?.packs||0)} Gebinde à ${format(p.pack_size)} ${p.unit}.`}
async function upload(productId,file,kind){
  const max=kind==='sds'?15:8;if(file.size>max*1024*1024)throw Error(`Datei zu groß: maximal ${max} MB.`);
  const data=new FormData();data.set('file',file,file.name);data.set('kind',kind);
  const response=await fetch(url(`/api/products/${productId}/files`),{method:'POST',headers:{requesttoken:OC.requestToken},body:data});
  const result=await response.json();if(!response.ok)throw Error(result.error||`Upload fehlgeschlagen (HTTP ${response.status}).`);
  return result.id;
}
$('product-form').addEventListener('submit',async event=>{
  event.preventDefault();const save=$('save-product');save.disabled=true;save.textContent='Speichere …';
  try{
    const f=event.currentTarget;
    const keys=['name','manufacturer','article','ean','ufi','category','use_area','unit','classification','ghs','signal','h_statements','storage_note','sds_date','checked_at'];
    const body=Object.fromEntries(keys.map(k=>[k,f.elements[k].value.trim()]));
    Object.assign(body,{pack_size:Number(f.elements.pack_size.value),hazardous:f.elements.hazardous.checked});
    const isNew=!state.current;
    const result=await request(isNew?'/api/products':`/api/products/${state.current.id}`,body);
    close('product-dialog');
    if(isNew){
      try{await request('/api/stock',{product_id:result.id,location_id:Number($('initial-location').value),action:'set',packs:Number($('initial-packs').value),note:'Anfangsbestand'})}
      catch(err){await load();notice('Produkt angelegt, aber der Anfangsbestand wurde nicht gebucht: '+err.message,true);return}
    }
    try{
      for(const file of $('photo-gallery').files)await upload(result.id,file,'photo');
      for(const file of $('photo-camera').files)await upload(result.id,file,'photo');
      if($('sds-file').files[0])await upload(result.id,$('sds-file').files[0],'sds');
    }catch(err){await load();notice('Produkt gespeichert, aber mindestens ein Upload ist fehlgeschlagen: '+err.message,true);return}
    await load();notice('Produkt und Dateien gespeichert.');
  }catch(err){notice(err.message,true)}
  finally{save.disabled=false;save.textContent='Produkt speichern'}
});
$('stock-form').addEventListener('submit',async event=>{
  event.preventDefault();const save=$('save-stock');save.disabled=true;
  try{await request('/api/stock',{product_id:state.stockProduct.id,location_id:Number($('stock-location').value),action:$('stock-action').value,packs:Number($('stock-packs').value),note:$('stock-note').value});close('stock-dialog');await load();notice('Bestand gebucht.')}
  catch(err){notice(err.message,true)}finally{save.disabled=false}
});
$('new-product').addEventListener('click',()=>openProduct());
$('add-location').addEventListener('click',async()=>{const name=prompt('Neuer Lagerort (z. B. Technikraum PLB):');if(name===null)return;try{await request('/api/locations',{name});await load();notice('Lagerort angelegt.')}catch(err){notice(err.message,true)}});
for(const id of ['search','location-filter','hazard-filter'])$(id).addEventListener(id==='search'?'input':'change',render);
$('stock-location').addEventListener('change',updateStockContext);
document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click',()=>close(b.dataset.close)));
document.querySelectorAll('dialog').forEach(d=>d.addEventListener('close',()=>{if(d.id==='scan-dialog')stopScanner()}));
function stopScanner(){state.scanning=false;state.stream?.getTracks().forEach(t=>t.stop());state.stream=null;$('scan-video').srcObject=null}
async function scan(){
  if(!('BarcodeDetector' in window)||!navigator.mediaDevices?.getUserMedia){notice('Barcode-Scan hier nicht verfügbar. Bitte EAN eingeben.',true);return}
  try{
    const supported=await BarcodeDetector.getSupportedFormats();const formats=['ean_13','ean_8','upc_a','code_128'].filter(x=>supported.includes(x));
    if(!formats.length)throw Error('Keine passenden Barcodeformate verfügbar.');
    const detector=new BarcodeDetector({formats});show('scan-dialog');
    state.stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}}});
    const video=$('scan-video');video.srcObject=state.stream;await video.play();state.scanning=true;
    while(state.scanning){const result=await detector.detect(video);if(result.length){$('product-form').elements.ean.value=result[0].rawValue;close('scan-dialog');notice('Barcode übernommen.');break}await new Promise(r=>setTimeout(r,350))}
  }catch(err){notice('Kamera/Scan: '+err.message,true);close('scan-dialog')}
}
$('scan-button').addEventListener('click',scan);
$('export-xlsx').href=url('/api/export/xlsx');
$('export-csv').href=url('/api/export/csv');
load();
