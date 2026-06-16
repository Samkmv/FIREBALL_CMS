<?php

namespace App\Services\Maintenance;

use App\Models\Page;
use App\Models\Post;
use App\Services\PostImageService;

final class CacheCleanupService
{
    public function clearCache(): array
    {
        // Keep cache invalidation centralized in FBL\Cache while preserving generated image cleanup.
        $deleted = cache()->clear();
        $deleted += PostImageService::clearGeneratedCache();
        Post::clearPublicCache();
        Page::clearPublicCache();

        return ['message' => 'Cache cleared.', 'deleted' => $deleted];
    }

    public function clearTempFiles(): array
    {
        $deleted = 0;
        foreach ([ROOT . '/tmp/temp', ROOT . '/tmp/uploads', ROOT . '/storage/temp'] as $directory) {
            $deleted += $this->clearDirectoryContents($directory);
        }

        return ['message' => 'Temporary files cleared.', 'deleted' => $deleted];
    }

    public function clearLogs(): array
    {
        $deleted = 0;
        foreach ([ROOT . '/tmp/logs', ROOT . '/storage/logs'] as $directory) {
            $deleted += $this->clearDirectoryContents($directory);
        }

        if (is_file(ERROR_LOGS) && file_put_contents(ERROR_LOGS, '', LOCK_EX) !== false) {
            $deleted++;
        }

        return ['message' => 'System logs cleared.', 'deleted' => $deleted];
    }

    private function clearDirectoryContents(string $directory): int
    {
        if ($directory === '' || !is_dir($directory)) {
            return 0;
        }

        $deleted = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                if (@rmdir($path)) {
                    $deleted++;
                }
                continue;
            }

            if (in_array($item->getFilename(), ['.gitkeep', '.htaccess'], true)) {
                continue;
            }

            if (@unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
