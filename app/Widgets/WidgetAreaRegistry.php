<?php

namespace App\Widgets;

final class WidgetAreaRegistry
{
    private array $areas = [];
    private array $required = [];

    public static function assertId(mixed $id): void
    {
        if (!is_string($id) || !preg_match('/^[a-z][a-z0-9._-]{0,99}$/D', $id)) {
            throw new \InvalidArgumentException('Invalid widget identifier.');
        }
    }

    public function register(string $id, string $label, array $pages, array $types = []): void
    {
        self::assertId($id);
        if (isset($this->areas[$id]) || $pages === []) {
            throw new \InvalidArgumentException('Duplicate or invalid widget area: ' . $id);
        }
        foreach ($pages as $page => $pageLabel) self::assertId($page);
        foreach ($types as $type) self::assertId($type);
        $this->areas[$id] = compact('id', 'label', 'pages', 'types');
    }

    /** Required instances belong to one fixed area. Never read constraints from SQL. */
    public function requireWidget(string $key, string $type, string $area, int $position, array $requiredPages = []): void
    {
        self::assertId($key);
        self::assertId($type);
        if (isset($this->required[$key]) || !isset($this->areas[$area])
            || array_diff($requiredPages, array_keys($this->areas[$area]['pages']))) {
            throw new \InvalidArgumentException('Invalid required widget registration: ' . $key);
        }
        $this->required[$key] = compact('key', 'type', 'area', 'position', 'requiredPages');
    }

    public function all(): array { return $this->areas; }
    public function get(string $id): ?array { return $this->areas[$id] ?? null; }
    public function required(): array { return $this->required; }
    public function slot(string $key): ?array { return $this->required[$key] ?? null; }
}
