<?php

declare(strict_types=1);

// Exercise real repository transactions and templates against an in-memory database.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../plugins/subscriptions/src/Support/Money.php';
require_once __DIR__ . '/../plugins/subscriptions/src/Support/RussianPhone.php';
require_once __DIR__ . '/../plugins/subscriptions/src/Services/PublicOfferService.php';
require_once __DIR__ . '/../plugins/subscriptions/src/Repositories/PlanRepository.php';

use Fireball\Subscriptions\Repositories\PlanRepository;

final class FeatureTestDatabase
{
    public readonly PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE subscription_plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT, name TEXT, description TEXT, features_json TEXT, permission_details_json TEXT,
            price_minor INTEGER, currency TEXT, duration_unit TEXT, duration_value INTEGER,
            grace_period_days INTEGER, is_recurring INTEGER, auto_renew_enabled INTEGER,
            is_active INTEGER, is_public INTEGER, is_popular INTEGER, sort_order INTEGER,
            created_at TEXT, updated_at TEXT
        )');
        $this->pdo->exec('CREATE TABLE subscription_plan_permissions (plan_id INTEGER, permission_key TEXT, permission_value TEXT)');
        $this->pdo->exec('CREATE TABLE subscription_plan_resources (
            plan_id INTEGER, resource_type TEXT, resource_id TEXT, can_view INTEGER, can_download INTEGER,
            archive_days INTEGER, max_fragment_minutes INTEGER, created_at TEXT, updated_at TEXT
        )');
    }

    public function query(string $sql, array $params = []): object
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return new class($statement) {
            public function __construct(private PDOStatement $statement) {}
            public function get(): array { return $this->statement->fetchAll(PDO::FETCH_ASSOC); }
            public function getOne(): array|false { return $this->statement->fetch(PDO::FETCH_ASSOC); }
        };
    }

    public function getInsertId(): int { return (int)$this->pdo->lastInsertId(); }
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
    public function inTransaction(): bool { return $this->pdo->inTransaction(); }
}

function db(): FeatureTestDatabase
{
    static $database = new FeatureTestDatabase();
    return $database;
}

final class FireballPluginSubscriptions
{
    public static string $locale = 'ru';
    public static function t(string $key): string
    {
        $translations = require __DIR__ . '/../plugins/subscriptions/lang/' . self::$locale . '.php';
        return $translations[$key] ?? $key;
    }
}

function make_slug(string $value, string $fallback = ''): string { return strtolower(trim($value)); }
function htmlSC(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function base_href(string $value = ''): string { return $value; }
function check_auth(): bool { return true; }
function get_alerts(): void {}
function plugin_setting(string $plugin, string $key, mixed $default = null): mixed { return $default; }
function get_csrf_field(): string { return '<input type="hidden" name="csrf" value="test">'; }
function render_partial(string $name, array $data = []): string { return ''; }
function view(): object
{
    return new class {
        public function renderPartial(string $name, array $data = []): string { return ''; }
    };
}

$checks = 0;
function featureCheck(bool $condition, string $label): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($label);
}

function featureView(string $name, array $data): string
{
    extract($data);
    ob_start();
    require __DIR__ . '/../plugins/subscriptions/views/' . $name . '.php';
    return (string)ob_get_clean();
}

$repository = new PlanRepository();
$input = ['name' => 'Test', 'slug' => 'test', 'price' => '150', 'is_active' => 1, 'is_public' => 1, 'permissions' => ['posts.view_paid' => 1]];
$id = $repository->save($input);
featureCheck($repository->find($id)['features'] === null, 'Legacy plans use automatic features');
$items = [
    ['title' => ' Archive ', 'description' => " First line\nSecond line "],
    ['title' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(1)>'],
];
$customInput = $input + ['features_present' => 1, 'features_mode' => 'custom', 'features' => $items];
$repository->save($customInput, $id);
$saved = $repository->find($id);
featureCheck($saved['features'][0] === ['title' => 'Archive', 'description' => "First line\nSecond line"], 'Titles and descriptions persist with trimmed edges');
featureCheck($saved['permissions'] === ['posts.view_paid' => true], 'Saving descriptive items preserves access permissions');
featureCheck($repository->purchaseSnapshot($saved)['features'] === $saved['features'], 'Purchase snapshot retains the displayed terms');
featureCheck($repository->all(true)[0]['features'] === $saved['features'], 'Public plan list contains saved items');
$copyId = $repository->clone($id);
featureCheck($repository->find($copyId)['features'] === $saved['features'], 'Cloning copies titles and descriptions');
$repository->save($input, $id);
featureCheck($repository->find($id)['features'] === $saved['features'], 'Callers omitting feature fields preserve saved items');
foreach ([['description' => 'No title'], ['title' => str_repeat('я', 256)], ['title' => 'Title', 'description' => str_repeat('x', 2001)], ['title' => ['invalid']]] as $invalid) {
    try {
        $repository->save(array_replace($customInput, ['name' => 'Changed', 'features' => [$invalid]]), $id);
        throw new RuntimeException('Invalid item accepted');
    } catch (InvalidArgumentException) {
        featureCheck($repository->find($id)['name'] === 'Test', 'Invalid input rolls back plan changes');
    }
}
foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    FireballPluginSubscriptions::$locale = $locale;
    $data = [
        'plan' => $saved, 'plans' => [$saved], 'permissions' => $saved['permissions'],
        'subscription' => ['plan_name' => 'Test', 'plan_id' => $id, 'status' => 'active', 'ends_at' => '2099-01-01'],
        'profile' => array_fill_keys(['first_name', 'last_name', 'country', 'region', 'city', 'street', 'house', 'apartment', 'postal_code', 'email', 'phone'], ''),
    ];
    foreach (['public/plans', 'public/checkout', 'public/account'] as $view) {
        $html = featureView($view, $data);
        featureCheck(str_contains($html, 'Archive') && str_contains($html, 'First line<br'), $view . ' renders titles and multiline descriptions');
        featureCheck(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<img src=x'), $view . ' escapes feature HTML');
    }
    $html = featureView('admin/plan-form', $data);
    featureCheck(str_contains($html, 'value="custom" selected'), 'Editor restores custom mode');
    featureCheck(str_contains($html, '&lt;script&gt;') && !str_contains($html, 'subscriptions_features_'), 'Editor escapes item text and translates labels');
}
$repository->save(array_replace($customInput, ['features' => []]), $id);
featureCheck($repository->find($id)['features'] === [], 'Removing all items persists an empty custom list');
$repository->save(array_replace($customInput, ['features_mode' => 'automatic']), $id);
featureCheck($repository->find($id)['features'] === null, 'Switching to automatic mode clears the override');

$permissionInput = $input + [
    'features_present' => 1, 'features_mode' => 'automatic', 'permission_details_present' => 1,
    'permission_details' => [
        'posts.view_paid' => ['title' => ' Renamed publication ', 'description' => " First line\nDetails "],
        'videos.view_paid' => ['title' => 'Hidden video', 'description' => 'Hidden description'],
        'camera_archive.max_days' => ['title' => 'History', 'description' => 'Archive retention'],
        'unsupported.permission' => ['title' => 'Unsupported', 'description' => 'Ignored'],
    ],
];
$repository->save($permissionInput, $id);
$permissionPlan = $repository->find($id);
featureCheck($permissionPlan['permission_details']['posts.view_paid'] === ['title' => 'Renamed publication', 'description' => "First line\nDetails"], 'Built-in item text persists');
featureCheck(!isset($permissionPlan['permission_details']['unsupported.permission']), 'Unknown permission metadata is ignored');
featureCheck($permissionPlan['permissions'] === ['posts.view_paid' => true], 'Editing built-in item text preserves enabled permissions');
featureCheck($repository->purchaseSnapshot($permissionPlan)['permission_details'] === $permissionPlan['permission_details'], 'Purchase snapshot keeps built-in item text');
featureCheck($repository->find($repository->clone($id))['permission_details'] === $permissionPlan['permission_details'], 'Clone keeps built-in item text');
$repository->save($input, $id);
featureCheck($repository->find($id)['permission_details'] === $permissionPlan['permission_details'], 'Omitting metadata preserves built-in item text');
$repository->save(array_replace($permissionInput, ['permissions' => []]), $id);
featureCheck($repository->find($id)['permission_details'] === $permissionPlan['permission_details'], 'Disabled items retain their titles and descriptions');
foreach ([false, ['posts.view_paid' => 'invalid'], ['posts.view_paid' => ['title' => ['invalid']]], ['posts.view_paid' => ['title' => str_repeat('я', 256)]], ['posts.view_paid' => ['description' => str_repeat('x', 2001)]]] as $invalid) {
    try {
        $repository->save(array_replace($permissionInput, ['name' => 'Changed', 'permission_details' => $invalid]), $id);
        throw new RuntimeException('Invalid built-in item accepted');
    } catch (InvalidArgumentException) {
        featureCheck($repository->find($id)['name'] === 'Test', 'Invalid metadata rolls back plan changes');
    }
}
foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    FireballPluginSubscriptions::$locale = $locale;
    $data['plan'] = $permissionPlan;
    $data['plans'] = [$permissionPlan];
    $data['permissions'] = $permissionPlan['permissions'];
    foreach (['public/plans', 'public/checkout', 'public/account'] as $view) {
        $html = featureView($view, $data);
        featureCheck(str_contains($html, 'Renamed publication') && str_contains($html, 'First line<br'), $view . ' shows edited built-in items');
        featureCheck(!str_contains($html, 'Hidden video') && !str_contains($html, 'Hidden description'), $view . ' hides disabled items');
    }
    $html = featureView('admin/plan-form', $data);
    foreach (PlanRepository::PERMISSIONS as $permission) {
        featureCheck(str_contains($html, 'name="permission_details[' . $permission . '][title]"') && str_contains($html, 'name="permission_details[' . $permission . '][description]"'), 'All built-in items have title and description inputs');
    }
    featureCheck(str_contains($html, 'value="Hidden video"'), 'Disabled item text is available for editing');
}
$escapedPlan = array_replace($permissionPlan, ['permission_details' => ['posts.view_paid' => ['title' => '<script>title</script>', 'description' => '<img src=x onerror=alert(1)>']]]);
foreach (['public/plans', 'public/checkout', 'public/account'] as $view) {
    $html = featureView($view, array_replace($data, ['plan' => $escapedPlan, 'plans' => [$escapedPlan]]));
    featureCheck(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<img src=x'), $view . ' escapes built-in item text');
}
$repository->save(array_replace($permissionInput, ['permission_details' => ['posts.view_paid' => ['description' => 'Description only']]]), $id);
featureCheck($repository->find($id)['permission_details']['posts.view_paid']['title'] === '', 'Description can be set while using the default title');
$allPermissions = array_fill_keys(PlanRepository::PERMISSIONS, true);
$allPermissions['camera_archive.max_days'] = 30;
$allPermissions['camera_archive.max_fragment_minutes'] = 15;
$allDetails = [];
foreach (PlanRepository::PERMISSIONS as $permission) {
    $allDetails[$permission] = ['title' => 'Title ' . $permission, 'description' => 'Description ' . $permission];
}
$allPlan = array_replace($permissionPlan, ['permissions' => $allPermissions, 'permission_details' => $allDetails]);
foreach (['public/plans', 'public/checkout', 'public/account'] as $view) {
    $html = featureView($view, array_replace($data, ['plan' => $allPlan, 'plans' => [$allPlan], 'permissions' => $allPermissions]));
    foreach (PlanRepository::PERMISSIONS as $permission) {
        featureCheck(str_contains($html, 'Title ' . $permission) && str_contains($html, 'Description ' . $permission), $view . ' renders every enabled built-in item');
    }
    featureCheck(str_contains($html, '30') && str_contains($html, '15'), $view . ' retains numeric limits');
}
$repository->save(array_replace($permissionInput, ['permission_details' => []]), $id);
featureCheck($repository->find($id)['permission_details'] === [], 'Clearing metadata restores default text');
echo "Subscription plan features passed. {$checks} checks.\n";
