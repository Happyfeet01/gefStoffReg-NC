<?php
declare(strict_types=1);

require __DIR__ . '/../lib/Service/ExternalShareProjection.php';

use OCA\Gefahrstoffkataster\Service\ExternalShareProjection as View;

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$rows = [
    ['id'=>1,'details'=>json_encode([
        'name'=>'Floc 5','manufacturer'=>'Witty','article'=>'A-42',
        'ean'=>'4001234567890','category'=>'Wasserpflege','use_area'=>'Freibad',
        'material_kind'=>'chemical','hazardous'=>true,'pack_size'=>35,'unit'=>'kg',
        'storage_note'=>'Trocken lagern','ufi'=>'ABC-123','classification'=>'Skin Corr. 1A',
        'ghs'=>'GHS05','signal'=>'Gefahr','h_statements'=>'H314','sds_date'=>'2026-09-01',
        'checked_at'=>'2026-10-01','sds_url'=>'https://example.test/sdb.pdf',
        'source_url'=>'https://example.test/product','pmb_url'=>'https://example.test/product.pdf',
        'sds_review'=>['status'=>'private reviewer notes'],'secret_internal_field'=>'must never leak',
    ])],
    ['id'=>2,'details'=>json_encode([
        'name'=>'Papierhandtücher','hazardous'=>false,'material_kind'=>'consumable',
        'pack_size'=>200,'unit'=>'Stück','category'=>'Sanitär',
    ])],
];
$places = [['id'=>10,'name'=>'Chemieraum'],['id'=>20,'name'=>'Putzraum'],['id'=>30,'name'=>'Unbenutzt']];
$stocks = [
    ['product_id'=>1,'location_id'=>10,'milli'=>2500],
    ['product_id'=>1,'location_id'=>20,'milli'=>1000],
    ['product_id'=>2,'location_id'=>20,'milli'=>4000],
];
$files = [
    ['id'=>11,'product_id'=>1,'kind'=>'sds','filename'=>'altes-sdb.pdf'],
    ['id'=>12,'product_id'=>1,'kind'=>'sds','filename'=>'aktuelles-sdb.pdf'],
    ['id'=>13,'product_id'=>1,'kind'=>'photo','filename'=>'etikett.png'],
    ['id'=>14,'product_id'=>2,'kind'=>'photo','filename'=>'papier.jpg'],
    ['id'=>15,'product_id'=>2,'kind'=>'sds','filename'=>'bad.pdf'],
    ['id'=>16,'product_id'=>1,'kind'=>'other','filename'=>'internal.txt'],
];
$hazard = json_decode($rows[0]['details'], true);
$plain = json_decode($rows[1]['details'], true);

$kataster = View::build($rows,$places,$stocks,$files,'kataster');
check(count($kataster['products']) === 1, 'Kataster leaked nonhazardous product');
$p = $kataster['products'][0];
check(count($p['stock']) === 2 && $p['stock'][0]['packs'] === 2.5, 'Kataster stock missing');
check($p['pack_size'] === 35.0 && $p['stock'][1]['location'] === 'Putzraum', 'Kataster stock details missing');
check(count($kataster['locations']) === 2, 'Kataster must not disclose unrelated locations');
check($p['article'] === 'A-42' && $p['ufi'] === 'ABC-123' && $p['sds_id'] === 12, 'Kataster product details missing');
check(count($p['photos']) === 1 && $p['photos'][0]['id'] === 13, 'Kataster photo missing');
check(!isset($p['sds_review']) && !isset($p['secret_internal_field']), 'Private source fields exposed');

$lager = View::build($rows,$places,$stocks,$files,'lager');
check(count($lager['products']) === 2 && count($lager['locations']) === 3, 'Warehouse view incomplete');
check($lager['products'][1]['stock'][0]['packs'] === 4, 'Warehouse stock missing');
check(!isset($lager['products'][0]['ufi']) && !isset($lager['products'][0]['sds_id'])
    && !isset($lager['products'][0]['sds_url']), 'Warehouse-only link leaked safety fields');
check(count($lager['products'][1]['photos']) === 1, 'Warehouse photo missing');

$both=View::build($rows,$places,$stocks,$files,'both');
check(count($both['products']) === 2 && $both['products'][0]['sds_id'] === 12, 'Combined scope incomplete');
check(!isset($both['products'][1]['ufi']), 'Non-hazardous products must not get fabricated safety metadata');
check(View::build($rows,$places,$stocks,$files,'invalid')['products'] === [], 'Invalid scope allowed');

$find = static function(int $id) use ($files): array {
    foreach ($files as $f) if ($f['id'] === $id) return $f;
    throw new RuntimeException('Bad fixture');
};
check(View::allowsFile($find(12),$hazard,$files,'kataster'), 'Latest SDB unavailable in Kataster');
check(View::allowsFile($find(12),$hazard,$files,'both'), 'Latest SDB unavailable in combined view');
check(!View::allowsFile($find(12),$hazard,$files,'lager'), 'Warehouse guest could download SDB');
check(!View::allowsFile($find(11),$hazard,$files,'kataster'), 'Old SDB accessible');
check(!View::allowsFile($find(15),$plain,$files,'both'), 'Nonhazardous product SDB accessible');
check(View::allowsFile($find(13),$hazard,$files,'kataster'), 'Hazard product photo unavailable');
check(!View::allowsFile($find(14),$plain,$files,'kataster'), 'Kataster guest accessed unrelated photo');
check(View::allowsFile($find(14),$plain,$files,'lager'), 'Warehouse product photo unavailable');
check(!View::allowsFile($find(16),$hazard,$files,'both'), 'Unknown file kind accessible');
check(!View::allowsFile($find(13),$hazard,$files,'invalid'), 'Invalid scope downloaded photo');

$oldKataster = View::legacy($kataster,'kataster');
check(!isset($oldKataster['products'][0]['stock'])
    && !isset($oldKataster['products'][0]['photos'])
    && $oldKataster['locations'] === [], 'Legacy Kataster link unexpectedly gained inventory access');
check($oldKataster['products'][0]['sds_id'] === 12, 'Legacy Kataster SDB missing');
$oldLager = View::legacy($lager,'lager');
check(isset($oldLager['products'][0]['stock'])
    && !isset($oldLager['products'][0]['photos'])
    && !isset($oldLager['products'][0]['ufi']), 'Legacy warehouse scope expanded');
check(!isset($oldLager['products'][0]['article']), 'Legacy link gained new master fields');

echo "External read-only scope, full stocks, locations, documents and field allowlist passed\n";
