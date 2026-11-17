<?php
declare(strict_types=1);

namespace OCP\DB {interface ISchemaWrapper {}}
namespace OCP\Migration {interface IOutput {} abstract class SimpleMigrationStep {}}
namespace {
    require __DIR__.'/../lib/Migration/Version20261117000001.php';

    final class IndexFixture {
        public function __construct(private string $name, private array $columns, private bool $unique) {}
        public function getName():string {return $this->name;}
        public function getColumns():array {return $this->columns;}
        public function isUnique():bool {return $this->unique;}
    }
    final class TableFixture {
        public array $dropped=[], $added=[];
        public function __construct(public array $indexes) {}
        // Nextcloud 35 returns a numeric list of IIndex objects. The PHP array
        // key is not an index name, even if some DBAL versions used named keys.
        public function getIndexes():array {return $this->indexes;}
        public function dropIndex(string $name):self {
            $this->dropped[]=$name;
            $found=false;
            foreach($this->indexes as $key=>$index) if($index->getName()===$name){unset($this->indexes[$key]);$found=true;}
            if(!$found)throw new \RuntimeException('Unknown index '.$name);
            return $this;
        }
        public function hasIndex(string $name):bool {foreach($this->indexes as $index)if($index->getName()===$name)return true;return false;}
        public function addUniqueIndex(array $columns,?string $name=null):self {
            if($name===null || $this->hasIndex($name))throw new \RuntimeException('Invalid/duplicate index');
            $this->added[]=['columns'=>$columns,'name'=>$name];
            $this->indexes[]=new IndexFixture($name,$columns,true);return $this;
        }
    }
    final class SchemaFixture implements \OCP\DB\ISchemaWrapper {
        public function __construct(public TableFixture $table,private bool $exists=true) {}
        public function hasTable(string $name):bool {return $this->exists && $name==='gsk_location';}
        public function getTable(string $name):TableFixture {if(!$this->hasTable($name))throw new \RuntimeException('Missing table');return $this->table;}
    }
    $migration=new \OCA\Gefahrstoffkataster\Migration\Version20261117000001();
    $output=new class implements \OCP\Migration\IOutput {};
    foreach([false,true] as $namedKeys){
        $indexes=[new IndexFixture('real_unique_location_name',['name'],true),new IndexFixture('ordinary_name_lookup',['name'],false),new IndexFixture('primary',['id'],true)];
        if($namedKeys)$indexes=['key_is_not_the_index_name'=>$indexes[0],'lookup'=>$indexes[1],'pk'=>$indexes[2]];
        $table=new TableFixture($indexes);$schema=new SchemaFixture($table);
        if($migration->changeSchema($output,fn()=>$schema,[])!==$schema)throw new \RuntimeException('Schema lost');
        if($table->dropped!==['real_unique_location_name'])throw new \RuntimeException('Wrong index removed');
        if($table->added!==[['columns'=>['catalog_id','name'],'name'=>'gsk_loc_cat_name']])throw new \RuntimeException('Scoped index incorrect');
        $migration->changeSchema($output,fn()=>$schema,[]);
        if(count($table->added)!==1 || count($table->dropped)!==1)throw new \RuntimeException('Retry not idempotent');
    }
    $table=new TableFixture([new IndexFixture('gsk_loc_cat_name',['catalog_id','name'],true)]);
    $schema=new SchemaFixture($table);$migration->changeSchema($output,fn()=>$schema,[]);
    if($table->dropped || $table->added)throw new \RuntimeException('Existing scoped index changed');
    $absent=new SchemaFixture(new TableFixture([]),false);
    if($migration->changeSchema($output,fn()=>$absent,[])!==null)throw new \RuntimeException('Absent table should be ignored');
    echo "Catalog location migration passed: numeric and arbitrary array keys, actual index names, unique/nonunique filtering, retry, existing index and missing table\n";
}
