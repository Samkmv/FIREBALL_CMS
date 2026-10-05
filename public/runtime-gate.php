<?php

// Keep requests out of partially replaced application code, before autoload/config/SQL.
$fireballStorage = dirname(__DIR__) . '/storage';
$fireballUnavailable = static function () use ($fireballStorage): never {
    http_response_code(503);
    header('Retry-After: 12');
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    // This screen must work while application files/dependencies are being replaced.
    // Use only built-in PHP, inline assets and public presentation metadata.
    $basePath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
    if (basename($basePath) === 'public') $basePath = dirname($basePath);
    if ($basePath === '.' || $basePath === '/') $basePath = '';
    $path = explode('?', (string)($_SERVER['REQUEST_URI'] ?? '/'), 2)[0];
    if ($basePath !== '' && str_starts_with($path, $basePath . '/')) $path = substr($path, strlen($basePath));
    $language = explode('/', trim($path, '/'))[0];
    $locale = in_array($language, ['en', 'de', 'zh-cn'], true) ? $language : 'ru';
    $metadata = json_decode((string)@file_get_contents($fireballStorage . '/update.maintenance', false, null, 0, 8192), true);
    $siteTitle = is_string($metadata['site_title'] ?? null) ? trim(substr($metadata['site_title'], 0, 512)) : '';
    if ($siteTitle === '') $siteTitle = 'FIREBALL CMS';
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $maintenance = [
        'locale' => $locale,
        'site_title' => $siteTitle,
        'home_url' => $basePath . ($locale === 'ru' ? '/' : '/' . $locale . '/'),
        'retry_after' => 12,
    ];
    $template = dirname(__DIR__) . '/app/Views/system/update.php';
    if (is_readable($template)) {
        require $template;
    } else {
        // Fail closed even if the first deployment has not published the shared view yet.
        $title = match ($locale) {
            'en' => 'The site is updating',
            'de' => 'Die Website wird aktualisiert',
            'zh-cn' => '网站正在更新',
            default => 'Сайт обновляется',
        };
        echo '<!doctype html><html lang="' . $escape($locale) . '" data-update-maintenance-page="1"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow"><meta http-equiv="refresh" content="12">'
            . '<title>' . $escape($title) . '</title></head><body><h1>' . $escape($title) . '</h1></body></html>';
    }
    exit;
};
if (is_file($fireballStorage . '/update.maintenance')) $fireballUnavailable();
$GLOBALS['fireball_runtime_lock'] = @fopen($fireballStorage . '/update.lock', 'c+');
if (!is_resource($GLOBALS['fireball_runtime_lock']) || !flock($GLOBALS['fireball_runtime_lock'], LOCK_SH | LOCK_NB)) $fireballUnavailable();
if (is_file($fireballStorage . '/update.maintenance')) $fireballUnavailable();
register_shutdown_function(static function (): void {
    if (is_resource($GLOBALS['fireball_runtime_lock'] ?? null)) {
        flock($GLOBALS['fireball_runtime_lock'], LOCK_UN);
        fclose($GLOBALS['fireball_runtime_lock']);
    }
});
