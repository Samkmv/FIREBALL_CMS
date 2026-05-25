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
    if (!is_array(app()->get('lang'))) {
        $baseLang = array_value_search(LANGS, 'base', 1);
        $lang = $baseLang;

        if (MULTILANGS) {
            $path = method_exists(request(), 'getPath') ? request()->getPath() : '';
            $firstSegment = strtok(trim((string)$path, '/'), '/');

            if (is_string($firstSegment) && array_key_exists($firstSegment, LANGS)) {
                $lang = $firstSegment;
            }
        }

        app()->set('lang', LANGS[$lang] ?? LANGS[$baseLang] ?? reset(LANGS));
        \FBL\Language::load(null);
    }

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

    return isset($errors[$field_name]) ? 'is-invalid' : '';
}

function old($field_name): string
{
    return isset(session()->get('form_data')[$field_name]) ? htmlSC((string)session()->get('form_data')[$field_name]) : '';
}

function htmlSC($str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
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

function check_creator(): bool
{
    return \FBL\Auth::hasRole('creator');
}

function get_role_rank(?string $role = null): int
{
    static $map = [
        'user' => 10,
        'moderator' => 20,
        'admin' => 30,
        'creator' => 40,
    ];

    $role = trim((string)($role ?? (get_user()['role'] ?? 'user')));

    return $map[$role] ?? 0;
}

function has_role_level(string $role): bool
{
    return get_role_rank() >= get_role_rank($role);
}

function can_moderate_chat(): bool
{
    return has_role_level('moderator');
}

function can_manage_chat_cleanup(): bool
{
    return has_role_level('admin');
}

function can_view_chat_audit(): bool
{
    return has_role_level('creator');
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

    if ($role === 'creator' && ($label === null || $label === 'Creator' || $label === 'Создатель')) {
        return $roleLabels[$role] = return_translation('tpl_auth_role_creator');
    }

    if ($role === 'admin' && ($label === null || $label === 'Admin')) {
        return $roleLabels[$role] = return_translation('tpl_auth_role_admin');
    }

    if ($role === 'moderator' && ($label === null || $label === 'Moderator')) {
        return $roleLabels[$role] = return_translation('tpl_auth_role_moderator');
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

function site_social_network_options(): array
{
    return [
        'telegram' => ['label' => 'Telegram', 'icon' => 'ci-telegram', 'placeholder' => 'https://t.me/your_channel'],
        'instagram' => ['label' => 'Instagram', 'icon' => 'ci-instagram', 'placeholder' => 'https://instagram.com/your_profile'],
        'facebook' => ['label' => 'Facebook', 'icon' => 'ci-facebook', 'placeholder' => 'https://facebook.com/your_page'],
        'youtube' => ['label' => 'YouTube', 'icon' => 'ci-youtube', 'placeholder' => 'https://youtube.com/@your_channel'],
        'x' => ['label' => 'X', 'icon' => 'ci-x', 'placeholder' => 'https://x.com/your_profile'],
        'tiktok' => ['label' => 'TikTok', 'icon' => 'ci-tiktok', 'placeholder' => 'https://www.tiktok.com/@your_profile'],
        'vk' => ['label' => 'VK', 'icon' => 'ci-vk', 'placeholder' => 'https://vk.com/your_page'],
        'linkedin' => ['label' => 'LinkedIn', 'icon' => 'ci-linkedin', 'placeholder' => 'https://linkedin.com/company/your_company'],
        'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'ci-whatsapp', 'placeholder' => 'https://wa.me/15555555555'],
        'discord' => ['label' => 'Discord', 'icon' => 'ci-discord', 'placeholder' => 'https://discord.gg/your_server'],
        'github' => ['label' => 'GitHub', 'icon' => 'ci-github', 'placeholder' => 'https://github.com/your_profile'],
        'viber' => ['label' => 'Viber', 'icon' => 'ci-viber', 'placeholder' => 'https://invite.viber.com/?g2=your_invite'],
        'messenger' => ['label' => 'Messenger', 'icon' => 'ci-messenger', 'placeholder' => 'https://m.me/your_page'],
        'phone' => ['label' => return_translation('admin_post_builder_social_phone'), 'icon' => 'ci-phone', 'placeholder' => '+15555555555'],
        'website' => ['label' => return_translation('admin_post_builder_social_external_link'), 'icon' => 'ci-globe', 'placeholder' => 'https://example.com'],
    ];
}

function site_social_links(): array
{
    $options = site_social_network_options();
    $json = site_setting('social_links', '');
    $items = [];

    if ($json !== '') {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $network = (string)($item['network'] ?? '');
                $url = trim((string)($item['url'] ?? ''));
                if ($url === '' || !isset($options[$network])) {
                    continue;
                }

                $items[] = [
                    'network' => $network,
                    'href' => $network === 'phone' ? normalize_phone_href($url) : $url,
                    'icon' => $options[$network]['icon'],
                    'label' => $options[$network]['label'],
                    'external' => $network !== 'phone',
                ];
            }
        }
    }

    if ($items) {
        return $items;
    }

    foreach (['telegram', 'instagram', 'facebook', 'youtube'] as $network) {
        $href = site_setting('social_' . $network, '');
        if ($href === '') {
            continue;
        }

        $items[] = [
            'network' => $network,
            'href' => $href,
            'icon' => $options[$network]['icon'],
            'label' => $options[$network]['label'],
            'external' => true,
        ];
    }

    return $items;
}

function normalize_phone_href(string $phone): string
{
    $phone = trim($phone);
    if (str_starts_with(strtolower($phone), 'tel:')) {
        return $phone;
    }

    $normalized = preg_replace('/[^\d+]/', '', $phone) ?: '';

    return $normalized !== '' ? 'tel:' . $normalized : $phone;
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
    $path = trim((string)$path);

    if ($path === '') {
        return base_url('/assets/img/no-image.png');
    }

    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }

    if (str_starts_with($path, '//')) {
        return $path;
    }

    $normalizedPath = ltrim($path, '/');
    return base_url('/' . $normalizedPath);
}

function site_favicon_url(): string
{
    $favicon = trim(site_setting('site_favicon', ''));
    if ($favicon === '') {
        return base_url('/assets/img/fbl_logo.png');
    }

    return get_image($favicon);
}

function site_favicon_type(): string
{
    $path = strtolower((string)(parse_url(site_favicon_url(), PHP_URL_PATH) ?? ''));
    $extension = pathinfo($path, PATHINFO_EXTENSION);

    return match ($extension) {
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        default => 'image/png',
    };
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
