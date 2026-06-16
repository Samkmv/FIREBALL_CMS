<?php

namespace FBL;

use App\Models\SiteSetting;
use App\Services\Themes\ThemeAssets;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Finds, validates and renders CMS themes from the project-level /themes directory.
 */
class ThemeManager
{
    public const DEFAULT_THEME = 'default';

    protected const RESERVED_SLUGS = [
        'default', 'admin', 'system', 'core', 'app', 'config', 'public', 'vendor',
        'tmp', 'cache', 'uploads', 'assets', 'themes', 'files', 'api', 'static',
    ];

    protected const MAX_PACKAGE_BYTES = 83886080;
    protected const MAX_PACKAGE_FILES = 2500;

    public string $content = '';
    protected string $themesPath;
    protected ?array $activeTheme = null;
    protected ThemeAssets $themeAssets;

    public function __construct(?string $themesPath = null)
    {
        $this->themesPath = rtrim($themesPath ?: ROOT . '/themes', '/');
        // Keep ThemeManager as the public facade while moving asset path logic into a focused service.
        $this->themeAssets = new ThemeAssets();
    }

    public function getThemes(): array
    {
        if (!is_dir($this->themesPath)) {
            return [];
        }

        $themes = [];
        foreach (scandir($this->themesPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $theme = $this->loadTheme($entry);
            if ($theme !== null) {
                $themes[$theme['slug']] = $theme;
            }
        }

        ksort($themes);

        return array_values($themes);
    }

    public function getTheme(string $slug): ?array
    {
        return $this->loadTheme($slug);
    }

    public function getActiveTheme(): array
    {
        if ($this->activeTheme !== null) {
            return $this->activeTheme;
        }

        $previewSlug = $this->getPreviewSlug();
        if ($previewSlug !== null) {
            $previewTheme = $this->loadTheme($previewSlug);
            if ($previewTheme !== null) {
                return $this->activeTheme = $previewTheme;
            }
        }

        $slug = self::DEFAULT_THEME;

        try {
            $settings = new SiteSetting();
            $settings->ensureTableExists();
            $current = trim($settings->get('active_theme', self::DEFAULT_THEME));
            if ($current === '') {
                $settings->setMany(['active_theme' => self::DEFAULT_THEME]);
            } else {
                $slug = $current;
            }
        } catch (\Throwable) {
            $slug = self::DEFAULT_THEME;
        }

        $theme = $this->loadTheme($slug) ?: $this->loadTheme(self::DEFAULT_THEME);
        if ($theme === null) {
            abort('Default theme is not available.', 500);
        }

        if ($theme['slug'] !== $slug) {
            $this->storeActiveTheme(self::DEFAULT_THEME);
        }

        return $this->activeTheme = $theme;
    }

    public function activate($slug): bool
    {
        $slug = trim((string)$slug);
        $theme = $this->loadTheme($slug);
        if ($theme === null || $theme['slug'] !== $slug) {
            $this->logThemeAction('theme_activation_failed', ['slug' => $slug]);
            return false;
        }

        $this->storeActiveTheme($slug);
        $this->activeTheme = $theme;
        $this->logThemeAction('theme_activated', ['slug' => $slug]);

        return true;
    }

    public function preview($slug): ?string
    {
        $slug = trim((string)$slug);
        $theme = $this->loadTheme($slug);
        if ($theme === null || !$this->validateThemeStructure($slug)) {
            $this->logThemeAction('theme_preview_failed', ['slug' => $slug]);
            return null;
        }

        return base_href('/') . '?preview_theme=' . rawurlencode($slug);
    }

    public function export($slug): string
    {
        $theme = $this->loadTheme(trim((string)$slug));
        if ($theme === null || !$this->validateThemeStructure($theme['slug'])) {
            $this->logThemeAction('theme_export_failed', ['slug' => (string)$slug]);
            throw new RuntimeException('Theme is not valid for export.');
        }

        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive extension is not available.');
        }

        $exportDir = ROOT . '/tmp/theme-exports';
        if (!is_dir($exportDir) && !mkdir($exportDir, 0755, true) && !is_dir($exportDir)) {
            throw new RuntimeException('Could not create export directory.');
        }

        $zipPath = $exportDir . '/' . $theme['slug'] . '-' . date('Ymd-His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create theme ZIP.');
        }

        $themeRoot = $theme['path'];
        foreach (['templates', 'partials', 'assets', 'assets/css', 'assets/js', 'assets/images'] as $directory) {
            $zip->addEmptyDir($directory);
        }

        foreach ($this->exportableThemeFiles($themeRoot) as $relativePath) {
            $absolutePath = $themeRoot . '/' . $relativePath;
            if (!$zip->addFile($absolutePath, $relativePath)) {
                $zip->close();
                @unlink($zipPath);
                throw new RuntimeException('Could not add file to theme ZIP.');
            }
        }

        $zip->close();
        $this->logThemeAction('theme_exported', ['slug' => $theme['slug'], 'zip' => $zipPath]);

        return $zipPath;
    }

    public function import($zipFile): array
    {
        $zipPath = $this->resolveUploadedZipPath($zipFile);
        $tempDir = ROOT . '/tmp/theme-import-' . bin2hex(random_bytes(8));
        $installedDir = null;

        try {
            $package = $this->validatePackage($zipPath);
            if (!mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
                throw new RuntimeException('Could not create temporary import directory.');
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Could not open theme ZIP.');
            }
            if (!$zip->extractTo($tempDir)) {
                $zip->close();
                throw new RuntimeException('Could not extract theme ZIP.');
            }
            $zip->close();

            $sourceRoot = $this->findExtractedThemeRoot($tempDir);
            $manifestPath = $sourceRoot . '/theme.json';
            $manifest = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                throw new RuntimeException('theme.json is not valid JSON.');
            }

            $slug = trim((string)($manifest['slug'] ?? ''));
            if (!$this->isValidSlug($slug) || $this->isReservedSlug($slug)) {
                throw new RuntimeException('Theme slug is invalid or reserved.');
            }

            if ($slug !== (string)($package['slug'] ?? '')) {
                throw new RuntimeException('Theme slug mismatch.');
            }

            if (!$this->isCompatibleCmsVersion((string)($manifest['minCmsVersion'] ?? ''))) {
                throw new RuntimeException('Theme requires a newer CMS version.');
            }

            if (file_exists($this->themeDirectory($slug))) {
                throw new RuntimeException('Theme already exists.');
            }

            $this->validateExtractedTheme($sourceRoot);
            $themesRoot = $this->ensureThemesRoot();
            $installedDir = $this->themeDirectory($slug);
            if (!$this->isPathInsideCandidate($installedDir, $themesRoot)) {
                throw new RuntimeException('Theme install path is invalid.');
            }

            if (!rename($sourceRoot, $installedDir)) {
                throw new RuntimeException('Could not install theme.');
            }

            $theme = $this->loadTheme($slug);
            if ($theme === null || !$this->validateThemeStructure($slug)) {
                $this->removeDirectory($installedDir);
                throw new RuntimeException('Installed theme failed validation.');
            }

            $this->logThemeAction('theme_imported', ['slug' => $slug]);
            return $theme;
        } catch (\Throwable $exception) {
            if ($installedDir && is_dir($installedDir)) {
                $this->removeDirectory($installedDir);
            }
            $this->logThemeAction('theme_import_error', [
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        } finally {
            if (is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
        }
    }

    public function validatePackage($path): array
    {
        $zipPath = (string)$path;
        if (!is_file($zipPath) || filesize($zipPath) > self::MAX_PACKAGE_BYTES) {
            $this->logThemeAction('theme_package_validation_error', ['error' => 'invalid_size']);
            throw new RuntimeException('Theme ZIP is missing or too large.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->logThemeAction('theme_package_validation_error', ['error' => 'open_failed']);
            throw new RuntimeException('Could not open theme ZIP.');
        }

        if ($zip->numFiles <= 0 || $zip->numFiles > self::MAX_PACKAGE_FILES) {
            $zip->close();
            throw new RuntimeException('Theme ZIP contains too many files.');
        }

        $themeJsonName = null;
        $totalSize = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = str_replace('\\', '/', (string)($stat['name'] ?? ''));
            $totalSize += (int)($stat['size'] ?? 0);

            $this->assertSafeZipEntry($name);

            if ($totalSize > self::MAX_PACKAGE_BYTES) {
                $zip->close();
                throw new RuntimeException('Theme ZIP contents are too large.');
            }

            if (basename($name) === 'theme.json') {
                if ($themeJsonName !== null) {
                    $zip->close();
                    throw new RuntimeException('Theme ZIP contains multiple theme.json files.');
                }
                $themeJsonName = $name;
            }
        }

        if ($themeJsonName === null) {
            $zip->close();
            throw new RuntimeException('theme.json was not found in theme ZIP.');
        }

        $manifestRaw = $zip->getFromName($themeJsonName);
        $zip->close();
        $manifest = json_decode((string)$manifestRaw, true);
        if (!is_array($manifest)) {
            throw new RuntimeException('theme.json is not valid JSON.');
        }

        $slug = trim((string)($manifest['slug'] ?? ''));
        if (!$this->isValidSlug($slug) || $this->isReservedSlug($slug)) {
            throw new RuntimeException('Theme slug is invalid or reserved.');
        }

        return [
            'slug' => $slug,
            'manifest' => $manifest,
            'theme_json' => $themeJsonName,
        ];
    }

    public function validateThemeStructure($slug): bool
    {
        $theme = $this->loadTheme(trim((string)$slug));
        if ($theme === null) {
            $this->logThemeAction('theme_validation_error', ['slug' => (string)$slug, 'error' => 'manifest']);
            return false;
        }

        foreach ($this->requiredThemeFiles() as $relativePath) {
            $path = $theme['path'] . '/' . $relativePath;
            $real = realpath($path);
            if ($real === false || !$this->isInside($real, $theme['path']) || !is_file($real)) {
                $this->logThemeAction('theme_validation_error', ['slug' => $theme['slug'], 'error' => 'missing_' . $relativePath]);
                return false;
            }
        }

        foreach ($this->requiredThemeDirectories() as $relativePath) {
            $path = $theme['path'] . '/' . $relativePath;
            $real = realpath($path);
            if ($real === false || !$this->isInside($real, $theme['path']) || !is_dir($real)) {
                $this->logThemeAction('theme_validation_error', ['slug' => $theme['slug'], 'error' => 'missing_' . $relativePath]);
                return false;
            }
        }

        return true;
    }

    public function createTheme(array $data): array
    {
        $data = $this->normalizeThemeData($data, true);
        $slug = $data['slug'];

        if (!$this->isValidSlug($slug) || $this->isReservedSlug($slug)) {
            throw new InvalidArgumentException('Invalid theme slug.');
        }

        $themesRoot = $this->ensureThemesRoot();
        $themeDir = $this->themesPath . '/' . $slug;
        if (is_dir($themeDir) || is_file($themeDir) || is_link($themeDir)) {
            throw new RuntimeException('Theme directory already exists.');
        }

        if (!$this->isPathInsideCandidate($themeDir, $themesRoot)) {
            throw new RuntimeException('Theme path is outside themes directory.');
        }

        if (!mkdir($themeDir, 0755, true) && !is_dir($themeDir)) {
            throw new RuntimeException('Could not create theme directory.');
        }

        $themeRoot = realpath($themeDir);
        if ($themeRoot === false || !$this->isInside($themeRoot, $themesRoot)) {
            throw new RuntimeException('Created theme path is invalid.');
        }

        foreach ($this->themeScaffoldDirectories() as $directory) {
            $path = $themeRoot . '/' . $directory;
            if (!mkdir($path, 0755, true) && !is_dir($path)) {
                throw new RuntimeException('Could not create theme directory: ' . $directory);
            }
        }

        $data['preview'] = $this->prepareThemePreview($themeRoot, $data, true);
        $this->writeThemeJson($themeRoot, $data);
        $this->writeThemeFile($themeRoot, 'templates/layout.php', $this->defaultLayoutTemplate());
        $this->writeThemeFile($themeRoot, 'templates/home.php', $this->defaultHomeTemplate());
        $this->writeThemeFile($themeRoot, 'templates/page.php', $this->defaultPageTemplate());
        $this->writeThemeFile($themeRoot, 'templates/post.php', $this->defaultPostTemplate());
        $this->writeThemeFile($themeRoot, 'templates/category.php', $this->defaultCategoryTemplate());
        $this->writeThemeFile($themeRoot, 'templates/search.php', $this->defaultSearchTemplate());
        $this->writeThemeFile($themeRoot, 'templates/archive.php', $this->defaultArchiveTemplate());
        $this->writeThemeFile($themeRoot, 'templates/404.php', $this->default404Template());
        $this->writeThemeFile($themeRoot, 'partials/header.php', $this->defaultHeaderPartial());
        $this->writeThemeFile($themeRoot, 'partials/footer.php', $this->defaultFooterPartial());
        $this->writeThemeFile($themeRoot, 'partials/menu.php', $this->defaultMenuPartial());
        $this->writeThemeFile($themeRoot, 'partials/sidebar.php', $this->defaultSidebarPartial());
        $this->writeThemeFile($themeRoot, 'assets/css/style.css', $this->defaultThemeCss());
        $this->writeThemeFile($themeRoot, 'assets/js/theme.js', $this->defaultThemeJs());

        return $this->loadTheme($slug) ?: $data + ['path' => $themeRoot];
    }

    public function updateTheme(string $slug, array $data): bool
    {
        $theme = $this->loadTheme($slug);
        if ($theme === null) {
            return false;
        }

        $data = $this->normalizeThemeData($data, false);
        $themeRoot = $theme['path'];
        $preview = $this->prepareThemePreview($themeRoot, $data, false);
        $updated = [
            'name' => $data['name'],
            'slug' => $theme['slug'],
            'version' => $data['version'],
            'author' => $data['author'],
            'description' => $data['description'],
            'preview' => $preview,
            'minCmsVersion' => $theme['minCmsVersion'] !== '' ? $theme['minCmsVersion'] : '1.5.0',
        ];

        $this->writeThemeJson($themeRoot, $updated);

        if ($this->activeTheme !== null && $this->activeTheme['slug'] === $slug) {
            $this->activeTheme = null;
        }

        return true;
    }

    public function deleteTheme(string $slug): bool
    {
        $theme = $this->loadTheme($slug);
        if ($theme === null || !$this->canDeleteTheme($slug)) {
            return false;
        }

        $themeRoot = realpath($theme['path']);
        $themesRoot = realpath($this->themesPath);
        if ($themeRoot === false || $themesRoot === false || !$this->isInside($themeRoot, $themesRoot)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if (!$this->isPathInsideCandidate($path, $themeRoot)) {
                return false;
            }

            if ($file->isDir() && !$file->isLink()) {
                if (!rmdir($path)) {
                    return false;
                }
            } elseif (!unlink($path)) {
                return false;
            }
        }

        return rmdir($themeRoot);
    }

    public function canDeleteTheme(string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '' || $slug === self::DEFAULT_THEME) {
            return false;
        }

        return $slug !== (string)($this->getActiveTheme()['slug'] ?? self::DEFAULT_THEME);
    }

    public function listThemeFiles(string $slug): array
    {
        $theme = $this->loadTheme($slug);
        if ($theme === null) {
            return [];
        }

        $themeRoot = $theme['path'];
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            if (!$this->isPathInsideCandidate($path, $themeRoot)) {
                continue;
            }

            $files[] = ltrim(str_replace($themeRoot, '', $path), '/');
        }

        sort($files);

        return $files;
    }

    public function render($template, $data = []): string
    {
        unset($data['this']);

        $theme = $this->getActiveTheme();
        $defaultTheme = $theme['slug'] === self::DEFAULT_THEME ? $theme : $this->loadTheme(self::DEFAULT_THEME);
        $templateFile = $this->themeFile($theme, 'templates', (string)$template)
            ?: ($defaultTheme ? $this->themeFile($defaultTheme, 'templates', (string)$template) : null);
        $layoutFile = $this->themeFile($theme, 'templates', 'layout')
            ?: ($defaultTheme ? $this->themeFile($defaultTheme, 'templates', 'layout') : null);

        if ($templateFile === null) {
            abort('Theme template not found: ' . (string)$template, 500);
        }

        if ($layoutFile === null) {
            abort('Theme layout not found.', 500);
        }

        $data = $this->standardizeTemplateData((string)$template, $data);
        extract($data);
        ob_start();
        require $templateFile;
        $this->content = ob_get_clean();

        ob_start();
        require $layoutFile;

        return $this->injectCmsComponents((string)ob_get_clean());
    }

    protected function injectCmsComponents(string $html): string
    {
        $components = renderAnalyticsTracker() . renderCookieConsent();
        if ($components === '') {
            return $html;
        }

        $position = strripos($html, '</body>');

        return $position === false
            ? $html . $components
            : substr($html, 0, $position) . $components . substr($html, $position);
    }

    public function partial($name, $data = []): string
    {
        unset($data['this']);

        $theme = $this->getActiveTheme();
        $partialFile = $this->themeFile($theme, 'partials', (string)$name);

        if ($partialFile === null && $theme['slug'] !== self::DEFAULT_THEME) {
            $default = $this->loadTheme(self::DEFAULT_THEME);
            $partialFile = $default ? $this->themeFile($default, 'partials', (string)$name) : null;
        }

        if ($partialFile === null) {
            return '';
        }

        extract($data);
        ob_start();
        require $partialFile;

        return ob_get_clean();
    }

    public function getLegalInformationMenu(): array
    {
        return (new \App\Models\Page())->getLegalInformationMenu();
    }

    public function siteName(): string
    {
        return (string)$this->setting('site_title', SITE_NAME);
    }

    public function siteUrl(string $path = ''): string
    {
        $path = trim($path);
        return $path === '' ? base_href('/') : base_href('/' . ltrim($path, '/'));
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $settings = (new SiteSetting())->all();
        if (!array_key_exists($key, $settings) || $settings[$key] === '') {
            return $default;
        }

        return $settings[$key];
    }

    public function currentUser(): ?array
    {
        return check_auth() ? (get_user() ?: null) : null;
    }

    public function currentLocale(): string
    {
        return (string)(app()->get('lang')['code'] ?? DEFAULT_LOCALE);
    }

    public function availableLocales(): array
    {
        return array_values(array_map(function (array $locale): array {
            $code = (string)($locale['code'] ?? '');
            return [
                'code' => $code,
                'title' => (string)($locale['title'] ?? $code),
                'active' => $code === $this->currentLocale(),
                'url' => $this->switchLocaleUrl($code),
            ];
        }, LANGS));
    }

    public function switchLocaleUrl(string $locale): string
    {
        if (!array_key_exists($locale, LANGS)) {
            return $this->siteUrl();
        }

        $segments = explode('/', trim(request()->getPath(), '/'));
        if ($segments !== [] && array_key_exists((string)$segments[0], LANGS)) {
            array_shift($segments);
        }
        $path = trim(implode('/', $segments), '/');
        $query = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');
        $prefix = !empty(LANGS[$locale]['base']) ? '' : '/' . $locale;
        $url = rtrim(app_base_url(), '/') . $prefix . ($path !== '' ? '/' . $path : '/');

        return $url . ($query !== '' ? '?' . $query : '');
    }

    public function getMenu(string $location = 'header'): array
    {
        $location = $location === 'main' ? 'header' : $location;
        $items = (new \App\Models\Page())->getMenuPages($location);
        $currentPath = rtrim(current_path(), '/') ?: '/';

        return array_map(static function (array $item) use ($currentPath): array {
            $url = (string)($item['href'] ?? '');
            $path = rtrim((string)(parse_url($url, PHP_URL_PATH) ?? ''), '/') ?: '/';
            return $item + [
                'title' => (string)($item['label'] ?? ''),
                'url' => $url,
                'active' => $path === $currentPath,
                'children' => [],
            ];
        }, $items);
    }

    public function getPages(array $options = []): array
    {
        $limit = max(0, (int)($options['limit'] ?? 0));
        $pages = (new \App\Models\Page())->getPublishedOptions();
        $pages = array_map(static fn(array $page): array => $page + [
            'url' => base_href('/' . ltrim((string)$page['slug'], '/')),
        ], $pages);

        return $limit > 0 ? array_slice($pages, 0, $limit) : $pages;
    }

    public function getPosts(array $options = []): array
    {
        $limit = max(1, min(100, (int)($options['limit'] ?? 10)));
        return (new \App\Models\Post())->getLatestPublishedPosts($limit);
    }

    public function renderPartial(string $name, array $data = []): string
    {
        return $this->partial($name, $data);
    }

    protected function standardizeTemplateData(string $template, array $data): array
    {
        $data += [
            'settings' => (new SiteSetting())->all(),
            'user' => $this->currentUser(),
            'locale' => $this->currentLocale(),
            'available_locales' => $this->availableLocales(),
        ];

        if (isset($data['page']) && is_array($data['page'])) {
            $data['page'] += [
                'seo_title' => (string)($data['page']['meta_title'] ?? $data['page']['title'] ?? ''),
                'seo_description' => (string)($data['page']['meta_description'] ?? ''),
                'seo_keywords' => '',
                'url' => base_href('/' . ltrim((string)($data['page']['slug'] ?? ''), '/')),
            ];
        }

        if (isset($data['post']) && is_array($data['post'])) {
            $post = $data['post'];
            $data['author'] ??= [
                'name' => (string)($post['author_name'] ?? ''),
                'role' => (string)($post['author_role'] ?? ''),
            ];
            $data['category'] ??= $this->normalizeCategory([
                'id' => (int)($post['category_id'] ?? 0),
                'name' => (string)($post['category_label'] ?? $post['category'] ?? ''),
                'slug' => (string)($post['category_slug'] ?? ''),
            ]);
            $data['post'] += [
                'url' => base_href('/posts/' . ltrim((string)($post['slug'] ?? ''), '/')),
                'seo_title' => (string)($post['seo_title'] ?? $post['title'] ?? ''),
                'seo_description' => (string)($post['seo_description'] ?? ''),
                'seo_keywords' => (string)($post['seo_keywords'] ?? ''),
            ];
        }

        if (isset($data['category']) && is_array($data['category'])) {
            $data['category'] = $this->normalizeCategory($data['category']);
        }

        if ($template === 'home') {
            $data['page'] ??= null;
            $data['posts'] ??= $data['featured_posts'] ?? [];
        }

        return $data;
    }

    protected function normalizeCategory(array $category): array
    {
        $slug = trim((string)($category['slug'] ?? ''));
        return $category + [
            'id' => (int)($category['id'] ?? 0),
            'name' => (string)($category['label'] ?? $category['name'] ?? ''),
            'slug' => $slug,
            'description' => (string)($category['description'] ?? ''),
            'url' => base_href('/category/' . rawurlencode($slug)),
        ];
    }

    public function asset($path): string
    {
        $theme = $this->getActiveTheme();
        $default = $theme['slug'] !== self::DEFAULT_THEME ? $this->loadTheme(self::DEFAULT_THEME) : null;

        return $this->themeAssets->asset((string)$path, $theme, $default);
    }

    public function assetPath($path): string
    {
        $theme = $this->getActiveTheme();
        $default = $theme['slug'] !== self::DEFAULT_THEME ? $this->loadTheme(self::DEFAULT_THEME) : null;

        return $this->themeAssets->assetPath((string)$path, $theme, $default);
    }

    public function previewUrl(array $theme): string
    {
        return $this->themeAssets->previewUrl($theme);
    }

    protected function getPreviewSlug(): ?string
    {
        try {
            $slug = trim((string)(request()->get('preview_theme', '') ?: request()->get('theme_preview', '')));
        } catch (\Throwable) {
            return null;
        }

        if ($slug === '' || !$this->isValidSlug($slug) || !$this->validateThemeStructure($slug)) {
            return null;
        }

        try {
            return check_admin() ? $slug : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveUploadedZipPath($zipFile): string
    {
        if (is_string($zipFile)) {
            return $zipFile;
        }

        if (!is_array($zipFile)) {
            throw new RuntimeException('Theme ZIP was not uploaded.');
        }

        $error = (int)($zipFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Theme ZIP upload failed.');
        }

        $name = (string)($zipFile['name'] ?? '');
        $tmpName = (string)($zipFile['tmp_name'] ?? '');
        $size = (int)($zipFile['size'] ?? 0);
        if ($tmpName === '' || !is_file($tmpName) || $size <= 0 || $size > self::MAX_PACKAGE_BYTES || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException('Uploaded file must be a ZIP archive.');
        }

        try {
            (new \App\Services\SafeUploadService())->validate(
                $tmpName,
                $name,
                $size,
                self::MAX_PACKAGE_BYTES,
                ['zip']
            );
        } catch (\RuntimeException $exception) {
            throw new RuntimeException('Uploaded file must be a valid ZIP archive.', 0, $exception);
        }

        return $tmpName;
    }

    protected function assertSafeZipEntry(string $name): void
    {
        $name = trim($name);
        if ($name === '' || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $name) || str_contains($name, "\0")) {
            throw new RuntimeException('Theme ZIP contains an unsafe path.');
        }

        $segments = explode('/', $name);
        foreach ($segments as $segment) {
            if ($segment === '..') {
                throw new RuntimeException('Theme ZIP contains path traversal.');
            }
        }

        $basename = basename($name);
        if ($basename === '.env' || $basename === '.htaccess' || $basename === '.DS_Store' || str_starts_with($basename, '.git')) {
            throw new RuntimeException('Theme ZIP contains forbidden files.');
        }

        if (preg_match('/(?:^|\\/)(?:node_modules|vendor|\\.git)(?:\\/|$)/', $name)) {
            throw new RuntimeException('Theme ZIP contains forbidden directories.');
        }

        if (preg_match('/\\.(?:bak|backup|old|orig|tmp|swp|exe|sh|bat|cmd|com|scr|phar|phtml|php[0-9]?)$/i', $basename)) {
            if (!preg_match('#^(?:[^/]+/)?(?:templates|partials)/[^/]+\\.php$#', $name)) {
                throw new RuntimeException('Theme ZIP contains forbidden executable files.');
            }
        }

        if (str_ends_with($name, '/')) {
            return;
        }

        if (strtolower(pathinfo($basename, PATHINFO_EXTENSION)) === 'php' && !preg_match('#^(?:[^/]+/)?(?:templates|partials)/[A-Za-z0-9._-]+\\.php$#', $name)) {
            throw new RuntimeException('PHP files are allowed only in templates/ and partials/.');
        }
    }

    protected function findExtractedThemeRoot(string $tempDir): string
    {
        $directManifest = $tempDir . '/theme.json';
        if (is_file($directManifest)) {
            return realpath($tempDir) ?: $tempDir;
        }

        $candidates = [];
        foreach (scandir($tempDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $tempDir . '/' . $entry;
            if (is_dir($path) && is_file($path . '/theme.json')) {
                $candidates[] = realpath($path) ?: $path;
            }
        }

        if (count($candidates) !== 1) {
            throw new RuntimeException('Could not determine theme root in ZIP.');
        }

        return $candidates[0];
    }

    protected function validateExtractedTheme(string $themeRoot): void
    {
        $themeRoot = realpath($themeRoot) ?: $themeRoot;
        foreach ($this->requiredThemeFiles() as $relativePath) {
            $path = realpath($themeRoot . '/' . $relativePath);
            if ($path === false || !$this->isInside($path, $themeRoot) || !is_file($path)) {
                throw new RuntimeException('Theme package is missing required file: ' . $relativePath);
            }
        }

        foreach ($this->requiredThemeDirectories() as $relativePath) {
            $path = realpath($themeRoot . '/' . $relativePath);
            if ($path === false || !$this->isInside($path, $themeRoot) || !is_dir($path)) {
                throw new RuntimeException('Theme package is missing required directory: ' . $relativePath);
            }
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if ($file->isLink()) {
                throw new RuntimeException('Theme package must not contain symlinks.');
            }

            $relative = ltrim(str_replace($themeRoot, '', $path), '/');
            $this->assertSafeZipEntry($relative);

            if ($file->isFile() && strtolower(pathinfo($relative, PATHINFO_EXTENSION)) === 'php' && !preg_match('#^(templates|partials)/[A-Za-z0-9._-]+\\.php$#', $relative)) {
                throw new RuntimeException('PHP files are allowed only in templates/ and partials/.');
            }
        }
    }

    protected function exportableThemeFiles(string $themeRoot): array
    {
        $files = [];
        $allowedRoots = ['theme.json', 'preview.png', 'templates', 'partials', 'assets'];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }

            $path = $file->getPathname();
            if (!$this->isPathInsideCandidate($path, $themeRoot)) {
                continue;
            }

            $relative = ltrim(str_replace($themeRoot, '', $path), '/');
            $first = explode('/', $relative, 2)[0] ?? '';
            if (!in_array($first, $allowedRoots, true) || $this->isExcludedExportPath($relative)) {
                continue;
            }

            $files[] = $relative;
        }

        sort($files);

        return $files;
    }

    protected function isExcludedExportPath(string $relativePath): bool
    {
        $basename = basename($relativePath);
        if ($basename === '.DS_Store' || $basename === '.env' || $basename === '.htaccess') {
            return true;
        }

        if (preg_match('/(?:^|\\/)(?:node_modules|vendor|\\.git)(?:\\/|$)/', $relativePath)) {
            return true;
        }

        return preg_match('/\\.(?:bak|backup|old|orig|tmp|swp)$/i', $basename) === 1;
    }

    protected function requiredThemeFiles(): array
    {
        return [
            'theme.json',
            'templates/layout.php',
            'templates/home.php',
            'templates/page.php',
            'templates/post.php',
            'partials/header.php',
            'partials/footer.php',
            'partials/menu.php',
        ];
    }

    protected function requiredThemeDirectories(): array
    {
        return [
            'templates',
            'partials',
            'assets',
            'assets/css',
            'assets/js',
            'assets/images',
        ];
    }

    protected function isCompatibleCmsVersion(string $minCmsVersion): bool
    {
        $minCmsVersion = trim($minCmsVersion);
        if ($minCmsVersion === '') {
            return true;
        }

        $currentVersion = '0.0.0';
        $versionFile = CONFIG . '/version.php';
        if (is_file($versionFile)) {
            $release = require $versionFile;
            if (is_array($release)) {
                $currentVersion = (string)($release['version'] ?? $currentVersion);
            }
        }

        return version_compare($currentVersion, $minCmsVersion, '>=');
    }

    protected function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if ($file->isDir() && !$file->isLink()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    protected function logThemeAction(string $event, array $context = []): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $event;
        if ($context) {
            $line .= ' ' . (json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}');
        }

        $logPath = ROOT . '/tmp/theme-actions.log';
        @file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    protected function normalizeThemeData(array $data, bool $includeSlug): array
    {
        $normalized = [
            'name' => trim(strip_tags((string)($data['name'] ?? ''))),
            'version' => trim(strip_tags((string)($data['version'] ?? '1.0.0'))),
            'author' => trim(strip_tags((string)($data['author'] ?? ''))),
            'description' => trim(strip_tags((string)($data['description'] ?? ''))),
            'preview' => $this->sanitizePreview((string)($data['preview'] ?? 'preview.png')),
            'preview_source' => trim((string)($data['preview_source'] ?? '')),
            'preview_upload' => is_array($data['preview_upload'] ?? null) ? $data['preview_upload'] : null,
            'minCmsVersion' => '1.5.0',
        ];

        if ($normalized['version'] === '') {
            $normalized['version'] = '1.0.0';
        }

        if ($normalized['preview'] === '') {
            $normalized['preview'] = 'preview.png';
        }

        if ($includeSlug) {
            $normalized['slug'] = trim(strtolower((string)($data['slug'] ?? '')));
        }

        return $normalized;
    }

    protected function writeThemeJson(string $themeRoot, array $data): void
    {
        $payload = [
            'name' => (string)($data['name'] ?? ''),
            'slug' => (string)($data['slug'] ?? ''),
            'version' => (string)($data['version'] ?? '1.0.0'),
            'author' => (string)($data['author'] ?? ''),
            'description' => (string)($data['description'] ?? ''),
            'preview' => $this->sanitizePreview((string)($data['preview'] ?? 'preview.png')),
            'minCmsVersion' => (string)($data['minCmsVersion'] ?? '1.5.0'),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || json_decode($json, true) === null) {
            throw new RuntimeException('Could not encode theme.json.');
        }

        $this->writeThemeFile($themeRoot, 'theme.json', $json . PHP_EOL, true);
    }

    protected function writeThemeFile(string $themeRoot, string $relativePath, string $content, bool $allowOverwrite = false): void
    {
        $safePath = $this->safePath($relativePath);
        if ($safePath === null) {
            throw new RuntimeException('Unsafe theme file path.');
        }

        if (!$this->isAllowedScaffoldFile($safePath) && !$allowOverwrite) {
            throw new RuntimeException('Theme file is not allowed in scaffold.');
        }

        $target = $themeRoot . '/' . $safePath;
        $directory = realpath(dirname($target));
        if ($directory === false || !$this->isInside($directory, $themeRoot)) {
            throw new RuntimeException('Theme file target is outside theme directory.');
        }

        if (!$allowOverwrite && file_exists($target)) {
            throw new RuntimeException('Theme file already exists.');
        }

        if (file_put_contents($target, $content, LOCK_EX) === false) {
            throw new RuntimeException('Could not write theme file.');
        }
    }

    protected function copyDefaultPreview(string $themeRoot): void
    {
        $source = $this->themeDirectory(self::DEFAULT_THEME) . '/preview.png';
        $target = $themeRoot . '/preview.png';
        if (is_file($source)) {
            copy($source, $target);
            return;
        }

        $placeholder = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        file_put_contents($target, $placeholder ?: '');
    }

    protected function prepareThemePreview(string $themeRoot, array $data, bool $createDefault): string
    {
        $upload = $data['preview_upload'] ?? null;
        if (is_array($upload) && !empty($upload['size'])) {
            return $this->copyPreviewUpload($themeRoot, $upload);
        }

        $source = trim((string)($data['preview_source'] ?? ''));
        if ($source !== '') {
            return $this->copyPreviewSource($themeRoot, $source);
        }

        $preview = $this->sanitizePreview((string)($data['preview'] ?? 'preview.png'));
        if ($createDefault && !is_file($themeRoot . '/' . $preview)) {
            $this->copyDefaultPreview($themeRoot);
            return 'preview.png';
        }

        return $preview !== '' ? $preview : 'preview.png';
    }

    protected function copyPreviewUpload(string $themeRoot, array $file): string
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmpName = (string)($file['tmp_name'] ?? '');
        $name = (string)($file['name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $extension = $this->previewExtension($name);

        if ($error !== UPLOAD_ERR_OK || $tmpName === '' || !is_file($tmpName) || $size <= 0 || $size > 5 * 1024 * 1024 || $extension === '' || $extension === 'svg') {
            throw new RuntimeException('Invalid preview upload.');
        }

        (new \App\Services\SafeUploadService())->validate(
            $tmpName,
            $name,
            $size,
            5 * 1024 * 1024,
            ['png', 'jpg', 'jpeg', 'webp', 'gif']
        );

        $targetName = 'preview.' . $extension;
        $target = $themeRoot . '/' . $targetName;
        if (!$this->isPathInsideCandidate($target, $themeRoot)) {
            throw new RuntimeException('Invalid preview target path.');
        }

        $stored = is_uploaded_file($tmpName)
            ? move_uploaded_file($tmpName, $target)
            : copy($tmpName, $target);

        if (!$stored) {
            throw new RuntimeException('Could not store preview upload.');
        }

        return $targetName;
    }

    protected function copyPreviewSource(string $themeRoot, string $source): string
    {
        $source = trim($source);
        if (!preg_match('~^/uploads/(?!.*(?:^|/)\.\.(?:/|$))[A-Za-z0-9/_.,%+\-=]+\.(png|jpe?g|webp|gif|svg)$~i', $source)) {
            throw new RuntimeException('Invalid preview source.');
        }

        $sourcePath = WWW . '/' . ltrim($source, '/');
        $realSource = realpath($sourcePath);
        $uploadsRoot = realpath(UPLOADS);
        if ($realSource === false || $uploadsRoot === false || !$this->isInside($realSource, $uploadsRoot) || !is_file($realSource)) {
            throw new RuntimeException('Preview source file was not found.');
        }

        $extension = $this->previewExtension($realSource);
        if ($extension === '') {
            throw new RuntimeException('Invalid preview source extension.');
        }

        $targetName = 'preview.' . $extension;
        $target = $themeRoot . '/' . $targetName;
        if (!$this->isPathInsideCandidate($target, $themeRoot) || !copy($realSource, $target)) {
            throw new RuntimeException('Could not copy preview source.');
        }

        return $targetName;
    }

    protected function previewExtension(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true) ? $extension : '';
    }

    protected function loadTheme(string $slug): ?array
    {
        if (!$this->isValidSlug($slug)) {
            return null;
        }

        $themeDir = $this->themeDirectory($slug);
        $manifest = $themeDir . '/theme.json';
        if (!is_dir($themeDir) || !is_file($manifest)) {
            return null;
        }

        $themeRoot = realpath($themeDir);
        $themesRoot = realpath($this->themesPath);
        $manifestPath = realpath($manifest);
        if ($themeRoot === false || $themesRoot === false || $manifestPath === false || !$this->isInside($themeRoot, $themesRoot) || !$this->isInside($manifestPath, $themeRoot)) {
            return null;
        }

        $data = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($data)) {
            return null;
        }

        $jsonSlug = trim((string)($data['slug'] ?? ''));
        if ($jsonSlug !== $slug || !$this->isValidSlug($jsonSlug)) {
            return null;
        }

        return [
            'name' => trim((string)($data['name'] ?? $slug)) ?: $slug,
            'slug' => $slug,
            'version' => trim((string)($data['version'] ?? '')),
            'author' => trim((string)($data['author'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
            'preview' => $this->sanitizePreview((string)($data['preview'] ?? 'preview.png')) ?: 'preview.png',
            'minCmsVersion' => trim((string)($data['minCmsVersion'] ?? '')),
            'path' => $themeRoot,
            'preview_url' => base_url('/themes/' . rawurlencode($slug) . '/' . rawurlencode($this->sanitizePreview((string)($data['preview'] ?? 'preview.png')) ?: 'preview.png')),
        ];
    }

    protected function themeFile(array $theme, string $directory, string $name): ?string
    {
        $safeName = $this->safePath($name);
        if ($safeName === null) {
            return null;
        }

        $file = $theme['path'] . '/' . $directory . '/' . $safeName . '.php';
        $realFile = realpath($file);
        $realBase = realpath($theme['path'] . '/' . $directory);
        if ($realFile === false || $realBase === false || !$this->isInside($realFile, $realBase) || !is_file($realFile)) {
            return null;
        }

        return $realFile;
    }

    protected function assetFile(array $theme, string $path): ?string
    {
        $file = $theme['path'] . '/assets/' . $path;
        $realFile = realpath($file);
        $realBase = realpath($theme['path'] . '/assets');
        if ($realFile === false || $realBase === false || !$this->isInside($realFile, $realBase) || !is_file($realFile)) {
            return null;
        }

        return $realFile;
    }

    protected function safePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '../') || str_contains($path, '..\\') || $path === '..' || str_contains($path, "\0")) {
            return null;
        }

        if (!preg_match('#^[A-Za-z0-9._/-]+$#', $path)) {
            return null;
        }

        return $path;
    }

    protected function sanitizePreview(string $preview): string
    {
        $preview = trim(str_replace('\\', '/', $preview));
        $preview = basename($preview);
        if ($preview === '' || str_contains($preview, '..') || !preg_match('/^[A-Za-z0-9._-]+\.(png|jpg|jpeg|webp|gif|svg)$/i', $preview)) {
            return 'preview.png';
        }

        return $preview;
    }

    protected function themeDirectory(string $slug): string
    {
        return $this->themesPath . '/' . $slug;
    }

    protected function isValidSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) === 1;
    }

    protected function isReservedSlug(string $slug): bool
    {
        return in_array($slug, self::RESERVED_SLUGS, true);
    }

    protected function isInside(string $path, string $base): bool
    {
        return $path === $base || str_starts_with($path, rtrim($base, '/') . '/');
    }

    protected function isPathInsideCandidate(string $path, string $base): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $base = rtrim(str_replace('\\', '/', $base), '/');

        return $path === $base || str_starts_with($path, $base . '/');
    }

    protected function ensureThemesRoot(): string
    {
        if (!is_dir($this->themesPath) && !mkdir($this->themesPath, 0755, true) && !is_dir($this->themesPath)) {
            throw new RuntimeException('Could not create themes directory.');
        }

        $themesRoot = realpath($this->themesPath);
        if ($themesRoot === false) {
            throw new RuntimeException('Themes directory is not available.');
        }

        return $themesRoot;
    }

    protected function themeScaffoldDirectories(): array
    {
        return [
            'templates',
            'partials',
            'assets',
            'assets/css',
            'assets/js',
            'assets/images',
        ];
    }

    protected function isAllowedScaffoldFile(string $path): bool
    {
        return in_array($path, [
            'templates/layout.php',
            'templates/home.php',
            'templates/page.php',
            'templates/post.php',
            'templates/category.php',
            'templates/search.php',
            'templates/archive.php',
            'templates/404.php',
            'partials/header.php',
            'partials/footer.php',
            'partials/menu.php',
            'partials/sidebar.php',
            'assets/css/style.css',
            'assets/js/theme.js',
        ], true);
    }

    protected function storeActiveTheme(string $slug): void
    {
        try {
            (new SiteSetting())->setMany(['active_theme' => $slug]);
        } catch (\Throwable) {
            // Rendering can still fall back to the default theme if settings storage is unavailable.
        }
    }

    protected function defaultLayoutTemplate(): string
    {
        return <<<'PHP'
<?php
/**
 * Layout Template
 *
 * Available variables: $settings, $user, $locale, $available_locales.
 * Page-specific variables are also available.
 */
?>
<!doctype html>
<html lang="<?= htmlSC(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <?= get_csrf_meta() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlSC($title ?? site_setting('site_title', SITE_NAME)) ?></title>
    <link rel="stylesheet" href="<?= theme_asset('css/style.css') ?>">
</head>
<body>
    <?= render_partial('header', get_defined_vars()) ?>
    <?= render_partial('menu', get_defined_vars()) ?>

    <main class="theme-main">
        <?= $this->content ?>
    </main>

    <?= render_partial('footer', get_defined_vars()) ?>
    <script src="<?= theme_asset('js/theme.js') ?>"></script>
</body>
</html>
PHP;
    }

    protected function defaultHomeTemplate(): string
    {
        return <<<'PHP'
<?php
/**
 * Home Template
 *
 * Available variables: $page, $posts, $settings, $user, $locale.
 */
?>
<section class="theme-section">
    <div class="theme-container">
        <h1><?= htmlSC($title ?? site_setting('site_title', SITE_NAME)) ?></h1>
        <p>This is the home page template for your new FIREBALL CMS theme.</p>
    </div>
</section>
PHP;
    }

    protected function defaultPageTemplate(): string
    {
        return <<<'PHP'
<?php
/**
 * Page Template
 *
 * Available variables: $page, $settings, $user, $locale.
 */
?>
<section class="theme-section">
    <div class="theme-container">
        <h1><?= htmlSC($page['title'] ?? $title ?? '') ?></h1>
        <div class="theme-content">
            <?= $page['content'] ?? '' ?>
        </div>
    </div>
</section>
PHP;
    }

    protected function defaultPostTemplate(): string
    {
        return <<<'PHP'
<?php
/**
 * Post Template
 *
 * Available variables: $post, $author, $category, $settings, $user.
 */
?>
<article class="theme-section">
    <div class="theme-container">
        <h1><?= htmlSC($post['title'] ?? $title ?? '') ?></h1>
        <?php if (!empty($post['published_at'])): ?>
            <p class="theme-muted"><?= htmlSC(date('d.m.Y', strtotime($post['published_at']))) ?></p>
        <?php endif; ?>
        <div class="theme-content">
            <?= $post['content'] ?? '' ?>
        </div>
    </div>
</article>
PHP;
    }

    protected function defaultCategoryTemplate(): string
    {
        return <<<'PHP'
<?php
/**
 * Category Template
 *
 * Available variables: $category, $posts, $pagination.
 */
?>
<section class="theme-section">
    <div class="theme-container">
        <h1><?= htmlSC($category['name'] ?? '') ?></h1>
        <?php if (!empty($category['description'])): ?>
            <p class="theme-muted"><?= htmlSC($category['description']) ?></p>
        <?php endif; ?>
        <?php foreach ($posts ?? [] as $post): ?>
            <article>
                <h2><a href="<?= htmlSC($post['url'] ?? base_href('/posts/' . $post['slug'])) ?>"><?= htmlSC($post['title']) ?></a></h2>
                <p><?= htmlSC($post['excerpt'] ?? '') ?></p>
            </article>
        <?php endforeach; ?>
        <?= $pagination ?? '' ?>
    </div>
</section>
PHP;
    }

    protected function defaultSearchTemplate(): string
    {
        return <<<'PHP'
<?php
/**
 * Search Template
 *
 * Available variables: $query, $results, $total, $pagination.
 */
?>
<section class="theme-section">
    <div class="theme-container">
        <h1>Search</h1>
        <form action="<?= base_href('/search') ?>" method="get">
            <input type="search" name="q" value="<?= htmlSC($query ?? '') ?>">
            <button type="submit">Search</button>
        </form>
        <?php foreach ($results ?? [] as $result): ?>
            <article>
                <h2><a href="<?= htmlSC($result['url']) ?>"><?= htmlSC($result['title']) ?></a></h2>
                <p><?= htmlSC($result['excerpt'] ?? '') ?></p>
            </article>
        <?php endforeach; ?>
        <?= $pagination ?? '' ?>
    </div>
</section>
PHP;
    }

    protected function defaultArchiveTemplate(): string
    {
        return <<<'PHP'
<?php
/**
 * Archive Template
 *
 * Available variables: $posts, $pagination.
 */
?>
<section class="theme-section">
    <div class="theme-container">
        <h1><?= htmlSC($title ?? 'Archive') ?></h1>
        <?php foreach ($posts ?? [] as $post): ?>
            <article>
                <h2><a href="<?= base_href('/posts/' . $post['slug']) ?>"><?= htmlSC($post['title']) ?></a></h2>
                <p><?= htmlSC($post['excerpt'] ?? '') ?></p>
            </article>
        <?php endforeach; ?>
        <?= $pagination ?? '' ?>
    </div>
</section>
PHP;
    }

    protected function default404Template(): string
    {
        return <<<'PHP'
<?php
/**
 * 404 Template
 *
 * Available variables: $settings.
 */
?>
<section class="theme-section">
    <div class="theme-container">
        <h1>404</h1>
        <p>Page not found.</p>
        <a href="<?= base_href('/') ?>">Home</a>
    </div>
</section>
PHP;
    }

    protected function defaultHeaderPartial(): string
    {
        return <<<'PHP'
<header class="theme-header">
    <div class="theme-container theme-header-inner">
        <a class="theme-brand" href="<?= site_url() ?>"><?= htmlSC(site_name()) ?></a>
    </div>
</header>
PHP;
    }

    protected function defaultFooterPartial(): string
    {
        return <<<'PHP'
<?php $legalInformationMenu = $this->getLegalInformationMenu(); ?>
<footer class="theme-footer">
    <div class="theme-container">
        <?php if ($legalInformationMenu): ?>
            <nav aria-label="<?= htmlSC(return_translation('footer_heading_legal_information')) ?>">
                <strong><?= print_translation('footer_heading_legal_information') ?></strong>
                <?php foreach ($legalInformationMenu as $item): ?>
                    <a href="<?= htmlSC($item['href']) ?>"><?= htmlSC($item['label']) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <p>&copy; <?= date('Y') ?> <?= htmlSC(site_setting('site_title', SITE_NAME)) ?></p>
    </div>
</footer>
PHP;
    }

    protected function defaultMenuPartial(): string
    {
        return <<<'PHP'
<nav class="theme-menu" aria-label="Main menu">
    <div class="theme-container">
        <a href="<?= site_url() ?>">Home</a>
        <?php foreach (get_menu('header') as $item): ?>
            <a href="<?= htmlSC($item['url']) ?>" <?= $item['active'] ? 'aria-current="page"' : '' ?>>
                <?= htmlSC($item['title']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
PHP;
    }

    protected function defaultSidebarPartial(): string
    {
        return <<<'PHP'
<?php
/**
 * Sidebar Partial
 *
 * Pass data explicitly: render_partial('sidebar', ['items' => $items]).
 */
?>
<?php if (!empty($items)): ?>
    <aside class="theme-sidebar">
        <?php foreach ($items as $item): ?>
            <a href="<?= htmlSC($item['url'] ?? '#') ?>"><?= htmlSC($item['title'] ?? '') ?></a>
        <?php endforeach; ?>
    </aside>
<?php endif; ?>
PHP;
    }

    protected function defaultThemeCss(): string
    {
        return <<<'CSS'
:root {
    --theme-bg: #ffffff;
    --theme-text: #1d2433;
    --theme-muted: #667085;
    --theme-border: #e4e7ec;
    --theme-accent: #d94f04;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    color: var(--theme-text);
    background: var(--theme-bg);
    font-family: Georgia, "Times New Roman", serif;
    line-height: 1.6;
}

.theme-container {
    width: min(1120px, calc(100% - 32px));
    margin: 0 auto;
}

.theme-header,
.theme-footer,
.theme-menu {
    border-bottom: 1px solid var(--theme-border);
}

.theme-header-inner,
.theme-menu .theme-container,
.theme-footer .theme-container {
    padding: 18px 0;
}

.theme-brand {
    color: var(--theme-text);
    font-size: 24px;
    font-weight: 700;
    text-decoration: none;
}

.theme-menu a {
    color: var(--theme-text);
    margin-right: 18px;
    text-decoration: none;
}

.theme-menu a:hover {
    color: var(--theme-accent);
}

.theme-section {
    padding: 56px 0;
}

.theme-content img {
    max-width: 100%;
    height: auto;
}

.theme-muted {
    color: var(--theme-muted);
}
CSS;
    }

    protected function defaultThemeJs(): string
    {
        return <<<'JS'
// Theme JavaScript entry point.
JS;
    }
}
