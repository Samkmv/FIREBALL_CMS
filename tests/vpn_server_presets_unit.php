<?php

declare(strict_types=1);

use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository;
use Fireball\VpnManagerV2\Repositories\PlanRepository;
use Fireball\VpnManagerV2\Repositories\ServerPresetRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Services\PlanManagerService;
use Fireball\VpnManagerV2\Services\ServerPresetService;

$root = dirname(__DIR__);
require $root . '/core/Plugins/PluginInterface.php';
require $root . '/core/Plugins/PluginMigrationInterface.php';
require $root . '/plugins/vpn-manager-v2/Plugin.php';

function return_translation(string $key): string {
    static $strings;
    $strings ??= require dirname(__DIR__) . '/plugins/vpn-manager-v2/lang/ru.php';
    return $strings[$key] ?? $key;
}
function get_user(): array { return ['id' => 1, 'role' => 'creator']; }
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function base_href(string $path): string { return $path; }
function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="fixture">'; }
function cache(): object {
    return new class { public function remove(string $key): void {} };
}
function view(): object {
    return new class {
        public function renderPartial(string $name, array $data = []): string {
            if ($name === 'admin/shell_open') return '<main><h1>' . htmlSC($data['title']) . '</h1>' . ($data['actions'] ?? '');
            if ($name === 'admin/shell_close') return '</main>';
            extract($data);
            ob_start();
            require dirname(__DIR__) . '/app/Views/themes/default/' . $name . '.php';
            return (string)ob_get_clean();
        }
    };
}
function db(): object {
    static $database;
    return $database ??= new class {
        public PDO $pdo;
        private int $affected = 0;
        public function __construct() {
            $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        }
        public function query(string $sql, array $parameters = []): object {
            $sql = str_replace(' FOR UPDATE', '', $sql);
            if (str_starts_with($sql, 'UPDATE vpn_v2_subscription_nodes sn')) {
                // SQLite equivalent of markTargetObsolete's MySQL UPDATE JOIN.
                $sql = "UPDATE vpn_v2_subscription_nodes SET is_obsolete = 1, updated_at = ?
                    WHERE subscription_id IN (SELECT id FROM vpn_v2_subscriptions WHERE plan_id = ?)
                      AND server_id = ? AND inbound_id = ? AND status NOT IN ('deleted', 'deleting') AND is_obsolete = 0";
                [$parameters[0], $parameters[1]] = [$parameters[1], $parameters[0]];
            }
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);
            $this->affected = $statement->rowCount();
            return new class($statement) {
                public function __construct(private PDOStatement $statement) {}
                public function get(): array { return $this->statement->fetchAll(PDO::FETCH_ASSOC); }
                public function getOne(): mixed { return $this->statement->fetch(PDO::FETCH_ASSOC); }
                public function getColumn(): mixed { return $this->statement->fetchColumn(); }
            };
        }
        public function getInsertId(): string { return $this->pdo->lastInsertId(); }
        public function rowCount(): int { return $this->affected; }
        public function beginTransaction(): void { $this->pdo->beginTransaction(); }
        public function commit(): void { $this->pdo->commit(); }
        public function rollBack(): void { $this->pdo->rollBack(); }
        public function inTransaction(): bool { return $this->pdo->inTransaction(); }
    };
}
$db = db();
$db->pdo->exec("CREATE TABLE vpn_v2_servers (id INTEGER PRIMARY KEY, name TEXT, code TEXT, status TEXT, is_enabled INTEGER);
CREATE TABLE vpn_v2_inbounds (id INTEGER PRIMARY KEY, server_id INTEGER, name TEXT, remote_inbound_id INTEGER,
 remark TEXT, port INTEGER, protocol TEXT, network TEXT, security TEXT, default_flow TEXT, status TEXT, is_enabled INTEGER);
CREATE TABLE vpn_v2_server_presets (id INTEGER PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE vpn_v2_server_preset_nodes (id INTEGER PRIMARY KEY, preset_id INTEGER REFERENCES vpn_v2_server_presets(id) ON DELETE CASCADE,
 server_id INTEGER, inbound_id INTEGER, flow_override TEXT, sort_order INTEGER, UNIQUE(preset_id,server_id,inbound_id));
CREATE TABLE vpn_v2_plans (id INTEGER PRIMARY KEY, name TEXT, description TEXT, duration_days INTEGER, traffic_limit_bytes INTEGER,
 device_limit INTEGER, ip_limit INTEGER, is_active INTEGER, deleted_at TEXT, created_at TEXT, updated_at TEXT,
 server_preset_id INTEGER REFERENCES vpn_v2_server_presets(id));
CREATE TABLE vpn_v2_plan_nodes (id INTEGER PRIMARY KEY AUTOINCREMENT, plan_id INTEGER, server_id INTEGER, inbound_id INTEGER,
 flow_override TEXT, is_enabled INTEGER, sort_order INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(plan_id,server_id,inbound_id));
CREATE TABLE vpn_v2_subscriptions (id INTEGER PRIMARY KEY, plan_id INTEGER, status TEXT, subscription_token TEXT, revision INTEGER,
 config_updated_at TEXT, updated_at TEXT, starts_at TEXT, expires_at TEXT);
CREATE TABLE vpn_v2_subscription_nodes (id INTEGER PRIMARY KEY, subscription_id INTEGER, server_id INTEGER, inbound_id INTEGER,
 status TEXT, is_obsolete INTEGER, updated_at TEXT, client_uuid TEXT);
CREATE TABLE vpn_v2_subscription_items (id INTEGER PRIMARY KEY, parent_subscription_id INTEGER, item_type TEXT, child_subscription_id INTEGER,
 connection_id INTEGER, is_enabled INTEGER, deleted_at TEXT);
CREATE TABLE vpn_v2_events (id INTEGER PRIMARY KEY, event_type TEXT, subscription_id INTEGER, node_id INTEGER, server_id INTEGER,
 user_id INTEGER, admin_id INTEGER, context_json TEXT, created_at TEXT);
CREATE TABLE vpn_v2_reconcile_operations (id INTEGER PRIMARY KEY, operation_id TEXT, plan_id INTEGER, status TEXT,
 initiated_by INTEGER, last_subscription_id INTEGER, batch_size INTEGER, options_json TEXT, total_count INTEGER,
 processed_count INTEGER, success_count INTEGER, failure_count INTEGER, skipped_count INTEGER,
 started_at TEXT, finished_at TEXT, last_error TEXT, created_at TEXT, updated_at TEXT);
INSERT INTO vpn_v2_servers VALUES (1,'Germany','de','online',1), (2,'Netherlands','nl','online',1), (3,'France','fr','online',1);
ALTER TABLE vpn_v2_servers ADD COLUMN country_code TEXT;
ALTER TABLE vpn_v2_servers ADD COLUMN maintenance_mode INTEGER DEFAULT 0;
ALTER TABLE vpn_v2_servers ADD COLUMN allow_new_connections INTEGER DEFAULT 1;
INSERT INTO vpn_v2_inbounds VALUES (10,1,'DE 443',1,'',443,'vless','tcp','reality',NULL,'active',1),
 (20,2,'NL 443',1,'',443,'vless','tcp','reality',NULL,'active',1), (30,3,'FR 443',1,'',443,'vless','tcp','reality',NULL,'active',1);");

$check = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$plans = new PlanRepository();
$presets = new ServerPresetRepository();
$service = new ServerPresetService();
$manager = new PlanManagerService();
$single = static fn(int $server, int $order): array => [
    'server_id' => $server, 'inbound_id' => $server * 10, 'sort_order' => $order,
];
$manual = $manager->create(['name' => 'Ручной тариф', 'nodes' => [$single(1, 15)], 'is_active' => 1]);
$service->save(0, ['name' => 'Европа', 'nodes' => [$single(1, 2), $single(2, 1)]], 1);
$presetId = (int)$presets->all()[0]['id'];
$a = $manager->create(['name' => 'Start 30', 'server_preset_id' => (string)$presetId, 'is_active' => 1]);
$b = $manager->create(['name' => 'Ultra', 'server_preset_id' => (string)$presetId, 'is_active' => 1]);
$targetOrders = static fn(int $id): array => array_map('intval', array_column($plans->nodes($id), 'server_id'));
$check($targetOrders($a) === [2, 1] && $targetOrders($b) === [2, 1], 'Plans inherit matching ordered server sets.');
$oldPlanNodeId = (int)$plans->nodes($a)[1]['id'];
foreach ([$a,$b,$manual] as $id) {
    $db->query(
        "INSERT INTO vpn_v2_subscriptions (id, plan_id, status, subscription_token, revision, starts_at) VALUES (?,?,'active',?,1,'2020-01-01')",
        [$id,$id,'token-'.$id]
    );
    $db->query(
        "INSERT INTO vpn_v2_subscription_nodes (id,subscription_id,server_id,inbound_id,status,is_obsolete,client_uuid)
         VALUES (?,?,1,10,'active',0,?)",
        [$id,$id,'credential-'.$id]
    );
}
try {
    $service->delete($presetId, 1);
    throw new RuntimeException('A linked preset must not be deleted.');
} catch (ValidationException) {
    $check($presets->find($presetId) !== null, 'A linked preset remains present.');
}
$report = $service->save($presetId, ['name' => 'Европа', 'nodes' => [$single(2, 2), $single(3, 1)]], 1);
$check($report === ['plans' => 2, 'queued' => 2], 'One edit enqueues both affected plans.');
$check($targetOrders($a) === [3, 2] && $targetOrders($b) === [3, 2] && $targetOrders($manual) === [1], 'Only linked plans follow order and topology.');
$check((int)$db->query("SELECT COUNT(*) FROM vpn_v2_subscription_nodes WHERE is_obsolete = 1")->getColumn() === 2, 'Only removed targets are obsolete.');
$check($db->query('SELECT client_uuid FROM vpn_v2_subscription_nodes WHERE id = ?', [$a])->getColumn() === 'credential-'.$a, 'Local identity is retained.');
$check((int)$db->query('SELECT revision FROM vpn_v2_subscriptions WHERE id = ?', [$a])->getColumn() > 1, 'Subscription config is invalidated immediately.');
$check((int)$db->query('SELECT revision FROM vpn_v2_subscriptions WHERE id = ?', [$manual])->getColumn() === 1, 'Manual plan caches remain unchanged.');
$check($db->query('SELECT subscription_token FROM vpn_v2_subscriptions WHERE id = ?', [$a])->getColumn() === 'token-'.$a, 'The subscription link remains valid.');
try {
    (new PlanReconciliationRepository())->createOrClaimNode(['plan_id' => $a], ['plan_node_id' => $oldPlanNodeId], null);
    throw new RuntimeException('A removed worker target was allowed to be reprovisioned.');
} catch (ProvisioningException) {}

try {
    (new SubscriptionRepository())->createLocal(['plan_id' => $a], [['plan_node_id' => $oldPlanNodeId]]);
    throw new RuntimeException('A subscription was created from a stale preset.');
} catch (ValidationException $error) {
    $check($error->getMessage() === return_translation('vpn_manager_v2_create_plan_changed'), 'Stale topology can be retried safely.');
}

try {
    $service->save($presetId, ['name' => 'Broken', 'nodes' => [$single(2, 1), $single(2, 2)]], 1);
    throw new RuntimeException('A duplicate target was allowed.');
} catch (ValidationException) {
    $check($presets->find($presetId)['name'] === 'Европа' && $targetOrders($a) === [3, 2], 'Validation failure is atomic.');
}
$report = $service->save($presetId, ['name' => 'Европа 2', 'nodes' => [$single(3, 1), $single(2, 2)]], 1);
$check($report === ['plans' => 0, 'queued' => 0], 'Renaming preserves pending operations and plan targets.');

if (getenv('VPN_PRESET_FIXTURE_DIR')) {
    $directory = realpath((string)getenv('VPN_PRESET_FIXTURE_DIR'));
    $allowed = realpath(__DIR__ . '/.tmp');
    if (!$directory || !$allowed || !str_starts_with($directory, $allowed . '/')) throw new RuntimeException('Invalid fixture path');
    $data = [
        'presets' => $presets->all(), 'servers' => $plans->serversForForm(), 'inbounds' => $plans->inboundsForForm(),
        'linkedPlans' => $presets->plans($presetId), 'preset' => $presets->find($presetId),
    ];
    $render = static function (string $screen, array $data) use ($root): string {
        extract($data);
        ob_start();
        require $root . '/plugins/vpn-manager-v2/views/admin/' . $screen . '.php';
        return (string)ob_get_clean();
    };
    foreach (['server-presets', 'server-preset-form', 'plan-form'] as $screen) {
        $page = $screen === 'plan-form' ? ['plan' => $plans->find($a), 'selectedNodes' => $plans->nodes($a)]
            : ['selectedNodes' => $presets->nodes($presetId)];
        $viewData = FireballPluginVpnManagerV2::viewData($screen === 'plan-form' ? 'plans' : 'servers', $data + $page + [
            'title' => ['server-presets' => 'Пресеты серверов', 'server-preset-form' => 'Редактировать пресет', 'plan-form' => 'Редактировать тариф'][$screen],
        ]);
        file_put_contents($directory . '/' . $screen . '.html', $render($screen, $viewData));
    }
}
$manager->update($a, ['name' => 'Start 30', 'server_preset_id' => '', 'nodes' => [$single(3, 1)], 'is_active' => 1]);
$check($plans->find($a)['server_preset_id'] === null && $targetOrders($a) === [3], 'Switching to manual applies only the chosen nodes.');
$check($manager->delete($b), 'Archiving a plan releases its preset.');
$service->delete($presetId, 1);
$check($presets->find($presetId) === null && $targetOrders($b) === [3, 2], 'Deleting an unused preset leaves archived plan topology intact.');
echo "PASS shared presets, legacy manual plans, active subscription revisions, queue, validation and delete protection.\n";
