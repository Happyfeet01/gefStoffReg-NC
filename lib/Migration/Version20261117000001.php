<?php
declare(strict_types=1);
namespace OCA\Gefahrstoffkataster\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Preserve existing rows while allowing the same location name in different catalogs. */
final class Version20261117000001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();
        if (!$schema->hasTable('gsk_location')) return null;
        $table = $schema->getTable('gsk_location');
        foreach ($table->getIndexes() as $name => $index) {
            if ($index->isUnique() && $index->getColumns() === ['name']) $table->dropIndex($name);
        }
        if (!$table->hasIndex('gsk_loc_cat_name')) $table->addUniqueIndex(['catalog_id', 'name'], 'gsk_loc_cat_name');
        return $schema;
    }
}
