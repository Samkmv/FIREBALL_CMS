<?php

namespace App\Widgets;

final class WidgetManager
{
    public readonly WidgetRegistry $types;
    public readonly WidgetAreaRegistry $areas;
    public readonly WidgetInstanceRepository $instances;
    public readonly WidgetRenderer $renderer;

    public function __construct()
    {
        $this->types = new WidgetRegistry();
        $this->areas = new WidgetAreaRegistry();
        $this->areas->register('posts.sidebar', 'widgets_posts_sidebar', ['posts.index' => 'widgets_page_index', 'posts.show' => 'widgets_page_show']);
        foreach ([
            'posts.categories' => [new Types\CategoriesWidget(), 'widgets_categories', true],
            'posts.trending' => [new Types\TrendingWidget(), 'widgets_trending', true],
            'posts.social' => [new Types\SocialWidget(), 'widgets_social', true],
            'posts.latest' => [new Types\LatestPostsWidget(), 'widgets_latest', false],
            'weather' => [new Types\WeatherWidget(), 'widgets_weather', false],
            'html' => [new Types\HtmlWidget(), 'widgets_html', false],
        ] as $id => [$widget, $label, $system]) {
            $this->types->register($id, $widget, ['label' => $label, 'system' => $system, 'areas' => $system || $id === 'posts.latest' ? ['posts.sidebar'] : ['*']]);
        }
        $this->areas->requireWidget('posts.categories', 'posts.categories', 'posts.sidebar', 0, ['posts.index', 'posts.show']);
        $this->areas->requireWidget('posts.trending', 'posts.trending', 'posts.sidebar', 1);
        $this->areas->requireWidget('posts.social', 'posts.social', 'posts.sidebar', 2);
        // Active plugins boot before controllers. Failure of one callback cannot break public pages.
        app()->hooks->doActionSafely('fireball_widgets_register', $this->types, $this->areas);
        $this->instances = new WidgetInstanceRepository($this->types, $this->areas);
        $this->renderer = new WidgetRenderer($this->types, $this->areas, $this->instances);
    }
}
