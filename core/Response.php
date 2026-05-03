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
            $redirect = $this->sanitizeRedirectTarget((string)$url);
        } else {
            $redirect = $this->sanitizeRedirectTarget((string)($_SERVER['HTTP_REFERER'] ?? ''));
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
        exit((string)$data);
    }

    /**
     * Пропускает только локальные URL для редиректа и отбрасывает внешние/повреждённые значения.
     */
    protected function sanitizeRedirectTarget(string $target): string
    {
        $target = trim(str_replace(["\r", "\n"], '', $target));
        if ($target === '') {
            return base_url('/');
        }

        if (preg_match('~^(?:https?:)?//~i', $target) === 1) {
            $targetHost = strtolower((string)(parse_url($target, PHP_URL_HOST) ?? ''));
            $baseHost = strtolower((string)(parse_url(base_url('/'), PHP_URL_HOST) ?? ''));

            if ($targetHost === '' || $baseHost === '' || $targetHost !== $baseHost) {
                return base_url('/');
            }
        } elseif (!str_starts_with($target, '/')) {
            $target = '/' . ltrim($target, '/');
        }

        return $target;
    }

}
