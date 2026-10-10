<?php
declare(strict_types=1);

// Render the installer only: no application boot, SQL connection or installation.
define('ROOT', dirname(__DIR__));
define('PATH', 'http://localhost/cms');
require ROOT . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

$app = (new ReflectionClass(FBL\Application::class))->newInstanceWithoutConstructor();
FBL\Application::$app = $app;
$app->view = new FBL\View(LAYOUT);
$app->session = (new ReflectionClass(FBL\Session::class))->newInstanceWithoutConstructor();
$_SESSION = ['needCSRFToken' => 'installer-view-test-token'];
FBL\Language::$lang_data = ['password_field_toggle' => 'Toggle password'];
$checks = 0;
function verifyInstallView(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function installerPreview(string $step, string $locale = 'ru', array $overrides = []): DOMXPath
{
    $html = view('install/index', array_replace([
        'step' => $step,
        'data' => ['locale' => $locale],
        'translations' => require APP . '/Languages/' . $locale . '/install/index.php',
        'result' => [],
        'languages' => LANGS,
        'requirements' => [['label' => 'PHP >= 8.2', 'ok' => true], ['label' => 'ZIP', 'ok' => true]],
        'requirements_pass' => true,
        'default_site_url' => 'https://example.invalid/cms',
        'timezones' => ['UTC', 'Europe/Moscow'],
    ], $overrides), false);
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}
$referenceKeys = array_keys(require APP . '/Languages/ru/install/index.php');
$forms = [
    'language' => ['locale'],
    'requirements' => [],
    'database' => ['db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'db_prefix'],
    'site' => ['site_name', 'site_url', 'timezone'],
    'admin' => ['admin_login', 'admin_email', 'admin_password', 'admin_password_confirmation', 'install_demo'],
];
foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $translations = require APP . '/Languages/' . $locale . '/install/index.php';
    verifyInstallView(!array_diff($referenceKeys, array_keys($translations)), "Missing translations: $locale");
    foreach (array_keys($forms + ['finish' => []]) as $step) {
        $xpath = installerPreview($step, $locale);
        verifyInstallView($xpath->query('//html[@lang="' . $locale . '"]')->length === 1, 'Language must be preserved.');
        verifyInstallView($xpath->query('//li[contains(@class,"install-stage")]')->length === 5, 'Every step must be present.');
        verifyInstallView($xpath->query('//*[@id="install-step-title"]')->length === 1, 'Each screen must have its own heading.');
        verifyInstallView($xpath->query('//link[contains(@href,"/cms/assets/default/css/install.css")]')->length === 1, 'Subdirectory assets must work.');
        if ($step === 'finish') {
            verifyInstallView($xpath->query('//form')->length === 0, 'Finish must not repeat installation.');
            continue;
        }
        verifyInstallView($xpath->query('//form[@method="post" and @action="http://localhost/cms/install"]')->length === 1, 'Keep the existing POST route.');
        verifyInstallView($xpath->query('//input[@name="step" and @value="' . $step . '"]')->length === 1, 'Preserve the current form step.');
        verifyInstallView($xpath->query('//input[@name="needCSRFToken" and @value="installer-view-test-token"]')->length === 1, 'Keep CSRF protection.');
        foreach ($forms[$step] as $name) verifyInstallView($xpath->query('//*[@name="' . $name . '"]')->length >= 1, 'Missing form field: ' . $name);
        if ($step !== 'language') verifyInstallView($xpath->query('//a[contains(@class,"install-back")]')->length === 1, 'Keep return navigation.');
    }
}
$xpath = installerPreview('requirements', 'ru', ['requirements_pass' => false]);
verifyInstallView($xpath->query('//button[@type="submit" and @disabled]')->length === 1, 'Failed checks must prevent continuation.');
verifyInstallView($xpath->query('//a[contains(@href,"step=requirements")]')->length === 1, 'Failed checks must offer a retry.');
$xpath = installerPreview('admin', 'ru', ['data' => ['db_tables' => ['existing'], 'demo' => true]]);
verifyInstallView($xpath->query('//input[@name="allow_existing" and @required and not(@checked)]')->length === 1, 'Existing tables need explicit consent.');
verifyInstallView($xpath->query('//input[@name="install_demo" and @checked]')->length === 1, 'Demo selection must survive validation errors.');
verifyInstallView($xpath->query('//input[@type="password" and @required and @minlength="12"]')->length === 2, 'Keep the strong password requirement.');
$xpath = installerPreview('database', 'ru', ['result' => ['status' => 'error', 'message' => '<script>alert(1)</script>']]);
verifyInstallView($xpath->query('//div[@role="alert"]')->length === 1, 'Connection failures must be accessible.');
verifyInstallView($xpath->query('//div[@role="alert"]//script')->length === 0, 'Error messages must be escaped.');
foreach (['css/install.css', 'js/install.js'] as $asset) {
    verifyInstallView(file_get_contents(ROOT . '/themes/default/assets/' . $asset) === file_get_contents(WWW . '/assets/default/' . $asset), 'Published assets must match their source.');
}
verifyInstallView($app->db === null, 'View tests must never connect to SQL.');
echo "$checks installer view checks passed.\n";
