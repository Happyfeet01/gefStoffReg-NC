// No-build regression guard for a practical, non-technical external inventory UI.
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=rel=>fs.readFileSync(path.join(__dirname,'..',rel),'utf8');
const js=read('js/external-share.js');
const css=read('css/external-share.css');
const markup=read('templates/external-share.php');
new Function(js);
assert.match(js,/const row=el\('div','external-field-row'\)/);
assert.match(js,/el\('span','external-field-label',label\)/);
assert.match(js,/el\('span','external-field-value',value\)/);
assert.doesNotMatch(js,/el\('dt','',label\)/);
assert.doesNotMatch(js,/el\('dd','',value\)/);
assert.match(js,/fieldGroup\('Stammdaten'/);
assert.match(js,/Witty-Pool|Produktname/);
const quick=js.indexOf("if(stockEditor){\n      const quick=el('details','external-quick-booking')");
const details=js.indexOf("const details=el('details','external-product-details')");
assert.ok(quick!==-1&&details>quick,'Stock action must appear before technical details');
assert.match(js,/el\('summary','external-quick-booking-toggle','Bestand ändern'\)/);
assert.match(js,/el\('summary','external-detail-toggle','Weitere Produktinfos'\)/);
assert.match(js,/select\.value=String\(preferred\.location_id\)/);
assert.match(js,/bookingFeedback=\{productId:p\.id/);
assert.match(js,/el\('p','external-booking-inline-error'\)/);
assert.match(js,/Ältere Freigabe: Einige Angaben und Dateien sind hier nicht verfügbar/);
assert.doesNotMatch(js,/Dies ist eine ältere, eingeschränkte Freigabe/);
for(const token of [
 '.external-detail-content > .external-detail-section',
 '.external-field-grid','.external-field-row','.external-field-label',
 '.external-field-value','.external-quick-booking-toggle',
 '.external-booking-feedback','.external-booking-inline-error'
])assert.ok(css.includes(token), 'Missing style '+token);
assert.match(css,/background:rgba\(255,255,255,\.83\)/);
assert.match(css,/@media \(max-width:600px\)/);
assert.match(markup,/id="external-products"/);
console.log('Practical inventory fields, milky cards, quick booking, and inline feedback passed');
