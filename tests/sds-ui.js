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
  'product-form':{elements:Object.fromEntries(['name','manufacturer','classification','signal','ghs','h_statements','hazardous'].map(key=>[key,{value:'existing '+key,checked:true}]))},
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
    return {ok:true,json:async()=>({status,filename:'SDB.pdf',sha256:'a'.repeat(64),ocr_pages:[],page_limit:4,notice:'Check original',
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
  console.log('SDB UI confirmation, existing file, stale product and review-status cases passed');
})().catch(error=>{console.error(error);process.exitCode=1});
