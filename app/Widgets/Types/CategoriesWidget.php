<?php

namespace App\Widgets\Types;

use App\Widgets\AbstractWidget;
use App\Widgets\WidgetContext;

final class CategoriesWidget extends AbstractWidget
{
    public function defaults(): array { return ['title' => '', 'category_order' => [], 'hidden_categories' => []]; }
    public function fields(WidgetContext $context): array
    {
        $options = [];
        foreach ($context->categories() as $category) $options[$category['slug']] = $category['label'] ?? $category['name'];
        return [
            'title' => $this->titleField(),
            'category_order' => ['type' => 'categories', 'label' => 'widgets_category_order', 'options' => $options],
            'hidden_categories' => ['type' => 'categories', 'label' => 'widgets_hidden_categories', 'options' => $options, 'multiple' => true],
        ];
    }
    public function render(array $settings, WidgetContext $context): string
    {
        $all = $context->categories();
        $total = array_sum(array_column($all, 'total')) ?: (int)$context->get('total_posts', 0);
        $categories = array_values(array_filter($all, static fn($category) => !in_array($category['slug'], $settings['hidden_categories'], true)));
        $rank = array_flip($settings['category_order']);
        // Stable sorting leaves newly added/unlisted categories in the model's original order.
        usort($categories, static fn($a, $b) => ($rank[$a['slug']] ?? PHP_INT_MAX) <=> ($rank[$b['slug']] ?? PHP_INT_MAX));
        return $context->partial('categories', compact('settings', 'categories', 'total'));
    }
}
