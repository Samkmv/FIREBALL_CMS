<?php

namespace App\Widgets\Types;

use App\Widgets\AbstractWidget;
use App\Widgets\WidgetContext;

final class SocialWidget extends AbstractWidget
{
    public function defaults(): array { return ['title' => '']; }
    public function fields(WidgetContext $context): array { return ['title' => $this->titleField()]; }
    public function render(array $settings, WidgetContext $context): string
    {
        $links = $context->once('social.links', static fn() => site_social_links());
        return $context->partial('social', compact('settings', 'links'));
    }
}
