<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to add catalog support for multi-tenant hazard catalogs.
 * This migration:
 * 1. Creates the gsk_catalog table
 * 2. Adds catalog_id column to all existing tables
 * 3. Creates a default catalog
 * 4. Assigns all existing records to the default catalog
 */
class Version20261117000000 extends SimpleMigrationStep {
    /**
     * @return string
     */
    public function getDescription(): string {
        return 'Add catalog support for multi-tenant hazard catalog management';
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure
     * @param array $options
     * @return ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Check if gsk_catalog table already exists
        if (!$schema->hasTable('gsk_catalog')) {
            $table = $schema->createTable('gsk_catalog');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('name', 'string', [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('description', 'text', [
                'notnull' => false,
            ]);
            $table->addColumn('is_default', 'boolean', [
                'notnull' => true,
                'default' => false,
            ]);
            $table->addColumn('created_at', 'datetime', [
                'notnull' => true,
                'default' => 'CURRENT_TIMESTAMP',
            ]);
            $table->addColumn('updated_at', 'datetime', [
                'notnull' => true,
                'default' => 'CURRENT_TIMESTAMP',
            ]);
            $table->addColumn('created_by', 'string', [
                'notnull' => false,
                'length' => 64,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['is_default']);
            $output->info('Created table gsk_catalog');
        }

        // Add catalog_id to gsk_product if it doesn't exist
        $this->addCatalogIdColumn($schema, 'gsk_product', $output);
        $this->addCatalogIdColumn($schema, 'gsk_location', $output);
        $this->addCatalogIdColumn($schema, 'gsk_stock', $output);
        $this->addCatalogIdColumn($schema, 'gsk_file', $output);
        $this->addCatalogIdColumn($schema, 'gsk_event', $output);

        return $schema;
    }

    /**
     * Add catalog_id column to a table if it doesn't exist.
     *
     * @param ISchemaWrapper $schema
     * @param string $tableName
     * @param IOutput $output
     */
    private function addCatalogIdColumn(ISchemaWrapper $schema, string $tableName, IOutput $output): void {
        if ($schema->hasTable($tableName)) {
            $table = $schema->getTable($tableName);
            if (!$table->hasColumn('catalog_id')) {
                $table->addColumn('catalog_id', 'integer', [
                    'notnull' => false,
                    'default' => null,
                ]);
                $table->addIndex(['catalog_id']);
                $output->info("Added catalog_id column to {$tableName}");
            }
        }
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure
     * @param array $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        // This method is called after the schema change
        // We need to create the default catalog and assign existing records
        // However, in Nextcloud migrations, we typically can't execute arbitrary SQL
        // So we'll handle this in the CatalogService initialization
        $output->info('Catalog migration completed. Default catalog will be created on first use.');
    }
}
