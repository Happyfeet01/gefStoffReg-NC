// Test the actual browser guest password request without a Nextcloud installation.
// Mocks only DOM and fetch, and checks that the exact password reaches URLSearchParams.
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(__dirname + '/../js/external-share.js', 'utf8');

const handlers = {};
const elements = {};
const make = id => ({
  id,
  hidden: false,
  value: '',
  type: id === 'external-password' ? 'password' : '',
  disabled: false,
  textContent: '',
  attributes: {},
  dataset: id === 'gsk-external' ? {
    apiUrl: '/index.php/apps/gefahrstoffkataster/shared/abc/data',
    filePattern: '/index.php/apps/gefahrstoffkataster/shared/abc/file/__FILE__',
  } : {},
  addEventListener(event, handler) {handlers[id + ':' + event] = handler;},
  setAttribute(k,v) {this.attributes[k] = v;},
  getAttribute(k) {return this.attributes[k];},
  querySelector(selector) {
    assert.equal(selector, 'button[type="submit"]', 'Password toggle must not be the submit control');
    return elements.submit;
  },
  replaceChildren() {},
  add() {},
  append() {},
  childElementCount:0,
});
for (const id of ['gsk-external','external-password-toggle','external-password','external-unlock',
  'external-error','external-auth','external-content','external-products','external-label',
  'external-info','external-count','external-empty','external-search',
  'external-location-filter','external-locations','external-locations-count',
  'external-location-overview','external-location-filter-wrap','external-readonly-explainer']) elements[id] = make(id);
elements.submit=make('submit');
const calls=[];
const context={
  document:{getElementById:id=>elements[id] || null, createElement:tag=>make(tag)},
  window:{location:{href:'https://cloud.example.test/index.php/apps/gefahrstoffkataster/shared/abc',origin:'https://cloud.example.test'},addEventListener(){}},
  URL, URLSearchParams, Intl, Date, console, Option:class {constructor(label,value){this.text=label;this.value=value}},
  fetch:async (url, options)=>{
    calls.push({url,options});
    return {ok:true,status:200,json:async ()=>({
      label:'Hygieneinstitut',scope:'kataster',expires_at:1800000000,products:[],
    })};
  },
};
vm.runInNewContext(source,context,{filename:'external-share.js'});
assert.ok(handlers['external-password-toggle:click']);
assert.ok(handlers['external-unlock:submit']);
handlers['external-password-toggle:click']();
assert.equal(elements['external-password'].type,'text','Password must be visible when requested');
assert.equal(elements['external-password-toggle'].attributes['aria-pressed'],'true');
handlers['external-password-toggle:click']();
assert.equal(elements['external-password'].type,'password','Password must be hidden again');
elements['external-password'].value='correct/Pass wort+ä?%';
(async()=>{
  await handlers['external-unlock:submit']({preventDefault(){},currentTarget:elements['external-unlock']});
  assert.equal(calls.length,1);
  const {url,options}=calls[0];
  assert.equal(url,'https://cloud.example.test/index.php/apps/gefahrstoffkataster/shared/abc/data');
  assert.equal(options.method,'POST');
  assert.equal(options.credentials,'same-origin');
  assert.match(options.headers['Content-Type'],/^application\/x-www-form-urlencoded/);
  assert.equal(new URLSearchParams(options.body).get('password'),'correct/Pass wort+ä?%');
  assert.equal(elements.submit.disabled,false,'Submit button must be re-enabled after response');
  assert.equal(elements['external-content'].hidden,false,'Products must become visible after login');
  assert.equal(elements['external-auth'].hidden,true,'Password card must close after login');
  console.log('Guest password transport, special characters and visibility toggles passed');
})().catch(err=>{console.error(err);process.exitCode=1});
