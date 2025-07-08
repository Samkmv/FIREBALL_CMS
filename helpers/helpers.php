<?php

use FBL\Auth;

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
    return PATH . $path;
}

function base_href($path = ''): string
{
    if (app()->get('lang')['base'] != 1) {
        return PATH . '/' . app()->get('lang')['code'] . $path;
    }

    return PATH . $path;
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

function get_user()
{
    return \FBL\Auth::user();
}

function logout()
{
    Auth::logout();
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
    } catch (Exception $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Error: {$e->getMessage()}" . PHP_EOL . "File: {$e->getFile()}" . PHP_EOL . "Line: {$e->getLine()}" . PHP_EOL . '========================================================================' . PHP_EOL, 3, ERROR_LOGS);
        abort('Sorry! An error has occurred!', 500);
        return false;
    }
}

function get_image($path): string
{
//    return $path ? base_href("/$path") : base_href("/assets/img/no-image.png");
    return $path ? base_url("/$path") : base_url("/assets/img/no-image.png");
}