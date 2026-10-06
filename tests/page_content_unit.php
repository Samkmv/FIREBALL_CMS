<?php
declare(strict_types=1);
namespace App\Models {
    function db(): object { return $GLOBALS['pageDb']; }
    function base_href(string $path): string { return $path; }
    function cache(): object { return $GLOBALS['pageCache']; }
    function search_sync_entity(string $provider, int $id): void {}
}
namespace App\Modules\BlockEditor {
    function return_translation(string $key): string { return $key; }
    function apply_filters(string $name, mixed $value, mixed ...$args): mixed { return $value; }
}
namespace App\Services {
    // Layout cache invalidation is outside this isolated model-content test.
    final class PublicLayoutContext { public static function invalidate(): void {} }
}
namespace {
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/helpers/helpers.php';
$GLOBALS['pageDb'] = new class {
    public PDO $pdo;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY, title TEXT, menu_title TEXT, slug TEXT, content TEXT, meta_title TEXT, meta_description TEXT, is_published INTEGER, show_in_header INTEGER, show_in_footer INTEGER, show_in_legal_information INTEGER, menu_order INTEGER, created_at TEXT, updated_at TEXT)');
    }
    public function query(string $sql, array $args = []): object {
        $s = $this->pdo->prepare($sql); $s->execute($args);
        return new class($s) {
            public function __construct(private PDOStatement $s) {}
            public function getOne(): array|false { return $this->s->fetch(PDO::FETCH_ASSOC); }
        };
    }
};
$GLOBALS['pageCache'] = new class {
    public array $items = [];
    public function get(string $k): mixed { return $this->items[$k] ?? null; }
    public function set(string $k, mixed $v, int $ttl): void { $this->items[$k] = $v; }
    public function remove(string $k): void { unset($this->items[$k]); }
};
$pages = new class extends App\Models\Page {
    protected function publicCacheKey(string $name): string { return $name; }
    public static function clearPublicCache(): void { $GLOBALS['pageCache']->items = []; }
};
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void { $checks++; if (!$ok) throw new RuntimeException($message); };
$types = ['downloads', 'gallery', 'slider', 'faq', 'newsletter', 'alert', 'audio', 'video', 'text', 'image', 'heading', 'quote', 'list', 'code', 'button', 'divider', 'html'];
foreach ($types as $index => $type) {
    $block = ['id' => 'block_' . $type, 'type' => $type, 'hidden' => false, 'data' => [
        'title' => 'Сложный ' . $type, 'html' => '<p>Content</p>', 'src' => '/uploads/media.mp4',
        'files' => [['url' => '/uploads/file.pdf', 'name' => 'file.pdf', 'size' => 1024]],
        'items' => [['src' => '/uploads/image.jpg', 'alt' => 'Image', 'caption' => 'Caption', 'question' => 'Q', 'answer' => 'A']],
    ], 'settings' => ['width' => 'wide', 'marginBottom' => 16]];
    $serialize = static fn(array $b): string => '<p>Fallback</p><template data-fb-editor-state="2">' . base64_encode(json_encode(['version' => 2, 'blocks' => [$b]], JSON_UNESCAPED_UNICODE)) . '</template>';
    $raw = sanitize_content_html($serialize($block));
    $data = ['title' => $type, 'slug' => $type, 'content' => $raw, 'is_published' => 1];
    $id = $index + 1;
    $GLOBALS['pageDb']->pdo->exec("INSERT INTO pages (id) VALUES ($id)");
    $pages->updatePage($id, $data);
    for ($cycle = 0; $cycle < 2; $cycle++) {
        $row = $pages->findById($id);
        $check($row['content'] === $raw, "$type RAW contract cycle $cycle");
        $check($row['meta_description'] === '', "$type admin SEO field never contains snapshot text");
        $decoded = (new App\Modules\BlockEditor\BlockRepository())->decodeBlocks($row['content']);
        $check($decoded === [$block], "$type nested data/ID/settings survive");
        $check(!str_contains($pages->findByIdForPreview($id)['content'], 'data-fb-editor-state'), "$type preview rendered only");
        $check(!str_contains($pages->findPublishedBySlug($type)['content'], 'data-fb-editor-state'), "$type public slug rendered");
        $check(!str_contains($pages->findPublishedById($id)['content'], 'data-fb-editor-state'), "$type public ID rendered");
        $check($pages->findById($id)['content'] === $raw, "$type preview/public never overwrite RAW");
        $block['data']['title'] .= ' edited';
        $raw = sanitize_content_html($serialize($block));
        $data['content'] = $raw;
        $pages->updatePage($id, $data);
    }
}
$GLOBALS['pageDb']->pdo->exec("UPDATE pages SET content = '  Plain text  ', is_published = 0 WHERE id = 1");
$check($pages->findById(1)['content'] === '  Plain text  ', 'RAW even preserves whitespace');
$check($pages->findByIdForPreview(1)['content'] === '<p>Plain text</p>', 'Plain-text preview compatibility');
$check($pages->findPublishedById(9999) === false, 'Missing public page');
echo "$checks page content checks passed\n";
}
