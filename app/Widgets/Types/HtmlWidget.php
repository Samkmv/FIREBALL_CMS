<?php

namespace App\Widgets\Types;

use App\Widgets\AbstractWidget;
use App\Widgets\WidgetContext;

final class HtmlWidget extends AbstractWidget
{
    public function defaults(): array { return ['title' => '', 'html' => '']; }
    public function fields(WidgetContext $context): array
    {
        return ['title' => $this->titleField(), 'html' => ['type' => 'html', 'label' => 'widgets_html_content', 'max' => 50000]];
    }
    public function validate(array $settings, WidgetContext $context): array
    {
        $settings = parent::validate($settings, $context);
        // Same policy as CMS post/page content, tightened to passive markup for widgets.
        $settings['html'] = \App\Widgets\WidgetHtml::sanitize($settings['html']);
        return $settings;
    }
    public function render(array $settings, WidgetContext $context): string { return $context->partial('html', compact('settings')); }
}
