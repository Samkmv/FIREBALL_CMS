<?php

namespace FBL;

use App\Services\ConfigService;
use FBL\Plugins\EventManager;
use FBL\Plugins\HookManager;
use FBL\Plugins\PluginManager;
/**
 * Основной объект приложения, который инициализирует базовые сервисы и запускает обработку запроса.
 */
class Application
{

    protected string $uri;
    public Request $request;
    public Response $response;
    public Router $router;
    public Session $session;
    public Cache $cache;
    public ?Database $db = null;
    public View $view;
    public ?ThemeManager $theme = null;
    public ?PluginManager $plugins = null;
    public HookManager $hooks;
    public EventManager $events;
    public static Application $app;
    protected array $container = [];
    protected ?array $installationStatus = null;

    /**
     * Создаёт основные сервисы приложения и подготавливает окружение для текущего запроса.
     */
    public function __construct()
    {
        self::$app = $this;
        $this->uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $this->request = new Request($this->uri);
        $this->response = new Response();
        $this->router = new Router($this->request, $this->response);
        $this->view = new View(LAYOUT);
        $this->session = new Session();
        $this->cache = new Cache();
        $this->hooks = new HookManager();
        $this->events = new EventManager();
        $this->generateCSRFToken();
        if ($this->isInstalled()) {
            $this->bootInstalledServices();
        }
    }

    /**
     * Запускает маршрутизацию и выводит результат обработки запроса.
     */
    public function run(): void
    {
        $installation = $this->inspectInstallation();
        if (($installation['state'] ?? '') === 'broken') {
            $this->renderInstallationProblem((array)($installation['errors'] ?? []));
        }

        if (!$this->isInstalled() && !$this->isInstallRequest()) {
            $this->response->redirect($this->installUrl());
        }

        if ($this->isInstalled() && $this->isInstallRequest() && !$this->isInstallFinishRequest()) {
            $this->response->redirect(base_url('/admin'));
        }

        if ($this->isInstalled() && $this->isUpdateInProgress()) {
            http_response_code(503);
            header('Retry-After: 60');
            header('Content-Type: text/html; charset=utf-8');
            exit('<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Maintenance</title><body style="font-family:sans-serif;padding:40px;text-align:center"><h1>FIREBALL CMS maintenance</h1><p>The site is being updated. Please try again shortly.</p></body></html>');
        }

        echo $this->router->dispatch();
    }

    protected function isUpdateInProgress(): bool
    {
        $maintenancePath = STORAGE . '/update.maintenance';
        if (!is_file($maintenancePath)) {
            return false;
        }

        $lockPath = STORAGE . '/update.lock';
        $handle = @fopen($lockPath, 'c+');
        if (!is_resource($handle)) {
            return true;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return true;
        }

        flock($handle, LOCK_UN);
        fclose($handle);
        @unlink($maintenancePath);
        @unlink($lockPath);

        return false;
    }

    public function isInstalled(): bool
    {
        return ($this->inspectInstallation()['state'] ?? '') === 'installed';
    }

    public function inspectInstallation(): array
    {
        if ($this->installationStatus !== null) {
            return $this->installationStatus;
        }

        $hasLock = is_file(INSTALLED_LOCK);
        $hasLocalConfig = is_file(CONFIG . '/config.local.php');
        if (!$hasLock && !$hasLocalConfig) {
            return $this->installationStatus = ['state' => 'not_installed', 'errors' => []];
        }

        $errors = [];
        if (!$hasLocalConfig) {
            $errors[] = 'Missing config/config.local.php.';
        }

        if ($hasLocalConfig) {
            $errors = array_merge($errors, $this->validateInstalledDatabase());
        }

        if (!$hasLock && $errors === []) {
            $this->createLegacyInstalledLock();
            $hasLock = is_file(INSTALLED_LOCK);
        }
        if (!$hasLock) {
            $errors[] = 'Missing storage/installed.lock.';
        }

        return $this->installationStatus = $errors === []
            ? ['state' => 'installed', 'errors' => []]
            : ['state' => 'broken', 'errors' => array_values(array_unique($errors))];
    }

    protected function validateInstalledDatabase(): array
    {
        $errors = [];
        try {
            if (trim((string)(DB_SETTINGS['database'] ?? '')) === '') {
                return ['Database name is missing in config/config.local.php.'];
            }

            $dsn = 'mysql:host=' . DB_SETTINGS['host'] . ';dbname=' . DB_SETTINGS['database'] . ';charset=' . DB_SETTINGS['charset'];
            if (!empty(DB_SETTINGS['port'])) {
                $dsn .= ';port=' . (int)DB_SETTINGS['port'];
            }

            $pdo = new \PDO($dsn, DB_SETTINGS['username'], DB_SETTINGS['password'], DB_SETTINGS['options']);
            foreach (['users', 'user_roles', 'site_settings'] as $table) {
                $statement = $pdo->prepare(
                    'SELECT COUNT(*)
                     FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = ?'
                );
                $statement->execute([$table]);
                if ((int)$statement->fetchColumn() === 0) {
                    $errors[] = 'Missing required database table: ' . $table . '.';
                }
            }

            if ($errors === []) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'creator'");
                if ((int)$stmt->fetchColumn() === 0) {
                    $errors[] = 'Creator account is missing.';
                }
            }
        } catch (\Throwable $exception) {
            $errors[] = 'Database connection failed: ' . $exception->getMessage();
        }

        return $errors;
    }

    protected function createLegacyInstalledLock(): void
    {
        if (!is_dir(STORAGE)) {
            @mkdir(STORAGE, 0755, true);
        }
        if (!is_dir(STORAGE) || !is_writable(STORAGE)) {
            return;
        }

        @file_put_contents(INSTALLED_LOCK, json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'migrated_from_legacy_installation' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    protected function renderInstallationProblem(array $errors): never
    {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        $items = '';
        foreach ($errors as $error) {
            $items .= '<li>' . htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        exit(
            '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>FIREBALL CMS installation error</title><body style="font-family:sans-serif;padding:40px;max-width:760px;margin:auto">'
            . '<h1>FIREBALL CMS installation is incomplete</h1>'
            . '<p>The installation marker exists, but required configuration or database data is unavailable.</p>'
            . '<ul>' . $items . '</ul>'
            . '<p>Restore the missing files or database from backup. Do not run installation over existing data.</p>'
            . '</body></html>'
        );
    }

    public function bootInstalledServices(): void
    {
        if ($this->db === null) {
            $this->db = new Database();
        }
        if ($this->theme === null) {
            $this->theme = new ThemeManager();
        }
        if ($this->plugins === null) {
            $this->plugins = new PluginManager();
        }

        $this->set('config', new ConfigService());
        Auth::setUser();
    }

    protected function isInstallRequest(): bool
    {
        $path = '/' . trim($this->request->getPath(), '/');
        if (MULTILANGS) {
            $segments = array_values(array_filter(explode('/', trim($path, '/'))));
            if (isset($segments[0]) && array_key_exists($segments[0], LANGS)) {
                array_shift($segments);
            }
            $path = '/' . implode('/', $segments);
        }

        return $path === '/install' || str_starts_with($path, '/install/');
    }

    protected function isInstallFinishRequest(): bool
    {
        return $this->isInstallRequest()
            && (string)($_GET['step'] ?? '') === 'finish'
            && (string)$this->session->get('install.result.status', '') === 'success';
    }

    protected function installUrl(): string
    {
        return base_url('/install');
    }

    /**
     * Убеждается, что в сессии существует CSRF-токен.
     */
    public function generateCSRFToken(): void
    {
        if (!session()->has('needCSRFToken')) {
            $this->regenerateCSRFToken();
        }
    }

    /**
     * Генерирует новый CSRF-токен и сохраняет его в сессии.
     */
    public function regenerateCSRFToken(): void
    {
        session()->set('needCSRFToken', bin2hex(random_bytes(32)));
    }

    /**
     * Сохраняет значение в контейнер приложения.
     */
    public function set($key, $value): void
    {
        $this->container[$key] = $value;
    }

    /**
     * Возвращает значение из контейнера приложения или значение по умолчанию.
     */
    public function get($key, $default = null)
    {
        return $this->container[$key] ?? $default;
    }

}
