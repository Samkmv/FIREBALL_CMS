<?php

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}

if (!defined('WWW')) {
    define('WWW', ROOT . '/public');
}

if (!defined('CONFIG')) {
    define('CONFIG', ROOT . '/config');
}

if (!defined('HELPERS')) {
    define('HELPERS', ROOT . '/helpers');
}

if (!defined('APP')) {
    define('APP', ROOT . '/app');
}

if (!defined('CORE')) {
    define('CORE', ROOT . '/core');
}

if (!defined('VIEWS')) {
    define('VIEWS', APP . '/Views');
}

if (!defined('ERROR_LOGS')) {
    define('ERROR_LOGS', ROOT . '/tmp/error.log');
}

if (!defined('CACHE')) {
    define('CACHE', ROOT . '/tmp/cache');
}

$defaults = [
    'DEBUG' => 1,
    'LAYOUT' => 'default',
    'THEME' => 'default',
    'PATH' => 'http://localhost:8888',
    'UPLOADS' => WWW . '/uploads',
    'SITE_NAME' => 'fbl',
    'CHAT_ENCRYPTION_KEY' => 'change-this-chat-key-in-production',
    'APP_TIMEZONE' => 'Europe/Moscow',
    'PAGINATION_SETTINGS' => [
        'perPage' => 10,
        'midSize' => 2,
        'maxPages' => 7,
        'tpl' => 'pagination/base',
    ],
    'MULTILANGS' => 1,
    'LANGS' => [
        'ru' => [
            'id' => 1,
            'code' => 'ru',
            'title' => 'Русский',
            'base' => 1,
        ],
        'en' => [
            'id' => 2,
            'code' => 'en',
            'title' => 'English',
            'base' => 0,
        ],
    ],
    'DB_SETTINGS' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'fbl',
        'username' => 'root',
        'password' => 'root',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'port' => 8889,
        'prefix' => '',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],
    'MAIL_SETTINGS' => [
        'host' => 'sandbox.smtp.mailtrap.io',
        'auth' => true,
        'username' => '5a8227c0fb4058',
        'password' => '4ca21db6c36d9a',
        'secure' => 'tls',
        'port' => 587,
        'from_email' => '809dd70a9c-b1e56f@inbox.mailtrap.io',
        'from_name' => 'FBL',
        'is_html' => true,
        'charset' => 'UTF-8',
        'debug' => 0,
    ],
];

$localConfig = [];
$localConfigPath = __DIR__ . '/config.local.php';

if (is_file($localConfigPath)) {
    $loadedLocalConfig = require $localConfigPath;

    if (is_array($loadedLocalConfig)) {
        $localConfig = $loadedLocalConfig;
    }
}

$config = array_replace_recursive($defaults, $localConfig);

$scalarKeys = [
    'DEBUG',
    'LAYOUT',
    'THEME',
    'PATH',
    'UPLOADS',
    'SITE_NAME',
    'CHAT_ENCRYPTION_KEY',
    'APP_TIMEZONE',
    'MULTILANGS',
];

foreach ($scalarKeys as $key) {
    if (!defined($key)) {
        define($key, $config[$key]);
    }
}

$arrayKeys = [
    'DB_SETTINGS',
    'MAIL_SETTINGS',
    'PAGINATION_SETTINGS',
    'LANGS',
];

foreach ($arrayKeys as $key) {
    if (!defined($key)) {
        define($key, $config[$key]);
    }
}

date_default_timezone_set($defaults['APP_TIMEZONE']);