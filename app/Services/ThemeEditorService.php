<?php

namespace App\Services;

use FBL\Theme;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Performs all filesystem operations exposed by the administrative theme editor.
 */
class ThemeEditorService
{
    public const MAX_TEXT_BYTES = 1048576;
    public const MAX_IMAGE_BYTES = 5242880;

    protected const TEXT_EXTENSIONS = ['php', 'html', 'htm', 'css', 'js', 'json', 'md', 'txt', 'svg'];
    protected const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
    protected const EDITOR_ROOTS = ['templates', 'partials', 'assets/css', 'assets/js', 'assets/images'];
    protected const REQUIRED_MANIFEST_FIELDS = ['name', 'slug', 'version', 'author', 'description', 'preview'];
    protected const PROTECTED_PATHS = ['theme.json', 'templates/layout.php'];
    protected const PROTECTED_DIRECTORIES = ['templates', 'partials', 'assets', 'assets/css', 'assets/js', 'assets/images'];

    protected string $themesRoot;
    protected string $backupRoot;
    protected string $logFile;

    public function __construct()
    {
        $this->themesRoot = $this->ensureDirectory(ROOT . '/themes');
        $this->backupRoot = $this->ensureDirectory(STORAGE . '/theme-backups');
        $this->logFile = STORAGE . '/logs/theme-editor.log';
        $this->ensureDirectory(dirname($this->logFile));
    }

    public function tree(string $slug): array
    {
        $root = $this->themeRoot($slug);
        $nodes = [];

        if (is_file($root . '/theme.json') && !is_link($root . '/theme.json')) {
            $nodes[] = $this->fileNode($root, 'theme.json');
        }

        foreach (['templates', 'partials', 'assets'] as $directory) {
            $path = $root . '/' . $directory;
            if (is_dir($path) && !is_link($path)) {
                $nodes[] = $this->directoryNode($root, $directory);
            }
        }

        return $nodes;
    }

    public function open(string $slug, string $relativePath): array
    {
        $root = $this->themeRoot($slug);
        $relativePath = $this->normalizePath($relativePath);
        $path = $this->resolveExistingPath($root, $relativePath);

        if (!is_file($path)) {
            throw new RuntimeException('Theme editor can open files only.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isImage = in_array($extension, self::IMAGE_EXTENSIONS, true)
            && str_starts_with($relativePath, 'assets/images/');
        $size = (int)filesize($path);

        if (!$isImage && $size > self::MAX_TEXT_BYTES) {
            throw new RuntimeException('Text file exceeds the 1 MB editor limit.');
        }

        $this->log('open', $slug, $relativePath);

        return [
            'type' => 'file',
            'path' => $relativePath,
            'name' => basename($relativePath),
            'extension' => $extension,
            'size' => $size,
            'modified_at' => (int)filemtime($path),
            'is_image' => $isImage,
            'content' => $isImage ? null : (string)file_get_contents($path),
            'url' => $isImage ? base_url('/themes/' . rawurlencode($slug) . '/' . $this->encodePath($relativePath)) : null,
            'language' => $this->editorLanguage($extension),
            'protected' => in_array($relativePath, self::PROTECTED_PATHS, true),
        ];
    }

    public function openDirectory(string $slug, string $relativePath): array
    {
        $root = $this->themeRoot($slug);
        $relativePath = $this->normalizePath($relativePath);
        $path = $this->resolveExistingPath($root, $relativePath, true);
        $this->log('open_directory', $slug, $relativePath);

        return [
            'type' => 'directory',
            'path' => $relativePath,
            'name' => basename($relativePath),
            'size' => 0,
            'is_image' => false,
            'protected' => in_array($relativePath, self::PROTECTED_DIRECTORIES, true),
            'modified_at' => (int)filemtime($path),
        ];
    }

    public function save(string $slug, string $relativePath, string $content): void
    {
        try {
            $root = $this->themeRoot($slug);
            $relativePath = $this->normalizePath($relativePath);
            $path = $this->resolveExistingPath($root, $relativePath);

            if (!is_file($path) || $this->isImagePath($relativePath)) {
                throw new RuntimeException('This file cannot be edited as text.');
            }
            if (strlen($content) > self::MAX_TEXT_BYTES) {
                throw new RuntimeException('Text file exceeds the 1 MB editor limit.');
            }

            $this->validateTextContent($slug, $relativePath, $content);
            $this->backupFile($slug, $relativePath, $path);
            $this->atomicWrite($path, $content);
            $this->log('save', $slug, $relativePath, ['size' => strlen($content)]);
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $event = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) === 'php'
                && preg_match('/(?:syntax error|parse error|errors parsing)/i', $message)
                ? 'syntax_error'
                : 'save_error';
            $this->log($event, $slug, $relativePath, ['error' => $message]);
            throw $exception;
        }
    }

    public function createFile(string $slug, string $directory, string $name): string
    {
        $root = $this->themeRoot($slug);
        $directory = $this->normalizePath($directory);
        $name = $this->validateName($name);
        $parent = $this->resolveExistingPath($root, $directory, true);
        $relativePath = ltrim($directory . '/' . $name, '/');
        $this->assertAllowedPath($relativePath);
        $this->assertAllowedFile($relativePath);
        $target = $parent . '/' . $name;

        if (file_exists($target) || is_link($target)) {
            throw new RuntimeException('A file or folder with this name already exists.');
        }
        if (!@touch($target)) {
            throw new RuntimeException('Could not create the theme file.');
        }

        $this->log('create_file', $slug, $relativePath);
        return $relativePath;
    }

    public function createDirectory(string $slug, string $directory, string $name): string
    {
        $root = $this->themeRoot($slug);
        $directory = $this->normalizePath($directory);
        $name = $this->validateName($name, false);
        $parent = $this->resolveExistingPath($root, $directory, true);
        $relativePath = ltrim($directory . '/' . $name, '/');
        $this->assertAllowedDirectory($relativePath);
        $target = $parent . '/' . $name;

        if (file_exists($target) || is_link($target)) {
            throw new RuntimeException('A file or folder with this name already exists.');
        }
        if (!mkdir($target, 0755)) {
            throw new RuntimeException('Could not create the theme folder.');
        }

        $this->log('create_directory', $slug, $relativePath);
        return $relativePath;
    }

    public function rename(string $slug, string $relativePath, string $newName): string
    {
        $root = $this->themeRoot($slug);
        $relativePath = $this->normalizePath($relativePath);
        if (in_array($relativePath, self::PROTECTED_PATHS, true)
            || in_array($relativePath, self::PROTECTED_DIRECTORIES, true)
        ) {
            throw new RuntimeException('This system theme file cannot be renamed.');
        }

        $source = $this->resolveExistingPath($root, $relativePath);
        if (is_dir($source)) {
            $this->assertDirectoryHasNoSymlinks($source);
        }
        $newName = $this->validateName($newName, is_file($source));
        $parentRelative = trim(dirname($relativePath), '.');
        $targetRelative = ltrim(($parentRelative !== '' ? $parentRelative . '/' : '') . $newName, '/');

        if (is_file($source)) {
            $this->assertAllowedFile($targetRelative);
        } else {
            $this->assertAllowedDirectory($targetRelative);
        }

        $target = dirname($source) . '/' . $newName;
        if (file_exists($target) || is_link($target)) {
            throw new RuntimeException('A file or folder with this name already exists.');
        }
        if (!rename($source, $target)) {
            throw new RuntimeException('Could not rename the theme item.');
        }

        $this->log('rename', $slug, $relativePath, ['target' => $targetRelative]);
        return $targetRelative;
    }

    public function delete(string $slug, string $relativePath): void
    {
        $root = $this->themeRoot($slug);
        $relativePath = $this->normalizePath($relativePath);
        if (in_array($relativePath, self::PROTECTED_PATHS, true)
            || in_array($relativePath, self::PROTECTED_DIRECTORIES, true)
        ) {
            throw new RuntimeException('This required theme file cannot be deleted.');
        }

        $path = $this->resolveExistingPath($root, $relativePath);
        if (is_file($path)) {
            $this->backupFile($slug, $relativePath, $path);
            if (!unlink($path)) {
                throw new RuntimeException('Could not delete the theme file.');
            }
        } elseif (is_dir($path)) {
            $this->assertDirectoryHasNoSymlinks($path);
            $this->backupDirectoryFiles($slug, $relativePath, $path);
            $this->deleteDirectory($path);
        } else {
            throw new RuntimeException('Theme item was not found.');
        }

        $this->log('delete', $slug, $relativePath);
    }

    public function replaceImage(string $slug, string $relativePath, array $upload): void
    {
        $root = $this->themeRoot($slug);
        $relativePath = $this->normalizePath($relativePath);
        $path = $this->resolveExistingPath($root, $relativePath);
        if (!is_file($path) || !$this->isImagePath($relativePath)) {
            throw new RuntimeException('Only theme images can be replaced.');
        }

        $tmp = (string)($upload['tmp_name'] ?? '');
        $size = (int)($upload['size'] ?? 0);
        $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        $extension = strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION));
        $targetExtension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp) || $size <= 0 || $size > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('Upload a valid image up to 5 MB.');
        }
        if (!in_array($extension, self::IMAGE_EXTENSIONS, true) || $extension !== $targetExtension) {
            throw new RuntimeException('The replacement image must use the same allowed extension.');
        }
        if ($extension !== 'svg' && @getimagesize($tmp) === false) {
            throw new RuntimeException('The uploaded file is not a valid image.');
        }
        if ($extension === 'svg') {
            $svg = (string)file_get_contents($tmp);
            $this->validateSvg($svg);
        }

        $this->backupFile($slug, $relativePath, $path);
        if (!move_uploaded_file($tmp, $path)) {
            throw new RuntimeException('Could not replace the theme image.');
        }
        $this->log('replace_image', $slug, $relativePath, ['size' => $size]);
    }

    public function history(string $slug, string $relativePath): array
    {
        $this->themeRoot($slug);
        $relativePath = $this->normalizePath($relativePath);
        $directory = $this->backupDirectory($slug, $relativePath);
        if (!is_dir($directory)) {
            return [];
        }

        $items = [];
        foreach (glob($directory . '/*.json') ?: [] as $metadataFile) {
            $metadata = json_decode((string)file_get_contents($metadataFile), true);
            if (!is_array($metadata) || ($metadata['path'] ?? '') !== $relativePath) {
                continue;
            }
            $backupFile = substr($metadataFile, 0, -5) . '.bak';
            if (!is_file($backupFile)) {
                continue;
            }
            $items[] = $metadata + ['id' => basename($metadataFile, '.json')];
        }

        usort($items, static fn(array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));
        return array_slice($items, 0, 20);
    }

    public function restore(string $slug, string $relativePath, string $backupId): void
    {
        $root = $this->themeRoot($slug);
        $relativePath = $this->normalizePath($relativePath);
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $backupId)) {
            throw new RuntimeException('Invalid backup identifier.');
        }

        $path = $this->resolveExistingPath($root, $relativePath);
        if (!is_file($path)) {
            throw new RuntimeException('Only existing files can be restored.');
        }

        $directory = $this->backupDirectory($slug, $relativePath);
        $backup = realpath($directory . '/' . $backupId . '.bak');
        $metadata = realpath($directory . '/' . $backupId . '.json');
        if ($backup === false || $metadata === false || !$this->isInside($backup, $directory)) {
            throw new RuntimeException('Backup was not found.');
        }

        $content = (string)file_get_contents($backup);
        if (!$this->isImagePath($relativePath)) {
            $this->validateTextContent($slug, $relativePath, $content);
        }
        $this->backupFile($slug, $relativePath, $path);
        $this->atomicWrite($path, $content);
        $this->log('restore', $slug, $relativePath, ['backup' => $backupId]);
    }

    public function copyTheme(string $sourceSlug, string $newSlug, string $newName = ''): array
    {
        $source = $this->themeRoot($sourceSlug);
        $newSlug = strtolower(trim($newSlug));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $newSlug)) {
            throw new RuntimeException('Invalid theme copy slug.');
        }

        $target = $this->themesRoot . '/' . $newSlug;
        if (file_exists($target) || is_link($target)) {
            throw new RuntimeException('A theme with this slug already exists.');
        }

        try {
            $this->copyDirectory($source, $target);
            $manifestPath = $target . '/theme.json';
            $manifest = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                throw new RuntimeException('Copied theme.json is invalid.');
            }
            $manifest['slug'] = $newSlug;
            $manifest['name'] = trim($newName) !== '' ? trim($newName) : (string)($manifest['name'] ?? $newSlug) . ' Copy';
            $this->validateManifest($manifest, $newSlug);
            $this->atomicWrite(
                $manifestPath,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
            );
            if (!Theme::validateThemeStructure($newSlug)) {
                throw new RuntimeException('The copied theme failed validation.');
            }
        } catch (\Throwable $exception) {
            if (is_dir($target)) {
                $this->deleteDirectory($target);
            }
            throw $exception;
        }

        $this->log('copy_theme', $sourceSlug, '', ['target' => $newSlug]);
        return Theme::getTheme($newSlug) ?? ['slug' => $newSlug, 'name' => $newName];
    }

    protected function validateTextContent(string $slug, string $relativePath, string $content): void
    {
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if ($extension === 'php') {
            $this->lintPhp($content);
        }
        if ($relativePath === 'theme.json') {
            $manifest = json_decode($content, true);
            if (!is_array($manifest) || json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Invalid JSON: ' . json_last_error_msg());
            }
            $this->validateManifest($manifest, $slug);
        } elseif ($extension === 'json') {
            json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Invalid JSON: ' . json_last_error_msg());
            }
        } elseif ($extension === 'svg') {
            $this->validateSvg($content);
        }
    }

    protected function lintPhp(string $content): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'fireball-theme-lint-');
        if ($temporary === false) {
            throw new RuntimeException('Could not create a temporary PHP lint file.');
        }

        try {
            file_put_contents($temporary, $content);
            $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($temporary) . ' 2>&1';
            exec($command, $output, $status);
            if ($status !== 0) {
                $message = trim(implode("\n", $output));
                $message = str_replace($temporary, 'theme file', $message);
                throw new RuntimeException($message !== '' ? $message : 'PHP syntax error.');
            }
        } finally {
            @unlink($temporary);
        }
    }

    protected function validateManifest(array $manifest, string $slug): void
    {
        foreach (self::REQUIRED_MANIFEST_FIELDS as $field) {
            if (!array_key_exists($field, $manifest) || trim((string)$manifest[$field]) === '') {
                throw new RuntimeException('theme.json is missing required field: ' . $field);
            }
        }
        if ((string)$manifest['slug'] !== $slug) {
            throw new RuntimeException('theme.json slug must match the theme directory.');
        }
    }

    protected function validateSvg(string $content): void
    {
        if (!preg_match('/<svg\b/i', $content)
            || preg_match('/<(?:script|iframe|object|embed)\b/i', $content)
            || preg_match('/\bon[a-z]+\s*=/i', $content)
            || preg_match('/(?:javascript|data)\s*:/i', $content)
        ) {
            throw new RuntimeException('SVG contains unsafe or invalid markup.');
        }
    }

    protected function backupFile(string $slug, string $relativePath, string $source): void
    {
        if (!is_file($source) || is_link($source)) {
            return;
        }

        $directory = $this->backupDirectory($slug, $relativePath);
        $this->ensureDirectory($directory);
        $id = gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $backup = $directory . '/' . $id . '.bak';
        if (!copy($source, $backup)) {
            throw new RuntimeException('Could not create a theme file backup.');
        }

        $user = get_user() ?: [];
        $metadata = [
            'theme' => $slug,
            'path' => $relativePath,
            'created_at' => gmdate('c'),
            'user_id' => (int)($user['id'] ?? 0),
            'user' => (string)($user['name'] ?? $user['login'] ?? 'system'),
            'size' => (int)filesize($backup),
        ];
        file_put_contents(
            $directory . '/' . $id . '.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
        $this->pruneBackups($directory);
    }

    protected function backupDirectoryFiles(string $slug, string $relativePath, string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && !$file->isLink()) {
                $child = $relativePath . '/' . substr($file->getPathname(), strlen($directory) + 1);
                $this->backupFile($slug, str_replace('\\', '/', $child), $file->getPathname());
            }
        }
    }

    protected function pruneBackups(string $directory): void
    {
        $metadataFiles = glob($directory . '/*.json') ?: [];
        usort($metadataFiles, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
        foreach (array_slice($metadataFiles, 20) as $metadataFile) {
            @unlink(substr($metadataFile, 0, -5) . '.bak');
            @unlink($metadataFile);
        }
    }

    protected function directoryNode(string $root, string $relativePath): array
    {
        $absolute = $this->resolveExistingPath($root, $relativePath, true);
        $children = [];
        foreach (scandir($absolute) ?: [] as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
                continue;
            }
            $childRelative = $relativePath . '/' . $name;
            $childAbsolute = $absolute . '/' . $name;
            if (is_link($childAbsolute) || !$this->isAllowedPath($childRelative)) {
                continue;
            }
            if (is_dir($childAbsolute)) {
                $children[] = $this->directoryNode($root, $childRelative);
            } elseif (is_file($childAbsolute) && $this->isAllowedFile($childRelative)) {
                $children[] = $this->fileNode($root, $childRelative);
            }
        }

        usort($children, static function (array $a, array $b): int {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strnatcasecmp($a['name'], $b['name']);
        });

        return [
            'type' => 'directory',
            'name' => basename($relativePath),
            'path' => $relativePath,
            'children' => $children,
        ];
    }

    protected function fileNode(string $root, string $relativePath): array
    {
        $path = $this->resolveExistingPath($root, $relativePath);
        return [
            'type' => 'file',
            'name' => basename($relativePath),
            'path' => $relativePath,
            'size' => (int)filesize($path),
            'is_image' => $this->isImagePath($relativePath),
        ];
    }

    protected function themeRoot(string $slug): string
    {
        $theme = Theme::getTheme($slug);
        if ($theme === null) {
            throw new RuntimeException('Theme was not found.');
        }
        $root = realpath((string)$theme['path']);
        if ($root === false || !$this->isInside($root, $this->themesRoot) || is_link((string)$theme['path'])) {
            throw new RuntimeException('Theme path is not safe.');
        }
        return $root;
    }

    protected function resolveExistingPath(string $root, string $relativePath, bool $directory = false): string
    {
        $this->assertAllowedPath($relativePath, $directory);
        $candidate = $root . '/' . $relativePath;
        if ($this->pathContainsSymlink($root, $relativePath)) {
            throw new RuntimeException('Symbolic links are not allowed in the theme editor.');
        }
        $resolved = realpath($candidate);
        if ($resolved === false || !$this->isInside($resolved, $root)) {
            throw new RuntimeException('Theme path does not exist or is outside the theme.');
        }
        if ($directory && !is_dir($resolved)) {
            throw new RuntimeException('Theme directory was not found.');
        }
        return $resolved;
    }

    protected function normalizePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) || str_contains($path, "\0")) {
            throw new RuntimeException('Invalid theme path.');
        }
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '..' || $segment === '.' || str_starts_with($segment, '.')) {
                throw new RuntimeException('Hidden files and path traversal are not allowed.');
            }
        }
        return implode('/', $segments);
    }

    protected function assertAllowedPath(string $path, bool $directory = false): void
    {
        if (!$this->isAllowedPath($path, $directory)) {
            throw new RuntimeException('This path is outside the editable theme directories.');
        }
    }

    protected function isAllowedPath(string $path, bool $directory = false): bool
    {
        if ($path === 'theme.json') {
            return !$directory;
        }
        if ($path === 'templates' || $path === 'partials' || $path === 'assets'
            || $path === 'assets/css' || $path === 'assets/js' || $path === 'assets/images'
        ) {
            return $directory;
        }
        foreach (self::EDITOR_ROOTS as $root) {
            if (str_starts_with($path, $root . '/')) {
                return true;
            }
        }
        return false;
    }

    protected function assertAllowedDirectory(string $path): void
    {
        $this->assertAllowedPath($path, true);
    }

    protected function assertAllowedFile(string $path): void
    {
        if (!$this->isAllowedFile($path)) {
            throw new RuntimeException('This file type or location is not allowed.');
        }
    }

    protected function isAllowedFile(string $path): bool
    {
        if ($path === 'theme.json') {
            return true;
        }
        if (!$this->isAllowedPath($path)) {
            return false;
        }
        $basename = basename($path);
        if ($basename === '' || str_starts_with($basename, '.') || $this->isForbiddenFilename($basename)) {
            return false;
        }
        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if (!in_array($extension, array_unique(array_merge(self::TEXT_EXTENSIONS, self::IMAGE_EXTENSIONS)), true)) {
            return false;
        }
        if ($extension === 'php' && !str_starts_with($path, 'templates/') && !str_starts_with($path, 'partials/')) {
            return false;
        }
        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)
            && !str_starts_with($path, 'assets/images/')
        ) {
            return false;
        }
        return true;
    }

    protected function isForbiddenFilename(string $name): bool
    {
        $lower = strtolower($name);
        if (in_array($lower, [
            '.env', '.htaccess', '.user.ini', 'composer.json', 'composer.lock',
            'package.json', 'package-lock.json', 'php.ini',
        ], true)) {
            return true;
        }
        return preg_match('/\.(?:sql|phar|exe|bat|cmd|sh)$/i', $name) === 1;
    }

    protected function validateName(string $name, bool $file = true): string
    {
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..' || str_starts_with($name, '.')
            || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name)
        ) {
            throw new RuntimeException('Use a safe file or folder name without paths.');
        }
        if ($this->isForbiddenFilename($name)) {
            throw new RuntimeException('This file name is forbidden.');
        }
        if ($file && pathinfo($name, PATHINFO_EXTENSION) === '') {
            throw new RuntimeException('A file extension is required.');
        }
        return $name;
    }

    protected function isImagePath(string $relativePath): bool
    {
        return str_starts_with($relativePath, 'assets/images/')
            && in_array(strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
    }

    protected function pathContainsSymlink(string $root, string $relativePath): bool
    {
        $current = $root;
        foreach (explode('/', $relativePath) as $segment) {
            $current .= '/' . $segment;
            if (is_link($current)) {
                return true;
            }
        }
        return false;
    }

    protected function assertDirectoryHasNoSymlinks(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Symbolic links are not allowed in the theme editor.');
            }
        }
    }

    protected function backupDirectory(string $slug, string $relativePath): string
    {
        return $this->backupRoot . '/' . $slug . '/' . sha1($relativePath);
    }

    protected function atomicWrite(string $path, string $content): void
    {
        $temporary = tempnam(dirname($path), '.fireball-theme-');
        if ($temporary === false) {
            throw new RuntimeException('Could not prepare the theme file for writing.');
        }
        try {
            if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $path)) {
                throw new RuntimeException('Could not save the theme file.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    protected function copyDirectory(string $source, string $target): void
    {
        if (!mkdir($target, 0755, true)) {
            throw new RuntimeException('Could not create the copied theme directory.');
        }
        foreach (scandir($source) ?: [] as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
                continue;
            }
            $from = $source . '/' . $name;
            $to = $target . '/' . $name;
            if (is_link($from)) {
                throw new RuntimeException('Themes containing symbolic links cannot be copied.');
            }
            if (is_dir($from)) {
                $this->copyDirectory($from, $to);
            } elseif (is_file($from) && !copy($from, $to)) {
                throw new RuntimeException('Could not copy a theme file.');
            }
        }
    }

    protected function deleteDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Symbolic links cannot be removed by the theme editor.');
            }
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        if (!@rmdir($directory)) {
            throw new RuntimeException('Could not delete the theme folder.');
        }
    }

    protected function editorLanguage(string $extension): string
    {
        return match ($extension) {
            'php' => 'php',
            'html', 'htm' => 'html',
            'css' => 'css',
            'js' => 'javascript',
            'json' => 'json',
            'md' => 'markdown',
            default => 'text',
        };
    }

    protected function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    protected function ensureDirectory(string $directory): string
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create required theme editor storage.');
        }
        $resolved = realpath($directory);
        if ($resolved === false) {
            throw new RuntimeException('Theme editor storage is unavailable.');
        }
        return $resolved;
    }

    protected function isInside(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, rtrim($root, '/') . '/');
    }

    protected function log(string $action, string $slug, string $path = '', array $context = []): void
    {
        $user = get_user() ?: [];
        $payload = [
            'created_at' => gmdate('c'),
            'action' => $action,
            'theme' => $slug,
            'path' => $path,
            'user_id' => (int)($user['id'] ?? 0),
            'user' => (string)($user['name'] ?? $user['login'] ?? 'system'),
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'context' => $context,
        ];
        @file_put_contents(
            $this->logFile,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
