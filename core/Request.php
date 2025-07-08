<?php

namespace FBL;

class Request
{

    public string $uri;
    public string $rowUri;
    public array $get;
    public array $post;
    public array $files;

    public function __construct($uri)
    {
        $this->rowUri = $uri;
        $this->uri = trim(urldecode($uri), '/');
        $this->get = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
    }

    // Получаем метод
    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    // Является ли запрос GET
    public function isGet(): bool
    {
        return $this->getMethod() == 'GET';
    }

    // Является ли запрос POST
    public function isPost(): bool
    {
        return $this->getMethod() == 'POST';
    }

    // Является ли запрос Ajax
    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }

    public function get($name, $default = null): ?string
    {
        return $this->get[$name] ?? $default;
    }

    public function post($name, $default = null): ?string
    {
        return $this->post[$name] ?? $default;
    }

    public function getPath(): string
    {
        return $this->removeQueryString();
    }

    protected function removeQueryString(): string
    {
        if ($this->uri) {
            $params = explode('?', $this->uri);

            return trim($params[0], '/');
        }

        return "";
    }

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