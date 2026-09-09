<?php

// Standalone, opt-in loopback UI fixture. Never boots CMS or connects to its database.
if (PHP_SAPI !== 'cli-server' || getenv('FIREBALL_NOTIFICATION_PREVIEW') !== '1') {
    http_response_code(404); exit;
}
$previewRoot = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/assets/')) {
    $file = realpath($previewRoot . '/public' . $path);
    if (!$file || !str_starts_with($file, $previewRoot . '/public/assets/')) { http_response_code(404); exit; }
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    header('Content-Type: ' . (['css' => 'text/css', 'js' => 'text/javascript', 'woff2' => 'font/woff2', 'woff' => 'font/woff'][$extension] ?? 'application/octet-stream'));
    readfile($file); exit;
}
session_name('FIREBALL_NOTIFICATION_PREVIEW');
session_start();
header('Cache-Control: no-store');
if ($path === '/notifications/read') $_SESSION['read'][(int)($_POST['notification_id'] ?? 0)] = true;
if ($path === '/notifications/clear') $_SESSION['read'] = array_fill_keys(range(1, 21), true);
if ($path === '/preview/new') { $_SESSION['new'] = true; unset($_SESSION['read'][21]); }
if ($path === '/preview/fail') $_SESSION['fail'] = true;
if ($path === '/notifications/feed') {
    if (!empty($_SESSION['fail'])) { unset($_SESSION['fail']); http_response_code(503); exit; }
    $items = [];
    for ($id = !empty($_SESSION['new']) ? 21 : 20; $id > 0; $id--) {
        if (!empty($_SESSION['read'][$id])) continue;
        $items[] = ['type' => $id % 2 ? 'system' : 'update', 'notification_id' => $id, 'sort_id' => $id,
            'source_label' => $id % 2 ? 'Подписки' : 'Обновление', 'title' => $id === 21 ? 'Новое уведомление пришло' : 'Уведомление ' . $id . ': подписка и обновление сайта',
            'text' => "Полный текст уведомления можно прочитать здесь, не переходя на другую страницу.\nВторая строка сообщения. " . ($id === 20 ? str_repeat('ДлинноеСлово', 12) . '<script>alert(1)</script>' : ''),
            'url' => $id === 19 ? '' : '/preview/opened', 'created_at' => '2026-09-09 12:30:00'];
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => true, 'total_unread_count' => count($items), 'chat_unread_count' => 0, 'items' => $items]); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') { header('Content-Type: application/json'); echo '{"status":true}'; exit; }
function htmlSC($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function base_href(string $value): string { return $value; }
function return_translation(string $key): string { static $translations; $translations ??= require dirname(__DIR__, 2) . '/app/Languages/ru.php'; return $translations[$key] ?? $key; }
function print_translation(string $key): string { return return_translation($key); }
$layout = file_get_contents($previewRoot . '/app/Views/layouts/default.php');
preg_match('~<div\s+class="dropdown"\s+data-notifications-center.*?(?=<\?php endif; \?>)~s', $layout, $match);
if (empty($match[0])) throw new RuntimeException('Notification markup not found');
?>
<!doctype html><html lang="ru" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="needCSRFToken" content="fixture-only"><title>Проверка уведомлений</title>
<link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"></head><body>
<header class="d-flex justify-content-between align-items-center p-3 border-bottom"><strong>Проверка уведомлений</strong><?php eval('?>' . $match[0]); ?></header>
<main class="p-3" style="min-height:180vh"><p>Изолированный пример. Реальные пользователи и уведомления не затрагиваются.</p>
<button class="btn btn-primary" onclick="fetch('/preview/new', {method:'POST'})">Добавить тестовое уведомление</button>
<button class="btn btn-outline-secondary" onclick="fetch('/preview/fail', {method:'POST'})">Сбой следующего запроса</button></main>
<script src="/assets/default/js/jquery-3.7.1.min.js"></script><script src="/assets/default/bootstrap/js/bootstrap.bundle.min.js"></script><script src="/assets/default/js/main.js"></script>
</body></html>
