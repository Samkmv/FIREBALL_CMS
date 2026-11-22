<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\VpnManagerV2\\';
    if (str_starts_with($class, $prefix)) require dirname(__DIR__) . '/plugins/vpn-manager-v2/src/' . str_replace('\\','/',substr($class,strlen($prefix))) . '.php';
});
final class FireballPluginVpnManagerV2 { public static function t(string $key): string { return $key; } }
function get_user(): array { return ['id'=>1,'role'=>$GLOBALS['role'] ?? 'creator']; }
function htmlSC(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function base_href(string $path): string { return $path; }
function get_csrf_field(): string { return '<input name="csrf" value="test">'; }
function view(): object { return new class { public function renderPartial(string $name,array $data=[]):string { return ''; } }; }
function cache(): object {
    static $cache;
    return $cache ??= new class { public array $removed=[]; public function remove(string $key): void { $this->removed[]=$key; } };
}
function db(): object {
    static $db;
    return $db ??= new class {
        public PDO $pdo;
        private int $affected=0;
        public function __construct() {
            $this->pdo=new PDO('sqlite::memory:', options:[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $this->pdo->sqliteCreateFunction('IF', static fn($condition,$yes,$no)=>$condition?$yes:$no);
            $this->pdo->sqliteCreateFunction('NOW', static fn()=>date('Y-m-d H:i:s'));
        }
        public function query(string $sql, array $params=[]): object {
            $stmt=$this->pdo->prepare(str_replace(' FOR UPDATE','',$sql)); $stmt->execute($params); $this->affected=$stmt->rowCount();
            return new class($stmt) {
                public function __construct(private PDOStatement $stmt) {}
                public function get(): array { return $this->stmt->fetchAll(PDO::FETCH_ASSOC); }
                public function getOne(): mixed { return $this->stmt->fetch(PDO::FETCH_ASSOC); }
                public function getColumn(): mixed { return $this->stmt->fetchColumn(); }
            };
        }
        public function rowCount(): int { return $this->affected; }
        public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
        public function inTransaction(): bool { return $this->pdo->inTransaction(); }
        public function commit(): bool { return $this->pdo->commit(); }
        public function rollBack(): bool { return $this->pdo->rollBack(); }
    };
}
use Fireball\VpnManagerV2\Services\ServerRemovalService;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionConfigRepository;
use Fireball\VpnManagerV2\Repositories\AutomationRepository;
use Fireball\VpnManagerV2\Exceptions\ValidationException;

$count=0;
$check=static function(bool $ok,string $message)use(&$count):void{ $count++; if(!$ok)throw new RuntimeException($message); };
$db=db();
$db->pdo->exec('CREATE TABLE vpn_v2_servers (id INTEGER PRIMARY KEY,name TEXT,code TEXT,panel_url TEXT,panel_path TEXT,api_url TEXT,
 auth_type TEXT,country_code TEXT,country_name TEXT,city TEXT,show_flag INTEGER,status TEXT,is_enabled INTEGER,maintenance_mode INTEGER,
 allow_new_connections INTEGER,verify_ssl INTEGER,allow_private_network INTEGER,connect_timeout INTEGER,read_timeout INTEGER,
 last_check_at TEXT,last_success_at TEXT,last_auth_at TEXT,last_sync_at TEXT,api_version TEXT,capabilities_json TEXT,last_error TEXT,
 created_at TEXT,updated_at TEXT,encrypted_username TEXT,encrypted_password TEXT,encrypted_token TEXT)');
$db->pdo->exec('CREATE TABLE vpn_v2_subscriptions (id INTEGER PRIMARY KEY,plan_id INTEGER,status TEXT,subscription_token TEXT,
 revision INTEGER,config_updated_at TEXT,updated_at TEXT,traffic_used_bytes INTEGER)');
$db->pdo->exec('CREATE TABLE vpn_v2_subscription_nodes (id INTEGER PRIMARY KEY,subscription_id INTEGER,server_id INTEGER,
 inbound_id INTEGER,status TEXT,desired_enabled INTEGER,is_obsolete INTEGER,sync_status TEXT,sync_error TEXT,last_error TEXT,
 client_uuid TEXT,traffic_used_bytes INTEGER,upload_bytes INTEGER,download_bytes INTEGER,updated_at TEXT,
 FOREIGN KEY(server_id) REFERENCES vpn_v2_servers(id) ON DELETE RESTRICT)');
$db->pdo->exec('CREATE TABLE vpn_v2_server_preset_nodes (id INTEGER PRIMARY KEY, preset_id INTEGER, server_id INTEGER)');
$db->pdo->exec('INSERT INTO vpn_v2_server_preset_nodes VALUES (1,1,1),(2,1,2)');
$db->pdo->exec('CREATE TABLE vpn_v2_plan_nodes (id INTEGER PRIMARY KEY,plan_id INTEGER,server_id INTEGER)');
$db->pdo->exec('CREATE TABLE vpn_v2_inbounds (id INTEGER PRIMARY KEY,server_id INTEGER,status TEXT,is_enabled INTEGER,updated_at TEXT)');
$db->pdo->exec('CREATE TABLE vpn_v2_subscription_items (id INTEGER PRIMARY KEY,parent_subscription_id INTEGER,item_type TEXT,
 connection_id INTEGER,child_subscription_id INTEGER,is_enabled INTEGER,effective_status TEXT,inactive_reason TEXT,
 relation_key TEXT,deleted_at TEXT,updated_at TEXT)');
$db->pdo->exec('CREATE TABLE vpn_v2_operations (id INTEGER PRIMARY KEY,server_id INTEGER,connection_id INTEGER,status TEXT,
 lease_until TEXT,heartbeat_at TEXT,idempotency_key TEXT,finished_at TEXT,updated_at TEXT,last_error TEXT)');
$db->pdo->exec('CREATE TABLE vpn_v2_sync_conflicts (id INTEGER PRIMARY KEY,server_id INTEGER,connection_id INTEGER,status TEXT,
 resolved_at TEXT,resolved_by INTEGER,updated_at TEXT)');
$db->pdo->exec('CREATE TABLE vpn_v2_events (event_type TEXT,subscription_id INTEGER,node_id INTEGER,server_id INTEGER,
 user_id INTEGER,admin_id INTEGER,context_json TEXT,created_at TEXT)');
$db->pdo->exec('PRAGMA foreign_keys=ON');
foreach([1,2] as $id)$db->query('INSERT INTO vpn_v2_servers(id,name,code,panel_url,is_enabled,status,updated_at,encrypted_token)
 VALUES(?,?,?,?,?,?,?,?)',[$id,$id===1?'<Lost>':'Live','s'.$id,'https://192.0.2.'.$id,$id===1?0:1,'online','2026-11-22 00:00:00','sensitive-secret']);
foreach([11=>5,12=>6,13=>6,14=>5,15=>6] as $id=>$plan)$db->query('INSERT INTO vpn_v2_subscriptions VALUES(?,?,?, ?,1,NULL,NULL,120)',[$id,$plan,'active',str_repeat(dechex($id),32)]);
foreach([[101,11,1,'active',70],[102,11,1,'delete_failed',30],[103,11,1,'deleted',10],[201,11,2,'active',20],[202,12,2,'active',5],[203,15,2,'active',5]] as [$id,$sub,$server,$status,$traffic])
 $db->query('INSERT INTO vpn_v2_subscription_nodes(id,subscription_id,server_id,inbound_id,status,client_uuid,traffic_used_bytes,upload_bytes,download_bytes,sync_status)
 VALUES(?,?,?,?,?,?,?,?,?,?)',[$id,$sub,$server,$server,$status,'uuid-'.$id,$traffic,0,$traffic,'synced']);
$db->pdo->exec("INSERT INTO vpn_v2_plan_nodes VALUES(1,5,1),(2,5,2),(3,6,2);
 INSERT INTO vpn_v2_inbounds VALUES(1,1,'active',1,NULL),(2,2,'active',1,NULL);
 INSERT INTO vpn_v2_subscription_items(id,parent_subscription_id,item_type,connection_id,child_subscription_id,is_enabled,relation_key)
 VALUES(1,12,'connection',101,NULL,1,'connection:101'),(2,13,'subscription',NULL,12,1,'subscription:12');
 INSERT INTO vpn_v2_operations(id,server_id,connection_id,status,lease_until) VALUES
 (1,1,101,'pending',NULL),(2,NULL,102,'retry',NULL),(3,2,201,'pending',NULL),(4,1,101,'running','2000-01-01 00:00:00');
 INSERT INTO vpn_v2_sync_conflicts(id,server_id,connection_id,status) VALUES(1,1,101,'open'),(2,2,201,'open');");
$service=new ServerRemovalService();$preview=$service->preview(1);$fingerprint=$preview['fingerprint'];
$render=static function(array $data):string{extract($data);$title='Remove';ob_start();require dirname(__DIR__).'/plugins/vpn-manager-v2/views/admin/server-delete.php';return (string)ob_get_clean();};
$html=$render($preview);
$check(str_contains($html,'&lt;Lost&gt;')&&!str_contains($html,'<Lost>'),'Removal preview escapes server name');
$check(str_contains($html,'name="server_is_gone"')&&str_contains($html,'required')&&str_contains($html,'name="fingerprint"')&&str_contains($html,'name="csrf"'),'Explicit confirmation, stale-form guard and CSRF field');
$check((int)$preview['counts']['connections']===3 && (int)$preview['counts']['subscriptions']===1,'Preview counts include historical links');
try{$service->remove(2,$service->preview(2)['fingerprint'],1);throw new RuntimeException('Live server removed');}
catch(ValidationException $e){$check(str_contains($e->getMessage(),'disable_first'),'Enabled server guard');}
try{$service->remove(1,str_repeat('0',64),1);throw new RuntimeException('Stale preview accepted');}
catch(ValidationException $e){$check(str_contains($e->getMessage(),'changed'),'Stale preview guard');}
$db->query("UPDATE vpn_v2_operations SET lease_until='2099-01-01 00:00:00' WHERE id=4");
try{$service->remove(1,$fingerprint,1);throw new RuntimeException('Active lease ignored');}
catch(ValidationException $e){$check(str_contains($e->getMessage(),'busy'),'Running work protected');}
$db->query("UPDATE vpn_v2_operations SET lease_until='2000-01-01 00:00:00' WHERE id=4");
$db->query("INSERT INTO vpn_v2_operations(id,server_id,status,lease_until,updated_at) VALUES(5,1,'running',NULL,'2000-01-01 00:00:00')");
$db->pdo->exec("CREATE TRIGGER reject_event BEFORE INSERT ON vpn_v2_events BEGIN SELECT RAISE(ABORT,'test'); END");
try{$service->remove(1,$fingerprint,1);throw new RuntimeException('Audit failure not rolled back');}
catch(PDOException){$check((new ServerRepository())->find(1)!==null && (int)$db->query('SELECT COUNT(*) FROM vpn_v2_plan_nodes WHERE server_id=1')->getColumn()===1,'Removal transaction rolls back');}
$db->pdo->exec('DROP TRIGGER reject_event');
$before=$db->query('SELECT id,subscription_token,status FROM vpn_v2_subscriptions ORDER BY id')->get();
$check((new SubscriptionConfigRepository())->trafficBreakdown(11)['traffic_used_bytes']===120,'Initial accounted traffic');
$name=$service->remove(1,$fingerprint,1);
$check((int)$db->pdo->query('SELECT COUNT(*) FROM vpn_v2_server_preset_nodes WHERE server_id = 1')->fetchColumn() === 0, 'Retired servers are removed from presets');
$check($name==='<Lost>' && (new ServerRepository())->find(1)===null && (new ServerRepository())->findWithSecrets(1)===null,'Removed server inaccessible');
$check(array_column((new ServerRepository())->all(),'id')===[2],'Removed server absent from listing');
$check((int)$db->query('SELECT COUNT(*) FROM vpn_v2_plan_nodes WHERE server_id=1')->getColumn()===0,'Plan links removed');
$check($db->query('SELECT id,subscription_token,status FROM vpn_v2_subscriptions ORDER BY id')->get()===$before,'All subscriptions, tokens and statuses preserved');
$check($db->query('SELECT client_uuid FROM vpn_v2_subscription_nodes WHERE id=201')->getColumn()==='uuid-201','Other server credential preserved');
$check($db->query('SELECT status FROM vpn_v2_subscription_nodes WHERE id=101')->getColumn()==='deleted','Old connection archived');
$check($db->query('SELECT status FROM vpn_v2_subscription_nodes WHERE id=102')->getColumn()==='deleted','Pending remote deletion finalized locally');
$check((new SubscriptionConfigRepository())->trafficBreakdown(11)['traffic_used_bytes']===120,'Usage survives removal');
$check((new AutomationRepository())->recalculateSubscriptionTraffic(11)===120,'Background traffic accounting preserves retired usage');
$check((int)$db->query('SELECT is_enabled FROM vpn_v2_subscription_items WHERE id=1')->getColumn()===0,'Direct dependency archived');
$check((int)$db->query('SELECT is_enabled FROM vpn_v2_subscription_items WHERE id=2')->getColumn()===1,'Whole-subscription dependency preserved');
foreach([11,12,13,14] as $id)$check((int)$db->query('SELECT revision FROM vpn_v2_subscriptions WHERE id=?',[$id])->getColumn()>1,'Revision invalidates source, consumer and ancestor '.$id);
$check((int)$db->query('SELECT revision FROM vpn_v2_subscriptions WHERE id=15')->getColumn()===1,'Unrelated subscription untouched');
$check($db->query('SELECT status FROM vpn_v2_operations WHERE id=1')->getColumn()==='cancelled'
 && $db->query('SELECT status FROM vpn_v2_operations WHERE id=2')->getColumn()==='cancelled'
 && $db->query('SELECT status FROM vpn_v2_operations WHERE id=3')->getColumn()==='pending','Cancel only target operations including indirect connection jobs');
$check($db->query('SELECT status FROM vpn_v2_operations WHERE id=5')->getColumn()==='cancelled','Stale running job without a lease cannot block removal forever');
$check($db->query('SELECT status FROM vpn_v2_sync_conflicts WHERE id=1')->getColumn()==='resolved'
 && $db->query('SELECT status FROM vpn_v2_sync_conflicts WHERE id=2')->getColumn()==='open','Only removed-server conflicts resolved');
$check($db->query('SELECT encrypted_token FROM vpn_v2_servers WHERE id=1')->getColumn()===null,'Old panel secret erased');
(new ServerRepository())->recordConnectionSuccess(1,99);
(new ServerRepository())->recordConnectionFailure(1,'error','late callback','transport');
$check((new ServerRepository())->find(1)===null,'Late callbacks cannot restore deleted server');
foreach(['encrypted_client_credential TEXT','lkg_snapshot_json TEXT','lkg_snapshot_hash TEXT','lkg_snapshot_version INTEGER'] as $column)
 $db->pdo->exec('ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN '.$column);
try{
 (new \Fireball\VpnManagerV2\Repositories\ConfigurationSyncRepository())->storeSnapshot(['id'=>101],[],str_repeat('a',64),'late_inventory');
 throw new RuntimeException('Late inventory revived an archived client');
}catch(\Fireball\VpnManagerV2\Exceptions\ObsoleteOperationException){$check(true,'Late node inventory rejected');}
try{
 (new \Fireball\VpnManagerV2\Repositories\InboundRepository())->syncServer(1,[]);
 throw new RuntimeException('Late inbound inventory revived an archived server');
}catch(\Fireball\VpnManagerV2\Exceptions\InboundSyncException){$check(true,'Late inbound inventory rejected');}
$check(count(cache()->removed)>0,'Subscription caches invalidated');
$events=$db->query('SELECT event_type,context_json FROM vpn_v2_events')->get();
$check(!str_contains(json_encode($events),'sensitive-secret') && str_contains(json_encode($events),'server.removed_locally'),'Audited without secrets');
echo json_encode(['status'=>'ok','assertions'=>$count]),PHP_EOL;
