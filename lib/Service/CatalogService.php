<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserSession;

/**
 * Service for managing hazard catalogs (multi-tenant support).
 * 
 * A catalog represents a separate hazard catalog for a specific location
 * (e.g., a swimming pool, a facility). Each catalog can have its own
 * products, locations, stock, and files.
 */
final class CatalogService {
    private const DEFAULT_CATALOG_NAME = 'Hauptkataster';
    private const TABLE_NAME = 'gsk_catalog';

    public function __construct(
        private IDBConnection $db,
        private IUserSession $session,
    ) {}

    /**
     * Ensure the default catalog exists and return its ID.
     * Creates it if it doesn't exist.
     *
     * @return int The default catalog ID
     */
    public function ensureDefaultCatalog(): int {
        $qb = $this->db->getQueryBuilder();
        
        // Check if default catalog exists
        $result = $qb->select('id')
            ->from(self::TABLE_NAME)
            ->where($qb->expr()->eq('is_default', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
            ->executeQuery();
        
        $row = $result->fetchAssociative();
        if ($row !== false) {
            return (int)$row['id'];
        }

        // Check if any catalog exists
        $result = $qb->select('id')
            ->from(self::TABLE_NAME)
            ->orderBy('id', 'ASC')
            ->setMaxResults(1)
            ->executeQuery();
        
        $row = $result->fetchAssociative();
        if ($row !== false) {
            // Mark the first catalog as default
            $qb->update(self::TABLE_NAME)
                ->set('is_default', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($row['id'])))
                ->executeStatement();
            return (int)$row['id'];
        }

        // Create default catalog
        $user = $this->session->getUser();
        $createdBy = $user ? $user->getUID() : 'system';
        
        $qb->insert(self::TABLE_NAME)
            ->setValue('name', $qb->createNamedParameter(self::DEFAULT_CATALOG_NAME))
            ->setValue('description', $qb->createNamedParameter('Standard-Gefahrstoffkataster'))
            ->setValue('is_default', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
            ->setValue('created_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
            ->setValue('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
            ->setValue('created_by', $qb->createNamedParameter($createdBy))
            ->executeStatement();
        
        return (int)$qb->getLastInsertId();
    }

    /**
     * Get all catalogs accessible to the current user.
     *
     * @return array Array of catalog records
     */
    public function getAllCatalogs(): array {
        $qb = $this->db->getQueryBuilder();
        
        // For now, return all catalogs (access control will be added later)
        $result = $qb->select('*')
            ->from(self::TABLE_NAME)
            ->orderBy('name', 'ASC')
            ->executeQuery();
        
        return $result->fetchAllAssociative();
    }

    /**
     * Get a specific catalog by ID.
     *
     * @param int $id
     * @return array|null
     */
    public function getCatalogById(int $id): ?array {
        $qb = $this->db->getQueryBuilder();
        
        $result = $qb->select('*')
            ->from(self::TABLE_NAME)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
            ->executeQuery();
        
        return $result->fetchAssociative() ?: null;
    }

    /**
     * Get the default catalog ID.
     *
     * @return int|null
     */
    public function getDefaultCatalogId(): ?int {
        $qb = $this->db->getQueryBuilder();
        
        $result = $qb->select('id')
            ->from(self::TABLE_NAME)
            ->where($qb->expr()->eq('is_default', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
            ->executeQuery();
        
        $row = $result->fetchAssociative();
        return $row ? (int)$row['id'] : null;
    }

    /**
     * Create a new catalog.
     *
     * @param string $name
     * @param string $description
     * @return int The new catalog ID
     */
    public function createCatalog(string $name, string $description = ''): int {
        $user = $this->session->getUser();
        if (!$user) {
            throw new \RuntimeException('User must be logged in to create a catalog');
        }
        
        $qb = $this->db->getQueryBuilder();
        
        // Ensure only one default catalog
        if ($this->getDefaultCatalogId() === null) {
            $isDefault = true;
        } else {
            $isDefault = false;
        }
        
        $qb->insert(self::TABLE_NAME)
            ->setValue('name', $qb->createNamedParameter($name))
            ->setValue('description', $qb->createNamedParameter($description))
            ->setValue('is_default', $qb->createNamedParameter($isDefault, IQueryBuilder::PARAM_BOOL))
            ->setValue('created_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
            ->setValue('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
            ->setValue('created_by', $qb->createNamedParameter($user->getUID()))
            ->executeStatement();
        
        return (int)$qb->getLastInsertId();
    }

    /**
     * Update a catalog.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateCatalog(int $id, array $data): bool {
        $qb = $this->db->getQueryBuilder();
        
        $updates = [];
        if (isset($data['name'])) {
            $updates['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updates['description'] = $data['description'];
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $updates['updated_at'] = date('Y-m-d H:i:s');
        
        $qb->update(self::TABLE_NAME);
        foreach ($updates as $column => $value) {
            $qb->set($column, $qb->createNamedParameter($value));
        }
        $qb->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        
        return $qb->executeStatement() > 0;
    }

    /**
     * Delete a catalog.
     * Only allowed if the catalog is empty (no products, locations, etc.)
     * and it's not the default catalog.
     *
     * @param int $id
     * @return bool
     */
    public function deleteCatalog(int $id): bool {
        $qb = $this->db->getQueryBuilder();
        
        // Check if it's the default catalog
        $catalog = $this->getCatalogById($id);
        if ($catalog === null) {
            return false;
        }
        
        if ($catalog['is_default'] === true || $catalog['is_default'] === '1') {
            throw new \RuntimeException('Cannot delete the default catalog');
        }
        
        // Check if catalog has any products
        $count = $qb->select($qb->func()->count('*', 'count'))
            ->from('gsk_product')
            ->where($qb->expr()->eq('catalog_id', $qb->createNamedParameter($id)))
            ->executeQuery()
            ->fetchOne();
        
        if ($count > 0) {
            throw new \RuntimeException('Cannot delete catalog with existing products');
        }
        
        // Check other tables
        $tables = ['gsk_location', 'gsk_stock', 'gsk_file', 'gsk_event'];
        foreach ($tables as $table) {
            $count = $qb->select($qb->func()->count('*', 'count'))
                ->from($table)
                ->where($qb->expr()->eq('catalog_id', $qb->createNamedParameter($id)))
                ->executeQuery()
                ->fetchOne();
            
            if ($count > 0) {
                throw new \RuntimeException("Cannot delete catalog with existing {$table} records");
            }
        }
        
        // Delete the catalog
        $qb->delete(self::TABLE_NAME)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
            ->executeStatement();
        
        return true;
    }

    /**
     * Assign all records without a catalog_id to the default catalog.
     * This is used during migration to ensure data consistency.
     *
     * @param int $catalogId
     * @return int Number of records updated
     */
    public function assignOrphanedRecordsToCatalog(int $catalogId): int {
        $qb = $this->db->getQueryBuilder();
        $total = 0;
        
        $tables = ['gsk_product', 'gsk_location', 'gsk_stock', 'gsk_file', 'gsk_event'];
        
        foreach ($tables as $table) {
            // Check if table has catalog_id column
            try {
                $result = $qb->select('catalog_id')
                    ->from($table)
                    ->where($qb->expr()->isNull('catalog_id'))
                    ->executeQuery();
                
                $count = $qb->update($table)
                    ->set('catalog_id', $qb->createNamedParameter($catalogId))
                    ->where($qb->expr()->isNull('catalog_id'))
                    ->executeStatement();
                
                $total += $count;
            } catch (\Exception $e) {
                // Column might not exist, skip
                continue;
            }
        }
        
        return $total;
    }

    /**
     * Get the current catalog ID from the session or request.
     * For now, we use the default catalog or a parameter.
     *
     * @return int
     */
    public function getCurrentCatalogId(): int {
        // For now, use the default catalog
        // Later, this will be determined by user selection or request parameter
        $defaultId = $this->getDefaultCatalogId();
        if ($defaultId !== null) {
            return $defaultId;
        }
        
        // If no default catalog exists, ensure and return it
        return $this->ensureDefaultCatalog();
    }
}
