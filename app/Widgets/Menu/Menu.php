<?php

namespace App\Widgets\Menu;

class Menu
{

    protected const DEFAULT_CACHE_KEYS = [
        'menu',
        'shop_desktop_menu',
        'shop_mobile_menu',
    ];

    protected array $data;
    protected string $table = 'categories';
    protected array $tree;
    protected string $menuHtml;
    protected string $tpl = 'menu_tpl';
    protected string $container = 'ul';
    protected string $class = 'menu';
    protected int $cacheTime = 3600;
    protected string $cacheKey = 'menu';
    protected array $attrs =[];
    protected string $prepend = '';
    protected string $append = '';

    public function __construct(array $options = [])
    {
        $this->getOptions($options);
        $this->tpl = __DIR__ . "/{$this->tpl}.php";
        $this->run();
    }

    public static function clearCache(): void
    {
        foreach (self::DEFAULT_CACHE_KEYS as $key) {
            cache()->remove($key);
        }
    }

    protected function run()
    {
        $menu_html = cache()->get($this->cacheKey);
        if ($menu_html) {
            echo $menu_html;
            return;
        }
        $this->data = db()->query("select * from {$this->table}")->getAssoc();
        $this->tree = $this->getTree();
        $this->menuHtml = $this->getMenuHtml($this->tree);
        $this->output();
    }

    protected function output()
    {
        $attrs = '';
        if (!empty($this->attrs)) {
            foreach ($this->attrs as $k => $v) {
                $attrs .= " $k=\"$v\" ";
            }
        }
        $menu_html = '';
        if ($this->container) {
            $menu_html .= "<{$this->container} class=\"{$this->class}\" $attrs>";
        }
        $menu_html .= $this->prepend;
        $menu_html .= $this->menuHtml;
        $menu_html .= $this->append;
        if ($this->container) {
            $menu_html .= "</{$this->container}>";
        }
        cache()->set($this->cacheKey, $menu_html, $this->cacheTime);
        echo $menu_html;
    }

    protected function getOptions($options)
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    protected function getTree(): array
    {
        $tree = [];
        $data = $this->data;

        foreach ($data as $id => &$node) {
            if (!$node['parent_id']) {
                $tree[$id] = &$node;
            } else {
                $data[$node['parent_id']]['children'][$id] = &$node;
            }
        }
        return $tree;
    }

    protected function getMenuHtml($tree, $tab = ''): string
    {
        $str = '';
        foreach ($tree as $id => $item) {
            $str .= $this->catToTemplate($item, $tab, $id);
        }
        return $str;
    }

    protected function catToTemplate($item, $tab, $id)
    {
        ob_start();
        require $this->tpl;
        return ob_get_clean();
    }

}
