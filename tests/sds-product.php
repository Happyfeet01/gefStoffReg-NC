<?php
declare(strict_types=1);
namespace OCP\AppFramework { class Controller {} }
namespace {
    require __DIR__ . '/../lib/Controller/InventoryController.php';
    $class = new ReflectionClass(OCA\Gefahrstoffkataster\Controller\InventoryController::class);
    $controller = $class->newInstanceWithoutConstructor();
    $validate = $class->getMethod('validateProduct');
    $input = ['name' => 'Test', 'unit' => 'l', 'pack_size' => 1, 'hazardous' => true];
    $review = ['status' => 'unclear', 'filename' => 'test.pdf', 'sha256' => str_repeat('a', 64), 'reviewed_at' => '2026-10-15T10:00:00.000Z'];
    $data = $validate->invoke($controller, $input + ['sds_review' => $review]);
    if (($data['sds_review'] ?? null) !== $review || $data['hazardous'] !== true) throw new RuntimeException('Review not preserved or hazard changed');
    $data = $validate->invoke($controller, $input);
    if (isset($data['sds_review'])) throw new RuntimeException('Unexpected review');
    foreach (['status' => 'safe', 'sha256' => 'invalid', 'filename' => [], 'reviewed_at' => 'yesterday'] as $key => $bad) {
        $changed = $review; $changed[$key] = $bad;
        try { $validate->invoke($controller, $input + ['sds_review' => $changed]); }
        catch (InvalidArgumentException $e) { continue; }
        throw new RuntimeException('Invalid review accepted: ' . $key);
    }
    echo "SDB product validation and review persistence cases passed\n";
}
