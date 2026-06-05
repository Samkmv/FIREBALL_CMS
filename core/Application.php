<?php

namespace FBL;

use Illuminate\Database\Capsule\Manager as Capsule;

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
    public static Application $app;
    protected array $container = [];

    /**
     * Создаёт основные сервисы приложения и подготавливает окружение для текущего запроса.
     */
    public function __construct()
    {
        self::$app = $this;
        $this->uri = $_SERVER['REQUEST_URI'];
        $this->request = new Request($this->uri);
        $this->response = new Response();
        $this->router = new Router($this->request, $this->response);
        $this->view = new View(LAYOUT);
        $this->session = new Session();
        $this->cache = new Cache();
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
        if (!$this->isInstalled() && !$this->isInstallRequest()) {
            $this->response->redirect($this->installUrl());
        }

        if ($this->isInstalled() && $this->isInstallRequest() && !$this->isInstallFinishRequest()) {
            $this->response->redirect(base_url('/admin'));
        }

        echo $this->router->dispatch();
    }

    public function isInstalled(): bool
    {
        if (is_file(INSTALLED_LOCK)) {
            return true;
        }

        try {
            $dsn = 'mysql:host=' . DB_SETTINGS['host'] . ';dbname=' . DB_SETTINGS['database'] . ';charset=' . DB_SETTINGS['charset'];
            if (!empty(DB_SETTINGS['port'])) {
                $dsn .= ';port=' . (int)DB_SETTINGS['port'];
            }

            $pdo = new \PDO($dsn, DB_SETTINGS['username'], DB_SETTINGS['password'], DB_SETTINGS['options']);
            $hasUsersTable = (int)$pdo->query(
                "SELECT COUNT(*)
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'users'"
            )->fetchColumn() > 0;

            if (!$hasUsersTable) {
                return false;
            }

            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'creator'");

            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function bootInstalledServices(): void
    {
        if ($this->db === null) {
            $this->db = new Database();
        }
        if ($this->theme === null) {
            $this->theme = new ThemeManager();
        }

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
