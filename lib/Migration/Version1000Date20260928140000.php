<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20260928140000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('gsk_location')) {
            $t = $schema->createTable('gsk_location');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 80]);
            $t->setPrimaryKey(['id']);
            $t->addUniqueIndex(['name'], 'gsk_location_name');
        }
        if (!$schema->hasTable('gsk_product')) {
            $t = $schema->createTable('gsk_product');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('details', Types::TEXT, ['notnull' => true]);
            $t->addColumn('created_at', Types::STRING, ['notnull' => true, 'length' => 32]);
            $t->addColumn('updated_at', Types::STRING, ['notnull' => true, 'length' => 32]);
            $t->setPrimaryKey(['id']);
        }
        if (!$schema->hasTable('gsk_stock')) {
            $t = $schema->createTable('gsk_stock');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('product_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('location_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('milli', Types::BIGINT, ['notnull' => true]);
            $t->setPrimaryKey(['id']);
            $t->addUniqueIndex(['product_id','location_id'], 'gsk_stock_product_loc');
        }
        if (!$schema->hasTable('gsk_event')) {
            $t = $schema->createTable('gsk_event');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('product_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('location_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('before_milli', Types::BIGINT, ['notnull' => true]);
            $t->addColumn('after_milli', Types::BIGINT, ['notnull' => true]);
            $t->addColumn('action', Types::STRING, ['notnull' => true, 'length' => 12]);
            $t->addColumn('note', Types::STRING, ['notnull' => true, 'length' => 200]);
            $t->addColumn('actor', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('created_at', Types::STRING, ['notnull' => true, 'length' => 32]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['product_id'], 'gsk_event_product');
        }
        if (!$schema->hasTable('gsk_file')) {
            $t = $schema->createTable('gsk_file');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('product_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 8]);
            $t->addColumn('filename', Types::STRING, ['notnull' => true, 'length' => 180]);
            $t->addColumn('mime', Types::STRING, ['notnull' => true, 'length' => 40]);
            $t->addColumn('storage_name', Types::STRING, ['notnull' => true, 'length' => 80]);
            $t->addColumn('created_at', Types::STRING, ['notnull' => true, 'length' => 32]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['product_id'], 'gsk_file_product');
        }
        return $schema;
    }
}
