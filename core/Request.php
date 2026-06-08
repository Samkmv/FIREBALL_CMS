<?php

namespace FBL;

/**
 * Инкапсулирует входящий HTTP-запрос и даёт доступ к его данным.
 */
class Request
{

    public string $uri;
    public string $rowUri;
    public array $get;
    public array $post;
    public array $files;
    protected ?array $jsonBody = null;

    /**
     * Сохраняет основные данные текущего запроса.
     */
    public function __construct($uri)
    {
        $this->rowUri = $uri;
        $this->uri = trim(urldecode($uri), '/');
        $this->get = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
    }

    /**
     * Возвращает HTTP-метод текущего запроса.
     */
    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    /**
     * Проверяет, является ли запрос GET-запросом.
     */
    public function isGet(): bool
    {
        return $this->getMethod() == 'GET';
    }

    /**
     * Проверяет, является ли запрос POST-запросом.
     */
    public function isPost(): bool
    {
        return $this->getMethod() == 'POST';
    }

    public function isStateChanging(): bool
    {
        return in_array($this->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Проверяет, был ли запрос отправлен как Ajax-запрос.
     */
    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }

    /**
     * Возвращает GET-параметр по имени.
     */
    public function get($name, $default = null): mixed
    {
        return $this->get[$name] ?? $default;
    }

    /**
     * Возвращает POST-параметр по имени.
     */
    public function post($name, $default = null): mixed
    {
        if (array_key_exists($name, $this->post)) {
            return $this->post[$name];
        }

        $jsonBody = $this->json();

        return $jsonBody[$name] ?? $default;
    }

    /**
     * Возвращает HTTP-заголовок в нормализованном виде.
     */
    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (array_key_exists($key, $_SERVER)) {
            return (string)$_SERVER[$key];
        }

        if (strtolower($name) === 'content-type' && isset($_SERVER['CONTENT_TYPE'])) {
            return (string)$_SERVER['CONTENT_TYPE'];
        }

        return $default;
    }

    /**
     * Возвращает распарсенное JSON-тело запроса, если оно есть.
     */
    public function json(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        $contentType = strtolower((string)$this->header('Content-Type', ''));
        if (!str_contains($contentType, 'application/json')) {
            return $this->jsonBody = [];
        }

        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || trim($rawBody) === '') {
            return $this->jsonBody = [];
        }

        $decoded = json_decode($rawBody, true);

        return $this->jsonBody = is_array($decoded) ? $decoded : [];
    }

    /**
     * Возвращает путь запроса без строки параметров.
     */
    public function getPath(): string
    {
        return $this->removeQueryString();
    }

    /**
     * Удаляет query string из URI и оставляет только маршрут.
     */
    protected function removeQueryString(): string
    {
        if ($this->uri) {
            $params = explode('?', $this->uri);

            return trim($params[0], '/');
        }

        return "";
    }

    /**
     * Возвращает нормализованные данные запроса с обрезанными строковыми значениями.
     */
    public function getData(): array
    {
        $data = [];

        $request_data = $this->isPost() ? array_replace($this->json(), $_POST) : $_GET;

        foreach ($request_data as $key => $value) {
            $data[$key] = $this->trimValue($value);
        }

        return $data;
    }

    protected function trimValue($value)
    {
        if (is_array($value)) {
            return array_map(fn($item) => $this->trimValue($item), $value);
        }

        return is_string($value) ? trim($value) : $value;
    }

}
