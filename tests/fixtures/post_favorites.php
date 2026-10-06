<?php
declare(strict_types=1);
// Real public templates, no application bootstrap or working database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/vendor/autoload.php';
define('VIEWS', dirname(__DIR__, 2) . '/app/Views');
define('THEME', 'default');
define('PAGINATION_SETTINGS', ['perPage' => 20]);
$locale = in_array($argv[1] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $argv[1] : 'ru';
$kind = in_array($argv[2] ?? '', ['posts', 'post', 'category', 'archive', 'home'], true) ? $argv[2] : 'posts';
$fallback = ($argv[3] ?? '') === 'fallback';
$mode = $argv[4] ?? 'member';
$translations = array_replace(require dirname(__DIR__, 2) . '/app/Languages/' . $locale . '.php',
    require dirname(__DIR__, 2) . '/app/Languages/' . $locale . '/posts/' . ($kind === 'post' ? 'show' : 'index') . '.php');
if ($kind === 'home') $translations = array_replace($translations,
    require dirname(__DIR__, 2) . '/app/Languages/' . $locale . '/home/index.php',
    require dirname(__DIR__, 2) . '/plugins/subscriptions/lang/' . $locale . '.php');
function htmlSC(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function return_translation(string $key): string { return $GLOBALS['translations'][$key] ?? $key; }
function print_translation(string $key): string { return return_translation($key); }
function check_auth(): bool { return $GLOBALS['mode'] !== 'guest'; }
function get_user(): array { return check_auth() ? ['id' => 7] : []; }
function base_href(string $p): string { return ($GLOBALS['locale'] === 'ru' ? '' : '/' . $GLOBALS['locale']) . $p; }
function base_url(string $p): string { return $p; }
function get_image(mixed $image): string { return '/fixture.png'; }
function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="fixture">'; }
function site_social_links(): array { return []; }
function site_setting(string $key, mixed $default = ''): mixed { return $default; }
function render_public_verified_badge(mixed $role): string { return ''; }
function theme_asset(string $p): string { return '/theme-assets/' . $p; }
function theme_asset_versioned(string $p): string { return theme_asset($p); }
function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $hook === 'public_video_access_allowed' ? false : $value; }
function view(): FBL\View { return $GLOBALS['view'] ??= new FBL\View('default'); }
final class FavoriteHomePostFixture { public function getNavigationCategories(): array { return []; } }
class_alias(FavoriteHomePostFixture::class, App\Models\Post::class);
final class FavoriteTemplateFixture extends FBL\ThemeManager {
    public function __construct() {}
    public function resolveFile(string $directory, string $name, ?string $slug = null): ?array {
        $path = dirname(__DIR__, 2) . '/themes/default/' . $directory . '/' . $name . '.php';
        return is_file($path) ? ['path' => $path] : null;
    }
    public function fixture(string $path, array $data): string { return $this->evaluateFile($path, $data); }
}
$posts = [];
for ($id = 41; $id <= 43; $id++) $posts[] = [
    'id' => $id, 'title' => 'Очень длинное название записи ' . str_repeat('НазваниеБезПробелов', 4) . ' ' . $id,
    'slug' => 'post-' . $id, 'published_at' => '2026-10-06 10:00:00', 'excerpt' => 'Описание записи',
    'image' => '/fixture.png', 'image_width' => 416, 'image_height' => 305,
    'category' => 'category', 'category_slug' => 'category', 'category_label' => 'Категория', 'views_count' => 10,
    'author_label' => 'Author', 'author_role' => 'user', 'show_post_image' => false, 'content' => '<p>Content</p>',
    'favorite_ready' => $mode !== 'unavailable', 'favorite_saved' => $mode !== 'guest' && $id === 41,
    'subscription_access' => ['allowed' => $id !== 43],
];
$data = ['posts' => $posts, 'post' => $posts[1], 'featured_posts' => $posts, 'total_posts' => 3,
    'pagination' => null, 'current_category' => null, 'categories' => [], 'trending_posts' => [], 'popular_posts' => [],
    'category' => ['name' => 'Категория'], 'title' => 'Archive'];
$path = $fallback && in_array($kind, ['posts', 'post', 'home'], true)
    ? VIEWS . '/themes/default/' . ['posts' => 'posts/index', 'post' => 'posts/show', 'home' => 'home/index'][$kind] . '.php'
    : dirname(__DIR__, 2) . '/themes/default/templates/' . $kind . '.php';
echo (new FavoriteTemplateFixture())->fixture($path, $data);
