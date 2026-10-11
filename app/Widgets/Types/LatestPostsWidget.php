<?php

namespace App\Widgets\Types;

use App\Widgets\AbstractWidget;
use App\Widgets\WidgetContext;

final class LatestPostsWidget extends AbstractWidget
{
    public function defaults(): array
    {
        return ['title' => '', 'limit' => 5, 'category' => '', 'thumbnails' => true, 'date' => true, 'order' => 'desc', 'exclude_current' => true];
    }
    public function fields(WidgetContext $context): array
    {
        $categories = ['' => return_translation('widgets_all_categories')];
        foreach ($context->categories() as $category) $categories[$category['slug']] = $category['label'] ?? $category['name'];
        return [
            'title' => $this->titleField(), 'limit' => ['type' => 'number', 'label' => 'widgets_count', 'min' => 1, 'max' => 20],
            'category' => ['type' => 'select', 'label' => 'widgets_category', 'options' => $categories],
            'thumbnails' => ['type' => 'checkbox', 'label' => 'widgets_thumbnails'], 'date' => ['type' => 'checkbox', 'label' => 'widgets_date'],
            'order' => ['type' => 'select', 'label' => 'widgets_date_order', 'options' => ['desc' => return_translation('widgets_newest'), 'asc' => return_translation('widgets_oldest')]],
            'exclude_current' => ['type' => 'checkbox', 'label' => 'widgets_exclude_current'],
        ];
    }
    public function render(array $settings, WidgetContext $context): string
    {
        $exclude = $settings['exclude_current'] && $context->page === 'posts.show' ? (string)($context->get('post', [])['slug'] ?? '') : '';
        $key = 'latest:' . hash('sha256', json_encode([$settings['limit'], $settings['category'], $settings['order'], $exclude]));
        $posts = $context->once($key, function () use ($settings, $context, $exclude) {
            $rows = $context->posts()->getWidgetLatestPosts($settings['limit'], $settings['category'], $settings['order'], $exclude);
            // Use the existing batch hook, then omit protected records. Never cache user decisions globally.
            $rows = apply_filters('public_posts_before_render', $rows, get_user() ?: []);
            if (!is_array($rows)) return [];
            return array_values(array_filter($rows, static fn($row) => is_array($row)
                && (!isset($row['subscription_access']) || !empty($row['subscription_access']['allowed']))));
        });
        return $context->partial('post-list', ['settings' => $settings, 'posts' => $posts, 'legacy' => false]);
    }
}
