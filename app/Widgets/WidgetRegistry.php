<?php

namespace App\Widgets;

final class WidgetRegistry
{
    private array $types = [];

    public function register(string $id, WidgetInterface $widget, array $options): void
    {
        WidgetAreaRegistry::assertId($id);
        if (isset($this->types[$id])) {
            throw new \InvalidArgumentException('Duplicate widget type: ' . $id);
        }
        $areas = $options['areas'] ?? [];
        if (!is_array($areas) || $areas === []) {
            throw new \InvalidArgumentException('Widget must declare allowed areas.');
        }
        foreach ($areas as $area) {
            if ($area !== '*') WidgetAreaRegistry::assertId($area);
        }
        $this->types[$id] = [
            'id' => $id, 'widget' => $widget,
            'label' => (string)($options['label'] ?? $id),
            'areas' => array_values($areas), 'system' => (bool)($options['system'] ?? false),
        ];
    }

    public function all(): array { return $this->types; }
    public function get(string $id): ?array { return $this->types[$id] ?? null; }

    public function allows(string $id, array $area): bool
    {
        $type = $this->get($id);
        return $type !== null
            && (in_array('*', $type['areas'], true) || in_array($area['id'], $type['areas'], true))
            && ($area['types'] === [] || in_array($id, $area['types'], true));
    }
}
