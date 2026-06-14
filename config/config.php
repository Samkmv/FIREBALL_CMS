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

if (!defined('STORAGE')) {
    define('STORAGE', ROOT . '/storage');
}

if (!defined('INSTALLED_LOCK')) {
    define('INSTALLED_LOCK', STORAGE . '/installed.lock');
}

$requestHost = preg_replace('/[^a-zA-Z0-9.:[\]-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
$requestIsSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$detectedAppUrl = ($requestIsSecure ? 'https' : 'http') . '://' . $requestHost;

$defaults = [
    'DEBUG' => 0,
    'LAYOUT' => 'default',
    'THEME' => 'default',
    'PATH' => $detectedAppUrl,
    'UPLOADS' => WWW . '/uploads',
    'SITE_NAME' => 'fbl',
    'DEFAULT_LOCALE' => 'ru',
    'CHAT_ENCRYPTION_KEY' => 'change-this-chat-key-in-production',
    'APP_TIMEZONE' => 'Europe/Moscow',
    'UPDATE_CHANNEL' => 'stable',
    'TRUSTED_PROXIES' => [],
    'PAGINATION_SETTINGS' => [
        'perPage' => 20,
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
        'de' => [
            'id' => 3,
            'code' => 'de',
            'title' => 'Deutsch',
            'base' => 0,
        ],
        'zh-cn' => [
            'id' => 4,
            'code' => 'zh-cn',
            'title' => '简体中文',
            'base' => 0,
        ],
    ],
    'DB_SETTINGS' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'database' => '',
        'username' => '',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'port' => 3306,
        'prefix' => '',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],
    'MAIL_SETTINGS' => [
        'host' => '',
        'auth' => true,
        'username' => '',
        'password' => '',
        'secure' => 'tls',
        'port' => 587,
        'from_email' => '',
        'from_name' => 'FIREBALL CMS',
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

if (
    is_file(INSTALLED_LOCK)
    && is_file($localConfigPath)
    && empty($localConfig['CHAT_ENCRYPTION_KEY'])
) {
    $lockPath = sys_get_temp_dir() . '/fireball-chat-key-' . hash('sha256', ROOT) . '.lock';
    $lockHandle = @fopen($lockPath, 'c');

    if ($lockHandle !== false && flock($lockHandle, LOCK_EX)) {
        try {
            $latestLocalConfig = require $localConfigPath;
            if (is_array($latestLocalConfig) && empty($latestLocalConfig['CHAT_ENCRYPTION_KEY'])) {
                $latestLocalConfig['CHAT_ENCRYPTION_KEY'] = bin2hex(random_bytes(32));
                $temporaryPath = $localConfigPath . '.tmp-' . bin2hex(random_bytes(4));
                $content = "<?php\n\nreturn " . var_export($latestLocalConfig, true) . ";\n";

                if (@file_put_contents($temporaryPath, $content, LOCK_EX) === false || !@rename($temporaryPath, $localConfigPath)) {
                    @unlink($temporaryPath);
                    throw new \RuntimeException('Unable to persist CHAT_ENCRYPTION_KEY.');
                }

                @chmod($localConfigPath, 0600);
                $localConfig = $latestLocalConfig;
            } elseif (is_array($latestLocalConfig)) {
                $localConfig = $latestLocalConfig;
            }
        } catch (\Throwable $exception) {
            error_log('Unable to initialize CHAT_ENCRYPTION_KEY: ' . $exception->getMessage());
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
}

$config = array_replace_recursive($defaults, $localConfig);
$defaultLocale = (string)($config['DEFAULT_LOCALE'] ?? $defaults['DEFAULT_LOCALE']);
if (isset($config['LANGS'][$defaultLocale])) {
    foreach ($config['LANGS'] as $code => $language) {
        $config['LANGS'][$code]['base'] = $code === $defaultLocale ? 1 : 0;
    }
}

$scalarKeys = [
    'DEBUG',
    'LAYOUT',
    'THEME',
    'PATH',
    'UPLOADS',
    'SITE_NAME',
    'DEFAULT_LOCALE',
    'CHAT_ENCRYPTION_KEY',
    'APP_TIMEZONE',
    'UPDATE_CHANNEL',
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
    'TRUSTED_PROXIES',
];

foreach ($arrayKeys as $key) {
    if (!defined($key)) {
        define($key, $config[$key]);
    }
}

date_default_timezone_set((string)($config['APP_TIMEZONE'] ?? $defaults['APP_TIMEZONE']));
