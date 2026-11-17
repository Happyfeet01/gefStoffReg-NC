<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Move only explicitly selected, unassigned products; preserve IDs and AppData files. */
final class CatalogAssignmentService {
    public function __construct(private IDBConnection $db) {}

    private function rows(string $table): array {
        return $this->db->getQueryBuilder()->select('*')->from($table)->executeQuery()->fetchAllAssociative();
    }

    private function update(string $table, int $id, array $fields): void {
        $q = $this->db->getQueryBuilder();
        $q->update($table)->where($q->expr()->eq('id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        foreach ($fields as $key => $value) $q->set($key, $q->createNamedParameter($value, is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR));
        $q->executeStatement();
    }

    public function assign(array $ids, int $targetId, string $targetName): int {
        if (!$ids || count($ids) > 200) throw new \InvalidArgumentException('Bitte 1 bis 200 Produkte auswählen.');
        foreach ($ids as $id) if (!is_int($id) || $id < 1) throw new \InvalidArgumentException('Produkt-ID ungültig.');
        $ids = array_values(array_unique($ids)); sort($ids);
        $this->db->beginTransaction();
        try {
            $target = $this->db->getQueryBuilder();
            $exists = $target->select('id')->from('gsk_catalog')->where($target->expr()->eq('id', $target->createNamedParameter($targetId, IQueryBuilder::PARAM_INT)))
                ->forUpdate()->executeQuery()->fetchAssociative();
            if (!$exists) throw new \RuntimeException('Zielkatalog wurde entfernt.');
            // Every stock writer takes this same product lock before reading the catalog.
            foreach ($ids as $id) {
                $q = $this->db->getQueryBuilder();
                $row = $q->select('*')->from('gsk_product')->where($q->expr()->eq('id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
                    ->forUpdate()->executeQuery()->fetchAssociative();
                if (!$row || !CatalogScope::matches($row, null)) throw new \RuntimeException('Ein ausgewähltes Produkt ist nicht mehr ohne Katalog. Bitte neu laden.');
            }
            $selected = array_fill_keys($ids, true);
            $stock = $this->rows('gsk_stock'); $events = $this->rows('gsk_event');
            $locations = $this->rows('gsk_location');
            $byId = array_column($locations, null, 'id');
            $needed = [];
            foreach (array_merge($stock, $events) as $row) if (isset($selected[$row['product_id']])) $needed[(int)$row['location_id']] = true;
            $mapping = [];
            foreach (array_keys($needed) as $lid) {
                $source = $byId[$lid] ?? null;
                if (!$source || !CatalogScope::matches($source, null)) throw new \RuntimeException('Lagerort-Zuordnung ist widersprüchlich. Bitte prüfen.');
                $shared = false;
                foreach (array_merge($stock, $events) as $row) {
                    if ((int)$row['location_id'] === $lid && !isset($selected[$row['product_id']])) {$shared = true; break;}
                }
                $matchingTarget = null;
                foreach ($locations as $location) {
                    if (CatalogScope::matches($location, $targetId) && mb_strtolower(trim((string)$location['name'])) === mb_strtolower(trim((string)$source['name']))) {$matchingTarget = (int)$location['id']; break;}
                }
                if ($matchingTarget !== null) {$mapping[$lid] = $matchingTarget; continue;}
                if (!$shared) {
                    $this->update('gsk_location', $lid, ['catalog_id' => $targetId]);
                    $mapping[$lid] = $lid;
                    continue;
                }
                // Shared source locations remain with the other products. A suffixed
                // name also works on old installations with a global unique-name index.
                $name = mb_substr((string)$source['name'], 0, 35) . ' · ' . mb_substr($targetName, 0, 35) . ' #' . $lid;
                $existing = null;
                foreach ($locations as $location) if (CatalogScope::matches($location, $targetId) && $location['name'] === $name) {$existing = (int)$location['id']; break;}
                if ($existing === null) {
                    $q = $this->db->getQueryBuilder();
                    $q->insert('gsk_location')->setValue('catalog_id', $q->createNamedParameter($targetId, IQueryBuilder::PARAM_INT))
                        ->setValue('name', $q->createNamedParameter($name))->executeStatement();
                    $existing = (int)$q->getLastInsertId();
                    $locations[] = ['id' => $existing, 'catalog_id' => $targetId, 'name' => $name];
                }
                $mapping[$lid] = $existing;
            }
            foreach ($ids as $id) $this->update('gsk_product', $id, ['catalog_id' => $targetId]);
            foreach (['gsk_stock' => $stock, 'gsk_event' => $events] as $table => $rows) {
                foreach ($rows as $row) if (isset($selected[$row['product_id']])) {
                    $this->update($table, (int)$row['id'], ['catalog_id' => $targetId, 'location_id' => $mapping[(int)$row['location_id']]]);
                }
            }
            foreach ($this->rows('gsk_file') as $row) if (isset($selected[$row['product_id']])) {
                $this->update('gsk_file', (int)$row['id'], ['catalog_id' => $targetId]);
            }
            $this->db->commit();
            return count($ids);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
