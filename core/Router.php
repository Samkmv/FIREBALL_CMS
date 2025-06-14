<?php

namespace FBL;

class Router
{

    protected Request $request;
    protected Response $response;
    protected array $routes = [];
    public array $route_params = [];

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function add($path, $callback, $method): self
    {
        $path = trim($path, '/');

        if (is_array($method)) {
            $method = array_map('strtoupper', $method);
        } else {
            $method = [strtoupper($method)];
        }

        $this->routes[] = [
            'path' => "/$path",
            'callback' => $callback,
            'middleware' => [],
            'method' => $method,
            'needCSRFToken' => true
        ];

        return $this;
    }

    public function get($path, $callback): self
    {
        return $this->add($path, $callback, 'GET');
    }

    public function post($path, $callback): self
    {
        return $this->add($path, $callback, 'POST');
    }

    public function put($path, $callback): self
    {
        return $this->add($path, $callback, 'PUT');
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function dispatch(): mixed
    {
        $path = $this->request->getPath();
        $route = $this->matchRoute($path);
        if (false === $route) {
            abort();
        }

        if (is_array($route['callback'])) {
            $route['callback'][0] = new $route['callback'][0];
        }

        return call_user_func($route['callback']);
    }

    protected function matchRoute($path): mixed
    {

        $allowed_methods = [];

        foreach ($this->routes as $route) {

            if (MULTILANGS) {
                $pattern = "#^/?(?P<lang>[a-z]+)?{$route['path']}?$#";
            } else {
                $pattern = "#^{$route['path']}$#";
            }

            if (preg_match($pattern, "/{$path}", $matches)) {

                if (!in_array($this->request->getMethod(), $route['method'])) {
                    $allowed_methods = array_merge($allowed_methods, $route['method']);
                    continue;
                }

                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $this->route_params[$key] = $value;
                    }
                }

                $lang = trim(get_route_param('lang'), '/');
                $base_lang = array_value_search(LANGS, 'base', 1);
                if ( ($lang && !array_key_exists($lang, LANGS)) || $lang == $base_lang) {
                    abort();
                }

                $lang = $lang ?: $base_lang;
                app()->set('lang', LANGS[$lang]);

                Language::load($route['callback']);

                if (request()->isPost()) {
                    if ($route['needCSRFToken'] && !$this->checkCSRFToken()) {
                        if (request()->isAjax()) {
                            echo json_encode([
                                'status' => false,
                                'data' => 'Security error'
                            ]);
                            exit();
                        } else {
                            abort('Page expired', 419);
                        }
                    }
                }

                if ($route['middleware']) {
                    foreach ($route['middleware'] as $item) {
                        $middleware = MIDDLEWARE[$item] ?? false;

                        if ($middleware) {
                            (new $middleware)->handle();
                        }
                    }
                }

                return $route;
            }
        }

        if ($allowed_methods) {
            header("Allow: " . implode(', ', $allowed_methods));

            if ($_SERVER['HTTP_ACCEPT' == 'application/json']) {
                response()->json(['status' => 'error', 'Method not allowed'], 405);
            }

            abort('Method not allowed', 405);
        }

        return false;
    }

    // Убирает проверку CSRF безопасности
    public function withoutCSRFToken(): self
    {
        $this->routes[array_key_last($this->routes)]['needCSRFToken'] = false;
        return $this;
    }

    public function checkCSRFToken(): bool
    {
        return request()->post('needCSRFToken') && (request()->post('needCSRFToken') == session()->get('needCSRFToken'));
    }

    public function middleware(array $middleware): self
    {
        $this->routes[array_key_last($this->routes)]['middleware'] = $middleware;
        return $this;
    }

}