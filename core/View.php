<?php

namespace FBL;

/**
 * Отвечает за рендеринг шаблонов представлений и макетов.
 */
class View
{

    public string $layout;
    public string $content = '';

    /**
     * Сохраняет имя макета по умолчанию для дальнейшего рендеринга.
     */
    public function __construct($layout)
    {
        $this->layout = $layout;
    }

    /**
     * Рендерит представление, а затем при необходимости оборачивает его в макет.
     */
    public function render($view, $data = [], $layout = ''): string
    {
        extract($data);

        $view_file = VIEWS . '/themes/' . THEME . "/{$view}.php";
        if (is_file($view_file)) {
            ob_start();
            require $view_file;
            $this->content = ob_get_clean();
        } else {
            abort("Not Found view - {$view_file}", 500);
        }

        if ($layout === false) {
            return $this->content;
        }

        $layout_file_name = $layout ?: $this->layout;
        $layout_file = VIEWS . "/layouts/{$layout_file_name}.php";
        if (is_file($layout_file)) {
            ob_start();
            require_once $layout_file;
            return ob_get_clean();
        } else {
            abort("Not Found layout - {$layout_file}", 500);
        }

        return '';

    }

    /**
     * Рендерит только частичное представление без подключения макета.
     */
    public function renderPartial($view, $data = []): string
    {
        extract($data);
        $view_file = VIEWS . '/themes/' . THEME . "/{$view}.php";

        if (is_file($view_file)) {
            ob_start();
            require $view_file;
            return ob_get_clean();
        } else {
            return "File - {$view_file} not found";
        }
    }

}
