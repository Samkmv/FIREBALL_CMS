<?php
declare(strict_types=1);

// Exercise real plugin routes and views against a fixture; no application SQL or push.
define('ROOT', dirname(__DIR__));
define('PATH', 'https://cms.example.invalid');
define('FIREBALL_CLI', true);
require ROOT . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';
require ROOT . '/plugins/toy-car-rental/Plugin.php';

final class RentalAccessResponseStop extends RuntimeException
{
    public function __construct(public mixed $payload, public int $status = 200) { parent::__construct('Response stopped'); }
}
final class RentalAccessResponse extends FBL\Response
{
    public function redirect($url = '') { throw new RentalAccessResponseStop($url, 302); }
    public function json($data, $code = 200) { throw new RentalAccessResponseStop($data, $code); }
}
final class RentalAccessDatabase extends FBL\Database
{
    public array $roles = [
        1 => ['id' => 1, 'name' => 'Administrator', 'slug' => 'admin'],
        4 => ['id' => 4, 'name' => 'Moderator', 'slug' => 'moderator'],
        7 => ['id' => 7, 'name' => 'Rental operator', 'slug' => 'rental-operator'],
    ];
    public array $settings = [];
    public array $queries = [];
    public array $rideInsert = [];
    public array $car = ['id' => 8, 'name' => 'Test car', 'number' => '2', 'status' => 'available', 'price_per_minute' => 20];
    public bool $emptyFleet = false;
    private array $rows = [];
    private bool $transaction = false;
    public function __construct() {}
    public function query(string $query, array $params = []): static
    {
        $this->queries[] = $query;
        $this->rows = [];
        if (str_contains($query, 'FROM user_roles')) {
            $this->rows = array_values(array_filter($this->roles, static fn(array $role): bool =>
                !in_array($role['slug'], ['admin', 'creator'], true) && (!$params || (int)$role['id'] === (int)$params[0])));
        } elseif (str_starts_with($query, 'INSERT INTO plugin_settings')) {
            $this->settings[$params[1]] = $params[2];
        } elseif (str_contains($query, 'FROM plugin_settings')) {
            foreach ($this->settings as $key => $value) $this->rows[] = ['setting_key' => $key, 'setting_value' => $value];
        } elseif (str_starts_with($query, 'INSERT INTO toy_rental_rides')) {
            $this->rideInsert = $params;
        } elseif (str_starts_with($query, 'UPDATE toy_rental_cars')) {
            // State mutation is recorded, but the next test starts with an available car.
        } elseif (str_contains($query, 'SELECT * FROM toy_rental_cars')) {
            $this->rows = $this->emptyFleet ? [] : [$this->car];
        } elseif (str_contains($query, 'FROM toy_rental_rides')) {
            // No current rides, expired rides or historical revenue in this fixture.
        } else {
            throw new RuntimeException('Unexpected fixture query: ' . $query);
        }
        return $this;
    }
    public function get(): false|array { return $this->rows; }
    public function getOne() { return $this->rows[0] ?? false; }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
}

$app = (new ReflectionClass(FBL\Application::class))->newInstanceWithoutConstructor();
FBL\Application::$app = $app;
$app->db = $database = new RentalAccessDatabase();
$app->session = (new ReflectionClass(FBL\Session::class))->newInstanceWithoutConstructor();
$app->view = new FBL\View(LAYOUT);
$app->response = new RentalAccessResponse();
$app->hooks = new FBL\Plugins\HookManager();
$app->plugins = new FBL\Plugins\PluginManager();
$app->set('lang', LANGS['ru']);
(new ReflectionProperty(App\Models\SiteSetting::class, 'cache'))->setValue(null, []);
(new ReflectionProperty(FBL\Application::class, 'installationStatus'))->setValue($app, ['state' => 'not_installed']);
$_GET = $_POST = $_FILES = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SESSION = ['needCSRFToken' => 'rental-access-test'];
$app->request = new FBL\Request('/profile/toy-rental');
$app->router = new FBL\Router($app->request, $app->response);
require ROOT . '/config/routes.php';
(new FireballPluginToyCarRental())->boot();
$router = $app->router;
require ROOT . '/plugins/toy-car-rental/admin.php';
require ROOT . '/plugins/toy-car-rental/routes.php';
$checks = 0;

function verifyRentalAccess(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function rentalTestUser(?string $role): void
{
    $_SESSION = ['needCSRFToken' => 'rental-access-test'];
    if ($role !== null) $_SESSION['user'] = ['id' => 1, 'role' => $role, 'name' => 'Test', 'login' => 'test'];
}
function rentalTestRole(int $id): void
{
    global $app, $database;
    $database->settings['operator_role_id'] = json_encode($id);
    (new ReflectionProperty(FBL\Plugins\PluginManager::class, 'settingsCache'))->setValue($app->plugins, []);
}
function rentalTestRequest(string $path, string $method = 'GET', array $data = []): void
{
    global $app;
    $_POST = $data;
    $_SERVER['REQUEST_METHOD'] = $method;
    $app->request = new FBL\Request($path);
}
function rentalTestAdminGuard(string $path, string $method): bool
{
    rentalTestRequest($path, $method);
    try { (new FBL\Middleware\Admin())->handle(); return true; }
    catch (RentalAccessResponseStop $stop) {
        verifyRentalAccess($stop->status === 302 && $stop->payload === base_href('/profile'), 'Denied admin access must return to the profile.');
        return false;
    }
}

rentalTestRole(0);
foreach (['moderator', 'rental-operator', 'user', null] as $role) {
    rentalTestUser($role);
    verifyRentalAccess(!FireballPluginToyCarRental::canOperate(), 'No selected role must mean no ordinary operators.');
}
rentalTestRole(7);
verifyRentalAccess(array_column(FireballPluginToyCarRental::operatorRoles(), 'id') === [4, 7], 'Selectable roles exclude full administrators.');
foreach (['rental-operator' => true, 'moderator' => false, 'user' => false, 'admin' => true, 'creator' => true] as $role => $allowed) {
    rentalTestUser($role);
    verifyRentalAccess(FireballPluginToyCarRental::canOperate() === $allowed, 'Wrong access for ' . $role);
    $menu = apply_filters('profile_menu', [], get_user());
    verifyRentalAccess(count($menu) === (int)$allowed, 'Profile link must follow the selected role.');
    if ($allowed) verifyRentalAccess($menu[0]['href'] === base_href('/profile/toy-rental'), 'Profile link must open the operator page.');
    verifyRentalAccess(check_admin() === in_array($role, ['admin', 'creator'], true), 'Selecting a role must not grant CMS administration.');
}
rentalTestUser('rental-operator');
$database->roles[7]['slug'] = 'renamed-operator';
verifyRentalAccess(!FireballPluginToyCarRental::canOperate(), 'The former slug must lose access after a role rename.');
rentalTestUser('renamed-operator');
verifyRentalAccess(FireballPluginToyCarRental::canOperate(), 'Role IDs keep access working after a rename.');
unset($database->roles[7]);
verifyRentalAccess(!FireballPluginToyCarRental::canOperate(), 'Deleting the selected role must revoke access.');
$database->roles[7] = ['id' => 7, 'name' => 'Rental operator', 'slug' => 'rental-operator'];
rentalTestRole(4);
rentalTestUser('moderator');
verifyRentalAccess(FireballPluginToyCarRental::canOperate(), 'Moderators may be selected explicitly.');
rentalTestUser('rental-operator');
verifyRentalAccess(!FireballPluginToyCarRental::canOperate(), 'Changing the selection must revoke the previous role.');
rentalTestRole(7);

foreach ($router->getRoutes() as $route) {
    if (str_starts_with($route['path'], '/admin')) {
        verifyRentalAccess(in_array('auth', $route['middleware'], true) && in_array('admin', $route['middleware'], true), 'Administrative routes must retain their CMS guards.');
        foreach (['rental-operator', 'moderator', 'admin', 'creator'] as $role) {
            rentalTestUser($role);
            verifyRentalAccess(rentalTestAdminGuard($route['path'], $route['method'][0]) === in_array($role, ['admin', 'creator'], true), 'Wrong administrative access: ' . $route['path']);
        }
    }
    if (!str_starts_with($route['path'], '/profile/toy-rental')) continue;
    verifyRentalAccess($route['middleware'] === ['auth'], 'Operator pages require a signed-in user.');
    if (in_array('POST', $route['method'], true)) verifyRentalAccess($route['needCSRFToken'], 'Operator mutations must retain CSRF protection.');
    foreach (['moderator', 'user', null] as $role) {
        rentalTestUser($role);
        rentalTestRequest($route['path'], $route['method'][0]);
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $database->queries = [];
        try { ($route['callback'])(); throw new RuntimeException('Unauthorized operator route ran.'); }
        catch (RentalAccessResponseStop $stop) {
            verifyRentalAccess($stop->status === 403 && $stop->payload['status'] === false, 'Every operator callback must reject unauthorized access.');
        }
        verifyRentalAccess(count(array_filter($database->queries, static fn(string $sql): bool => !str_starts_with($sql, 'SELECT ') || (!str_contains($sql, 'FROM user_roles') && !str_contains($sql, 'FROM plugin_settings')))) === 0, 'Reject access before any fleet or ride query.');
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }
}

rentalTestUser('rental-operator');
foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $app->set('lang', LANGS[$locale]);
    FBL\Language::$lang_data = array_merge(require APP . '/Languages/' . $locale . '.php', require ROOT . '/plugins/toy-car-rental/lang/' . $locale . '.php');
    rentalTestRequest('/' . $locale . '/profile/toy-rental');
    verifyRentalAccess(FireballPluginToyCarRental::operatorBasePath() === '/profile/toy-rental', 'Localized profiles must use operator endpoints.');
    $data = FireballPluginToyCarRental::operatorViewData(['cars' => [$database->car]]);
    $html = plugin_view('toy-car-rental', 'profile-operator', $data, false);
    verifyRentalAccess(!str_contains($html, '/admin/') && !str_contains($html, 'toy-rental-tabs') && !str_contains($html, 'toy-rental-stats'), 'Operator page must not contain admin sections or totals.');
    verifyRentalAccess(str_contains($html, base_href('/profile/toy-rental/rides/start')) && str_contains($html, base_href('/profile/toy-rental/rides/complete')), 'All forms must use protected profile actions.');
    verifyRentalAccess(str_contains($html, base_href('/profile/toy-rental/state')), 'Live timer state must come from the profile endpoint.');
    verifyRentalAccess(str_contains($data['styles'][0], '/profile/toy-rental/assets/toy-rental.css'), 'Profile styling must use plugin assets.');
    foreach (['toy_rental_settings_operator_role', 'toy_rental_error_operator_access', 'toy_rental_back_profile'] as $key) {
        verifyRentalAccess(FireballPluginToyCarRental::t($key) !== $key, 'New labels must be translated in ' . $locale);
    }
    verifyRentalAccess(FireballPluginToyCarRental::operatorRoleLabel(['slug' => 'moderator', 'name' => 'Модератор']) === FBL\Language::get('tpl_auth_role_moderator'), 'Translate system role labels in rental settings.');
    verifyRentalAccess(FireballPluginToyCarRental::operatorRoleLabel(['slug' => 'user', 'name' => 'Пользователь']) === FBL\Language::get('tpl_auth_role_user'), 'Translate the system user role.');
    verifyRentalAccess(FireballPluginToyCarRental::operatorRoleLabel(['slug' => 'custom', 'name' => 'My operator team']) === 'My operator team', 'Retain user-created role names.');
}
$app->set('lang', LANGS['ru']);
rentalTestRequest('/profile/toy-rental');
$database->emptyFleet = true;
$html = plugin_view('toy-car-rental', 'profile-operator', FireballPluginToyCarRental::operatorViewData(['cars' => []]), false);
verifyRentalAccess(!str_contains($html, '/admin/'), 'An empty operator panel must not offer administrative car creation.');
$database->emptyFleet = false;
$state = FireballPluginToyCarRental::operatorState();
verifyRentalAccess(!isset($state['stats']) && count($state['cards']) === 1, 'Operators see current cards, without administrative totals.');

foreach ([0, 7] as $roleId) {
    FireballPluginToyCarRental::saveSettings(array_merge(FireballPluginToyCarRental::defaultSettings(), ['operator_role_id' => $roleId]));
    verifyRentalAccess(FireballPluginToyCarRental::settings()['operator_role_id'] === $roleId, 'Role selection must persist through native plugin settings.');
}
foreach ([true, false] as $creatorCopy) {
    $settings = array_merge(FireballPluginToyCarRental::defaultSettings(), ['operator_role_id' => 7]);
    if (!$creatorCopy) unset($settings['creator_notifications_enabled']);
    FireballPluginToyCarRental::saveSettings($settings);
    verifyRentalAccess(FireballPluginToyCarRental::settings()['creator_notifications_enabled'] === $creatorCopy, 'Persist the creator copy switch, including an unchecked form field.');
}
foreach ([-1, 'invalid', 999, 1] as $invalidRole) {
    $before = $database->settings;
    try {
        FireballPluginToyCarRental::saveSettings(['operator_role_id' => $invalidRole]);
        throw new LogicException('Invalid role was accepted.');
    } catch (RuntimeException $exception) {
        verifyRentalAccess($database->settings === $before, 'Invalid role selection must not change any settings.');
    }
}
rentalTestRequest('/profile/toy-rental/rides/start', 'POST', ['needCSRFToken' => 'wrong']);
verifyRentalAccess(!$router->checkCSRFToken(), 'Reject a forged operator form token.');
rentalTestRequest('/profile/toy-rental/rides/start', 'POST', ['needCSRFToken' => 'rental-access-test', 'car_id' => 8, 'billing_type' => 'fixed', 'duration_minutes' => 5, 'price_per_minute' => 0]);
verifyRentalAccess($router->checkCSRFToken(), 'Accept the current session token.');
$startRoute = array_values(array_filter($router->getRoutes(), static fn(array $route): bool => $route['path'] === '/profile/toy-rental/rides/start'))[0];
try { ($startRoute['callback'])(); }
catch (RentalAccessResponseStop $stop) {
    verifyRentalAccess($stop->status === 302 && $stop->payload === base_href('/profile/toy-rental'), 'Non-AJAX starts must return to the operator panel.');
}
verifyRentalAccess($database->rideInsert[2] === 20.0 && $database->rideInsert[8] === 100.0, 'A forged operator rate must not change fixed ride pricing.');
rentalTestRequest('/admin/toy-rental');
verifyRentalAccess(FireballPluginToyCarRental::operatorBasePath() === '/admin/toy-rental', 'Administrative cards must retain administrative endpoints.');
$app->hooks = new FBL\Plugins\HookManager();
verifyRentalAccess(apply_filters('profile_menu', [], get_user()) === [], 'An inactive plugin must not add a profile entrance.');
echo "$checks rental role and operator access checks passed; no application SQL or push.\n";
