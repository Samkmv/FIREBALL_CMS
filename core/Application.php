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
    public Database $db;
    public View $view;
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
        $this->db = new Database();
        Auth::setUser();
    }

    /**
     * Запускает маршрутизацию и выводит результат обработки запроса.
     */
    public function run(): void
    {
        echo $this->router->dispatch();
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
