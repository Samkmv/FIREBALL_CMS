<?php

use FBL\Auth;

function log_error_details(string $title, array $context = [], ?\Throwable $exception = null): void
{
    $lines = [
        '[' . date('Y-m-d H:i:s') . '] ' . $title,
        'Request URI: ' . ($_SERVER['REQUEST_URI'] ?? '-'),
        'Request Method: ' . ($_SERVER['REQUEST_METHOD'] ?? '-'),
        'Remote IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '-'),
    ];

    if ($exception) {
        $lines[] = 'Exception: ' . get_class($exception);
        $lines[] = 'Message: ' . $exception->getMessage();
        $lines[] = 'File: ' . $exception->getFile();
        $lines[] = 'Line: ' . $exception->getLine();
        $lines[] = 'Trace: ' . $exception->getTraceAsString();
    }

    foreach ($context as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $formattedValue = (string)$value;
        } else {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $formattedValue = $encoded !== false ? $encoded : '[unserializable]';
        }

        $lines[] = $key . ': ' . $formattedValue;
    }

    $lines[] = str_repeat('=', 72);

    error_log(implode(PHP_EOL, $lines) . PHP_EOL, 3, ERROR_LOGS);
}

function log_last_php_error(string $title = 'Fatal PHP error'): void
{
    $error = error_get_last();

    if (!$error) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'] ?? 0, $fatalTypes, true)) {
        return;
    }

    log_error_details($title, [
        'PHP Error Type' => $error['type'] ?? '',
        'Message' => $error['message'] ?? '',
        'File' => $error['file'] ?? '',
        'Line' => $error['line'] ?? '',
    ]);
}

function app(): \FBL\Application
{
    return \FBL\Application::$app;
}

function request(): \FBL\Request
{
    return app()->request;
}

function response(): \FBL\Response
{
    return app()->response;
}

function session(): \FBL\Session
{
    return app()->session;
}

function cache(): \FBL\Cache
{
    return app()->cache;
}

function get_route_params(): array
{
    return app()->router->route_params;
}

function get_route_param($key, $default = ''): string
{
    return app()->router->route_params[$key] ?? $default;
}

function array_value_search($arr, $index, $value): int|string|null
{
    foreach ($arr as $key => $v) {
        if ($v[$index] == $value) {
            return $key;
        }
    }

    return null;
}

function db(): \FBL\Database
{
    return app()->db;
}

function view($view = '', $data = [], $layout = ''): string|\FBL\View
{
    if ($view) {
        return app()->view->render($view, $data, $layout);
    }

    return app()->view;
}

function abort($error = '', $code = 404)
{
    response()->setResponseCode($code);
    echo view("errors/{$code}", ['error' => $error], false);
    die;
}

function base_url($path = ''): string
{
    return app_base_url() . $path;
}

function base_href($path = ''): string
{
    $baseUrl = app_base_url();

    if (app()->get('lang')['base'] != 1) {
        return $baseUrl . '/' . app()->get('lang')['code'] . $path;
    }

    return $baseUrl . $path;
}

function app_base_url(): string
{
    $configuredUrl = rtrim((string)PATH, '/');
    $requestOrigin = detect_request_origin();

    if ($configuredUrl === '') {
        return $requestOrigin;
    }

    $configuredHost = strtolower((string)(parse_url($configuredUrl, PHP_URL_HOST) ?? ''));
    $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $requestHost = explode(':', $requestHost)[0] ?? '';

    if ($requestOrigin !== '' && is_local_host($configuredHost) && $requestHost !== '' && !is_local_host($requestHost)) {
        $configuredPath = trim((string)(parse_url($configuredUrl, PHP_URL_PATH) ?? ''), '/');

        return rtrim($requestOrigin . ($configuredPath !== '' ? '/' . $configuredPath : ''), '/');
    }

    return $configuredUrl;
}

function detect_request_origin(): string
{
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    return ($isSecure ? 'https' : 'http') . '://' . $host;
}

function is_local_host(string $host): bool
{
    $normalizedHost = trim(strtolower($host), '[]');

    return in_array($normalizedHost, ['localhost', '127.0.0.1', '::1'], true)
        || str_ends_with($normalizedHost, '.local');
}

function current_path(): string
{
    $path = '/' . ltrim((string)request()->getPath(), '/');
    return $path === '//' ? '/' : $path;
}

function current_url_with_query(array $changes = []): string
{
    $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?: '';
    parse_str($query, $params);

    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
            continue;
        }

        $params[$key] = $value;
    }

    $path = current_path();
    $url = base_href($path === '/' ? '' : $path);

    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    return $url;
}

function admin_table_sort_url(string $column, string $currentSort = '', string $currentDirection = 'asc'): string
{
    $nextDirection = ($currentSort === $column && strtolower($currentDirection) === 'asc') ? 'desc' : 'asc';

    return current_url_with_query([
        'sort' => $column,
        'direction' => $nextDirection,
        'page' => 1,
    ]);
}

function uri_without_lang(): string
{
    $request_uri = request()->uri;
    $request_uri = explode('/', $request_uri, 2);

    if (array_key_exists($request_uri[0], LANGS)) {
        unset($request_uri[0]);
    }

    $request_uri = implode('/', $request_uri);

    return $request_uri ? '/' . $request_uri : '';
}

function get_alerts(): void
{
    if (!empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $key => $value) {
            echo view()->renderPartial("incs/alert_{$key}", ["flash_{$key}" => session()->getFlash($key)]);
        }
    }
}

function get_errors($field_name): string
{
    $output = '';
    $errors = session()->get('form_errors');

    if (isset($errors[$field_name])) {
        $output .= '<div class="invalid-feedback d-block"><ul class="list-unstyled">';
        foreach ($errors[$field_name] as $error) {
            $output .= "<li>{$error}</li>";
        }
        $output .= '</ul></div>';
    }
    return $output;
}

function get_validation_class($field_name): string
{
    $errors = session()->get('form_errors');

    if (empty($errors)) {
        return '';
    }

    return isset($errors[$field_name]) ? 'is-invalid' : 'is-valid';
}

function old($field_name): string
{
    return isset(session()->get('form_data')[$field_name]) ? htmlSC(session()->get('form_data')[$field_name]) : '';
}

function htmlSC($str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function make_slug(string $value, string $fallback = 'item'): string
{
    $value = mb_strtolower(trim($value));
    $value = strtr($value, [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ]);
    $value = preg_replace('/[^a-z0-9\s-]/u', '', $value) ?? '';
    $value = preg_replace('/[\s-]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : $fallback;
}

function get_csrf_field(): string
{
    return '<input type="hidden" name="needCSRFToken" value="' . session()->get('needCSRFToken') . '">';
}

function get_csrf_meta(): string
{
    return '<meta name="needCSRFToken" content="' . session()->get('needCSRFToken') . '">';
}

function check_auth(): bool
{
    return \FBL\Auth::isAuth();
}

function check_admin(): bool
{
    return \FBL\Auth::isAdmin();
}

function get_user()
{
    return \FBL\Auth::user();
}

function logout()
{
    Auth::logout();
}

function get_user_role_label(?string $role = null): string
{
    static $roleLabels = [];

    $role = $role ?: (get_user()['role'] ?? 'user');
    if (isset($roleLabels[$role])) {
        return $roleLabels[$role];
    }

    $label = null;

    if (class_exists(\App\Models\User::class)) {
        $label = (new \App\Models\User())->getRoleLabel($role);
    }

    if ($role === 'admin' && ($label === null || $label === 'Admin')) {
        return $roleLabels[$role] = return_translation('tpl_auth_role_admin');
    }

    if ($role === 'user' && ($label === null || $label === 'User')) {
        return $roleLabels[$role] = return_translation('tpl_auth_role_user');
    }

    if ($label !== null && $label !== '') {
        return $roleLabels[$role] = $label;
    }

    return $roleLabels[$role] = ucfirst(str_replace(['-', '_'], ' ', $role));
}

function site_setting(string $key, string $default = ''): string
{
    static $settings = null;

    if ($settings === null && class_exists(\App\Models\SiteSetting::class)) {
        $settings = (new \App\Models\SiteSetting())->all();
    }

    if (!is_array($settings) || !array_key_exists($key, $settings)) {
        return $default;
    }

    $value = (string)$settings[$key];

    return $value !== '' ? $value : $default;
}

function print_translation($key): void
{
    echo \FBL\Language::get($key);
}

function return_translation($key): string
{
    return \FBL\Language::get($key);
}

function send_mail(array $to, string $subject, string $tpl, array $data = [], array $attachments = []): bool
{
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        //Server settings
        $mail->SMTPDebug = MAIL_SETTINGS['debug'];
        $mail->isSMTP();
        $mail->Host       = MAIL_SETTINGS['host'];
        $mail->SMTPAuth   = MAIL_SETTINGS['auth'];
        $mail->Username   = MAIL_SETTINGS['username'];
        $mail->Password   = MAIL_SETTINGS['password'];
        $mail->SMTPSecure = MAIL_SETTINGS['secure'];
        $mail->Port       = MAIL_SETTINGS['port'];

        $mail->setFrom(MAIL_SETTINGS['from_email'], MAIL_SETTINGS['from_name']);
        foreach ($to as $email) {
            $mail->addAddress($email);
        }

        if ($attachments) {
            foreach ($attachments as $attachment) {
                $mail->addAttachment($attachment);
            }
        }

        $mail->isHTML(MAIL_SETTINGS['is_html']);
        $mail->CharSet = MAIL_SETTINGS['charset'];
        $mail->Subject = $subject;
        $mail->Body    = view($tpl, $data, false);

        return $mail->send();
    } catch (\Throwable $e) {
        log_error_details('Mail send error', [
            'Subject' => $subject,
            'Recipients' => $to,
            'Template' => $tpl,
        ], $e);
        return false;
    }
}

function get_image($path): string
{
//    return $path ? base_href("/$path") : base_href("/assets/img/no-image.png");
    $normalizedPath = ltrim((string)$path, '/');
    return $normalizedPath !== '' ? base_url('/' . $normalizedPath) : base_url("/assets/img/no-image.png");
}

function get_user_avatar(?string $path = null, string $size = 'default'): string
{
    $fallbacks = [
        'sm' => '/assets/default/img/account/avatar.jpg',
        'lg' => '/assets/default/img/account/avatar.jpg',
        'default' => '/assets/default/img/account/avatar.jpg',
    ];

    $fallback = $fallbacks[$size] ?? $fallbacks['default'];

    return $path ? get_image($path) : base_url($fallback);
}
