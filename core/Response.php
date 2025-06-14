<?php

namespace FBL;

class Response
{

    public function setResponseCode(int $code): void
    {
        http_response_code($code);
    }

    public function redirect($url = '')
    {
        if ($url) {
            $redirect = $url;
        } else {
            $redirect = $_SERVER['HTTP_REFERER'] ?? base_url('/');
        }

        header("Location: $redirect");
        exit;
    }

    public function json($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode($data));
    }

    public function text($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        exit(json_encode($data));
    }

}