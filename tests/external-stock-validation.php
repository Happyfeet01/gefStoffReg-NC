<?php
declare(strict_types=1);
require __DIR__ . '/../lib/Service/ExternalShareProjection.php';
require __DIR__ . '/../lib/Service/ExternalStockBookingService.php';
use OCA\Gefahrstoffkataster\Service\ExternalStockBookingService as Stock;
function insistStock(bool $ok,string $msg):void { if(!$ok) throw new RuntimeException($msg); }
$input=[
  'product_id'=>17,'location_id'=>2,'action'=>'add','packs'=>1.125,
  'expected_milli'=>2500,'note'=>'Lieferung für Freibad',
];
$normalized=Stock::normalize($input);
insistStock($normalized['milli']===1125,'Wrong stock conversion');
insistStock($normalized['expected_milli']===2500,'Expected stock altered');
insistStock($normalized['note']==='Lieferung für Freibad','Note altered');
foreach(['add','remove','set'] as $action){
  $normal=Stock::normalize(array_replace($input,['action'=>$action,'packs'=>$action==='set'?0:0.001]));
  insistStock($normal['milli'] === ($action==='set'?0:1), 'Allowed action incorrectly converted');
}

$invalid=[
 ['product_id'=>0],['location_id'=>0],['action'=>'delete'],
 ['packs'=>-1],['packs'=>'NaN'],['packs'=>1000001],
 ['packs'=>0,'action'=>'add'],['packs'=>0,'action'=>'remove'],
 ['expected_milli'=>-1],['expected_milli'=>'not-current'],
 ['note'=>str_repeat('x',201)]
];
foreach($invalid as $change){
  try{Stock::normalize(array_replace($input,$change));throw new LogicException('Invalid booking accepted');}
  catch(InvalidArgumentException $e){/* expected */}
}
$danger=['hazardous'=>true];
$ordinary=['hazardous'=>false];
insistStock(Stock::allowsProduct($danger,'kataster'),'Kataster cannot book hazardous stock');
insistStock(!Stock::allowsProduct($ordinary,'kataster'),'Kataster can edit unrelated product');
insistStock(Stock::allowsProduct($ordinary,'lager'),'Warehouse cannot edit normal stock');
insistStock(Stock::allowsProduct($danger,'both'),'Combined scope cannot book hazardous stock');
insistStock(!Stock::allowsProduct($danger,'untrusted'),'Invalid scope accepted');
echo "External stock booking validation, quantity precision and scope checks passed\n";
