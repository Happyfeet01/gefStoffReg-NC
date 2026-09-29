const state = {products:[],locations:[],current:null,stockProduct:null,scannerControls:null,cameraTrack:null,scanning:false,torchOn:false};
const $ = id => document.getElementById(id);
const url = path => OC.generateUrl('/apps/gefahrstoffkataster'+path);
const format = n => new Intl.NumberFormat('de-DE',{maximumFractionDigits:3}).format(n);
let toastTimer;
function notice(message,error=false){const el=$('toast');el.textContent=message;el.className=(error?'error ':'')+'show';clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.className='',4800)}
async function request(path,body,method='POST'){
  const data=new FormData();data.set('payload',JSON.stringify(body));
  const response=await fetch(url(path),{method,headers:{requesttoken:OC.requestToken},body:data});
  let result;try{result=await response.json()}catch{throw Error(`Serverantwort konnte nicht gelesen werden (HTTP ${response.status}).`)}
  if(!response.ok)throw Error(result.error||`HTTP ${response.status}`);
  return result;
}
async function load(){
  try{
    const r=await fetch(url('/api/bootstrap'));
    if(!r.ok){
      let detail='';try{const body=await r.json();detail=body.error||''}catch{}
      throw Error(`Laden fehlgeschlagen (HTTP ${r.status})${detail?': '+detail:''}.`);
    }
    const data=await r.json();state.products=data.products;state.locations=data.locations;render();$('load-error').hidden=true;
  }catch(err){$('load-error-text').textContent=err.message;$('load-error').hidden=false;notice(err.message,true)}
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
  $('count-missing').textContent=state.products.filter(x=>x.hazardous&&!x.sds_id&&!x.sds_url).length;
  $('count-locations').textContent=state.locations.length;
  optionize($('location-filter'),$('location-filter').value,'Alle Lagerorte');
  const q=$('search').value.trim().toLocaleLowerCase('de');const loc=$('location-filter').value;const hazard=$('hazard-filter').value;
  const matching=state.products.filter(p=>(!q||[p.name,p.manufacturer,p.article,p.ean,p.ufi].join(' ').toLocaleLowerCase('de').includes(q))&&(!loc||p.stock.some(s=>String(s.location_id)===loc&&s.packs>0))&&(!hazard||hazard==='hazard'&&p.hazardous||hazard==='missing'&&p.hazardous&&!p.sds_id&&!p.sds_url));
  const list=$('product-list');list.replaceChildren();$('empty').hidden=matching.length>0;
  if(!matching.length)$('empty').textContent=state.products.length?'Keine Produkte für diese Filter gefunden.':'Hier ist noch kein Produkt erfasst. Starte mit dem ersten Gebinde.';
  for(const p of matching){
    const card=el('article','card');const top=el('div','card-top');top.append(el('div','product-icon',p.hazardous?'!':'●'));
    const details=el('div');details.append(el('h3','',p.name),el('small','muted',[p.manufacturer,p.category].filter(Boolean).join(' · ')||'Noch keine weiteren Angaben'));
    if(p.hazardous)details.append(el('span','badge hazard','Gefahrstoff'));
    if(p.hazardous&&!p.sds_id&&!p.sds_url)details.append(el('span','badge warn','SDB fehlt'));
    if(p.sds_url&&!p.sds_id)details.append(el('span','badge','SDB als Herstellerlink'));
    if(p.hazardous&&!p.checked_at)details.append(el('span','badge warn','Prüfung offen'));
    top.append(details);card.append(top);
    const total=p.stock.reduce((a,s)=>a+s.packs,0);const quantity=el('div','card-stock');quantity.append(el('strong','',`${format(total*p.pack_size)} ${p.unit}`),el('small','',`${format(total)} × ${format(p.pack_size)} ${p.unit}`));card.append(quantity);
    const places=el('div','card-locations');for(const s of p.stock.filter(s=>s.packs>0)){const place=state.locations.find(l=>l.id===s.location_id);places.append(el('span','place',`${place?.name||'?'}: ${format(s.packs)} Gebinde`))}if(!places.childElementCount)places.append(el('span','', 'Noch kein Bestand'));card.append(places);
    const actions=el('div','actions');actions.append(button('Bestand ändern',()=>openStock(p)),button('Bearbeiten',()=>openProduct(p)));
    if(p.sds_id){const link=el('a','', 'SDB herunterladen ↗');link.href=url(`/api/files/${p.sds_id}`);link.target='_blank';link.rel='noopener';actions.append(link)}
    if(p.source_url){const link=el('a','', 'Herstellerseite ↗');link.href=p.source_url;link.target='_blank';link.rel='noopener noreferrer';actions.append(link)}
    if(p.sds_url){const link=el('a','', 'SDB beim Hersteller ↗');link.href=p.sds_url;link.target='_blank';link.rel='noopener noreferrer';actions.append(link)}
    if(p.pmb_url){const link=el('a','', 'Produktmerkblatt ↗');link.href=p.pmb_url;link.target='_blank';link.rel='noopener noreferrer';actions.append(link)}
    for(const photo of p.photos||[]){const link=el('a','', 'Foto herunterladen ↗');link.href=url(`/api/files/${photo.id}`);link.target='_blank';link.rel='noopener';actions.append(link)}
    card.append(actions);list.append(card);
  }
}
function show(id){$(id).showModal()}
function close(id){$(id).close();if(id==='scan-dialog')stopScanner()}
function openProduct(p=null){
  state.current=p;
  const f=$('product-form');f.reset();$('photo-gallery').value='';$('photo-camera').value='';$('sds-file').value='';
  $('lookup-result').hidden=true;$('lookup-result').replaceChildren();
  $('source-result').hidden=true;$('source-result').replaceChildren();
  $('dialog-title').textContent=p?'Produkt bearbeiten':'Produkt erfassen';$('initial-stock').hidden=!!p;
  $('initial-packs').required=!p;$('initial-location').required=!p;
  optionize($('initial-location'),String(state.locations[0]?.id||''));
  if(p){for(const name of ['name','manufacturer','article','ean','ufi','category','use_area','pack_size','unit','classification','ghs','signal','h_statements','storage_note','sds_date','checked_at','source_url','sds_url','pmb_url'])f.elements[name].value=p[name]??'';f.elements.hazardous.checked=!!p.hazardous}
  const links=$('current-files');links.replaceChildren();
  if(p?.sds_id){const a=el('a','', 'Vorhandenes SDB ↗');a.href=url(`/api/files/${p.sds_id}`);a.target='_blank';a.rel='noopener';links.append(a)}
  for(const photo of p?.photos||[]){const a=el('a','',photo.filename+' ↗');a.href=url(`/api/files/${photo.id}`);a.target='_blank';a.rel='noopener';links.append(a)}
  show('product-dialog');
}
function lookupMessage(message){const box=$('lookup-result');box.replaceChildren(el('p','',message));box.hidden=false;return box}
function sourceMessage(message){const box=$('source-result');box.replaceChildren(el('p','',message));box.hidden=false;return box}
async function lookupEan(){
  const ean=$('product-form').elements.ean.value.trim();
  if(!/^[0-9]{8,14}$/.test(ean)){lookupMessage('Bitte zuerst eine EAN/GTIN mit 8 bis 14 Ziffern scannen oder eingeben.');return}
  const button=$('lookup-button');button.disabled=true;
  lookupMessage('Suche zuerst im Bestand, danach in Open Products Facts …');
  try{
    const response=await fetch(url('/api/lookup/'+encodeURIComponent(ean)));
    const data=await response.json();if(!response.ok)throw Error(data.error||`HTTP ${response.status}`);
    if($('product-form').elements.ean.value.trim()!==ean)return;
    if(data.match==='local'){
      const box=lookupMessage('Dieses Produkt ist bereits im eigenen Bestand: '+data.name+'.');
      const existing=state.products.find(p=>p.id===data.id);
      if(existing)box.append(buttonElement('Vorhandenes Produkt öffnen',()=>{close('product-dialog');openProduct(existing)}));
    }else if(data.match==='external'){
      const box=lookupMessage('Vorschlag von Open Products Facts: '+[data.name,data.manufacturer].filter(Boolean).join(' · ')+'. Bitte mit dem Etikett abgleichen.');
      if(data.name||data.manufacturer)box.append(buttonElement('Vorschlag übernehmen',()=>{
        const f=$('product-form');if(data.name)f.elements.name.value=data.name;if(data.manufacturer)f.elements.manufacturer.value=data.manufacturer;
        lookupMessage('Name und Marke übernommen. Hersteller, Produktvariante und alle Gefahrstoffangaben selbst prüfen.');
      }));
      const link=el('a','', 'Quelle ansehen ↗');link.href=data.source_url;link.target='_blank';link.rel='noopener noreferrer';box.append(link);
    }else{
      const box=lookupMessage('In Open Products Facts kein Treffer. Diese Datenbank enthält viele Spezialprodukte nicht.');
      const link=el('a','', 'EAN im Web suchen ↗');link.href='https://www.google.com/search?q='+encodeURIComponent('"'+ean+'" Produkt Hersteller');link.target='_blank';link.rel='noopener noreferrer';box.append(link);
    }
  }catch(err){
    const box=lookupMessage(err.message);
    const link=el('a','', 'EAN im Web suchen ↗');link.href='https://www.google.com/search?q='+encodeURIComponent('"'+ean+'" Produkt Hersteller');link.target='_blank';link.rel='noopener noreferrer';box.append(link);
  }finally{button.disabled=false}
}
function buttonElement(label,action){const b=button(label,action);b.className='secondary';return b}
function normalText(value){return String(value||'').normalize('NFKD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('de').replace(/[^a-z0-9]+/g,' ').trim()}
async function preparedLabelPhoto(file){
  if(!/^image\/(jpeg|png|webp)$/.test(file.type))return file;
  const objectUrl=URL.createObjectURL(file);
  try{
    const photo=new Image();photo.src=objectUrl;await photo.decode();
    const cropWidth=Math.round(photo.naturalWidth*.82),cropHeight=Math.round(photo.naturalHeight*.78);
    const scale=Math.min(2,2400/cropWidth,3000/cropHeight);
    const canvas=document.createElement('canvas');canvas.width=Math.round(cropWidth*scale);canvas.height=Math.round(cropHeight*scale);
    const ctx=canvas.getContext('2d',{willReadFrequently:true});if(!ctx)throw Error('Bildverarbeitung nicht verfügbar');
    ctx.drawImage(photo,Math.round((photo.naturalWidth-cropWidth)/2),Math.round((photo.naturalHeight-cropHeight)/2),cropWidth,cropHeight,0,0,canvas.width,canvas.height);
    const pixels=ctx.getImageData(0,0,canvas.width,canvas.height);
    for(let i=0;i<pixels.data.length;i+=4){
      const gray=.299*pixels.data[i]+.587*pixels.data[i+1]+.114*pixels.data[i+2];
      const value=Math.max(0,Math.min(255,(gray-128)*1.35+140));
      pixels.data[i]=pixels.data[i+1]=pixels.data[i+2]=value;
    }
    ctx.putImageData(pixels,0,0);
    const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/jpeg',.9));
    if(!blob)throw Error('Bild konnte nicht vorbereitet werden');
    return new File([blob],'etikett-optimiert.jpg',{type:'image/jpeg'});
  }finally{URL.revokeObjectURL(objectUrl)}
}
function labelSuggestions(lines){
  const text=normalText(lines.join(' '));
  const scored=state.products.map(p=>{
    const name=normalText(p.name),manufacturer=normalText(p.manufacturer),article=normalText(p.article),ean=String(p.ean||'').trim();
    const nameTokens=name.split(' ').filter(word=>word.length>=4&&!['witty','flamingo','aqua','pool','chlor','wasser','reiniger'].includes(word));
    const nameSeen=name.length>=7&&new RegExp('(?:^| )'+name.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'(?: |$)').test(text);
    const manufacturerSeen=manufacturer.length>=4&&text.includes(manufacturer);
    const articleSeen=article.length>=5&&new RegExp('(?:^| )'+article.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'(?: |$)').test(text);
    const eanSeen=ean.length>=8&&new RegExp('(?:^| )'+ean+'(?: |$)').test(text);
    const tokenSeen=nameTokens.length>0&&nameTokens.every(word=>text.split(' ').includes(word));
    const score=eanSeen?100:articleSeen&&manufacturerSeen?95:nameSeen&&manufacturerSeen?90:nameSeen?78:tokenSeen&&manufacturerSeen?72:0;
    return {product:p,score};
  }).filter(item=>item.score>=72).sort((a,b)=>b.score-a.score).slice(0,3);
  const manufacturer=/flamingo|fwt gmbh/i.test(text)?'FWT GmbH Flamingo water technology':/witty/i.test(text)?'Witty':/aquatec/i.test(text)?'AquaTec':lines.some(line=>/^ja!/i.test(line.trim()))?'ja!':'';
  const plausible=lines.map(line=>line.trim()).filter(line=>{
    const clean=normalText(line);
    return clean.length>=5&&clean.length<=75&&/[a-z]{4}/i.test(clean)&&!/(anwendung|dosierung|gefahr|achtung|gmbh|telefon|www |schutz|schwimm|beckenwasser|trinkwasser|lager|produkt darf|desinfektion|abgerufen)/i.test(clean);
  });
  let name=plausible.find(line=>/[®™]/.test(line))||plausible.find(line=>/\b(?:witty|liqui|aqua|pool)\b/i.test(line))||'';
  if(!name&&/sp[uü]lmittel/i.test(text)&&/zitrone/i.test(text))name='Geschirrspülmittel Zitrone';
  const pack=lines.map(line=>line.match(/\b(?:inhalt|nettoinhalt|gebinde(?:groesse|größe)?|fuellmenge|füllmenge)\s*:?\s*(\d+(?:[,.]\d+)?)\s*(kg|l|ml|g)\b/i)||line.trim().match(/^(\d+(?:[,.]\d+)?)\s*(kg|l|ml|g)$/i)).find(Boolean);
  return {scored,manufacturer,name:name.replace(/[®™]/g,'').trim(),pack:pack?{size:pack[1].replace(',','.'),unit:pack[2].toLowerCase()}:null};
}
$('lookup-button').addEventListener('click',lookupEan);
async function searchName(){
  const query=$('product-form').elements.name.value.trim();
  if(query.length<3){lookupMessage('Bitte erst einen Produktnamen eingeben oder eine erkannte Etikettzeile auswählen.');return}
  const action=$('name-search-button');action.disabled=true;lookupMessage('Suche nach Produktvorschlägen …');
  try{
    const result=await request('/api/search',{query});
    const box=lookupMessage(result.matches.length?'Mögliche Treffer in Open Products Facts. Bitte genaue Variante und EAN mit dem Etikett abgleichen.':'Keine passenden Produktvorschläge gefunden. Angaben bitte selbst eintragen.');
    for(const match of result.matches){
      box.append(buttonElement([match.name||'Ohne Name',match.manufacturer,match.ean].filter(Boolean).join(' · '),()=>{
        const f=$('product-form');if(match.name)f.elements.name.value=match.name;
        if(match.manufacturer)f.elements.manufacturer.value=match.manufacturer;
        f.elements.ean.value=match.ean;
        lookupMessage('Vorschlag übernommen. EAN und Produktvariante prüfen; Gefahrstoffangaben nur aus passendem SDB übernehmen.');
      }));
    }
  }catch(err){lookupMessage(err.message)}finally{action.disabled=false}
}
$('name-search-button').addEventListener('click',searchName);
$('manufacturer-search').addEventListener('click',()=>{
  const f=$('product-form');const terms=[f.elements.article.value,f.elements.name.value,f.elements.manufacturer.value].map(x=>x.trim()).filter(Boolean);
  if(!terms.length){sourceMessage('Bitte erst Produktname, Artikelnummer oder Hersteller eingeben.');return}
  window.open('https://www.google.com/search?q='+encodeURIComponent(terms.join(' ')), '_blank', 'noopener,noreferrer');
  sourceMessage('Öffne die passende Herstellerseite im Suchergebnis und kopiere deren Link in das Feld „Link zur Herstellerseite“.');
});
$('source-preview').addEventListener('click',async()=>{
  const f=$('product-form');const source=f.elements.source_url.value.trim();
  if(!source){sourceMessage('Bitte zuerst den Link einer Herstellerseite einfügen.');return}
  const action=$('source-preview');action.disabled=true;action.textContent='Lese …';sourceMessage('Herstellerseite wird gelesen …');
  try{
    const data=await request('/api/source-preview',{url:source});
    if(f.elements.source_url.value.trim()!==source)return;
    const box=sourceMessage('Vorschlag von der Herstellerseite: '+[data.name,data.article,data.pack_size&&`${data.pack_size} ${data.unit}`].filter(Boolean).join(' · ')+'. Bitte mit dem Gebinde vergleichen.');
    box.append(buttonElement('Stammdaten übernehmen',()=>{
      if(data.name)f.elements.name.value=data.name;
      if(data.manufacturer)f.elements.manufacturer.value=data.manufacturer;
      if(data.article)f.elements.article.value=data.article;
      if(data.pack_size){f.elements.pack_size.value=data.pack_size;f.elements.unit.value=data.unit}
      if(data.sds_url)f.elements.sds_url.value=data.sds_url;
      if(data.pmb_url)f.elements.pmb_url.value=data.pmb_url;
      const result=sourceMessage('Stammdaten übernommen. Variante und SDB-Link prüfen; GHS und H-Sätze anhand des SDB eintragen.');
      if(data.sds_url){const link=el('a','', 'Hersteller-SDB öffnen ↗');link.href=data.sds_url;link.target='_blank';link.rel='noopener noreferrer';result.append(link)}
      if(data.pmb_url){const link=el('a','', 'Produktmerkblatt öffnen ↗');link.href=data.pmb_url;link.target='_blank';link.rel='noopener noreferrer';result.append(link)}
    }));
    const link=el('a','', 'Herstellerseite ansehen ↗');link.href=data.source_url;link.target='_blank';link.rel='noopener noreferrer';box.append(link);
  }catch(err){sourceMessage('Einlesen fehlgeschlagen: '+err.message)}finally{action.disabled=false;action.textContent='Herstellerseite einlesen'}
});
$('product-form').elements.ean.addEventListener('change',()=>{if($('product-form').elements.ean.value.trim())lookupEan()});
$('label-button').addEventListener('click',()=>$('label-input').click());
$('label-input').addEventListener('change',async event=>{
  const file=event.currentTarget.files?.[0];if(!file)return;
  const box=lookupMessage('Etiketttext wird auf deinem Nextcloud-Server erkannt …');
  try{
    let ocrFile=file;
    try{ocrFile=await preparedLabelPhoto(file)}catch{ocrFile=file}
    const data=new FormData();data.set('file',ocrFile,ocrFile.name);
    const response=await fetch(url('/api/label'),{method:'POST',headers:{requesttoken:OC.requestToken},body:data});
    const result=await response.json();if(!response.ok)throw Error(result.error||`HTTP ${response.status}`);
    const lines=result.lines||[];
    box.replaceChildren(el('p','',lines.length?'Etikett erkannt. Bitte Produkt und Variante am Gebinde prüfen.':'Kein lesbarer Text gefunden. Bitte ein scharfes Foto aufnehmen oder Angaben selbst eintragen.'));
    if(lines.length){
      const suggestion=labelSuggestions(lines);
      for(const {product} of suggestion.scored){
        const detail=[product.name,product.manufacturer,product.pack_size&&`${format(product.pack_size)} ${product.unit}`].filter(Boolean).join(' · ');
        box.append(buttonElement(`Im Bestand gefunden: ${detail} → Menge buchen`,()=>{close('product-dialog');openStock(product);$('stock-packs').focus()}));
      }
      if(suggestion.name||suggestion.manufacturer||suggestion.pack){
        const f=$('product-form');
        if(!state.current&&!suggestion.scored.length){
          if(suggestion.name&&!f.elements.name.value.trim())f.elements.name.value=suggestion.name;
          if(suggestion.manufacturer&&!f.elements.manufacturer.value.trim())f.elements.manufacturer.value=suggestion.manufacturer;
          if(suggestion.pack&&!f.elements.pack_size.value){f.elements.pack_size.value=suggestion.pack.size;f.elements.unit.value=suggestion.pack.unit}
        }
        const details=[suggestion.name,suggestion.manufacturer,suggestion.pack&&`${suggestion.pack.size} ${suggestion.pack.unit}`].filter(Boolean).join(' · ');
        box.append(el('p','',`Vorschlag für neues Produkt: ${details}`));
        box.append(buttonElement('Erkannte Stammdaten übernehmen',()=>{
          const f=$('product-form');if(suggestion.name)f.elements.name.value=suggestion.name;
          if(suggestion.manufacturer)f.elements.manufacturer.value=suggestion.manufacturer;
          if(suggestion.pack){f.elements.pack_size.value=suggestion.pack.size;f.elements.unit.value=suggestion.pack.unit}
          notice('Stammdaten übernommen. Produktvariante und Gebindegröße prüfen.');
        }));
      }
      const extracted=el('details');extracted.append(el('summary','',`Erkannte Textzeilen (${lines.length}) – anderen Namen auswählen`));
      for(const line of lines.slice(0,20))extracted.append(buttonElement(line.slice(0,160),()=>{$('product-form').elements.name.value=line.slice(0,160);notice('Produktname aus Etikett übernommen.')}));
      box.append(extracted);
    }
    const search=el('a','', 'Produkt beim Hersteller suchen ↗');
    const suggestion=labelSuggestions(lines);
    search.href='https://www.google.com/search?q='+encodeURIComponent([suggestion.manufacturer,suggestion.name||lines.filter(line=>normalText(line).length>=5).slice(0,3).join(' ')].filter(Boolean).join(' ')+' Produkt');
    search.target='_blank';search.rel='noopener noreferrer';box.append(search);
    const possibleEan=lines.join(' ').match(/\b\d{8,14}\b/);
    if(possibleEan)box.append(buttonElement('Erkannte EAN '+possibleEan[0]+' suchen',()=>{$('product-form').elements.ean.value=possibleEan[0];lookupEan()}));
  }catch(err){lookupMessage(err.message+' Du kannst die Angaben weiterhin von Hand eintragen.')}finally{event.currentTarget.value=''}
});
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
    const keys=['name','manufacturer','article','ean','ufi','category','use_area','unit','classification','ghs','signal','h_statements','storage_note','sds_date','checked_at','source_url','sds_url','pmb_url'];
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
$('retry-load').addEventListener('click',load);
$('add-location').addEventListener('click',async()=>{const name=prompt('Neuer Lagerort (z. B. Technikraum PLB):');if(name===null)return;try{await request('/api/locations',{name});await load();notice('Lagerort angelegt.')}catch(err){notice(err.message,true)}});
for(const id of ['search','location-filter','hazard-filter'])$(id).addEventListener(id==='search'?'input':'change',render);
$('stock-location').addEventListener('change',updateStockContext);
document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click',()=>close(b.dataset.close)));
document.querySelectorAll('dialog').forEach(d=>d.addEventListener('close',()=>{if(d.id==='scan-dialog')stopScanner()}));
function stopScanner(){
  state.scanning=false;
  try{state.scannerControls?.stop()?.catch?.(()=>{})}catch{}
  state.scannerControls=null;
  state.cameraTrack=null;
  state.torchOn=false;
  $('scan-focus').hidden=true;
  $('scan-torch').hidden=true;
  $('scan-torch').textContent='Blitz ein';
  const video=$('scan-video');
  video.srcObject?.getTracks().forEach(track=>track.stop());
  video.srcObject=null;
}
async function configureScanCamera(controls){
  const track=$('scan-video').srcObject?.getVideoTracks()[0];
  if(!track||!state.scanning)return;
  state.cameraTrack=track;
  let capabilities={};
  try{capabilities=track.getCapabilities?.()||{}}catch{}
  const focusModes=Array.isArray(capabilities.focusMode)?capabilities.focusMode:[];
  if(focusModes.includes('continuous')){
    try{await track.applyConstraints({advanced:[{focusMode:'continuous'}]})}catch{}
  }
  if(!state.scanning)return;
  $('scan-focus').hidden=!focusModes.includes('single-shot');
  $('scan-torch').hidden=!(capabilities.torch===true&&typeof controls.switchTorch==='function');
}
async function scan(){
  if(state.scanning)return;
  $('scan-status').textContent='Kamera wird geöffnet …';
  $('scan-focus').hidden=true;$('scan-torch').hidden=true;
  show('scan-dialog');
  state.scanning=true;
  if(!window.isSecureContext||!navigator.mediaDevices?.getUserMedia){
    state.scanning=false;
    $('scan-status').textContent='Kamera nicht verfügbar. Öffne Nextcloud über HTTPS oder gib die Nummer selbst ein.';
    return;
  }
  if(!window.GefahrstoffScanner?.start){
    state.scanning=false;
    $('scan-status').textContent='Scanner konnte nicht geladen werden. Bitte die Seite neu laden oder die Nummer selbst eingeben.';
    return;
  }
  try{
    const controls=await window.GefahrstoffScanner.start($('scan-video'),value=>{
      if(!state.scanning)return;
      $('product-form').elements.ean.value=value;
      close('scan-dialog');
      notice('Barcode übernommen.');
      lookupEan();
    });
    if(!state.scanning){try{await controls.stop()}catch{}return}
    state.scannerControls=controls;
    $('scan-status').textContent='Barcode vor die Kamera halten …';
    await configureScanCamera(controls);
  }catch(err){
    if(!state.scanning)return;
    stopScanner();
    $('scan-status').textContent=err.name==='NotAllowedError'
      ? 'Kamerazugriff verweigert. Bitte in den Browser-Einstellungen erlauben oder die Nummer selbst eingeben.'
      : 'Kamera konnte nicht geöffnet werden: '+err.message;
  }
}
$('scan-button').addEventListener('click',scan);
$('scan-focus').addEventListener('click',async()=>{
  if(!state.cameraTrack)return;
  try{await state.cameraTrack.applyConstraints({advanced:[{focusMode:'single-shot'}]});$('scan-status').textContent='Fokus wird neu eingestellt …'}
  catch{$('scan-status').textContent='Fokussteuerung nicht verfügbar. Bitte den Barcode fotografieren.';$('scan-focus').hidden=true}
});
$('scan-torch').addEventListener('click',async()=>{
  if(!state.scannerControls?.switchTorch)return;
  try{
    const turnOn=!state.torchOn;
    await state.scannerControls.switchTorch(turnOn);
    state.torchOn=turnOn;
    $('scan-torch').textContent=turnOn?'Blitz aus':'Blitz ein';
  }catch{$('scan-torch').hidden=true;$('scan-status').textContent='Blitz in diesem Browser nicht verfügbar. Bitte den Barcode fotografieren.'}
});
$('scan-photo-button').addEventListener('click',()=>{
  stopScanner();
  $('scan-status').textContent='Foto mit der Kamera aufnehmen …';
  $('scan-photo-input').value='';
  $('scan-photo-input').click();
});
$('scan-photo-input').addEventListener('change',async event=>{
  const file=event.currentTarget.files?.[0];
  if(!file||!$('scan-dialog').open)return;
  $('scan-status').textContent='Barcode im Foto wird gesucht …';
  try{
    if(!window.GefahrstoffScanner?.decodePhoto)throw Error('Scanner nicht geladen');
    const value=await window.GefahrstoffScanner.decodePhoto(file);
    if(!$('scan-dialog').open)return;
    $('product-form').elements.ean.value=value;
    close('scan-dialog');
    notice('Barcode aus Foto übernommen.');
    lookupEan();
  }catch{
    if($('scan-dialog').open)$('scan-status').textContent='Im Foto kein Barcode erkannt. Bitte näher und scharf fotografieren oder die Nummer eingeben.';
  }finally{event.currentTarget.value=''}
});
$('scan-photo-input').addEventListener('cancel',()=>{$('scan-status').textContent='Kein Foto aufgenommen. Du kannst es erneut versuchen oder die Nummer eingeben.'});
$('scan-manual').addEventListener('click',()=>{close('scan-dialog');$('product-form').elements.ean.focus()});
$('export-xlsx').href=url('/api/export/xlsx');
$('export-csv').href=url('/api/export/csv');
load();
