<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
define('STORAGE', sys_get_temp_dir() . '/fireball-favorites-fixture-absent');
define('LANGS', ['ru' => ['code' => 'ru', 'base' => 1], 'en' => ['code' => 'en', 'base' => 0]]);
define('DEFAULT_LOCALE', 'ru'); define('DEBUG', 0);
define('PAGINATION_SETTINGS', ['perPage' => 20, 'midSize' => 2, 'maxPages' => 7, 'tpl' => 'pagination/base']);
function db(): object { return $GLOBALS['favoriteDb']; }
function app(): object { return new class { public mixed $db = null; public function get(string $key): mixed { return ['code' => 'ru']; } }; }
function base_href(string $path): string { return '/cms' . $path; }
function return_translation(string $key): string { return $key; }
function abort(string $message = '', int $status = 404): never { throw new RuntimeException($message, $status); }
function request(): object { return $GLOBALS['request'] ??= new class {
    public string $uri = '/en/profile/favorites?filter=all'; public array $get = [];
    public function get(string $key, mixed $default = null): mixed { return $this->get[$key] ?? $default; }
}; }
$GLOBALS['favoriteDb'] = new class {
    public PDO $pdo; public int $queries = 0; private PDOStatement $statement;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, excerpt TEXT, image TEXT, published_at TEXT NOT NULL, hide_placeholder_image INTEGER, category_id INTEGER, category TEXT, is_published INTEGER);
            CREATE TABLE post_categories (id INTEGER PRIMARY KEY, name TEXT, name_ru TEXT, name_en TEXT);
            INSERT INTO post_categories VALUES (1, "Category", "Категория", "Category");
            CREATE TABLE user_favorites (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, entity_type TEXT, entity_id INTEGER, created_at TEXT, UNIQUE(user_id, entity_type, entity_id))');
        for ($id = 1; $id <= 45; $id++) {
            $this->query('INSERT INTO posts VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$id, 'Post ' . $id, 'post-' . $id, '<p>Excerpt</p>', '/uploads/photo.jpg', '2026-10-01 10:00:00', 0, 1, 'Legacy', 1]);
        }
    }
    public function query(string $sql, array $args = []): static {
        $this->queries++;
        // Test only: equivalent conflict semantics; production SQL remains MySQL.
        $sql = str_replace("ON DUPLICATE KEY UPDATE entity_type = 'post'", 'ON CONFLICT(user_id, entity_type, entity_id) DO NOTHING', $sql);
        $this->statement = $this->pdo->prepare($sql); $this->statement->execute($args); return $this;
    }
    public function getColumn(): mixed { return $this->statement->fetchColumn(); }
    public function getOne(): array|false { return $this->statement->fetch(PDO::FETCH_ASSOC); }
    public function findOne(string $table, int $id): array|false { return $this->query("SELECT * FROM {$table} WHERE id = ?", [$id])->getOne(); }
    public function rowCount(): int { return $this->statement->rowCount(); }
    public function get(): array { return $this->statement->fetchAll(PDO::FETCH_ASSOC); }
};
use App\Models\UserFavorite;
$model = new UserFavorite(); $checks = 0;
function check(bool $ok, string $label): void { $GLOBALS['checks']++; if (!$ok) throw new RuntimeException($label); }
check(UserFavorite::available(), 'Existing migration detected');
check($model->add(1, 'post', 1), 'Add');
check($model->add(1, 'post', 1), 'Duplicate add succeeds');
check((int)db()->query('SELECT COUNT(*) FROM user_favorites')->getColumn() === 1, 'No duplicate');
check($model->add(2, 'post', 1), 'Independent owners');
$model->remove(2, 'post', 1); $model->remove(2, 'post', 1);
check($model->ids(1, 'post', [1]) === [1] && $model->ids(2, 'post', [1]) === [], 'Own deletion only; idempotent');
foreach ([[0, 'post', 1], [1, 'page', 1], [1, 'post', -1], [1, "post' OR 1=1", 1]] as $args) {
    try { $model->add(...$args); check(false, 'Invalid request accepted'); } catch (InvalidArgumentException) { check(true, 'Invalid request rejected'); }
}
check(!$model->add(1, 'post', 9999), 'Deleted/missing post cannot be added');
db()->query('UPDATE posts SET is_published = 0 WHERE id = 2');
check(!$model->add(1, 'post', 2), 'Draft cannot be added');
for ($id = 3; $id <= 45; $id++) $model->add(1, 'post', $id);
$start = db()->queries;
$ids = $model->ids(1, 'post', [1, 3, 4, 4, -1, 9999]); sort($ids);
check($ids === [1, 3, 4] && db()->queries === $start + 1, 'Batch state in one query');
$start = db()->queries;
$cards = $model->withPostStates([['id' => 1, 'title' => 'Protected title', 'subscription_access' => ['allowed' => false]], ['id' => 9999]], 1);
check($cards[0]['favorite_saved'] && !$cards[1]['favorite_saved'] && $cards[0]['favorite_ready'] && db()->queries === $start + 1, 'Card state added in one batch');
check($cards[0]['title'] === 'Protected title' && $cards[0]['subscription_access']['allowed'] === false, 'Viewer state preserves public access filtering');
$start = db()->queries;
check(!$model->withPostStates([['id' => 1]], 0)[0]['favorite_saved'] && db()->queries === $start, 'Guest state without a favorites query');
check($model->withPostStates([], 1) === [] && db()->queries === $start, 'Empty lists need no query');
$start = db()->queries;
$first = $model->paginatedPosts(1);
check(count($first['items']) === 20 && $first['total'] === 44 && $first['pagination']['total_pages'] === 3, 'Twenty per page');
check(db()->queries === $start + 2, 'Count and one joined fetch, no N+1');
check($first['items'][0]['category'] === 'Категория' && $first['items'][0]['url'] === '/cms/posts/post-45', 'Category localization and configured URL');
check($first['items'][0]['published_at'] === '2026-10-01 10:00:00', 'Uses the real posts publication column; no invented created_at field');
request()->get['page'] = 3;
check(count($model->paginatedPosts(1)['items']) === 4, 'Last page');
check($model->paginatedPosts(1)['pagination']['prev_url'] === '/en/profile/favorites?filter=all&page=2', 'Pagination keeps locale and query');
request()->get = [];
db()->query('DELETE FROM posts WHERE id = 45'); db()->query('UPDATE posts SET is_published = 0 WHERE id = 44');
check($model->paginatedPosts(1)['total'] === 42, 'Removed/unpublished favorites hidden');
check($model->paginatedPosts(2)['items'] === [], 'No other owner entries');
$routes = file_get_contents(dirname(__DIR__) . '/config/routes.php');
foreach (['add', 'remove'] as $action) check((bool)preg_match("~router->post\('/profile/favorites/{$action}'.*->middleware\(\['auth'\]\)~", $routes), 'Authenticated POST route ' . $action);
$controller = file_get_contents(dirname(__DIR__) . '/app/Controllers/AuthController.php');
check(str_contains($controller, "apply_filters('public_posts_before_render', \$favorites['items'], get_user()"), 'Existing subscription/access policy applied to cards');
echo "$checks favorite checks passed\n";
