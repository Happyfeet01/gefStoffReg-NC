<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

/**
 * Explicit projection of public, read-only fields.
 * The raw product JSON and internal API responses are never passed to guests.
 * The scope controls the visible product set and safety documents, not whether
 * an otherwise permitted product's current stock is displayed.
 */
final class ExternalShareProjection {
    private const SCOPES = ['kataster','lager','both'];

    public static function allowsProduct(array $product, string $scope): bool {
        return in_array($scope, self::SCOPES, true)
            && ($scope !== 'kataster' || !empty($product['hazardous']));
    }

    public static function allowsFile(array $file, array $product, array $files, string $scope): bool {
        if (!self::allowsProduct($product, $scope)) return false;
        if ($file['kind'] === 'photo') return true;
        if ($file['kind'] !== 'sds' || $scope === 'lager' || empty($product['hazardous'])) return false;
        // Only the newest SDB is linked in the public product details.
        foreach ($files as $candidate) {
            if (($candidate['kind'] ?? null) === 'sds'
                && (int)($candidate['product_id'] ?? 0) === (int)$file['product_id']
                && (int)($candidate['id'] ?? 0) > (int)$file['id']) return false;
        }
        return true;
    }

    public static function build(array $products, array $places, array $stocks, array $files, string $scope): array {
        if (!in_array($scope, self::SCOPES, true)) return ['products'=>[], 'locations'=>[]];

        $allLocations = [];
        foreach ($places as $place) {
            $allLocations[(int)$place['id']] = (string)$place['name'];
        }
        $stock = [];
        foreach ($stocks as $row) {
            $id = (int)$row['product_id'];
            $lid = (int)$row['location_id'];
            $stock[$id][] = [
                'location_id' => $lid,
                'location' => $allLocations[$lid] ?? 'Unbekannter Lagerort',
                'packs' => (int)$row['milli'] / 1000,
            ];
        }
        $photos = [];
        $sds = [];
        foreach ($files as $file) {
            $pid = (int)$file['product_id'];
            if ($file['kind'] === 'photo') {
                $photos[$pid][] = ['id'=>(int)$file['id'],'filename'=>(string)$file['filename']];
            } elseif ($file['kind'] === 'sds') {
                $fid = (int)$file['id'];
                if (!isset($sds[$pid]) || $fid > $sds[$pid]) $sds[$pid] = $fid;
            }
        }
        $visible = [];
        $usedLocationIds = [];
        foreach ($products as $row) {
            $p = json_decode((string)$row['details'], true);
            if (!is_array($p) || !self::allowsProduct($p, $scope)) continue;
            $id = (int)$row['id'];
            $item = [
                'id' => $id,
                'name' => (string)($p['name'] ?? ''),
                'manufacturer' => (string)($p['manufacturer'] ?? ''),
                'article' => (string)($p['article'] ?? ''),
                'ean' => (string)($p['ean'] ?? ''),
                'category' => (string)($p['category'] ?? ''),
                'material_kind' => (string)($p['material_kind'] ?? 'unclassified'),
                'use_area' => (string)($p['use_area'] ?? ''),
                'hazardous' => !empty($p['hazardous']),
                'pack_size' => (float)($p['pack_size'] ?? 0),
                'unit' => (string)($p['unit'] ?? ''),
                'storage_note' => (string)($p['storage_note'] ?? ''),
                'source_url' => (string)($p['source_url'] ?? ''),
                'pmb_url' => (string)($p['pmb_url'] ?? ''),
                'stock' => $stock[$id] ?? [],
                'photos' => $photos[$id] ?? [],
            ];
            foreach ($item['stock'] as $stockEntry) {
                $usedLocationIds[$stockEntry['location_id']] = true;
            }
            if ($scope !== 'lager' && $item['hazardous']) {
                foreach (['ufi','classification','ghs','signal','h_statements','sds_date','checked_at','sds_url'] as $field) {
                    $item[$field] = (string)($p[$field] ?? '');
                }
                $item['sds_id'] = $sds[$id] ?? null;
            }
            $visible[] = $item;
        }
        // A Kataster-only guest learns the names of locations used by the
        // shared hazardous products, not unrelated empty/private locations.
        $locations = [];
        foreach ($allLocations as $id => $name) {
            if ($scope !== 'kataster' || isset($usedLocationIds[$id])) {
                $locations[] = ['id' => $id, 'name' => $name];
            }
        }
        return ['products'=>$visible,'locations'=>$locations];
    }
    /**
     * Preserve the original narrow scope of links issued before v0.1.32.
     * Never expand an already-distributed capability link by upgrading the app.
     */
    public static function legacy(array $view, string $scope): array {
        $products = [];
        $allowed = ['id','name','manufacturer','category','material_kind','hazardous'];
        if ($scope !== 'kataster') {
            array_push($allowed, 'pack_size', 'unit', 'stock');
        }
        if ($scope !== 'lager') {
            array_push($allowed, 'classification', 'ghs', 'signal',
                'h_statements', 'sds_date', 'sds_url', 'sds_id');
        }
        foreach ($view['products'] ?? [] as $p) {
            $item = [];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $p)) $item[$key] = $p[$key];
            }
            $products[] = $item;
        }
        // The old public response had location names only inside stock rows.
        return ['products'=>$products,'locations'=>[]];
    }

}
