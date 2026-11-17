<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Service for handling database migrations.
 */
final class MigrationService {
    private const MIGRATION_CLASSES = [
        \OCA\Gefahrstoffkataster\Migration\Version20261117000000::class,
    ];

    public function __construct(
        private IDBConnection $db,
    ) {}

    /**
     * Get all migration steps.
     *
     * @return array
     */
    public function getMigrationSteps(): array {
        $steps = [];
        foreach (self::MIGRATION_CLASSES as $className) {
            if (class_exists($className)) {
                $steps[] = $className;
            }
        }
        return $steps;
    }

    /**
     * Check if all migrations have been run.
     *
     * @return bool
     */
    public function isUpToDate(): bool {
        // Check if gsk_catalog table exists
        $schema = $this->db->getConnection()->getSchemaManager();
        $tables = $schema->listTables();
        
        foreach ($tables as $table) {
            if ($table->getName() === 'gsk_catalog') {
                return true;
            }
        }
        return false;
    }

    /**
     * Run all pending migrations.
     *
     * @param IOutput $output
     */
    public function migrate(IOutput $output): void {
        $schema = $this->db->getConnection()->getSchemaManager();
        
        foreach ($this->getMigrationSteps() as $className) {
            /** @var IMigrationStep $migration */
            $migration = new $className();
            
            try {
                $schemaWrapper = $migration->changeSchema($output, function() use ($schema) {
                    return $schema;
                }, []);
                
                $migration->postSchemaChange($output, function() use ($schema) {
                    return $schema;
                }, []);
            } catch (\Exception $e) {
                $output->error('Migration failed: ' . $e->getMessage());
                throw $e;
            }
        }
    }
}
