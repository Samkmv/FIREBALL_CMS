<?php

declare(strict_types=1);

define('SITE_NAME', 'FIREBALL CMS');
define('PATH', 'https://example.test');
define('DEFAULT_LOCALE', 'ru');
define('APP_TIMEZONE', 'Europe/Moscow');
define('CONFIG', dirname(__DIR__) . '/config');

final class SupportKnowledgeBaseCache
{
    public function remove(string $key): void
    {
    }
}

final class SupportKnowledgeBaseDatabase
{
    public array $categories = [];
    public array $articles = [];
    public array $settings = [];
    private mixed $result = null;
    private int $insertId = 0;
    private bool $transaction = false;

    public function query(string $sql, array $params = []): self
    {
        $this->result = null;
        if (str_starts_with($sql, 'SELECT setting_value FROM site_settings')) {
            $this->result = $this->settings[(string)$params[0]] ?? false;
        } elseif (str_starts_with($sql, 'SELECT id FROM support_kb_categories')) {
            $slug = (string)$params[0];
            $this->result = $this->categories[$slug]['id'] ?? false;
        } elseif (str_starts_with($sql, 'INSERT INTO support_kb_categories')) {
            $this->insertId = count($this->categories) + 1;
            $this->categories[(string)$params[1]] = [
                'id' => $this->insertId,
                'name' => (string)$params[0],
                'slug' => (string)$params[1],
            ];
        } elseif (str_starts_with($sql, 'SELECT COUNT(*) FROM support_kb_articles')) {
            $this->result = isset($this->articles[(string)$params[0]]) ? 1 : 0;
        } elseif (str_starts_with($sql, 'DELETE FROM support_kb_articles WHERE slug IN')) {
            foreach ($params as $slug) {
                unset($this->articles[(string)$slug]);
            }
        } elseif (str_starts_with($sql, 'DELETE FROM support_kb_categories')) {
            $slug = (string)($params[0] ?? '');
            $categoryId = (int)($this->categories[$slug]['id'] ?? 0);
            $hasArticles = false;
            foreach ($this->articles as $article) {
                if ((int)($article['category_id'] ?? 0) === $categoryId) {
                    $hasArticles = true;
                    break;
                }
            }
            if (!$hasArticles) {
                unset($this->categories[$slug]);
            }
        } elseif (str_starts_with($sql, 'INSERT INTO support_kb_articles')) {
            $this->insertId = count($this->articles) + 1;
            $this->articles[(string)$params[1]] = [
                'id' => $this->insertId,
                'title' => (string)$params[0],
                'slug' => (string)$params[1],
                'content' => (string)$params[3],
                'category_id' => (int)$params[4],
            ];
        } elseif (str_starts_with($sql, 'INSERT INTO site_settings')) {
            $this->settings[(string)$params[0]] = (string)$params[1];
        }

        return $this;
    }

    public function getColumn(): mixed
    {
        return $this->result;
    }

    public function getInsertId(): string
    {
        return (string)$this->insertId;
    }

    public function beginTransaction(): bool
    {
        $this->transaction = true;
        return true;
    }

    public function commit(): bool
    {
        $this->transaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        $this->transaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }
}

function db(): SupportKnowledgeBaseDatabase
{
    static $database;
    return $database ??= new SupportKnowledgeBaseDatabase();
}

function cache(): SupportKnowledgeBaseCache
{
    static $cache;
    return $cache ??= new SupportKnowledgeBaseCache();
}

function kb_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require_once __DIR__ . '/../app/Services/LanguagePackService.php';
require_once __DIR__ . '/../app/Models/SiteSetting.php';
require_once __DIR__ . '/../app/Support/DefaultKnowledgeBase.php';
require_once __DIR__ . '/../app/Services/DefaultKnowledgeBaseSeeder.php';

$categories = App\Support\DefaultKnowledgeBase::categories();
$articles = App\Support\DefaultKnowledgeBase::articles();
$categorySlugs = array_column($categories, 'slug');
$articleSlugs = array_column($articles, 'slug');

kb_assert(count($categories) >= 6, 'The built-in knowledge base must contain the public help sections.');
kb_assert(count($articles) >= 25, 'The built-in knowledge base must answer the main public-user questions.');
kb_assert(count($categorySlugs) === count(array_unique($categorySlugs)), 'Knowledge base category slugs must be unique.');
kb_assert(count($articleSlugs) === count(array_unique($articleSlugs)), 'Knowledge base article slugs must be unique.');
kb_assert(
    count(array_diff(
        ['registratsiya-i-vhod', 'profil-i-bezopasnost', 'dostup-k-materialam', 'uvedomleniya-i-obshchenie', 'problemy-i-pomoshch'],
        $categorySlugs
    )) === 0,
    'The public knowledge base is missing registration, profile, access, notification, or help sections.'
);
kb_assert(
    count(array_intersect($articleSlugs, App\Support\DefaultKnowledgeBase::legacyArticleSlugs())) === 0,
    'Legacy administrative articles must not remain in the public catalog.'
);
kb_assert(
    str_starts_with(App\Support\DefaultKnowledgeBase::signature(), App\Support\DefaultKnowledgeBase::VERSION . ':'),
    'The knowledge base signature must include its content version.'
);
kb_assert(
    count(array_diff(array_unique(array_column($articles, 'category_slug')), $categorySlugs)) === 0,
    'Every knowledge base article must reference an available category.'
);
foreach ($articles as $article) {
    kb_assert(trim((string)$article['title']) !== '', 'Every knowledge base article needs a title.');
    kb_assert(trim((string)$article['excerpt']) !== '', 'Every knowledge base article needs an excerpt.');
    kb_assert(
        str_contains((string)$article['content'], '<h2>Что сделать</h2>')
        && substr_count((string)$article['content'], '<li>') >= 4,
        'Every knowledge base article must contain actionable instructions.'
    );
}

$publicCatalogText = mb_strtolower(json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
foreach (['административная панель', 'smtp', 'vapid', 'github api', '3x-ui', 'sql-запрос', 'настройка плагина'] as $technicalTerm) {
    kb_assert(!str_contains($publicCatalogText, $technicalTerm), 'The public catalog contains technical administration guidance: ' . $technicalTerm);
}

db()->categories['nachalo-raboty'] = ['id' => 900, 'name' => 'Начало работы', 'slug' => 'nachalo-raboty'];
db()->articles['kak-ustroena-administrativnaya-panel'] = [
    'id' => 900,
    'title' => 'Как устроена административная панель',
    'slug' => 'kak-ustroena-administrativnaya-panel',
    'content' => 'legacy',
    'category_id' => 900,
];

$seeder = new App\Services\DefaultKnowledgeBaseSeeder();
$firstRun = $seeder->seed();
kb_assert($firstRun['categories'] === count($categories), 'The first seed must add every missing category.');
kb_assert($firstRun['articles'] === count($articles), 'The first seed must add every missing article.');
kb_assert(!isset(db()->articles['kak-ustroena-administrativnaya-panel']), 'The old built-in technical article must be removed.');
kb_assert(!isset(db()->categories['nachalo-raboty']), 'An empty old built-in category must be removed.');

$secondRun = $seeder->seed();
kb_assert($secondRun['categories'] === 0 && $secondRun['articles'] === 0, 'Repeated seeding must be idempotent.');

$preservedSlug = (string)$articles[0]['slug'];
db()->articles[$preservedSlug]['title'] = 'Пользовательская редакция';
unset(db()->settings['support_kb_catalog_version']);
$repairRun = $seeder->seed();
kb_assert($repairRun['categories'] === 0 && $repairRun['articles'] === 0, 'Catalog repair must not duplicate existing rows.');
kb_assert(db()->articles[$preservedSlug]['title'] === 'Пользовательская редакция', 'Catalog repair must preserve edited articles.');

fwrite(STDOUT, json_encode([
    'status' => 'ok',
    'categories' => count($categories),
    'articles' => count($articles),
    'audience' => 'public_users',
    'legacy_admin_catalog_removed' => true,
    'idempotent' => true,
    'preserves_edits' => true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
