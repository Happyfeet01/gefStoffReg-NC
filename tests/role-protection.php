<?php
declare(strict_types=1);
namespace OCP\AppFramework { class Controller {} }
namespace {
    require __DIR__ . '/../lib/Controller/InventoryController.php';
    $klass = new \ReflectionClass(\OCA\Gefahrstoffkataster\Controller\InventoryController::class);
    $create = $klass->getMethod('stockEditorMayCreate');
    $update = $klass->getMethod('stockEditorUpdate');
    $base = ['name'=>'Toilettenpapier','material_kind'=>'consumable','manufacturer'=>'', 'article'=>'', 'ean'=>'',
        'category'=>'Sanitär','use_area'=>'', 'pack_size'=>8.0, 'unit'=>'Stück', 'hazardous'=>false,
        'ufi'=>'','classification'=>'','ghs'=>'','signal'=>'','h_statements'=>'','storage_note'=>'',
        'source_url'=>'','sds_url'=>'','pmb_url'=>'','sds_date'=>'','checked_at'=>''];
    if (!$create->invoke(null, $base)) throw new \RuntimeException('Could not create standard consumable');
    foreach ([['material_kind'=>'chemical'],['hazardous'=>true],['sds_url'=>'https://example.com/sds.pdf'],['classification'=>'Skin Corr. 1A']] as $changes) {
        if ($create->invoke(null, array_replace($base, $changes))) throw new \RuntimeException('Restricted product created');
    }
    $original = $base;
    $original['sds_review'] = ['status'=>'unclear','sha256'=>str_repeat('a',64)];
    $changed = array_replace($base, ['name'=>'Papierhandtücher','category'=>'Waschraum']);
    $merged = $update->invoke(null, $original, $changed);
    if ($merged === null || $merged['name'] !== 'Papierhandtücher' || ($merged['sds_review'] ?? null) !== $original['sds_review'])
        throw new \RuntimeException('Safe field update discarded protected metadata');
    foreach ([['hazardous'=>true],['classification'=>'H314'],['sds_url'=>'https://example.com/test.pdf']] as $changes) {
        if ($update->invoke(null, $original, array_replace($changed, $changes)) !== null) throw new \RuntimeException('Safety change was accepted');
    }
    if ($update->invoke(null, array_replace($base, ['hazardous'=>true]), $changed) !== null) throw new \RuntimeException('Stock editor changed hazardous product');
    echo "Stock-editor safe fields and protected safety metadata passed\n";
}
