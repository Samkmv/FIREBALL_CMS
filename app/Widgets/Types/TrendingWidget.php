<?php

namespace App\Widgets\Types;

use App\Widgets\AbstractWidget;
use App\Widgets\WidgetContext;

/** Preserves the existing six date-sorted recommendations, including plugin lock badges. */
final class TrendingWidget extends AbstractWidget
{
    public function defaults(): array { return ['title' => '']; }
    public function fields(WidgetContext $context): array { return ['title' => $this->titleField()]; }
    public function render(array $settings, WidgetContext $context): string
    {
        return $context->partial('post-list', ['settings' => $settings, 'posts' => $context->get('trending_posts', []), 'legacy' => true]);
    }
}
