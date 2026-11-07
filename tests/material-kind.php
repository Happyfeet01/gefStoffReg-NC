<?php
declare(strict_types=1);
namespace OCP\AppFramework { class Controller {} }
namespace {
    require __DIR__ . '/../lib/Controller/InventoryController.php';
    $controller = (new ReflectionClass(OCA\Gefahrstoffkataster\Controller\InventoryController::class))->newInstanceWithoutConstructor();
    $validate = (new ReflectionClass($controller))->getMethod('validateProduct');
    $base = ['name'=>'Papierhandtücher', 'unit'=>'Stück', 'pack_size'=>100, 'hazardous'=>false];
    $legacy = $validate->invoke($controller, $base);
    if ($legacy['material_kind'] !== 'unclassified') throw new RuntimeException('Legacy products must stay unclassified');
    $consumable = $validate->invoke($controller, $base + ['material_kind'=>'consumable']);
    if ($consumable['material_kind'] !== 'consumable' || $consumable['hazardous']) throw new RuntimeException('Consumable was misclassified');
    $hazard = $validate->invoke($controller, array_replace($base, ['material_kind'=>'chemical', 'hazardous'=>true]));
    if ($hazard['material_kind'] !== 'chemical' || !$hazard['hazardous']) throw new RuntimeException('Chemical category not preserved');
    // Safety labels are an independent field, never silently derived from material kind.
    $flagged = $validate->invoke($controller, array_replace($base, ['material_kind'=>'consumable', 'hazardous'=>true]));
    if (!$flagged['hazardous']) throw new RuntimeException('Hazard marking was silently cleared');
    foreach (['invalid', '', 123, ['consumable']] as $value) {
        try {
            $validate->invoke($controller, array_replace($base, ['material_kind'=>$value]));
            throw new RuntimeException('Invalid material kind accepted');
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Invalid material kind accepted') throw $e;
        }
    }
    echo "Material kind validation, legacy fallback and hazard independence passed\n";
}
