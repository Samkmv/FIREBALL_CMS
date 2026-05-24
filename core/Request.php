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
    public function get($name, $default = null): ?string
    {
        return $this->get[$name] ?? $default;
    }

    /**
     * Возвращает POST-параметр по имени.
     */
    public function post($name, $default = null): ?string
    {
        return $this->post[$name] ?? $default;
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

        $request_data = $this->isPost() ? $_POST : $_GET;

        foreach ($request_data as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            $data[$key] = $value;
        }

        return $data;
    }

}
