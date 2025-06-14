<?php

namespace FBL;

use Illuminate\Database\Capsule\Manager as Capsule;

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

    public function run(): void
    {
        echo $this->router->dispatch();
    }

    public function generateCSRFToken(): void
    {
        if (!session()->has('needCSRFToken')) {
            session()->set('needCSRFToken', md5(uniqid(mt_rand(), true)));
        }
    }

    public function set($key, $value): void
    {
        $this->container[$key] = $value;
    }

    public function get($key, $default = null)
    {
        return $this->container[$key] ?? $default;
    }

}