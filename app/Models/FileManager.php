<?php

namespace App\Models;

use FBL\Pagination;

/**
 * Управляет файлами в каталоге загрузок для административного файлового менеджера.
 */
class FileManager
{
    protected const MAX_UPLOAD_SIZE_BYTES = 209715200;

    protected string $rootPath;
    protected string $rootUrl;
    protected array $protectedDirectories = [
        'avatars',
    ];
    protected array $undeletableDirectories = [
        'avatars',
        'categories',
        'chat',
        'posts',
        'seo',
    ];
    protected array $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'webp', 'gif',
        'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx',
        'zip', 'rar', 'mp3', 'wav', 'mp4', 'webm',
    ];

    /**
     * Настраивает корневую директорию менеджера и создаёт её при необходимости.
     */
    public function __construct()
    {
        $this->rootPath = rtrim(UPLOADS, '/');
        $this->rootUrl = rtrim(base_url('/uploads'), '/');
        $this->ensureDirectoryExists('');
    }

    /**
     * Возвращает содержимое директории вместе с хлебными крошками и метаданными файлов.
     */
    public function getDirectoryData(string $relativeDir = '', array $options = []): array
    {
        $relativeDir = $this->normalizeRelativePath($relativeDir);
        $perPage = max(1, (int)($options['per_page'] ?? 15));
        $search = trim((string)($options['search'] ?? ''));
        $sort = (string)($options['sort'] ?? 'modified');
        $direction = strtolower((string)($options['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $currentDirectoryData = $this->collectDirectoryItems($relativeDir);
        $directories = $currentDirectoryData['directories'];
        $files = $currentDirectoryData['files'];

        usort($directories, static fn(array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));
        usort($files, static fn(array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));
        $directoryItems = $search !== ''
            ? $this->collectRecursiveItems('')
            : array_merge($directories, $files);

        if ($search !== '') {
            $directoryItems = array_values(array_filter(
                $directoryItems,
                fn(array $directoryItem): bool => $this->matchesSearch($directoryItem, $search)
            ));
        }

        $directoryItems = $this->sortItems($directoryItems, $sort, $direction);
        $total = count($directoryItems);
        $pagination = new Pagination($total, $perPage);
        $offset = $pagination->getOffset();
        $directoryItems = array_slice($directoryItems, $offset, $perPage);

        return [
            'current_dir' => $relativeDir,
            'parent_dir' => $this->getParentDirectory($relativeDir),
            'breadcrumbs' => $this->buildBreadcrumbs($relativeDir),
            'directories' => $directories,
            'files' => $files,
            'items' => $directoryItems,
            'total' => $total,
            'pagination' => $pagination,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
        ];
    }

    /**
     * Загружает файл в выбранную директорию и возвращает его публичный путь.
     */
    public function upload(string $relativeDir, \FBL\File $file): string
    {
        return $this->storeUploadedFile(
            $relativeDir,
            $file->getName(),
            $file->getTmpName(),
            $file->getError(),
            $file->getSize()
        );
    }

    /**
     * Загружает несколько файлов в выбранную директорию.
     */
    public function uploadMany(string $relativeDir, array $files): int
    {
        $normalizedFiles = $this->normalizeUploadedFiles($files);
        if ($normalizedFiles === []) {
            throw new \RuntimeException(return_translation('admin_files_upload_error'));
        }

        $uploadedCount = 0;

        foreach ($normalizedFiles as $file) {
            $this->storeUploadedFile(
                $relativeDir,
                (string)($file['name'] ?? ''),
                (string)($file['tmp_name'] ?? ''),
                (int)($file['error'] ?? UPLOAD_ERR_NO_FILE),
                (int)($file['size'] ?? 0)
            );
            $uploadedCount++;
        }

        return $uploadedCount;
    }

    /**
     * Создаёт папку внутри выбранной директории и возвращает её относительный путь.
     */
    public function createDirectory(string $relativeDir, string $directoryName): string
    {
        $relativeDir = $this->normalizeRelativePath($relativeDir);
        $absoluteDir = $this->ensureDirectoryExists($relativeDir);
        $directoryName = trim($directoryName);

        if ($directoryName === '') {
            throw new \RuntimeException(return_translation('admin_files_folder_name_required'));
        }

        $directoryName = make_slug($directoryName, 'folder');

        $targetRelativePath = ltrim($relativeDir . '/' . $directoryName, '/');
        if ($this->isProtectedPath($targetRelativePath)) {
            throw new \RuntimeException(return_translation('admin_files_protected_path'));
        }

        $targetAbsolutePath = $absoluteDir . '/' . $directoryName;
        if (file_exists($targetAbsolutePath)) {
            throw new \RuntimeException(return_translation('admin_files_folder_exists'));
        }

        if (!mkdir($targetAbsolutePath, 0755, true) && !is_dir($targetAbsolutePath)) {
            throw new \RuntimeException(return_translation('admin_files_folder_create_error'));
        }

        return $targetRelativePath;
    }

    /**
     * Удаляет файл по относительному пути внутри каталога загрузок.
     */
    public function delete(string $relativePath): void
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === '') {
            throw new \RuntimeException(return_translation('admin_files_delete_error'));
        }

        $absolutePath = $this->resolveExistingPath($relativePath);
        if (!is_file($absolutePath) || !@unlink($absolutePath)) {
            throw new \RuntimeException(return_translation('admin_files_delete_error'));
        }
    }

    /**
     * Удаляет несколько файлов и папок по относительным путям.
     */
    public function deleteMany(array $items): int
    {
        $deletedCount = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = trim((string)($item['type'] ?? ''));
            $path = trim((string)($item['path'] ?? ''));

            if ($path === '') {
                continue;
            }

            if ($type === 'directory') {
                $this->deleteDirectory($path);
                $deletedCount++;
                continue;
            }

            $this->delete($path);
            $deletedCount++;
        }

        return $deletedCount;
    }

    /**
     * Удаляет папку по относительному пути вместе с её содержимым.
     */
    public function deleteDirectory(string $relativePath): void
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === '') {
            throw new \RuntimeException(return_translation('admin_files_folder_delete_error'));
        }

        if ($this->isDeletionProtectedPath($relativePath)) {
            throw new \RuntimeException(return_translation('admin_files_folder_delete_protected'));
        }

        if ($this->isProtectedPath($relativePath)) {
            throw new \RuntimeException(return_translation('admin_files_protected_path'));
        }

        $absolutePath = $this->resolveExistingPath($relativePath);
        if (!is_dir($absolutePath)) {
            throw new \RuntimeException(return_translation('admin_files_folder_delete_error'));
        }

        $this->deleteDirectoryRecursively($absolutePath);
    }

    /**
     * Переименовывает файл или папку, сохраняя расширение файла при необходимости.
     */
    public function rename(string $relativePath, string $newName): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === '' || $this->isProtectedPath($relativePath)) {
            throw new \RuntimeException(return_translation('admin_files_rename_error'));
        }

        $absolutePath = $this->resolveExistingPath($relativePath);
        if (!is_file($absolutePath) && !is_dir($absolutePath)) {
            throw new \RuntimeException(return_translation('admin_files_rename_error'));
        }

        $newName = trim($newName);
        if ($newName === '') {
            throw new \RuntimeException(return_translation('admin_files_rename_name_required'));
        }

        $relativeDir = dirname($relativePath);
        $relativeDir = $relativeDir === '.' ? '' : $relativeDir;
        $absoluteDir = $relativeDir === '' ? $this->rootPath : $this->rootPath . '/' . $relativeDir;
        $isDirectory = is_dir($absolutePath);
        $extension = $isDirectory ? '' : strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $baseName = make_slug(pathinfo($newName, PATHINFO_FILENAME), $isDirectory ? 'folder' : 'file');
        $targetName = $extension !== '' ? ($baseName . '.' . $extension) : $baseName;
        $targetRelativePath = ltrim($relativeDir . '/' . $targetName, '/');
        $targetAbsolutePath = $absoluteDir . '/' . $targetName;

        if ($targetRelativePath === $relativePath) {
            return '/uploads/' . $targetRelativePath;
        }

        if (file_exists($targetAbsolutePath)) {
            throw new \RuntimeException(return_translation('admin_files_rename_exists'));
        }

        if (!@rename($absolutePath, $targetAbsolutePath)) {
            throw new \RuntimeException(return_translation('admin_files_rename_error'));
        }

        return '/uploads/' . $targetRelativePath;
    }

    /**
     * Создаёт директорию при необходимости и возвращает её абсолютный путь.
     */
    protected function ensureDirectoryExists(string $relativeDir): string
    {
        $relativeDir = $this->normalizeRelativePath($relativeDir);
        if ($this->isProtectedPath($relativeDir)) {
            throw new \RuntimeException(return_translation('admin_files_protected_path'));
        }

        $absoluteDir = $relativeDir === '' ? $this->rootPath : $this->rootPath . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException(return_translation('admin_files_invalid_path'));
        }

        return $absoluteDir;
    }

    /**
     * Преобразует относительный путь в существующий безопасный абсолютный путь.
     */
    protected function resolveExistingPath(string $relativePath): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        $absolutePath = $this->rootPath . '/' . $relativePath;
        $realPath = realpath($absolutePath);
        $realRoot = realpath($this->rootPath) ?: $this->rootPath;

        if ($realPath === false || !str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR) && $realPath !== $realRoot) {
            throw new \RuntimeException(return_translation('admin_files_invalid_path'));
        }

        return $realPath;
    }

    /**
     * Нормализует относительный путь и убирает опасные сегменты.
     */
    protected function normalizeRelativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            return '';
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }

            $segment = preg_replace('/[^A-Za-z0-9._-]/', '-', $segment) ?? '';
            $segment = trim($segment, '.-');
            if ($segment === '') {
                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * Строит хлебные крошки для текущей директории.
     */
    protected function buildBreadcrumbs(string $relativeDir): array
    {
        $breadcrumbs = [
            [
                'label' => return_translation('admin_files_root'),
                'dir' => '',
            ],
        ];

        if ($relativeDir === '') {
            return $breadcrumbs;
        }

        $path = '';
        foreach (explode('/', $relativeDir) as $segment) {
            $path = ltrim($path . '/' . $segment, '/');
            $breadcrumbs[] = [
                'label' => $segment,
                'dir' => $path,
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Возвращает относительный путь родительской директории.
     */
    protected function getParentDirectory(string $relativeDir): ?string
    {
        if ($relativeDir === '') {
            return null;
        }

        $parent = dirname($relativeDir);
        return $parent === '.' ? '' : $parent;
    }

    /**
     * Генерирует уникальное имя файла внутри директории.
     */
    protected function generateFileName(string $originalName, string $extension, string $absoluteDir): string
    {
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseName = make_slug($baseName, 'file');
        $candidate = $baseName . '.' . $extension;
        $counter = 1;

        while (file_exists($absoluteDir . '/' . $candidate)) {
            $candidate = $baseName . '-' . $counter . '.' . $extension;
            $counter++;
        }

        return $candidate;
    }

    /**
     * Сохраняет один загруженный файл после валидации.
     */
    protected function storeUploadedFile(string $relativeDir, string $originalName, string $tmpName, int $error, int $size): string
    {
        if ($error !== UPLOAD_ERR_OK || $tmpName === '') {
            throw new \RuntimeException(return_translation('admin_files_upload_error'));
        }

        if ($size > self::MAX_UPLOAD_SIZE_BYTES) {
            throw new \RuntimeException(return_translation('admin_files_size_error'));
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, $this->allowedExtensions, true)) {
            throw new \RuntimeException(return_translation('admin_files_type_error'));
        }

        $relativeDir = $this->normalizeRelativePath($relativeDir);
        $absoluteDir = $this->ensureDirectoryExists($relativeDir);
        $fileName = $this->generateFileName($originalName, $extension, $absoluteDir);
        $absolutePath = $absoluteDir . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            throw new \RuntimeException(return_translation('admin_files_upload_error'));
        }

        return '/uploads/' . ltrim($relativeDir . '/' . $fileName, '/');
    }

    /**
     * Нормализует массив $_FILES к списку отдельных файлов.
     */
    protected function normalizeUploadedFiles(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [[
                'name' => (string)($files['name'] ?? ''),
                'tmp_name' => (string)($files['tmp_name'] ?? ''),
                'error' => (int)($files['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int)($files['size'] ?? 0),
            ]];
        }

        $normalized = [];

        foreach ($files['name'] as $index => $name) {
            $normalized[] = [
                'name' => (string)$name,
                'tmp_name' => (string)($files['tmp_name'][$index] ?? ''),
                'error' => (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int)($files['size'][$index] ?? 0),
            ];
        }

        return array_values(array_filter($normalized, static function (array $file): bool {
            return trim((string)($file['name'] ?? '')) !== '' || (int)($file['size'] ?? 0) > 0;
        }));
    }

    /**
     * Рекурсивно удаляет директорию и её содержимое.
     */
    protected function deleteDirectoryRecursively(string $absoluteDir): void
    {
        $items = scandir($absoluteDir);
        if ($items === false) {
            throw new \RuntimeException(return_translation('admin_files_folder_delete_error'));
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $absoluteDir . '/' . $item;

            if (is_dir($path)) {
                $this->deleteDirectoryRecursively($path);
                continue;
            }

            if (!@unlink($path)) {
                throw new \RuntimeException(return_translation('admin_files_folder_delete_error'));
            }
        }

        if (!@rmdir($absoluteDir)) {
            throw new \RuntimeException(return_translation('admin_files_folder_delete_error'));
        }
    }

    /**
     * Проверяет, относится ли путь к защищённым директориям.
     */
    protected function isProtectedPath(string $relativePath): bool
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === '') {
            return false;
        }

        $segments = explode('/', $relativePath);
        $rootSegment = $segments[0] ?? '';

        return in_array($rootSegment, $this->protectedDirectories, true);
    }

    /**
     * Проверяет, относится ли путь к системным директориям, запрещённым к удалению.
     */
    protected function isDeletionProtectedPath(string $relativePath): bool
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === '') {
            return false;
        }

        $segments = explode('/', $relativePath);
        $rootSegment = $segments[0] ?? '';

        return in_array($rootSegment, $this->undeletableDirectories, true);
    }

    /**
     * Форматирует размер файла в человекочитаемый вид.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return (string)ceil($bytes / 1024) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Возвращает элементы текущей директории без рекурсивного обхода.
     */
    protected function collectDirectoryItems(string $relativeDir): array
    {
        $absoluteDir = $this->ensureDirectoryExists($relativeDir);
        $items = scandir($absoluteDir) ?: [];
        $directories = [];
        $files = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $absolutePath = $absoluteDir . '/' . $item;
            $relativePath = ltrim($relativeDir . '/' . $item, '/');

            if (is_dir($absolutePath)) {
                if ($this->isProtectedPath($relativePath)) {
                    continue;
                }

                $directories[] = $this->buildDirectoryItem($item, $relativePath, $absolutePath);
                continue;
            }

            if (is_file($absolutePath)) {
                $files[] = $this->buildFileItem($item, $relativePath, $absolutePath);
            }
        }

        return [
            'directories' => $directories,
            'files' => $files,
        ];
    }

    /**
     * Рекурсивно собирает все элементы из доступных директорий.
     */
    protected function collectRecursiveItems(string $relativeDir = ''): array
    {
        $absoluteDir = $this->ensureDirectoryExists($relativeDir);
        $items = scandir($absoluteDir) ?: [];
        $collected = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $absolutePath = $absoluteDir . '/' . $item;
            $relativePath = ltrim($relativeDir . '/' . $item, '/');

            if (is_dir($absolutePath)) {
                if ($this->isProtectedPath($relativePath)) {
                    continue;
                }

                $collected[] = $this->buildDirectoryItem($item, $relativePath, $absolutePath);
                $collected = array_merge($collected, $this->collectRecursiveItems($relativePath));
                continue;
            }

            if (is_file($absolutePath)) {
                $collected[] = $this->buildFileItem($item, $relativePath, $absolutePath);
            }
        }

        return $collected;
    }

    /**
     * Формирует данные директории для таблицы файлового менеджера.
     */
    protected function buildDirectoryItem(string $name, string $relativePath, string $absolutePath): array
    {
        $modifiedTimestamp = (int)(filemtime($absolutePath) ?: time());

        return [
            'type' => 'directory',
            'name' => $name,
            'relative_path' => $relativePath,
            'public_path' => '/uploads/' . $relativePath,
            'extension' => '',
            'size' => '',
            'size_bytes' => null,
            'modified_at' => date('Y-m-d H:i', $modifiedTimestamp),
            'modified_timestamp' => $modifiedTimestamp,
            'type_sort' => 0,
            'can_delete' => !$this->isDeletionProtectedPath($relativePath),
        ];
    }

    /**
     * Формирует данные файла для таблицы файлового менеджера.
     */
    protected function buildFileItem(string $name, string $relativePath, string $absolutePath): array
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $sizeBytes = (int)filesize($absolutePath);
        $modifiedTimestamp = (int)(filemtime($absolutePath) ?: time());

        return [
            'type' => 'file',
            'name' => $name,
            'relative_path' => $relativePath,
            'public_path' => '/uploads/' . $relativePath,
            'url' => $this->rootUrl . '/' . $relativePath,
            'extension' => $extension,
            'size' => $this->formatBytes($sizeBytes),
            'size_bytes' => $sizeBytes,
            'modified_at' => date('Y-m-d H:i', $modifiedTimestamp),
            'modified_timestamp' => $modifiedTimestamp,
            'type_sort' => 1,
            'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
        ];
    }

    /**
     * Проверяет, подходит ли элемент списка под строку поиска.
     */
    protected function matchesSearch(array $item, string $search): bool
    {
        $needle = mb_strtolower(trim($search));
        if ($needle === '') {
            return true;
        }

        $haystacks = [
            (string)($item['name'] ?? ''),
            (string)($item['relative_path'] ?? ''),
            (string)($item['public_path'] ?? ''),
            (string)($item['extension'] ?? ''),
            (string)($item['type'] ?? ''),
        ];

        foreach ($haystacks as $haystack) {
            if (mb_stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Сортирует элементы директории по выбранной колонке и направлению.
     */
    protected function sortItems(array $items, string $sort, string $direction): array
    {
        $directionMultiplier = $direction === 'asc' ? 1 : -1;

        usort($items, function (array $left, array $right) use ($sort, $directionMultiplier): int {
            $result = match ($sort) {
                'name' => strnatcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? '')),
                'type' => ((int)($left['type_sort'] ?? 0) <=> (int)($right['type_sort'] ?? 0)),
                'size' => (($left['size_bytes'] ?? -1) <=> ($right['size_bytes'] ?? -1)),
                'modified' => ((int)($left['modified_timestamp'] ?? 0) <=> (int)($right['modified_timestamp'] ?? 0)),
                default => ((int)($left['modified_timestamp'] ?? 0) <=> (int)($right['modified_timestamp'] ?? 0)),
            };

            if ($result === 0) {
                $result = strnatcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
            }

            return $result * $directionMultiplier;
        });

        return $items;
    }

}
