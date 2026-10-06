<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$locale = in_array($argv[1] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $argv[1] : 'ru';
$translations = require dirname(__DIR__, 2) . '/app/Languages/' . $locale . '.php';
function check_auth(): bool { return ($GLOBALS['argv'][2] ?? '') !== 'guest'; }
function htmlSC(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function return_translation(string $k): string { return $GLOBALS['translations'][$k] ?? $k; }
function print_translation(string $k): string { return return_translation($k); }
function base_href(string $p): string { return ($GLOBALS['locale'] === 'ru' ? '' : '/' . $GLOBALS['locale']) . $p; }
function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="fixture">'; }
$favorite_ready = ($argv[3] ?? '') !== 'unavailable'; $favorite_saved = ($argv[3] ?? '') === 'saved'; $post = ['id' => 42];
require dirname(__DIR__, 2) . '/themes/default/partials/favorite_button.php';
