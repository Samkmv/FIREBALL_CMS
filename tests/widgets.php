<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/widgets_bootstrap.php';
widget_test_seed();

use App\Widgets\WidgetContext;
use App\Widgets\WidgetRegistry;
use App\Widgets\WidgetAreaRegistry;
use App\Widgets\WidgetInstanceRepository;
use App\Widgets\WidgetRenderer;
use App\Widgets\Types\HtmlWidget;
use App\Widgets\Types\LatestPostsWidget;
use App\Services\WeatherService;

$checks = [];
function verify(bool $condition, string $description): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    $checks[] = $description;
}
function rejected(callable $callback, string $description): void
{
    try { $callback(); } catch (InvalidArgumentException|\App\Widgets\WidgetConflictException $exception) { verify(true, $description); return; }
    verify(false, $description);
}
try {
    add_action('fireball_widgets_register', static function () { throw new RuntimeException('TEST PLUGIN FAILURE'); }, 1);
    add_action('fireball_widgets_register', static function ($types, $areas) { $types->register('test.plugin', new HtmlWidget(), ['label' => 'Test plugin', 'areas' => ['posts.sidebar']]); }, 2);
    $manager = widgets();
    verify($manager->types->get('test.plugin') !== null, 'Plugin Widget API registers types and isolates failed registration callbacks');
    $repository = $manager->instances;
    $context = new WidgetContext('admin');
    $revision = static fn(): int => $repository->snapshot()['revision'];
    verify(count($repository->snapshot()['instances']) === 3, 'Migration seeds three required blocks');
    $initialRevision = $revision();
    $repository->initialize(); $repository->initialize();
    verify(count($repository->snapshot()['instances']) === 3 && $revision() === $initialRevision, 'Repeated initialization creates no duplicates or revision changes');
    (new \App\Services\SqlFileRunner())->executeDatabase((string)file_get_contents(ROOT . '/database/migrations/20261011_create_widgets.sql'));
    verify((int)db()->query('SELECT COUNT(*) FROM widget_instances')->getColumn() === 3, 'Repeated migration SQL is idempotent');
    verify((new \App\Services\MigrationRunner())->run() === [], 'MigrationRunner journal skips applied migrations');
    verify((new \App\Services\MigrationRunner())->run(null, ['20261011_create_widgets.sql']) === [], 'Targeted MigrationRunner is idempotent');
    rejected(fn() => (new \App\Services\MigrationRunner())->run(null, ['../unknown.sql']), 'Targeted migrations reject unknown paths');
    $rows = $repository->snapshot()['instances'];
    $categoryId = $rows[0]['id'];
    rejected(fn() => $repository->delete($categoryId, $revision()), 'Server rejects required widget deletion');
    rejected(fn() => $repository->update($categoryId, [], false, ['posts.index' => true, 'posts.show' => true], $revision(), $context), 'Server rejects required widget disabling');
    rejected(fn() => $repository->update($categoryId, [], true, ['posts.index' => false, 'posts.show' => true], $revision(), $context), 'Category navigation cannot be hidden on required pages');
    rejected(fn() => $repository->add('posts.categories', 'posts.sidebar', $revision(), $context), 'Server rejects duplicate required type');
    rejected(fn() => $repository->add('html', 'missing.area', $revision(), $context), 'Unknown areas rejected');
    rejected(fn() => $manager->types->register('html', new HtmlWidget(), ['areas' => ['*']]), 'Conflicting widget identifiers rejected');
    rejected(fn() => $manager->areas->register('posts.sidebar', 'Duplicate', ['posts.index' => 'Index']), 'Conflicting area identifiers rejected');
    $id1 = $repository->add('html', 'posts.sidebar', $revision(), $context);
    $id2 = $repository->add('html', 'posts.sidebar', $revision(), $context);
    $repository->update($id1, ['title' => 'First HTML', 'html' => '<p>One</p>'], true, ['posts.index' => true, 'posts.show' => false], $revision(), $context);
    $repository->update($id2, ['title' => 'Second HTML', 'html' => '<p>Two</p>'], false, ['posts.index' => true, 'posts.show' => true], $revision(), $context);
    $stored = array_column($repository->snapshot()['instances'], null, 'id');
    verify($stored[$id1]['settings']['title'] === 'First HTML' && $stored[$id2]['settings']['title'] === 'Second HTML', 'Multiple instances have independent settings');
    $index = new WidgetContext('posts.index', ['categories' => [['slug' => 'science', 'name' => 'Science', 'total' => 2]], 'trending_posts' => []]);
    $show = new WidgetContext('posts.show', ['categories' => [], 'trending_posts' => [], 'post' => ['slug' => 'first', 'category_slug' => 'science']]);
    verify(str_contains($manager->renderer->render('posts.sidebar', $index), 'First HTML'), 'Enabled widget visible on selected listing page');
    verify(!str_contains($manager->renderer->render('posts.sidebar', $show), 'First HTML'), 'Per-page visibility honored');
    verify(!str_contains($manager->renderer->render('posts.sidebar', $index), 'Second HTML'), 'Disabled optional widget hidden');
    $layout = array_map(static fn($row) => ['id' => $row['id'], 'area' => $row['area']], array_reverse($repository->snapshot()['instances']));
    $repository->reorder($layout, $revision());
    $fresh = new WidgetInstanceRepository($manager->types, $manager->areas);
    verify(array_column($fresh->snapshot()['instances'], 'id') === array_column($layout, 'id'), 'System and optional widget order persists in SQL and fresh repositories');
    rejected(fn() => $repository->reorder(array_slice($layout, 1), $revision()), 'Incomplete sort payload rejected');
    $duplicate = $layout; $duplicate[1] = $duplicate[0];
    rejected(fn() => $repository->reorder($duplicate, $revision()), 'Duplicate IDs in sort payload rejected');
    rejected(fn() => $repository->delete($id1, $revision() - 1), 'Stale revision rejected');
    $manager->areas->register('test.footer', 'Footer', ['posts.index' => 'Index']);
    $layout = array_map(static fn($row) => ['id' => $row['id'], 'area' => $row['id'] === $id1 ? 'test.footer' : $row['area']], $repository->snapshot()['instances']);
    $repository->reorder($layout, $revision());
    verify(array_column($repository->snapshot()['instances'], 'area', 'id')[$id1] === 'test.footer', 'Allowed optional instance moves between registered areas');
    $layout[0] = ['id' => $categoryId, 'area' => 'test.footer'];
    rejected(fn() => $repository->reorder($layout, $revision()), 'System widget cannot leave its trusted area');
    $restrictedId = $repository->add('posts.latest', 'posts.sidebar', $revision(), $context);
    $restricted = array_map(static fn($row) => ['id' => $row['id'], 'area' => $row['id'] === $restrictedId ? 'test.footer' : $row['area']], $repository->snapshot()['instances']);
    rejected(fn() => $repository->reorder($restricted, $revision()), 'Widget type area restrictions are enforced');
    $repository->delete($restrictedId, $revision());
    $before = $revision();
    rejected(fn() => $repository->update($id1, ['title' => str_repeat('x', 151)], true, ['posts.index' => true], $revision(), $context), 'Invalid settings roll back');
    verify($revision() === $before, 'Failed mutation leaves revision unchanged');
    $repository->delete($id2, $revision());
    verify($manager->types->get('html') !== null && !isset(array_column($repository->snapshot()['instances'], null, 'id')[$id2]), 'Deleting instance preserves type registration');
    $categories = new \App\Widgets\Types\CategoriesWidget();
    $categoryContext = new WidgetContext('posts.index', ['current_category' => 'science', 'categories' => [
        ['slug' => 'science', 'name' => '<Science>', 'total' => 2], ['slug' => 'travel', 'name' => 'Travel', 'total' => 1], ['slug' => 'hidden', 'name' => 'Hidden', 'total' => 4],
    ]]);
    $html = $categories->render(['title' => '<Title>', 'category_order' => ['travel', 'science'], 'hidden_categories' => ['hidden']], $categoryContext);
    verify(strpos($html, 'Travel') < strpos($html, '&lt;Science&gt;') && !str_contains($html, '>Hidden'), 'Category order and hiding are instance-local and escaped');
    verify(str_contains($html, '(7)') && str_contains($html, 'btn-dark') && str_contains($html, '?category=science'), 'Category totals, active state and existing URLs preserved');
    $safe = \App\Widgets\WidgetHtml::sanitize('<p class="alert">Safe <a href="javascript:alert(1)" onclick="evil()">Link</a></p><script>alert(1)</script><iframe src="https://example.test"></iframe><img src="x" onerror="evil()"><form>bad</form><?php echo "SECRET"; ?><span data-bs-toggle="modal" style="position:fixed">Text</span>');
    verify(!preg_match('/<script|<iframe|<form|onerror|onclick|javascript:|data-bs-|style=|<\?php/i', $safe), 'Custom HTML removes active content and PHP instructions');
    verify(str_contains($safe, 'Safe') && str_contains($safe, 'class="alert"'), 'Custom HTML preserves passive markup and theme classes');
    $databaseOrder = db()->query('SELECT id, slug FROM post_categories ORDER BY id')->get();
    $repository->update($categoryId, ['title' => 'Custom categories', 'category_order' => ['travel', 'science'], 'hidden_categories' => ['travel']], true, ['posts.index' => true, 'posts.show' => true], $revision(), $context);
    verify(db()->query('SELECT id, slug FROM post_categories ORDER BY id')->get() === $databaseOrder, 'Sidebar category sorting does not alter global category rows');
    $postModel = new \App\Models\Post();
    $latest = $postModel->getWidgetLatestPosts(20);
    verify(array_column($latest, 'slug') === ['third', 'second', 'first'], 'Latest feed excludes drafts and future posts and sorts descending');
    verify(array_column($postModel->getWidgetLatestPosts(20, 'science', 'asc', 'first'), 'slug') === ['third'], 'Latest feed applies category, date order and current post exclusion');
    add_filter('public_posts_before_render', static function ($posts) { foreach ($posts as &$post) $post['subscription_access'] = ['allowed' => $post['slug'] !== 'second']; return $posts; });
    $latestWidget = new LatestPostsWidget();
    $output = $latestWidget->render($latestWidget->defaults(), new WidgetContext('posts.show', ['post' => ['slug' => 'first']]));
    verify(str_contains($output, 'Third publication') && !str_contains($output, 'Second publication') && !str_contains($output, 'First publication'), 'Latest widget honors batch access filters and excludes inaccessible records');
    $calls = 0;
    $transport = static function ($endpoint, $params) use (&$calls) { $calls++; return str_contains($endpoint, 'geocoding') ? ['results' => [['name' => 'Test city', 'latitude' => 55.75, 'longitude' => 37.62]]] : ['current' => ['temperature_2m' => 10, 'relative_humidity_2m' => 70, 'apparent_temperature' => 8, 'weather_code' => 3, 'wind_speed_10m' => 2, 'is_day' => 1], 'daily' => ['time' => ['2026-10-11'], 'weather_code' => [3], 'temperature_2m_max' => [12], 'temperature_2m_min' => [7]]]; };
    $weather = new WeatherService($transport);
    verify($weather->get('Widget Test City', 'celsius', 1)['current']['temperature_2m'] === 10, 'Weather current and forecast data parsed');
    $weather->get('Widget Test City', 'celsius', 1);
    (new WeatherService($transport))->get('Widget Test City', 'celsius', 1);
    verify($calls === 2, 'Weather cache shared across instances and presentations');
    $geoCalls = 0; $geoParams = [];
    $geoTransport = static function ($endpoint, $params) use (&$geoCalls, &$geoParams, $transport) { $geoCalls++; $geoParams = $params; return $transport($endpoint, $params); };
    $geoWeather = (new WeatherService($geoTransport))->getByCoordinates(56.1234, 40.5678, 'celsius', 1);
    verify($geoWeather !== null && $geoCalls === 1 && $geoParams['latitude'] === 56.12 && $geoParams['longitude'] === 40.57, 'Coordinate weather uses one forecast request and rounds precise GPS data');
    (new WeatherService($geoTransport))->getByCoordinates(56.1239, 40.5680, 'celsius', 1);
    verify($geoCalls === 1, 'Nearby visitors share rounded-coordinate weather cache');
    verify((new WeatherService($geoTransport))->getByCoordinates(91, 40, 'celsius', 1) === null && $geoCalls === 1, 'Out-of-range coordinates never contact weather provider');
    $sharedContext = new WidgetContext('posts.index'); $sharedCalls = 0;
    $sharedLoader = static function () use (&$sharedCalls) { return ++$sharedCalls; };
    $sharedContext->forInstance(['id' => 1])->once('shared', $sharedLoader);
    $sharedContext->forInstance(['id' => 2])->once('shared', $sharedLoader);
    verify($sharedCalls === 1, 'Per-widget context metadata keeps shared page loaders free of duplicate queries');
    $failedCalls = 0;
    $failed = static function () use (&$failedCalls) { $failedCalls++; throw new RuntimeException('PRIVATE FAILURE'); };
    verify((new WeatherService($failed))->get('Failure City', 'celsius', 1) === null, 'Weather network failure returns graceful unavailable data');
    (new WeatherService($failed))->get('Failure City', 'celsius', 1);
    verify($failedCalls === 1, 'Weather failures are negatively cached');
    $weatherKey = 'widgets:weather:v1:' . hash('sha256', json_encode([mb_strtolower('Widget Test City'), 'ru', 'celsius', 1]));
    cache()->remove($weatherKey);
    verify(!empty((new WeatherService($failed))->get('Widget Test City', 'celsius', 1)['stale']), 'Stale weather survives temporary API failure');
    $unavailableContext = new WidgetContext('posts.index');
    $unavailable = $unavailableContext->partial('weather', ['settings' => (new \App\Widgets\Types\WeatherWidget())->defaults(), 'weather' => null]);
    verify(str_contains($unavailable, return_translation('widgets_weather_unavailable')) && !str_contains($unavailable, 'PRIVATE'), 'Weather fallback reveals no provider error details');
    $types = new WidgetRegistry(); $areas = new WidgetAreaRegistry();
    $areas->register('test.area', 'Test', ['posts.index' => 'Index']);
    $bad = new class extends \App\Widgets\AbstractWidget { public function defaults(): array { return []; } public function fields(WidgetContext $context): array { return []; } public function render(array $settings, WidgetContext $context): string { ob_start(); echo 'LEAK'; throw new RuntimeException('PRIVATE RENDER ERROR'); } };
    $types->register('test.bad', $bad, ['areas' => ['test.area'], 'label' => 'Bad']);
    $repo = new WidgetInstanceRepository($types, $areas);
    $repo->add('test.bad', 'test.area', $repo->snapshot()['revision'], $context);
    $renderer = new WidgetRenderer($types, $areas, $repo);
    $level = ob_get_level();
    verify($renderer->render('test.area', new WidgetContext('posts.index')) === '' && ob_get_level() === $level, 'A broken optional widget cannot leak buffers or break the page');
    db()->query('UPDATE widget_instances SET enabled = 0, area = ?, type = ?, visibility = ? WHERE id = ?', ['test.footer', 'html', '{}', $categoryId]);
    $trustedRepo = new WidgetInstanceRepository($manager->types, $manager->areas);
    $trusted = array_column($trustedRepo->snapshot()['instances'], null, 'id')[$categoryId];
    verify($trusted['type'] === 'posts.categories' && $trusted['area'] === 'posts.sidebar' && $trusted['enabled'] && $trusted['visibility']['posts.index'], 'Required constraints override tampered SQL flags, type and area');
    db()->query('UPDATE widget_instances SET type = ?, area = ? WHERE id = ?', ['posts.categories', 'posts.sidebar', $categoryId]);
    db()->query('DELETE FROM widget_instances WHERE id = ?', [$categoryId]);
    $repairRepo = new WidgetInstanceRepository($manager->types, $manager->areas);
    $repairRepo->initialize(); $repairRepo->initialize();
    verify((int)db()->query('SELECT COUNT(*) FROM widget_instances WHERE system_key = ?', ['posts.categories'])->getColumn() === 1, 'Missing required slot is repaired without duplicates');
    db()->query('RENAME TABLE widget_instances TO widget_instances_test_hidden');
    try {
        $fallbackRepo = new WidgetInstanceRepository($manager->types, $manager->areas);
        $fallback = (new WidgetRenderer($manager->types, $manager->areas, $fallbackRepo))->render('posts.sidebar', $categoryContext);
        verify(str_contains($fallback, '&lt;Science&gt;'), 'Public pages retain required blocks when widget storage is unavailable');
    } finally { db()->query('RENAME TABLE widget_instances_test_hidden TO widget_instances'); }
    $_POST = ['needCSRFToken' => 'wrong'];
    app()->request = new \FBL\Request('/');
    verify(!app()->router->checkCSRFToken(), 'Incorrect CSRF token rejected');
    $_POST = ['needCSRFToken' => session()->get('needCSRFToken')];
    app()->request = new \FBL\Request('/');
    verify(app()->router->checkCSRFToken(), 'Valid CSRF token accepted');
    verify(!check_admin(), 'Guest has no widget administration rights');
    session()->set('user', ['id' => 1, 'role' => 'user']); verify(!check_admin(), 'Ordinary user has no widget administration rights');
    session()->set('user', ['id' => 1, 'role' => 'admin']); verify(check_admin(), 'Administrator has widget management rights');
    session()->remove('user');
    // Reset widget configuration for browser acceptance checks.
    db()->query('DELETE FROM widget_instances WHERE system_key IS NULL');
    db()->query('UPDATE widget_instances SET settings = ?, enabled = 1, visibility = ?, position = FIELD(system_key, ?, ?, ?) - 1', ['{}', '{"posts.index":true,"posts.show":true}', 'posts.categories', 'posts.trending', 'posts.social']);
    db()->query('UPDATE widget_state SET revision = revision + 1 WHERE id = 1');
    file_put_contents(ROOT . '/tests/artifacts/widgets/automated-tests.json', json_encode(['passed' => count($checks), 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo count($checks) . " widget checks passed.\n";
} catch (Throwable $exception) { fwrite(STDERR, $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n"); exit(1); }
