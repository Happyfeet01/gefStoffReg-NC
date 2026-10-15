// Exercise the review flow without a Nextcloud runtime or external libraries.
const {readFileSync}=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
class Element {
  constructor(tag='',text=''){this.tag=tag;this.text=text;this.children=[];this.checked=false}
  append(...children){this.children.push(...children)}
  replaceChildren(...children){this.children=children}
  setAttribute(){}
  closest(){return this}
  after(element){elements[element.id]=element}
}
const elements={
  'sds-file':Object.assign(new Element(),{files:[]}),
  'product-form':{elements:Object.fromEntries(['name','manufacturer','classification','signal','ghs','h_statements','hazardous','sds_url'].map(key=>[key,{value:key==='sds_url'?'':'existing '+key,checked:true}]))},
};
const form=elements['product-form'];
let status='classified',lastRequest;
const context={
  $,FormData,Date,console,
  state:{current:{sds_id:17}},
  el:(tag,css,text)=>new Element(tag,text),
  buttonElement:(text,action)=>Object.assign(new Element('button',text),{action}),
  document:{createTextNode:text=>new Element('text',text)},
  notice:()=>{},url:path=>path,OC:{requestToken:'test-token'},
  fetch:async(path,options)=>{
    lastRequest={path,...options};
    return {ok:true,json:async()=>({status,filename:'SDB.pdf',sha256:'a'.repeat(64),ocr_pages:[],page_limit:4,draft_token:options.body.get('source_url')?'test-draft':null,notice:'Check original',
      suggestions:[{field:'classification',value:'Skin Corr. 1A',evidence:[{page:1,text:'Skin Corr. 1A H314'}]}],evidence:[]})};
  },
};
function $(id){return elements[id]}
function descendants(element){return [element,...element.children.flatMap(descendants)]}
function find(text){return descendants(elements['sds-analysis']).find(element=>element.text===text)}
vm.createContext(context);
const source=readFileSync(__dirname+'/../js/app.js','utf8');
vm.runInContext(source.slice(source.indexOf('let sdsAnalysisGeneration='),source.indexOf('function normalText(')),context);
(async()=>{
  context.setupSdsAnalysis();
  await find('SDB auswerten').action();
  assert.equal(lastRequest.body.get('file_id'),'17');
  assert.equal(lastRequest.headers.requesttoken,'test-token');
  assert.equal(form.elements.classification.value,'existing classification');
  find('Geprüften Vorschlag übernehmen').action();
  assert.equal(form.elements.classification.value,'existing classification','Unconfirmed write');
  const checkbox=descendants(elements['sds-analysis']).find(element=>element.type==='checkbox');checkbox.checked=true;
  find('Geprüften Vorschlag übernehmen').action();
  assert.equal(form.elements.classification.value,'Skin Corr. 1A');
  assert.equal(form.sdsReview.sha256,'a'.repeat(64));
  form.elements.name.value='Another product';form.elements.classification.value='unchanged';
  find('Geprüften Vorschlag übernehmen').action();
  assert.equal(form.elements.classification.value,'unchanged','Stale proposal accepted');
  for(const value of ['not_classified','unclear']){
    status=value;context.setupSdsAnalysis();await find('SDB auswerten').action();
    assert.equal(form.elements.hazardous.checked,true,'Negative result cleared hazard flag');
    assert.equal(find('Nach Prüfung als Gefahrstoff markieren'),undefined);
    descendants(elements['sds-analysis']).find(element=>element.type==='checkbox').checked=true;
    find('Ergebnis als Prüfvermerk übernehmen').action();assert.equal(form.sdsReview.status,value);
  }
  elements['sds-file'].onchange();assert.equal(form.sdsReview,null,'Changed PDF retained review');
  context.state.current={sds_id:null};form.elements.sds_url.value='https://manufacturer.example/sds.pdf';
  context.setupSdsAnalysis();await find('SDB auswerten').action();
  assert.equal(lastRequest.body.get('source_url'),form.elements.sds_url.value,'Link-only product not supported');
  assert.equal(form.sdsDraft.token,'test-draft','Downloaded PDF not staged');
  assert.equal(find('Original-SDB öffnen ↗').href,'/api/sds-preview/test-draft','Preview does not use exact local PDF');
  context.state.current={sds_id:17,sds_review:{status:'classified',sha256:'b'.repeat(64),filename:'old.pdf',reviewed_at:'2026-10-01T10:00:00.000Z'}};context.setupSdsAnalysis();await find('SDB auswerten').action();
  assert.equal(form.sdsReview,null,'Review for different PDF remained valid');
  assert.equal(lastRequest.body.get('file_id'),'17','Existing copy should be used by default');
  await find('SDB vom Link aktualisieren').action();
  assert.equal(lastRequest.body.get('source_url'),form.elements.sds_url.value,'Refresh did not use manufacturer link');
  assert.equal(form.elements.sds_url.value,'https://manufacturer.example/sds.pdf','Manufacturer link lost');
  form.elements.sds_url.oninput();assert.equal(form.sdsDraft,null,'Changed link retained draft');
  console.log('SDB UI confirmation, existing file, stale product and review-status cases passed');
})().catch(error=>{console.error(error);process.exitCode=1});
