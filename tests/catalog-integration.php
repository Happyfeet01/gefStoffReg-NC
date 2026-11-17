<?php
declare(strict_types=1);
// Real PDO/SQLite storage with minimal Nextcloud interfaces, not a live Nextcloud test.
namespace OCP {
    interface IDBConnection {} interface IRequest {} interface IConfig {} interface IUserSession {}
    interface IGroupManager {} interface IUserManager {} interface IURLGenerator {}
}
namespace OCP\Files {interface IAppData {}}
namespace Psr\Log {interface LoggerInterface {}}
namespace OCP\DB\QueryBuilder {interface IQueryBuilder {const PARAM_INT=1; const PARAM_STR=2; const PARAM_NULL=0; const PARAM_BOOL=5;}}
namespace OCP\AppFramework {
    class Controller {public $request;}
    class Http {const STATUS_BAD_REQUEST=400, STATUS_FORBIDDEN=403, STATUS_NOT_FOUND=404, STATUS_CONFLICT=409, STATUS_CREATED=201, STATUS_INTERNAL_SERVER_ERROR=500;}
}
namespace OCP\AppFramework\Http {
    class Response {public array $headers=[]; public function __construct(public $data=null, public int $status=200) {} public function addHeader($name,$value):void {$this->headers[$name]=$value;} public function throttle():void {}}
    class DataResponse extends Response {}
    class DataDownloadResponse extends Response {public function __construct($body,$name,$mime) {parent::__construct($body);}}
}
namespace {
    foreach (['AccessPolicy','CatalogService','CatalogScope','CatalogAssignmentService','ExternalShareService','ExternalShareProjection','ExternalStockBookingService'] as $file) require __DIR__.'/../lib/Service/'.$file.'.php';
    require __DIR__.'/../lib/Controller/InventoryController.php';
    require __DIR__.'/../lib/Controller/ExternalShareController.php';
    use OCA\Gefahrstoffkataster\Service\CatalogScope as Scope;
    use OCA\Gefahrstoffkataster\Service\CatalogAssignmentService;
    use OCA\Gefahrstoffkataster\Service\ExternalShareService;
    use OCA\Gefahrstoffkataster\Service\ExternalStockBookingService;

    function verify(bool $ok,string $message):void {if(!$ok)throw new \RuntimeException($message);}
    final class Result {
        public function __construct(private \PDOStatement $s) {}
        public function fetchAllAssociative():array {return $this->s->fetchAll(\PDO::FETCH_ASSOC);}
        public function fetchAssociative():array|false {return $this->s->fetch(\PDO::FETCH_ASSOC);}
        public function fetchOne():mixed {return $this->s->fetchColumn();}
    }
    final class Query {
        private string $mode='select', $table='', $select='*'; private array $params=[], $fields=[], $where=[], $order=[]; private ?int $limit=null;
        public function __construct(private DB $db) {}
        public function select(...$columns):self {$this->mode='select';$this->select=implode(',',$columns);return $this;}
        public function from($table):self {$this->table=$table;return $this;}
        public function update($table):self {$this->mode='update';$this->table=$table;return $this;}
        public function insert($table):self {$this->mode='insert';$this->table=$table;return $this;}
        public function delete($table):self {$this->mode='delete';$this->table=$table;return $this;}
        public function createNamedParameter($value,$type=null):string {$key=':p'.count($this->params);$this->params[$key]=$value;return $key;}
        public function setValue($key,$value):self {$this->fields[$key]=$value;return $this;}
        public function set($key,$value):self {return $this->setValue($key,$value);}
        public function where($condition):self {$this->where=[$condition];return $this;}
        public function andWhere($condition):self {$this->where[]=$condition;return $this;}
        public function expr():self {return $this;}
        public function eq($a,$b):string {return "$a = $b";}
        public function isNull($a):string {return "$a IS NULL";}
        public function orX(...$x):string {return '('.implode(' OR ',$x).')';}
        public function count($column,$alias):string {return "COUNT($column) AS $alias";}
        public function func():self {return $this;}
        public function orderBy($column,$direction='ASC'):self {$this->order=["$column $direction"];return $this;}
        public function setMaxResults($limit):self {$this->limit=$limit;return $this;}
        public function forUpdate():self {$this->db->locks++;return $this;} // SQLite fixture is single-connection; does not prove production locking.
        private function execute():\PDOStatement {
            $sql=match($this->mode){
                'insert'=>'INSERT INTO '.$this->table.' ('.implode(',',array_keys($this->fields)).') VALUES ('.implode(',',$this->fields).')',
                'update'=>'UPDATE '.$this->table.' SET '.implode(',',array_map(fn($key)=>$key.'='.$this->fields[$key],array_keys($this->fields))),
                'delete'=>'DELETE FROM '.$this->table,
                default=>'SELECT '.$this->select.' FROM '.$this->table,
            };
            if($this->where)$sql.=' WHERE '.implode(' AND ',$this->where);
            if($this->order)$sql.=' ORDER BY '.implode(',',$this->order);
            if($this->limit!==null)$sql.=' LIMIT '.$this->limit;
            $s=$this->db->pdo->prepare($sql);$s->execute($this->params);return $s;
        }
        public function executeQuery():Result {return new Result($this->execute());}
        public function executeStatement():int {return $this->execute()->rowCount();}
        public function getLastInsertId():int {return (int)$this->db->pdo->lastInsertId();}
    }
    final class DB implements \OCP\IDBConnection {
        public \PDO $pdo; public int $locks=0;
        public function __construct() {$this->pdo=new \PDO('sqlite::memory:',null,null,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);}
        public function getQueryBuilder():Query {return new Query($this);}
        public function beginTransaction():void {$this->pdo->beginTransaction();}
        public function commit():void {$this->pdo->commit();}
        public function rollBack():void {$this->pdo->rollBack();}
        public function inTransaction():bool {return $this->pdo->inTransaction();}
    }
    final class Config implements \OCP\IConfig {public array $data=[];public function getAppValue($app,$key,$default=''):string {return $this->data[$key]??$default;} public function setAppValue($app,$key,$value):void {$this->data[$key]=$value;}}
    final class Session implements \OCP\IUserSession {public function getUser():object {return new class {public function getUID():string {return 'lars';}};}}
    final class Groups implements \OCP\IGroupManager {public bool $admin=true;public function isAdmin($id):bool {return $this->admin;}public function isInGroup($id,$group):bool {return false;}}
    final class Users implements \OCP\IUserManager {public function get($id):object {return new \stdClass();}}
    final class Request implements \OCP\IRequest {public function __construct(public array $params=[]) {} public function getParam($key,$default=null):mixed {return $this->params[$key]??$default;}public function getHeader($key):string {return '1';}}
    final class Log implements \Psr\Log\LoggerInterface {public function __call($method,$args):void {}}
    final class Files implements \OCP\Files\IAppData {public int $reads=0;public function getFolder($name):self {return $this;}public function getFile($name):self {$this->reads++;return $this;}public function getContent():string {return '%PDF-fixture';}}
    final class Urls implements \OCP\IURLGenerator {public function linkToRouteAbsolute($name,$params):string {return 'https://example.test/file?'.http_build_query($params);}}
    function controller(string $class,array $properties):object {$r=new \ReflectionClass($class);$object=$r->newInstanceWithoutConstructor();foreach($properties as $name=>$value)$r->getProperty($name)->setValue($object,$value);return $object;}

    $db=new DB();
    $db->pdo->exec('CREATE TABLE gsk_catalog(id INTEGER PRIMARY KEY, name TEXT, description TEXT, is_default INTEGER, created_at TEXT, updated_at TEXT, created_by TEXT);
        CREATE TABLE gsk_product(id INTEGER PRIMARY KEY, catalog_id INTEGER, details TEXT, created_at TEXT, updated_at TEXT);
        CREATE TABLE gsk_location(id INTEGER PRIMARY KEY, catalog_id INTEGER, name TEXT);
        CREATE TABLE gsk_stock(id INTEGER PRIMARY KEY, catalog_id INTEGER, product_id INTEGER, location_id INTEGER, milli INTEGER);
        CREATE TABLE gsk_file(id INTEGER PRIMARY KEY, catalog_id INTEGER, product_id INTEGER, kind TEXT, filename TEXT, mime TEXT, storage_name TEXT, created_at TEXT);
        CREATE TABLE gsk_event(id INTEGER PRIMARY KEY, catalog_id INTEGER, product_id INTEGER, location_id INTEGER, before_milli INTEGER, after_milli INTEGER, action TEXT, note TEXT, actor TEXT, created_at TEXT);
        INSERT INTO gsk_catalog(id,name,is_default) VALUES(1,"Hallenbad Schlüchtern",1),(2,"Anderer Katalog",0);
        INSERT INTO gsk_location VALUES(10,NULL,"Chemieraum"),(11,NULL,"Lager ohne Bestand"),(20,2,"Fremder Lagerort");
        INSERT INTO gsk_stock VALUES(1,NULL,1,10,2500),(2,NULL,2,10,1000),(3,2,3,20,5000);
        INSERT INTO gsk_file VALUES(1,NULL,1,"sds","witty.pdf","application/pdf","unchanged-storage",NULL),(2,2,3,"sds","foreign.pdf","application/pdf","foreign-storage",NULL);
        INSERT INTO gsk_event(id,product_id,location_id,before_milli,after_milli) VALUES(1,1,10,0,2500),(2,2,10,0,1000);');
    $base=['name'=>'Chlor','manufacturer'=>'Witty','article'=>'','ean'=>'','ufi'=>'','category'=>'Chemie','use_area'=>'','unit'=>'l','pack_size'=>20,'hazardous'=>true,'classification'=>'','ghs'=>'','signal'=>'','h_statements'=>'','storage_note'=>'','source_url'=>'','sds_url'=>'','pmb_url'=>'','sds_date'=>'','checked_at'=>'','material_kind'=>'chemical'];
    $insert=$db->pdo->prepare('INSERT INTO gsk_product(id,catalog_id,details) VALUES(?,?,?)');
    foreach([[1,null,'Chlor'],[2,null,'Papier'],[3,2,'Fremdes Produkt']] as [$id,$cat,$name])$insert->execute([$id,$cat,json_encode(array_replace($base,['name'=>$name]))]);
    $config=new Config();$session=new Session();$groups=new Groups();$users=new Users();$logger=new Log();$files=new Files();$urls=new Urls();
    $access=new \OCA\Gefahrstoffkataster\Service\AccessPolicy($config,$session,$groups,$users);
    $catalogs=new \OCA\Gefahrstoffkataster\Service\CatalogService($db,$session);
    $request=new Request(['catalog_id'=>'legacy']);
    $inventory=controller(\OCA\Gefahrstoffkataster\Controller\InventoryController::class,['request'=>$request,'db'=>$db,'session'=>$session,'access'=>$access,'catalogService'=>$catalogs,'logger'=>$logger,'appData'=>$files,'urls'=>$urls]);
    $legacy=$inventory->bootstrap();verify($legacy->status===200 && count($legacy->data['products'])===2 && count($legacy->data['locations'])===2,'Unassigned inventory missing');
    $request->params=['catalog_id'=>'1'];$empty=$inventory->bootstrap();verify($empty->status===200 && $empty->data['products']===[] && $empty->data['locations']===[],'First/default new catalog inherited unassigned inventory');
    $request->params=['catalog_id'=>'2'];verify(count($inventory->bootstrap()->data['products'])===1,'Named catalog isolation failed');
    $request->params=['catalog_id'=>'999'];verify($inventory->bootstrap()->status===403,'Unknown catalog accepted');
    foreach(['-1','0','1 OR 1=1',[],true] as $bad){$request->params=['catalog_id'=>$bad];verify($inventory->bootstrap()->status===403,'Invalid catalog accepted');}
    $request->params=['catalog_id'=>'1','payload'=>json_encode(array_replace($base,['name'=>'Neu in Schlüchtern']))];
    $new=$inventory->saveProduct();verify($new->status===201 && (int)$db->pdo->query('SELECT catalog_id FROM gsk_product WHERE id='.$new->data['id'])->fetchColumn()===1,'Create ignored active catalog');
    $db->pdo->exec('DELETE FROM gsk_product WHERE id='.$new->data['id']);
    $request->params=['catalog_id'=>'1','payload'=>json_encode(['name'=>'Neuer Lagerort'])];$location=$inventory->addLocation();verify($location->status===201,'Scoped location creation failed');
    $db->pdo->exec('DELETE FROM gsk_location WHERE id='.$location->data['id']);
    $request->params=['catalog_id'=>'1'];verify($inventory->download(2)->status===404 && $files->reads===0,'Internal foreign-file download leaked');
    $request->params=['catalog_id'=>'1','payload'=>json_encode($base)];verify($inventory->updateProduct(3)->status===404,'Cross-catalog product update allowed');
    $groups->admin=false;$config->data['shares_v1']=json_encode(['users'=>['lars'=>'reader'],'groups'=>[]]);
    verify($inventory->bootstrap()->status===403,'Global reader bypassed catalog grants');
    $groups->admin=true;
    $mover=new CatalogAssignmentService($db);verify($mover->assign([1],1,'Hallenbad Schlüchtern')===1,'Assignment failed');
    $moved=$inventory->bootstrap();verify(count($moved->data['products'])===1 && count($moved->data['locations'])===1,'Assigned view incorrect');
    verify($moved->data['products'][0]['stock'][0]['packs']===2.5,'Stock amount changed during move');
    verify($moved->data['products'][0]['sds_id']===1 && $db->pdo->query('SELECT storage_name FROM gsk_file WHERE id=1')->fetchColumn()==='unchanged-storage','Document reference changed');
    verify((int)$db->pdo->query('SELECT location_id FROM gsk_stock WHERE product_id=2')->fetchColumn()===10,'Other product lost shared location');
    verify((int)$db->pdo->query('SELECT location_id FROM gsk_event WHERE product_id=1')->fetchColumn()===$moved->data['locations'][0]['id'],'History did not follow location mapping');
    $snapshot=$db->pdo->query('SELECT * FROM gsk_product ORDER BY id')->fetchAll();
    try{$mover->assign([2,3],1,'Hallenbad');throw new \LogicException('Assigned products accepted in legacy batch');}catch(\RuntimeException $e){}
    verify($db->pdo->query('SELECT * FROM gsk_product ORDER BY id')->fetchAll()===$snapshot,'Failed batch partially changed data');
    $shares=new ExternalShareService($config);
    $one=$shares->create('Hallenbad','both',7,'','lars',$shares->listing(1)['revision'],'read','',1);
    $old=$shares->create('Bisheriger Link','both',7,'','lars',$shares->listing()['revision']);
    verify(count($shares->listing(1)['links'])===1 && count($shares->listing()['links'])===1,'External link listings cross catalogs');
    $stock=new ExternalStockBookingService($db);
    $publicRequest=new Request(['catalog_id'=>'2']);
    $public=controller(\OCA\Gefahrstoffkataster\Controller\ExternalShareController::class,['request'=>$publicRequest,'shares'=>$shares,'stockBookings'=>$stock,'access'=>$access,'catalogs'=>$catalogs,'db'=>$db,'appData'=>$files,'session'=>$session,'urls'=>$urls,'logger'=>$logger]);
    $publicView=$public->read($one['token']);verify($publicView->status===200 && array_column($publicView->data['products'],'id')===[1],'Public request overrode token catalog');
    verify($public->file($one['token'],2)->status===404 && $files->reads===0,'Foreign public file leaked');
    verify($public->file($one['token'],1)->status===200 && $files->reads===1,'Own public file rejected');
    verify(array_column($public->read($old['token'])->data['products'],'id')===[2],'Legacy link expanded into named catalogs');
    $lid=$moved->data['locations'][0]['id'];$booking=['product_id'=>1,'location_id'=>$lid,'action'=>'add','packs'=>1,'expected_milli'=>2500];
    foreach([array_replace($booking,['product_id'=>3,'location_id'=>20]),array_replace($booking,['location_id'=>20])] as $bad){try{$stock->book($bad,'both','external:'.str_repeat('a',16),1);throw new \LogicException('Cross-catalog booking accepted');}catch(\DomainException $e){}}
    verify($stock->book($booking,'both','external:'.str_repeat('a',16),1)['after']===3.5,'Own catalog booking failed');
    $request->params=['catalog_id'=>'1'];verify($inventory->export('csv')->status===200 && !str_contains($inventory->export('csv')->data,'Fremdes Produkt'),'CSV leaked another catalog');
    verify($db->locks>0,'Assignment and stock writers did not request product locks');
    echo "Catalog integration fixture passed: actual controllers, empty/default catalog, create, scoped updates/files/exports, assignment/rollback, stock/history/documents, token-bound reads/files/bookings and legacy links\n";
}
