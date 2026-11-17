<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

/** Catalog ownership comes from database columns, never from product JSON. */
final class CatalogScope {
    public static function parse(mixed $value): ?int {
        if ($value === null || $value === '' || $value === 'legacy') return null;
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)) {
            throw new \InvalidArgumentException('Katalog ungültig.');
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new \InvalidArgumentException('Katalog ungültig.');
        return $id;
    }

    public static function matches(array $row, ?int $catalogId): bool {
        $owner = $row['catalog_id'] ?? null;
        return $catalogId === null ? ($owner === null || $owner === 0 || $owner === '0')
            : $owner !== null && (string)$owner === (string)$catalogId;
    }

    public static function filter(array $rows, ?int $catalogId): array {
        return array_values(array_filter($rows, static fn(array $row): bool => self::matches($row, $catalogId)));
    }

    /** Parent product ownership also protects old files whose own column is unset. */
    public static function project(array $products, array $locations, array $stock, array $files, ?int $catalogId): array {
        $products = self::filter($products, $catalogId);
        $locations = self::filter($locations, $catalogId);
        $pids = array_fill_keys(array_column($products, 'id'), true);
        $lids = array_fill_keys(array_column($locations, 'id'), true);
        return [
            'products' => $products, 'locations' => $locations,
            'stock' => array_values(array_filter($stock, static fn(array $r): bool => isset($pids[$r['product_id']], $lids[$r['location_id']]))),
            'files' => array_values(array_filter($files, static fn(array $r): bool => isset($pids[$r['product_id']]))),
        ];
    }
}
