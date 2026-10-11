<?php
/** Test-only runtime. Every generated file and the private MySQL data directory live under tests/.tmp. */
$widgetTestRoot = dirname(__DIR__);
$widgetTestDirectory = realpath((string)getenv('WIDGET_TEST_DIR'));
if ($widgetTestDirectory === false || !str_starts_with($widgetTestDirectory, $widgetTestRoot . '/tests/.tmp/widgets-')) {
    throw new RuntimeException('Set WIDGET_TEST_DIR to a private tests/.tmp/widgets-* directory.');
}
define('CACHE', $widgetTestDirectory . '/cache');
define('STORAGE', $widgetTestDirectory . '/storage');
define('ERROR_LOGS', $widgetTestDirectory . '/errors.log');
define('INSTALLED_LOCK', STORAGE . '/installed.lock');
define('PATH', 'http://127.0.0.1:' . (getenv('WIDGET_TEST_PORT') ?: '8093'));
define('DEBUG', 0);
define('DB_SETTINGS', [
    'host' => 'localhost;unix_socket=' . $widgetTestDirectory . '/mysql.sock', 'database' => 'fireball_widgets_test',
    'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'driver' => 'mysql',
    'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
]);
ini_set('session.save_path', $widgetTestDirectory . '/sessions');
ini_set('error_log', ERROR_LOGS);
require $widgetTestRoot . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require HELPERS . '/helpers.php';
\FBL\PerformanceProfiler::start();
$app = new \FBL\Application(false);

function widget_test_seed(): void
{
    $pdo = new PDO('mysql:unix_socket=' . dirname(CACHE) . '/mysql.sock', 'root', '', DB_SETTINGS['options']);
    $pdo->exec('DROP DATABASE IF EXISTS fireball_widgets_test');
    $pdo->exec('CREATE DATABASE fireball_widgets_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE fireball_widgets_test');
    app()->db = new \FBL\Database($pdo);
    $sql = new \App\Services\SqlFileRunner();
    $sql->executeDatabase((string)file_get_contents(ROOT . '/database/schema.sql'));
    $sql->executeDatabase((string)file_get_contents(ROOT . '/database/seed.sql'), ['now' => date('Y-m-d H:i:s')]);
    (new \App\Services\MigrationRunner())->run();
    file_put_contents(INSTALLED_LOCK, '{}');
    db()->query("INSERT INTO users (name, login, email, password, role, created_at) VALUES ('Widget test', 'widget-test', 'widgets@example.test', ?, 'creator', NOW())", [password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT)]);
    db()->query("INSERT INTO users (name, login, email, password, role, created_at) VALUES ('Widget reader', 'widget-reader', 'reader@example.test', ?, 'user', NOW())", [password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT)]);
    db()->query("INSERT INTO post_categories (name, name_ru, name_en, slug, created_at) VALUES ('Science', 'Наука', 'Science', 'science', NOW()), ('Travel', 'Поездки', 'Travel', 'travel', NOW())");
    foreach ([['first', 1, 1, '-4 days'], ['second', 2, 1, '-3 days'], ['third', 1, 1, '-2 days'], ['draft', 1, 0, '-1 day'], ['future', 1, 1, '+2 days']] as [$slug, $category, $published, $date]) {
        db()->query('INSERT INTO posts (title, slug, category_id, category, excerpt, content, published_at, is_published, author_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [ucfirst($slug) . ' publication', $slug, $category, $category === 1 ? 'Science' : 'Travel', 'Fixture excerpt', '<p>Fixture article content</p>', date('Y-m-d H:i:s', strtotime($date)), $published, 'Test author']);
    }
    (new \App\Models\SiteSetting())->setMany(['default_locale' => 'ru', 'active_theme' => 'default', 'pwa_enabled' => '0', 'analytics_enabled' => '0', 'social_telegram' => 'https://t.me/fireball_test']);
    \App\Services\SchemaManifest::rebuild();
    app()->theme = new \FBL\ThemeManager();
    app()->set('lang', LANGS['ru']);
    \FBL\Language::load(null);
}
