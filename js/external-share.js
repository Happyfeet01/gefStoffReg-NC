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
  async function download(id,name,button){
    button.disabled=true;
    try{
      if(!Number.isInteger(id)||id<=0)throw new Error('Datei-ID ungültig.');
      const url=filePattern.replace('__FILE__',String(id));
      const response=await authenticatedFetch(url);
      const blob=await response.blob();
      const local=URL.createObjectURL(blob);
      const a=document.createElement('a');
      a.href=local;a.download='SDB-'+String(name).replace(/[^a-zA-Z0-9_-]/g,'_').slice(0,60)+'.pdf';
      document.body.append(a);a.click();a.remove();
      window.setTimeout(()=>URL.revokeObjectURL(local),30000);
    }catch(err){$('external-error').textContent=err.message;$('external-error').hidden=false}
    finally{button.disabled=false}
  }
  function renderProduct(p,scope){
    const card=el('article','external-card');
    const title=el('h3','',p.name||'Unbenanntes Produkt');
    card.append(title);
    const subtitle=[p.manufacturer,p.category,kindNames[p.material_kind]||kindNames.unclassified].filter(Boolean);
    if(subtitle.length)card.append(el('p','external-muted',subtitle.join(' · ')));
    if(p.hazardous)card.append(el('span','external-badge','Gefahrstoff'));
    if(scope!=='kataster'){
      const stock=el('div','external-stock');
      const qty=(p.stock||[]).reduce((sum,item)=>sum+Number(item.packs||0),0);
      stock.append(el('strong','',format(qty*Number(p.pack_size||0))+' '+(p.unit||'')));
      for(const item of p.stock||[])stock.append(el('p','',item.location+': '+format(item.packs)+' Gebinde'));
      card.append(stock);
    }
    if(scope!=='lager'&&p.hazardous){
      const safety=el('dl','external-safety');
      for(const [label,value] of [['Einstufung',p.classification],['GHS',p.ghs],['Signalwort',p.signal],['H-Sätze',p.h_statements],['SDB-Datum',p.sds_date]]){
        if(!value)continue;
        const dt=el('dt','',label),dd=el('dd','',value);
        safety.append(dt,dd);
      }
      card.append(safety);
      const links=el('div','external-actions');
      if(Number.isInteger(p.sds_id)&&p.sds_id>0){
        const b=el('button','external-download','SDB als PDF herunterladen');
        b.type='button';b.addEventListener('click',()=>download(p.sds_id,p.name,b));
        links.append(b);
      }
      if(p.sds_url){const a=safeExternalLink(p.sds_url,'SDB beim Hersteller');if(a)links.append(a)}
      if(links.childElementCount)card.append(links);
    }
    return card;
  }
  $('external-password-toggle').addEventListener('click',()=>{
    const field=$('external-password'),button=$('external-password-toggle');
    const visible=field.type==='password';
    field.type=visible?'text':'password';
    button.textContent=visible?'Verbergen':'Anzeigen';
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
      $('external-password-toggle').textContent='Anzeigen';
      $('external-password-toggle').setAttribute('aria-label','Passwort anzeigen');
      $('external-auth').hidden=true;
    }catch(error){$('external-error').textContent=error.message;$('external-error').hidden=false;$('external-content').hidden=true}
    finally{button.disabled=false}
  });
})();
