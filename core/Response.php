<?php

namespace FBL;

/**
 * Формирует HTTP-ответы: коды, редиректы и данные для клиента.
 */
class Response
{

    /**
     * Устанавливает HTTP-код ответа.
     */
    public function setResponseCode(int $code): void
    {
        http_response_code($code);
    }

    /**
     * Перенаправляет клиента на указанный адрес или обратно на предыдущую страницу.
     */
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

    /**
     * Отправляет JSON-ответ и завершает выполнение скрипта.
     */
    public function json($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode($data));
    }

    /**
     * Отправляет текстовый ответ и завершает выполнение скрипта.
     */
    public function text($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        exit(json_encode($data));
    }

}
