<?php
declare(strict_types=1);

// Default: no DB connection. Optional integration uses session-local TEMPORARY tables only.
define('ROOT', dirname(__DIR__));
define('PATH', 'http://localhost/cms');
require ROOT . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

use App\Modules\BlockEditor\BlockRenderer;
use App\Services\DatabaseMaintenanceService;
use App\Services\InstallService;
use App\Services\SqlFileRunner;
use App\Search\SearchText;
use FBL\Application;
use FBL\Database;
use FBL\Plugins\HookManager;

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

class RecordingDemoDatabase extends Database
{
    public array $queries = [];
    public function __construct() {}
    public function query(string $query, array $params = []): static
    {
        $this->queries[] = [$query, $params];
        return $this;
    }
}

$app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
Application::$app = $app;
$app->hooks = new HookManager();
$recording = new RecordingDemoDatabase();
$app->db = $recording;
$runner = new SqlFileRunner();
$sql = file_get_contents(ROOT . '/database/demo.sql');
$statements = $runner->split($sql);
check(count($statements) === 13, 'Expected one category insert, eight post inserts and four page inserts.');
foreach ($statements as $statement) {
    check(preg_match('/^INSERT INTO (post_categories|posts|pages)\b/', $statement) === 1, 'Demo must write content tables only.');
    check(!preg_match('/\b(?:TRUNCATE|DELETE FROM|DROP TABLE)\b/i', $statement), 'Demo dataset must not contain destructive statements.');
}
preg_match_all("~REPLACE\\('({\"version\":2,.*?})', '\\{\\{base_path\\}\\}', :base_path\\)~s", $sql, $nativeDocuments);
check(count($nativeDocuments[1]) === 3, 'Gallery, checklist and FAQ examples must preserve native Editor 2 state.');
$nativeTypes = [];
foreach ($nativeDocuments[1] as $json) {
    $state = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    check($state['version'] === 2 && count($state['blocks']) >= 3, 'Native documents must contain valid Editor 2 blocks.');
    $ids = array_column($state['blocks'], 'id');
    check(count($ids) === count(array_unique($ids)), 'Editor block IDs must be unique.');
    foreach ($state['blocks'] as $block) $nativeTypes[] = $block['type'];
    check(mb_strlen(SearchText::plainText($json)) > 350, 'Native block content must be searchable.');
}
foreach (['gallery', 'table', 'faq', 'checklist'] as $type) {
    check(in_array($type, $nativeTypes, true), 'Native example block missing: ' . $type);
}
$runner->executeDatabase($sql, ['now'=>'2026-10-10 12:30:00', 'creator_id'=>73, 'base_path'=>'/cms', 'unused'=>'not bound']);
check(count($recording->queries) === 13, 'Database runner must execute every statement.');
foreach ($recording->queries as [$query, $bindings]) {
    check(!isset($bindings['unused']), 'Unused bindings must be omitted.');
    check(isset($bindings['creator_id']) === str_starts_with($query, 'INSERT INTO posts'), 'Posts must bind the current Creator ID.');
    foreach (array_keys($bindings) as $name) check(str_contains($query, ':' . $name), 'Every binding must belong to its statement.');
}
$recording->queries = [];
$runner->executeDatabase('SELECT 1;');
check($recording->queries === [['SELECT 1', []]], 'Existing parameterless SQL runner callers must remain compatible.');

$maintenance = file_get_contents(ROOT . '/app/Services/DatabaseMaintenanceService.php');
$installer = file_get_contents(ROOT . '/app/Services/InstallService.php');
check(str_contains($maintenance, "ROOT . '/database/demo.sql'"), 'Reset must use the shared SQL dataset.');
check(str_contains($installer, "ROOT . '/database/demo.sql'"), 'Installer must use the shared SQL dataset.');
check(!str_contains($maintenance, 'createMinimalDemoContent'), 'Old minimal content generator must be removed.');
check(strpos($maintenance, '!is_readable') < strpos($maintenance, '$this->truncateTables('), 'Dataset preflight must occur before data is cleared.');
check(str_contains($maintenance, "? ['creator', 'admin', 'moderator']\n            : ['creator']"), 'Existing reset role profiles must be preserved.');

foreach (['workspace', 'content', 'community'] as $asset) {
    $source = ROOT . '/themes/default/assets/images/demo/' . $asset . '.svg';
    $published = ROOT . '/public/assets/default/images/demo/' . $asset . '.svg';
    check(is_file($source) && is_file($published), 'Demo illustrations must ship in source and published assets.');
    check(file_get_contents($source) === file_get_contents($published), 'Source and published illustration must match.');
    $document = new DOMDocument();
    check($document->load($source), 'Illustration must be valid XML.');
    check($document->getElementsByTagName('script')->length === 0 && $document->getElementsByTagName('image')->length === 0, 'Demo illustrations must be local, script-free vectors.');
}

if (in_array('--mysql-temporary', $argv, true)) {
    $settings = DB_SETTINGS;
    $dsn = 'mysql:host=' . $settings['host'] . ';dbname=' . $settings['database'] . ';charset=utf8mb4';
    if (!empty($settings['port'])) $dsn .= ';port=' . (int)$settings['port'];
    try {
        $pdo = new PDO($dsn, $settings['username'], $settings['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    } catch (Throwable) {
        throw new RuntimeException('Temporary MySQL test connection unavailable; no application data was modified.');
    }
    $schema = $runner->split(file_get_contents(ROOT . '/database/schema.sql'));
    foreach (['users', 'post_categories', 'posts', 'pages'] as $table) {
        $matched = false;
        foreach ($schema as $definition) {
            if (!str_starts_with($definition, 'CREATE TABLE IF NOT EXISTS ' . $table . ' (')) continue;
            $pdo->exec(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $definition));
            $matched = true;
            break;
        }
        check($matched, 'Temporary table definition must exist: ' . $table);
    }
    $pdo->prepare('INSERT INTO users (id, name, login, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([73, 'Тестовый создатель', 'demo-test', 'demo@example.invalid', 'not-a-login-password', 'creator', '2026-10-10 12:30:00']);
    $params = ['now'=>'2026-10-10 12:30:00', 'creator_id'=>73, 'base_path'=>'/cms'];
    $runner->executePdo($pdo, $sql, $params);
    $app->db = new Database($pdo);
    $service = (new ReflectionClass(DatabaseMaintenanceService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(DatabaseMaintenanceService::class, 'createDemoContent');
    $summary = $method->invoke($service, ['id'=>73, 'name'=>'Тестовый создатель'], $params['now']);
    check($summary['categories_count'] === 3 && $summary['posts_count'] === 8 && $summary['pages_count'] === 4, 'Both import paths must produce identical counts without duplicates.');
    check((int)$pdo->query('SELECT COUNT(*) FROM posts WHERE is_published = 1')->fetchColumn() === 7, 'Seven posts must be public.');
    check((int)$pdo->query('SELECT COUNT(*) FROM posts WHERE is_published = 0')->fetchColumn() === 1, 'One post must remain an unpublished draft.');
    check((int)$pdo->query('SELECT COUNT(*) FROM posts WHERE show_on_home = 1 AND is_published = 1')->fetchColumn() === 6, 'Home must have six featured posts.');
    check((int)$pdo->query('SELECT COUNT(*) FROM posts WHERE author_id = 73 AND author_name = "Тестовый создатель"')->fetchColumn() === 8, 'Every author must be the real preserved Creator, not ID 1.');
    check((int)$pdo->query('SELECT SUM(views_count) FROM posts')->fetchColumn() === 0, 'Demo must not fabricate views.');
    check((int)$pdo->query('SELECT COUNT(*) FROM pages WHERE show_in_header = 1')->fetchColumn() === 3, 'Header navigation must have three pages.');
    check((int)$pdo->query('SELECT COUNT(*) FROM pages WHERE show_in_legal_information = 1')->fetchColumn() === 0, 'Demo must not publish fake legal documents.');
    $posts = $pdo->query('SELECT * FROM posts')->fetchAll();
    $pages = $pdo->query('SELECT * FROM pages')->fetchAll();
    $slugs = array_merge(array_column($pages, 'slug'), ['posts', 'profile', 'contacts'], array_map(static fn(array $post): string => 'posts/' . $post['slug'], $posts));
    $renderer = new BlockRenderer();
    foreach (array_merge($posts, $pages) as $row) {
        $html = $renderer->renderPublicContent($row['content']);
        check(!str_contains($html, '{{base_path}}'), 'Local URL tokens must be replaced.');
        check(mb_strlen(strip_tags($html)) > 350, 'Demo texts must be substantive.');
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8">' . $html);
        foreach ($document->getElementsByTagName('a') as $link) {
            $path = (string)parse_url($link->getAttribute('href'), PHP_URL_PATH);
            if (str_starts_with($path, '/cms/assets/')) {
                check(is_file(WWW . substr($path, 4)), 'Linked media must exist.');
            } else {
                check(in_array(ltrim(substr($path, 4), '/'), $slugs, true), 'Internal links must resolve to a demo or core route.');
            }
        }
        if (!empty($row['image'])) {
            check(is_file(WWW . '/' . $row['image']), 'Covers must be stored relative to the public root, including subdirectory installs.');
        }
        if ($row['slug'] === 'block-editor-examples') {
            $state = json_decode($row['content'], true, 512, JSON_THROW_ON_ERROR);
            check(in_array('gallery', array_column($state['blocks'], 'type'), true), 'Imported gallery must remain one editable block, not separate images.');
            check(substr_count($html, 'data-glightbox') === 3, 'Gallery must contain three GLightbox images.');
            preg_match_all('/data-gallery="([^"]+)"/', $html, $groups);
            check(count(array_unique($groups[1])) === 1, 'Gallery images must share one slide group.');
            check(str_contains($html, '<table') && str_contains($html, '<details'), 'Table and FAQ must survive sanitization.');
        }
        if ($row['slug'] === 'site-launch-checklist') {
            check(str_contains($html, 'data-fb-checklist') && substr_count($html, 'data-checked="0"') === 8, 'Checklist must keep all eight unchecked tasks.');
            check(!str_contains($html, '☐ ☐'), 'Checklist must not duplicate checkbox glyphs on import.');
        }
    }
    $insertCreator = new ReflectionMethod(InstallService::class, 'insertCreator');
    $installService = (new ReflectionClass(InstallService::class))->newInstanceWithoutConstructor();
    $account = ['login'=>'demo-test', 'email'=>'demo@example.invalid', 'password'=>'isolated-test-password'];
    check($insertCreator->invoke($installService, $pdo, $account, $params['now']) === 73, 'Installer must resolve an existing Creator ID on upsert.');
    check($insertCreator->invoke($installService, $pdo, ['login'=>'new-demo', 'email'=>'new@example.invalid', 'password'=>'isolated-test-password'], $params['now']) > 73, 'Installer must resolve a new Creator ID.');
    // Disconnecting automatically removes every TEMPORARY table; no permanent tables are touched.
    $app->db = null;
    $pdo = null;
}

echo $checks . " demo content checks passed.\n";
