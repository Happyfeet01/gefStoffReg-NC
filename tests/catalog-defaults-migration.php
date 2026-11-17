<?php
declare(strict_types=1);
namespace OCP\DB {interface ISchemaWrapper {}}
namespace OCP\Migration {interface IOutput {} abstract class SimpleMigrationStep {}}
namespace {
    require __DIR__.'/../lib/Migration/Version20261117000002.php';
    final class DefaultColumn {
        public int $writes=0;
        public function __construct(public mixed $default,public bool $notnull=true) {}
        public function getDefault():mixed {return $this->default;}
        public function setDefault(mixed $default):self {$this->default=$default;$this->writes++;return $this;}
    }
    final class DefaultTable {
        public function __construct(public array $columns) {}
        public function hasColumn(string $name):bool {return isset($this->columns[$name]);}
        public function getColumn(string $name):DefaultColumn {return $this->columns[$name];}
    }
    final class DefaultSchema implements \OCP\DB\ISchemaWrapper {
        public array $requested=[];
        public function __construct(public DefaultTable $table,private bool $exists=true) {}
        public function hasTable(string $name):bool {$this->requested[]=$name;return $this->exists && $name==='gsk_catalog';}
        public function getTable(string $name):DefaultTable {if($name!=='gsk_catalog')throw new \RuntimeException('Unrelated table accessed');return $this->table;}
    }
    function ensureDefault(bool $ok,string $message):void {if(!$ok)throw new \RuntimeException($message);}
    $migration=new \OCA\Gefahrstoffkataster\Migration\Version20261117000002();
    $output=new class implements \OCP\Migration\IOutput {};
    foreach(['CURRENT_TIMESTAMP','current_timestamp()',"'CURRENT_TIMESTAMP'"] as $oldDefault){
        $table=new DefaultTable(['created_at'=>new DefaultColumn($oldDefault),'updated_at'=>new DefaultColumn($oldDefault),'name'=>new DefaultColumn('unchanged')]);
        $schema=new DefaultSchema($table);
        ensureDefault($migration->changeSchema($output,fn()=>$schema,[])===$schema,'Existing defaults not normalized');
        foreach(['created_at','updated_at'] as $name){
            ensureDefault($table->columns[$name]->default===null,'Default not removed');
            ensureDefault($table->columns[$name]->notnull===true,'Nullability changed');
            ensureDefault($table->columns[$name]->writes===1,'Wrong number of writes');
        }
        ensureDefault($table->columns['name']->default==='unchanged' && $table->columns['name']->writes===0,'Unrelated column changed');
        ensureDefault($migration->changeSchema($output,fn()=>$schema,[])===null,'Retry was not a no-op');
    }
    foreach([[],['created_at'=>new DefaultColumn(null)],['created_at'=>new DefaultColumn(null),'updated_at'=>new DefaultColumn(null)]] as $columns){
        $schema=new DefaultSchema(new DefaultTable($columns));
        ensureDefault($migration->changeSchema($output,fn()=>$schema,[])===null,'Missing/already-normalized columns should be ignored');
    }
    $schema=new DefaultSchema(new DefaultTable([]),false);
    ensureDefault($migration->changeSchema($output,fn()=>$schema,[])===null,'Missing table should be ignored');
    echo "Catalog timestamp default migration passed: legacy default spellings, nullability preserved, unrelated columns preserved, retry, fresh schema and missing table\n";
}
