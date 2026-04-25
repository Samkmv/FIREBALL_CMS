<?php

namespace FBL;

/**
 * Строит пагинацию для списков записей и генерирует ссылки на страницы.
 */
class Pagination
{

    protected int $countPages;
    protected int $currentPage;
    protected string $uri;

    /**
     * Рассчитывает параметры пагинации на основе общего числа записей и текущего запроса.
     */
    public function __construct(
        protected int $totalRecords,
        protected int $perPage = PAGINATION_SETTINGS['perPage'],
        protected int $midSize = PAGINATION_SETTINGS['midSize'],
        protected int $maxPages = PAGINATION_SETTINGS['maxPages'],
        protected string $tpl = PAGINATION_SETTINGS['tpl'],
        protected string $pageParam = 'page',
    )
    {
        $this->countPages = $this->getCountPages();
        $this->currentPage = $this->getCurrentPage();
        $this->uri = $this->getParams();
        $this->midSize = $this->getMidSize();
    }

    /**
     * Возвращает общее количество страниц.
     */
    protected function getCountPages(): int
    {
        return (int)ceil($this->totalRecords / $this->perPage) ?: 1;
    }

    /**
     * Определяет текущую страницу и проверяет, что она находится в допустимом диапазоне.
     */
    protected function getCurrentPage(): int
    {
        $page = (int)request()->get($this->pageParam, 1);
        if ($page < 1 || $page > $this->countPages) {
            abort();
        }
        return $page;
    }

    /**
     * Формирует URI без параметра текущей страницы.
     */
    protected function getParams()
    {
        $url = request()->uri;
        $url = parse_url($url);
        $uri = '/' . ltrim($url['path'] ?? '', '/');

        if ($uri === '//') {
            $uri = '/';
        }

        if (!empty($url['query']) && $url['query'] != '&') {
            parse_str($url['query'], $params);
            if (isset($params[$this->pageParam])) {
                unset($params[$this->pageParam]);
            }
            if (!empty($params)) {
                $uri .= '?' . http_build_query($params);
            }
        }
        return $uri;
    }

    /**
     * Определяет, сколько соседних страниц показывать в навигации.
     */
    protected function getMidSize(): int
    {
        return ($this->countPages <= $this->maxPages) ? $this->countPages : $this->midSize;
    }

    /**
     * Возвращает смещение для SQL-запроса с LIMIT.
     */
    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    /**
     * Генерирует HTML пагинации на основе выбранного шаблона.
     */
    public function getHtml()
    {
        $back = '';
        $forward = '';
        $first_page = '';
        $last_page = '';
        $pages_left = [];
        $pages_right = [];
        $current_page = $this->currentPage;

        if ($this->currentPage > 1) {
            $back = $this->getLink($this->currentPage - 1);
        }

        if ($this->currentPage < $this->countPages) {
            $forward = $this->getLink($this->currentPage + 1);
        }

        if ($this->currentPage > $this->midSize + 1) {
            $first_page = $this->getLink(1);
        }

        if ($this->currentPage < ($this->countPages - $this->midSize)) {
            $last_page = $this->getLink($this->countPages);
        }

        for ($i = $this->midSize; $i > 0; $i--) {
            if ($this->currentPage - $i > 0) {
                $pages_left[] = [
                    'link' => $this->getLink($this->currentPage - $i),
                    'number' => $this->currentPage - $i,
                ];
            }
        }

        for ($i = 1; $i <= $this->midSize; $i++) {
            if ($this->currentPage + $i <= $this->countPages) {
                $pages_right[] = [
                    'link' => $this->getLink($this->currentPage + $i),
                    'number' => $this->currentPage + $i,
                ];
            }
        }

        return view()->renderPartial($this->tpl, compact('back', 'forward', 'first_page', 'last_page', 'pages_left', 'pages_right', 'current_page'));
    }

    /**
     * Строит ссылку на указанную страницу с учётом текущих параметров запроса.
     */
    protected function getLink($page): string
    {
        if ($page == 1) {
            return rtrim($this->uri, '?&');
        }
        if (str_contains($this->uri, '&') || str_contains($this->uri, '?')) {
            return "{$this->uri}&{$this->pageParam}={$page}";
        } else {
            return "{$this->uri}?{$this->pageParam}={$page}";
        }
    }

    /**
     * Позволяет выводить объект пагинации как готовый HTML.
     */
    public function __toString(): string
    {
        return $this->getHtml();
    }

}
