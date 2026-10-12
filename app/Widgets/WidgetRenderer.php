<?php

namespace App\Widgets;

final class WidgetRenderer
{
    public function __construct(private WidgetRegistry $types, private WidgetAreaRegistry $areas, private WidgetInstanceRepository $instances) {}

    public function render(string $areaId, WidgetContext $context): string
    {
        $area = $this->areas->get($areaId);
        if ($area === null || !array_key_exists($context->page, $area['pages'])) return '';
        try {
            $rows = $this->instances->snapshot()['instances'];
        } catch (\Throwable $exception) {
            log_error_details('Widget configuration unavailable; using required defaults', [], $exception);
            $rows = $this->instances->fallback();
        }
        $html = '';
        foreach ($rows as $row) {
            if ($row['area'] !== $areaId || !$row['enabled'] || empty($row['visibility'][$context->page])
                || !$this->types->allows($row['type'], $area)) continue;
            $widget = $this->types->get($row['type'])['widget'];
            $widgetContext = $context->forInstance($row);
            $level = ob_get_level();
            try {
                ob_start();
                try {
                    $settings = $widget->validate($row['settings'], $widgetContext);
                } catch (\InvalidArgumentException $exception) {
                    if (!$this->instances->isSystem($row)) throw $exception;
                    $settings = $widget->defaults();
                }
                $key = 'render:' . $row['id'] . ':' . $row['type'] . ':' . $context->presentation . ':' . hash('sha256', json_encode($settings));
                $output = $context->once($key, fn() => $widget->render($settings, $widgetContext));
                $filtered = apply_filters('fireball_widget_html', $output, $row, $context);
                if (is_string($filtered)) $html .= $filtered;
            } catch (\Throwable $exception) {
                log_error_details('Widget rendering failed', ['Type' => $row['type'], 'Instance' => $row['id']], $exception);
            } finally {
                while (ob_get_level() > $level) ob_end_clean();
            }
        }
        return $html;
    }
}
