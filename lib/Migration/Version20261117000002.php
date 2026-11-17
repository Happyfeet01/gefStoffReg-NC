<?php
declare(strict_types=1);
namespace OCA\Gefahrstoffkataster\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Normalize earlier catalog schemas without rewriting any timestamp values. */
final class Version20261117000002 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();
        if (!$schema->hasTable('gsk_catalog')) return null;
        $table = $schema->getTable('gsk_catalog');
        $changed = false;
        foreach (['created_at', 'updated_at'] as $name) {
            if (!$table->hasColumn($name)) continue;
            $column = $table->getColumn($name);
            if ($column->getDefault() !== null) {
                $column->setDefault(null);
                $changed = true;
            }
        }
        return $changed ? $schema : null;
    }
}
