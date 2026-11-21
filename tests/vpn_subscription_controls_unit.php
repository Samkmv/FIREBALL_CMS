<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\VpnManagerV2\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/plugins/vpn-manager-v2/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
final class FireballPluginVpnManagerV2 {
    public static function t(string $key): string { return $key; }
}
function cache(): object {
    static $cache;
    return $cache ??= new class {
        public array $removed = [];
        public function remove(string $key): void { $this->removed[] = $key; }
    };
}
function db(): object {
    static $db;
    return $db ??= new class {
        public PDO $pdo;
        private int $affected = 0;
        public function __construct() {
            $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }
        public function inTransaction(): bool { return $this->pdo->inTransaction(); }
        public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
        public function commit(): bool { return $this->pdo->commit(); }
        public function rollBack(): bool { return $this->pdo->rollBack(); }
        public function rowCount(): int { return $this->affected; }
        public function query(string $sql, array $parameters = []): object {
            // SQLite verifies transactional writes; MySQL row locks require the deployment pilot.
            $statement = $this->pdo->prepare(str_replace(' FOR UPDATE', '', $sql));
            $statement->execute($parameters);
            $this->affected = $statement->rowCount();
            return new class($statement) {
                public function __construct(private PDOStatement $statement) {}
                public function get(): array { return $this->statement->fetchAll(PDO::FETCH_ASSOC); }
                public function getOne(): mixed { return $this->statement->fetch(PDO::FETCH_ASSOC); }
                public function getColumn(): mixed { return $this->statement->fetchColumn(); }
            };
        }
    };
}
use Fireball\VpnManagerV2\Support\HappRoutingProfile;
use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Services\SubscriptionLinkService;
use Fireball\VpnManagerV2\Validators\SettingsValidator;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionConfigRepository;

$count = 0;
$check = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) throw new RuntimeException($message);
};
$codec = new HappRoutingProfile();
$original = ['Name' => 'RoscomVPN', 'GlobalProxy' => 'true', 'RouteOrder' => 'block-proxy-direct',
    'UseChunkFiles' => 'true', 'DirectSites' => ['geosite:whitelist'], 'ProxySites' => ['geosite:github'],
    'BlockSites' => ['geosite:win-spy'], 'DnsHosts' => (object)['lkfl2.nalog.ru' => '213.24.64.175'],
    'Geositeurl' => 'https://example.org/original.dat', 'LastUpdated' => '1790000000'];
$happ = $codec->normalize(json_encode($original));
$incy = $codec->normalize($happ, incy: true);
$payload = json_decode(base64_decode(substr($incy, strlen('incy://routing/onadd/'))), true);
$check($payload['DirectSites'] === $original['DirectSites'] && $payload['RouteOrder'] === $original['RouteOrder'], 'Incy rule identity');
$check($payload['useChunkFiles'] === true && !isset($payload['UseChunkFiles']), 'Incy trimming key');
$check($payload['DnsHosts']['lkfl2.nalog.ru'] === '213.24.64.175' && $payload['Geositeurl'] === $original['Geositeurl'], 'Incy DNS and Geo identity');
$settings = array_replace(SettingsService::defaults(), ['happ_routing_link' => $happ, 'happ_routing_enabled' => true,
    'incy_routing_enabled' => true]);
$check($codec->incyHeaders(array_replace($settings, ['incy_routing_enabled' => false])) === [], 'Upgrade leaves unmanaged Incy routing untouched');
$validated = (new SettingsValidator())->validate($settings, SettingsService::defaults())->toArray();
$check($validated['incy_routing_link'] === '' && $validated['incy_routing_enabled'], 'Allow shared routing source');
$check($codec->incyHeaders($validated) === ['routing' => $incy], 'Inherit Happ as Incy');
$check($codec->incyHeaders(array_replace($validated, ['incy_routing_enabled' => false])) === ['routing' => 'off'], 'Disable Incy independently');
$override = $codec->normalize('{"Name":"Custom","DirectSites":["domain:custom.example"]}', incy: true);
$check($codec->incyHeaders(array_replace($validated, ['incy_routing_link' => $override])) === ['routing' => $override], 'Explicit Incy override');
$check($codec->incyHeaders(array_replace($validated, ['incy_routing_link' => "bad\r\nInjected: yes"])) === [], 'No header injection');
$updated = $codec->markUpdated($codec->normalize('{"Name":"RoscomVPN","DirectSites":["domain:new.example"]}'), $happ);
$newPayload = json_decode(base64_decode(substr($updated, strlen('happ://routing/onadd/'))), true);
$check((int)$newPayload['LastUpdated'] > (int)$original['LastUpdated'], 'Changed rules get a fresh timestamp');
$check($codec->markUpdated($updated, $updated) === $updated, 'Timestamp stable on unchanged save');

$database = db();
$columns = 'id INTEGER PRIMARY KEY, user_id INTEGER, plan_id INTEGER, status TEXT, starts_at TEXT, expires_at TEXT,
 traffic_limit_bytes INTEGER, traffic_used_bytes INTEGER, device_limit INTEGER, ip_limit INTEGER,
 subscription_token TEXT, subscription_token_hash TEXT, revision INTEGER, config_updated_at TEXT,
 created_by INTEGER, created_at TEXT, updated_at TEXT';
$database->pdo->exec('CREATE TABLE vpn_v2_subscriptions (' . $columns . ')');
$database->pdo->exec('CREATE TABLE vpn_v2_events (event_type TEXT, subscription_id INTEGER, node_id INTEGER, server_id INTEGER,
 user_id INTEGER, admin_id INTEGER, context_json TEXT, created_at TEXT)');
$old = str_repeat('a', 64);
$database->query('INSERT INTO vpn_v2_subscriptions (id,user_id,status,subscription_token,subscription_token_hash,revision)
 VALUES (1,2,?,?,?,7)', ['partial_sync', $old, hash('sha256', $old)]);
$configs = new SubscriptionConfigRepository();
$check($configs->findByToken($old)['id'] === 1, 'Old token initially valid');
(new SubscriptionLinkService())->rotate(1, hash('sha256', $old), 3);
$after = $database->query('SELECT * FROM vpn_v2_subscriptions WHERE id = 1')->getOne();
$check($after['subscription_token'] !== $old && strlen($after['subscription_token']) === 64, 'Secure new token');
$check($after['status'] === 'partial_sync' && $after['revision'] === 8, 'Status preserved and revision advanced');
$check($configs->findByToken($old) === null && $configs->findByToken($after['subscription_token'])['id'] === 1, 'Old URL revoked, new URL authorized');
$check(count(cache()->removed) === 4, 'Old subscription and QR renders invalidated');
$event = $database->query('SELECT * FROM vpn_v2_events')->getOne();
$check($event['event_type'] === 'subscription.access_link_rotated'
    && !str_contains(json_encode($event), $old) && !str_contains(json_encode($event), $after['subscription_token']), 'Audit contains no tokens');
try { (new SubscriptionLinkService())->rotate(1, hash('sha256', $old), 3); throw new RuntimeException('Stale form rotated twice'); }
catch (InvalidArgumentException $e) { $check($e->getMessage() === 'subscription_link_changed', 'Stale form rejected'); }
$check($database->query('SELECT subscription_token FROM vpn_v2_subscriptions WHERE id=1')->getColumn() === $after['subscription_token'], 'Stale form preserves URL');
$database->query("UPDATE vpn_v2_subscriptions SET status='deleted' WHERE id=1");
try { (new SubscriptionLinkService())->rotate(1, hash('sha256', $after['subscription_token']), 3); throw new RuntimeException('Deleted subscription rotated'); }
catch (InvalidArgumentException $e) { $check($e->getMessage() === 'subscription_deleting', 'Deleted subscription protected'); }
$database->query("UPDATE vpn_v2_subscriptions SET status='active' WHERE id=1");
$database->pdo->exec("CREATE TRIGGER reject_event BEFORE INSERT ON vpn_v2_events BEGIN SELECT RAISE(ABORT, 'test'); END");
try { (new SubscriptionLinkService())->rotate(1, hash('sha256', $after['subscription_token']), 3); throw new RuntimeException('Missing rollback'); }
catch (PDOException) { $check($database->query('SELECT subscription_token FROM vpn_v2_subscriptions WHERE id=1')->getColumn() === $after['subscription_token'], 'Audit failure rolls token back'); }
$database->pdo->exec('DROP TRIGGER reject_event');

foreach (['partial_sync', 'sync_error', 'suspended'] as $i => $status) {
    $database->query('INSERT INTO vpn_v2_subscriptions (id,status) VALUES (?,?)', [$i+2, $status]);
}
$check(array_column($configs->activeRevisionMetadata(), 'id') === [1,2,3], 'Global refresh includes working partial subscriptions');

// Exercise the real node query: inactive servers/inbounds are hidden while
// maintenance remains in the result for an unavailable placeholder.
$database->pdo->exec('CREATE TABLE vpn_v2_servers (id INTEGER, name TEXT, code TEXT, panel_url TEXT,
 country_code TEXT, country_name TEXT, city TEXT, show_flag INTEGER, maintenance_mode INTEGER, updated_at TEXT, is_enabled INTEGER)');
$database->pdo->exec('CREATE TABLE vpn_v2_inbounds (id INTEGER, server_id INTEGER, remote_inbound_id TEXT, name TEXT,
 remark TEXT, protocol TEXT, port INTEGER, network TEXT, security TEXT, settings_json TEXT, stream_settings_json TEXT,
 synced_at TEXT, updated_at TEXT, is_enabled INTEGER, status TEXT)');
$database->pdo->exec('CREATE TABLE vpn_v2_subscription_nodes (id INTEGER, subscription_id INTEGER, server_id INTEGER, inbound_id INTEGER,
 sort_order INTEGER, remote_client_id TEXT, client_uuid TEXT, encrypted_client_credential TEXT, client_email TEXT,
 client_sub_id TEXT, flow TEXT, traffic_limit_bytes INTEGER, sync_status TEXT, lkg_snapshot_json TEXT, lkg_snapshot_hash TEXT,
 lkg_validity TEXT, updated_at TEXT, status TEXT, is_obsolete INTEGER)');
$database->pdo->exec('CREATE TABLE vpn_v2_plan_nodes (id INTEGER, plan_id INTEGER, server_id INTEGER, inbound_id INTEGER, sort_order INTEGER, is_enabled INTEGER)');
for ($i=1;$i<=3;$i++) {
    $database->query('INSERT INTO vpn_v2_servers (id,is_enabled,maintenance_mode) VALUES (?,?,?)', [$i, $i===2 ? 0:1, $i===3 ? 1:0]);
    $database->query('INSERT INTO vpn_v2_inbounds (id,server_id,is_enabled,status) VALUES (?,?,1,?)', [$i,$i,'active']);
    $database->query('INSERT INTO vpn_v2_subscription_nodes (id,subscription_id,server_id,inbound_id,sort_order,status,is_obsolete)
     VALUES (?,1,?,?,?, ?,0)', [$i,$i,$i,$i,'active']);
}
$nodes = $configs->activeNodes(1);
$check(array_column($nodes,'server_id') === [1,3], 'Inactive server excluded');
$check($nodes[1]['maintenance_mode'] === 1, 'Maintenance retained as unavailable');
$database->query('UPDATE vpn_v2_inbounds SET is_enabled=0 WHERE id=1');
$check(array_column($configs->activeNodes(1),'server_id') === [3], 'Disabled inbound excluded');
echo json_encode(['status'=>'ok','assertions'=>$count]), PHP_EOL;
