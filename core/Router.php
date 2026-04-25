<?php

namespace FBL;

/**
 * Регистрирует маршруты приложения, подбирает подходящий маршрут и запускает его обработчик.
 */
class Router
{

    protected Request $request;
    protected Response $response;
    protected array $routes = [];
    public array $route_params = [];

    /**
     * Получает зависимости роутера для работы с запросом и ответом.
     */
    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Регистрирует маршрут с обработчиком и набором HTTP-методов.
     */
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

    /**
     * Регистрирует GET-маршрут.
     */
    public function get($path, $callback): self
    {
        return $this->add($path, $callback, 'GET');
    }

    /**
     * Регистрирует POST-маршрут.
     */
    public function post($path, $callback): self
    {
        return $this->add($path, $callback, 'POST');
    }

    /**
     * Регистрирует PUT-маршрут.
     */
    public function put($path, $callback): self
    {
        return $this->add($path, $callback, 'PUT');
    }

    /**
     * Возвращает список всех зарегистрированных маршрутов.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Находит маршрут для текущего запроса и вызывает его обработчик.
     */
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

    /**
     * Подбирает маршрут по пути, методу, языку и middleware.
     */
    protected function matchRoute($path): mixed
    {

        $allowed_methods = [];
        $langCodes = array_keys(LANGS);
        $langPattern = $langCodes ? implode('|', array_map(static fn(string $code): string => preg_quote($code, '#'), $langCodes)) : '[a-z]+';

        foreach ($this->routes as $route) {
            $routePath = trim($route['path'], '/');

            if (MULTILANGS) {
                if ($routePath === '') {
                    $pattern = "#^/(?:(?P<lang>{$langPattern})/?)?$#";
                } else {
                    $pattern = "#^/(?:(?P<lang>{$langPattern})/)?{$routePath}/?$#";
                }
            } else {
                if ($routePath === '') {
                    $pattern = "#^/?$#";
                } else {
                    $pattern = "#^/{$routePath}/?$#";
                }
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
                            response()->json([
                                'status' => false,
                                'message' => Language::get('tpl_security_error'),
                            ], 419);
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

            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                response()->json(['status' => 'error', 'Method not allowed'], 405);
            }

            abort('Method not allowed', 405);
        }

        return false;
    }

    /**
     * Отключает проверку CSRF-токена для последнего зарегистрированного маршрута.
     */
    public function withoutCSRFToken(): self
    {
        $this->routes[array_key_last($this->routes)]['needCSRFToken'] = false;
        return $this;
    }

    /**
     * Сравнивает CSRF-токен из запроса с токеном в сессии.
     */
    public function checkCSRFToken(): bool
    {
        $requestToken = (string)request()->post('needCSRFToken', '');
        $sessionToken = (string)session()->get('needCSRFToken', '');

        return $requestToken !== '' && $sessionToken !== '' && hash_equals($sessionToken, $requestToken);
    }

    /**
     * Назначает middleware для последнего зарегистрированного маршрута.
     */
    public function middleware(array $middleware): self
    {
        $this->routes[array_key_last($this->routes)]['middleware'] = $middleware;
        return $this;
    }

}
