(() => {
  'use strict';
  const root=document.getElementById('gsk-external');
  if(!root)return;
  const $=id=>document.getElementById(id);
  const api=root.dataset.apiUrl;
  const filePattern=root.dataset.filePattern;
  let password='';
  const scopeNames={kataster:'Gefahrstoffkataster',lager:'Lager',both:'Lager und Gefahrstoffkataster'};
  const kindNames={chemical:'Chemikalien',consumable:'Verbrauchsmaterial',other:'Sonstiges',unclassified:'Nicht zugeordnet'};
  const format=n=>new Intl.NumberFormat('de-DE',{maximumFractionDigits:3}).format(n);
  const el=(tag,cls,value)=>{
    const node=document.createElement(tag);
    if(cls)node.className=cls;
    if(value!==undefined)node.textContent=String(value);
    return node;
  };
  // Nextcloud's IRequest reliably parses application/x-www-form-urlencoded POST data.
  // Keep requests same-origin so a link cannot redirect the password elsewhere.
  async function authenticatedFetch(endpoint){
    const target=new URL(endpoint,window.location.href);
    if(target.origin!==window.location.origin)throw new Error('Ungültiger Freigabe-Endpunkt.');
    const response=await fetch(target.href,{
      method:'POST',
      headers:{
        'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8',
        'Accept':'application/json, application/pdf',
      },
      body:new URLSearchParams({password}),
      credentials:'same-origin',
      redirect:'error',
      cache:'no-store',
      referrerPolicy:'no-referrer',
    });
    if(!response.ok){
      if(response.status===429)throw new Error('Zu viele Versuche. Bitte später erneut probieren.');
      if(response.status===403)throw new Error('Zugriff verweigert (HTTP 403). Bitte die Freigabe erneut öffnen.');
      if(response.status===404)throw new Error('Link nicht verfügbar oder Passwort falsch (HTTP 404).');
      throw new Error('Abruf fehlgeschlagen (HTTP '+response.status+').');
    }
    return response;
  }
  function safeExternalLink(url,label){
    try{
      const u=new URL(url);
      if(u.protocol!=='https:')return null;
      const a=el('a','external-link',label);
      a.href=u.href;a.target='_blank';a.rel='noopener noreferrer';a.referrerPolicy='no-referrer';
      return a;
    }catch{return null}
  }
  const photoUrls=[];
  let allProducts=[];
  let currentScope='kataster';
  window.addEventListener('pagehide',()=>photoUrls.forEach(url=>URL.revokeObjectURL(url)));

  function error(message) {
    $('external-error').textContent=message;
    $('external-error').hidden=false;
  }
  function safeFilename(value) {
    return String(value||'Dokument').replace(/[^a-zA-Z0-9._-]/g,'_').slice(0,90);
  }
  async function download(id,name,button,isPhoto=false){
    button.disabled=true;
    try{
      if(!Number.isInteger(id)||id<=0)throw new Error('Datei-ID ungültig.');
      const response=await authenticatedFetch(filePattern.replace('__FILE__',String(id)));
      const blob=await response.blob();
      const local=URL.createObjectURL(blob);
      const a=document.createElement('a');
      a.href=local;
      a.download=isPhoto?safeFilename(name):'SDB-'+safeFilename(name)+'.pdf';
      document.body.append(a);a.click();a.remove();
      window.setTimeout(()=>URL.revokeObjectURL(local),30000);
    }catch(err){error(err.message)}finally{button.disabled=false}
  }
  async function previewPhoto(id,name,button,target){
    button.disabled=true;
    try{
      if(!Number.isInteger(id)||id<=0)throw new Error('Foto-ID ungültig.');
      const response=await authenticatedFetch(filePattern.replace('__FILE__',String(id)));
      const blob=await response.blob();
      if(!['image/jpeg','image/png','image/webp','image/heic','image/heif'].includes(blob.type)) {
        throw new Error('Fotoformat nicht unterstützt.');
      }
      const local=URL.createObjectURL(blob);photoUrls.push(local);
      const img=el('img','external-photo',undefined);
      img.src=local;img.alt=name||'Produktfoto';img.loading='lazy';
      target.replaceChildren(img);
      button.hidden=true;
    }catch(err){error(err.message)}finally{button.disabled=false}
  }
  function labeledValue(label,value){
    const dt=el('dt','',label),dd=el('dd','',value);
    return [dt,dd];
  }
  function fieldGroup(title,items){
    const selected=items.filter(([,value])=>value!==undefined&&value!==null&&String(value).trim()!=='');
    if(!selected.length)return null;
    const group=el('section','external-detail-section');
    group.append(el('h4','',title));
    const dl=el('dl','external-fields');
    for(const [label,value] of selected)dl.append(...labeledValue(label,value));
    group.append(dl);
    return group;
  }
  function linkGroup(p,includeSafety){
    const section=el('section','external-detail-section');
    section.append(el('h4','','Quellen und Dokumente'));
    const links=el('div','external-actions');
    for(const [url,label] of [[p.source_url,'Herstellerseite'],[p.pmb_url,'Produktmerkblatt']]){
      if(url){const a=safeExternalLink(url,label);if(a)links.append(a)}
    }
    if(includeSafety&&p.hazardous){
      if(p.sds_url){const a=safeExternalLink(p.sds_url,'SDB beim Hersteller');if(a)links.append(a)}
      if(Number.isInteger(p.sds_id)&&p.sds_id>0){
        const b=el('button','external-download','Sicherheitsdatenblatt herunterladen');
        b.type='button';b.addEventListener('click',()=>download(p.sds_id,p.name,b));
        links.append(b);
      }
    }
    if(links.childElementCount)section.append(links);
    const photos=Array.isArray(p.photos)?p.photos:[];
    if(photos.length){
      const group=el('div','external-photo-group');
      group.append(el('h5','',photos.length===1?'Produktfoto':'Produktfotos ('+photos.length+')'));
      for(const photo of photos){
        if(!Number.isInteger(photo.id))continue;
        const photoBlock=el('div','external-photo-row');
        const filename=String(photo.filename||'Produktfoto');
        photoBlock.append(el('span','external-muted',filename));
        const controls=el('div','external-actions');
        const preview=el('div','external-photo-preview');
        const show=el('button','external-secondary-button','Ansehen');
        show.type='button';show.addEventListener('click',()=>previewPhoto(photo.id,filename,show,preview));
        const save=el('button','external-secondary-button','Herunterladen');
        save.type='button';save.addEventListener('click',()=>download(photo.id,filename,save,true));
        controls.append(show,save);
        photoBlock.append(controls,preview);
        group.append(photoBlock);
      }
      section.append(group);
    }
    return section.childElementCount>1?section:null;
  }
  function renderProduct(p,scope){
    const includeSafety=scope!=='lager';
    const card=el('article','external-card');
    const head=el('div','external-product-heading');
    head.append(el('h3','',p.name||'Unbenanntes Produkt'));
    if(p.hazardous)head.append(el('span','external-badge','Gefahrstoff'));
    card.append(head);
    const subtitle=[p.manufacturer,p.category,kindNames[p.material_kind]||kindNames.unclassified].filter(Boolean);
    if(subtitle.length)card.append(el('p','external-muted',subtitle.join(' · ')));
    const rows=Array.isArray(p.stock)?p.stock:[];
    const packs=rows.reduce((sum,r)=>sum+Number(r.packs||0),0);
    const size=Number(p.pack_size||0);
    const stock=el('div','external-stock');
    const amount=size>0?format(packs*size)+' '+(p.unit||''):'';
    stock.append(el('strong','','Bestand: '+format(packs)+' Gebinde'+(amount?' · '+amount:'')));
    const occupied=rows.filter(r=>Number(r.packs)>0);
    stock.append(el('p','',occupied.length?occupied.map(r=>r.location).join(' · '):'Kein aktueller Bestand hinterlegt'));
    card.append(stock);

    const details=el('details','external-product-details');
    const summary=el('summary','external-detail-toggle','Produktdetails anzeigen');
    details.append(summary);
    const content=el('div','external-detail-content');
    const general=fieldGroup('Stammdaten',[
      ['Produktname',p.name],['Hersteller',p.manufacturer],['Artikelnummer',p.article],
      ['EAN / GTIN',p.ean],['Materialart',kindNames[p.material_kind]||kindNames.unclassified],
      ['Kategorie',p.category],['Einsatzbereich',p.use_area],
      ['Gebindegröße',size>0?format(size)+' '+(p.unit||''):''],
      ['Gefahrstoff',p.hazardous?'Ja':'Nein'],
      ['Lagerbedingungen / Hinweise',p.storage_note],
    ]);
    if(general)content.append(general);
    const store=el('section','external-detail-section');
    store.append(el('h4','','Lagerorte und Bestände'));
    if(rows.length){
      const list=el('div','external-stock-list');
      for(const r of rows){
        const row=el('div','external-stock-row');
        row.append(el('span','',r.location||'Unbekannter Lagerort'));
        const qty=el('strong','',format(Number(r.packs||0))+' Gebinde'+(size>0?' · '+format(Number(r.packs||0)*size)+' '+(p.unit||''):''));
        row.append(qty);list.append(row);
      }
      store.append(list);
    }else{store.append(el('p','external-muted','Kein Bestand für dieses Produkt hinterlegt.'))}
    content.append(store);
    if(includeSafety&&p.hazardous){
      const safety=fieldGroup('Gefahrstoffangaben',[
        ['UFI',p.ufi],['Einstufung',p.classification],['GHS-Codes',p.ghs],
        ['Signalwort',p.signal],['H- / EUH-Sätze',p.h_statements],
        ['Sicherheitsdatenblatt vom',p.sds_date],['Fachlich geprüft am',p.checked_at],
      ]);
      if(safety)content.append(safety);
    }
    const documents=linkGroup(p,includeSafety);
    if(documents)content.append(documents);
    details.append(content);card.append(details);
    return card;
  }
  function renderProducts(){
    const q=String($('external-search')?.value||'').trim().toLocaleLowerCase('de');
    const location=String($('external-location-filter')?.value||'');
    const filtered=allProducts.filter(p=>{
      if(location&&!(p.stock||[]).some(row=>String(row.location_id)===location))return false;
      if(!q)return true;
      return [p.name,p.manufacturer,p.article,p.ean,p.category,p.use_area,p.material_kind]
        .some(value=>String(value||'').toLocaleLowerCase('de').includes(q));
    });
    const area=$('external-products');area.replaceChildren();
    for(const p of filtered)area.append(renderProduct(p,currentScope));
    $('external-empty').hidden=filtered.length>0;
    $('external-empty').textContent=allProducts.length?'Keine Produkte für diese Filter.':'Keine Produkte für diese Freigabe vorhanden.';
    $('external-count').textContent=filtered.length===allProducts.length
      ?allProducts.length+' Produkte'
      :filtered.length+' von '+allProducts.length+' Produkten';
  }
  function renderLocations(locations){
    const area=$('external-locations'),select=$('external-location-filter');
    area.replaceChildren();
    select.replaceChildren(new Option('Alle Lagerorte',''));
    for(const loc of locations){
      area.append(el('span','external-location-tag',loc.name));
      select.add(new Option(loc.name,String(loc.id)));
    }
    $('external-locations-count').textContent=locations.length+' Lagerorte';
    if(!locations.length)area.append(el('p','external-muted','Keine Lagerorte in diesem Bereich hinterlegt.'));
  }
  $('external-search').addEventListener('input',renderProducts);
  $('external-location-filter').addEventListener('change',renderProducts);
  $('external-password-toggle').addEventListener('click',()=>{
    const field=$('external-password'),button=$('external-password-toggle');
    const visible=field.type==='password';
    field.type=visible?'text':'password';
    button.title=visible?'Passwort verbergen':'Passwort anzeigen';
    button.setAttribute('aria-pressed',String(visible));
    button.setAttribute('aria-label',visible?'Passwort verbergen':'Passwort anzeigen');
  });
  $('external-unlock').addEventListener('submit',async event=>{
    event.preventDefault();
    const button=event.currentTarget.querySelector('button[type="submit"]');
    button.disabled=true;$('external-error').hidden=true;
    try{
      password=$('external-password').value;
      const response=await authenticatedFetch(api);
      const data=await response.json();
      const products=Array.isArray(data.products)?data.products:[];
      const content=$('external-products');content.replaceChildren();
      for(const p of products)content.append(renderProduct(p,data.scope));
      $('external-label').textContent=data.label||'Freigabe';
      const expiry=new Date(Number(data.expires_at)*1000);
      $('external-info').textContent=(scopeNames[data.scope]||'Freigabe')+' · gültig bis '+expiry.toLocaleDateString('de-DE');
      $('external-count').textContent=products.length+' Produkte';
      $('external-empty').hidden=products.length>0;
      $('external-content').hidden=false;
      $('external-password').value='';
      $('external-password').type='password';
      $('external-password-toggle').setAttribute('aria-pressed','false');
      $('external-password-toggle').title='Passwort anzeigen';
      $('external-password-toggle').setAttribute('aria-label','Passwort anzeigen');
      $('external-auth').hidden=true;
    }catch(error){$('external-error').textContent=error.message;$('external-error').hidden=false;$('external-content').hidden=true}
    finally{button.disabled=false}
  });
})();
