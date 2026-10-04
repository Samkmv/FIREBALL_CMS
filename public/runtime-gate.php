<?php

// Keep requests out of partially replaced application code, before autoload/config/SQL.
$fireballStorage = dirname(__DIR__) . '/storage';
$fireballUnavailable = static function (): never {
    http_response_code(503);
    header('Retry-After: 30');
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=utf-8');
    $language = explode('/', trim((string)($_SERVER['REQUEST_URI'] ?? ''), '/'))[0];
    $message = match ($language) {
        'en' => 'The site is temporarily unavailable. Please try again later.',
        'de' => 'Die Website ist vorübergehend nicht verfügbar. Bitte versuchen Sie es später erneut.',
        'zh-cn' => '网站暂时不可用，请稍后重试。',
        default => 'Сайт временно недоступен. Пожалуйста, попробуйте позже.',
    };
    exit('<!doctype html><html><meta name="viewport" content="width=device-width, initial-scale=1"><meta charset="utf-8"><title>503</title><main style="max-width:40rem;margin:15vh auto;padding:1rem;font:1.2rem system-ui"><h1>503</h1><p>' . $message . '</p></main></html>');
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
