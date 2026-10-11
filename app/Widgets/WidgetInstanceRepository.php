<?php

namespace App\Widgets;

/** One configuration query per request; all mutations serialize on the revision row. */
final class WidgetInstanceRepository
{
    private ?array $snapshot = null;

    public function __construct(private WidgetRegistry $types, private WidgetAreaRegistry $areas) {}

    public function snapshot(): array
    {
        if ($this->snapshot !== null) return $this->snapshot;
        $rows = db()->query('SELECT w.*, s.revision AS state_revision FROM widget_state s
            LEFT JOIN widget_instances w ON 1 = 1 WHERE s.id = 1 ORDER BY w.position, w.id')->get() ?: [];
        if (!$rows) throw new \RuntimeException('Widgets migration has not been applied.');
        $revision = (int)$rows[0]['state_revision'];
        $instances = [];
        foreach ($rows as $row) {
            if ($row['id'] !== null) $instances[] = $this->normalize($row);
        }
        return $this->snapshot = ['revision' => $revision, 'instances' => $this->withRequired($instances)];
    }

    /** Called by the editor, and safe to repeat. Public rendering never performs SQL writes. */
    public function initialize(): void
    {
        db()->beginTransaction();
        try {
            db()->query('SELECT revision FROM widget_state WHERE id = 1 FOR UPDATE');
            $rows = db()->query('SELECT system_key FROM widget_instances WHERE system_key IS NOT NULL')->get() ?: [];
            $keys = array_column($rows, 'system_key');
            $changed = false;
            foreach ($this->areas->required() as $slot) {
                if (in_array($slot['key'], $keys, true)) continue;
                $type = $this->types->get($slot['type']);
                if ($type === null || !$this->types->allows($slot['type'], $this->areas->get($slot['area']))) continue;
                db()->query('INSERT IGNORE INTO widget_instances (type, area, position, settings, enabled, visibility, system_key)
                    VALUES (?, ?, ?, ?, 1, ?, ?)', [
                    $slot['type'], $slot['area'], $slot['position'], $this->json($type['widget']->defaults()),
                    $this->json(array_fill_keys(array_keys($this->areas->get($slot['area'])['pages']), true)), $slot['key'],
                ]);
                $changed = true;
            }
            if ($changed) db()->query('UPDATE widget_state SET revision = revision + 1 WHERE id = 1');
            db()->commit();
            $this->snapshot = null;
        } catch (\Throwable $exception) {
            if (db()->inTransaction()) db()->rollBack();
            throw $exception;
        }
    }

    public function fallback(): array { return $this->withRequired([]); }

    private function normalize(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['position'] = (int)$row['position'];
        $row['enabled'] = (bool)$row['enabled'];
        foreach (['settings', 'visibility'] as $key) {
            $decoded = json_decode((string)$row[$key], true);
            $row[$key] = is_array($decoded) ? $decoded : [];
        }
        unset($row['state_revision']);
        return $row;
    }

    private function withRequired(array $instances): array
    {
        $found = [];
        foreach ($instances as &$row) {
            $slot = $this->areas->slot((string)($row['system_key'] ?? ''));
            if ($slot !== null) {
                $found[$slot['key']] = true;
                $row['type'] = $slot['type'];
                $row['area'] = $slot['area'];
                $row['enabled'] = true;
                foreach ($slot['requiredPages'] as $page) $row['visibility'][$page] = true;
            }
        }
        unset($row);
        foreach ($this->areas->required() as $slot) {
            if (isset($found[$slot['key']])) continue;
            $type = $this->types->get($slot['type']);
            if ($type === null || !$this->types->allows($slot['type'], $this->areas->get($slot['area']))) continue;
            $instances[] = [
                'id' => 0, 'type' => $slot['type'], 'area' => $slot['area'], 'position' => $slot['position'],
                'settings' => $type['widget']->defaults(), 'enabled' => true,
                'visibility' => array_fill_keys(array_keys($this->areas->get($slot['area'])['pages']), true),
                'system_key' => $slot['key'],
            ];
        }
        usort($instances, static fn($a, $b) => [$a['position'], $a['id']] <=> [$b['position'], $b['id']]);
        return $instances;
    }

    public function add(string $typeId, string $areaId, int $revision, WidgetContext $context): int
    {
        $type = $this->types->get($typeId);
        $area = $this->areas->get($areaId);
        if ($type === null || $type['system'] || $area === null || !$this->types->allows($typeId, $area)) $this->invalid();
        $settings = $type['widget']->validate($type['widget']->defaults(), $context);
        return $this->mutate($revision, function (array $rows) use ($typeId, $areaId, $area, $settings): int {
            if (count($rows) >= 100) $this->invalid();
            $position = 0;
            foreach ($rows as $row) if ($row['area'] === $areaId) $position = max($position, $row['position'] + 1);
            db()->query('INSERT INTO widget_instances (type, area, position, settings, enabled, visibility) VALUES (?, ?, ?, ?, 1, ?)',
                [$typeId, $areaId, $position, $this->json($settings), $this->json(array_fill_keys(array_keys($area['pages']), true))]);
            return (int)db()->getInsertId();
        });
    }

    public function update(int $id, array $settings, mixed $enabled, array $visibility, int $revision, WidgetContext $context): void
    {
        $this->mutate($revision, function (array $rows) use ($id, $settings, $enabled, $visibility, $context): void {
            $row = $this->find($rows, $id);
            $type = $this->types->get($row['type']);
            $area = $this->areas->get($row['area']);
            if ($type === null || $area === null || !$this->types->allows($row['type'], $area)
                || !is_bool($enabled) || array_diff(array_keys($visibility), array_keys($area['pages']))) $this->invalid();
            $pages = [];
            foreach ($area['pages'] as $page => $label) {
                if (!isset($visibility[$page]) || !is_bool($visibility[$page])) $this->invalid();
                $pages[$page] = $visibility[$page];
            }
            $slot = $this->areas->slot((string)($row['system_key'] ?? ''));
            if ($this->isSystem($row) && !$enabled) $this->invalid();
            foreach ($slot['requiredPages'] ?? [] as $page) if (!$pages[$page]) $this->invalid();
            $normalized = $type['widget']->validate($settings, $context);
            db()->query('UPDATE widget_instances SET settings = ?, enabled = ?, visibility = ? WHERE id = ?',
                [$this->json($normalized), (int)$enabled, $this->json($pages), $id]);
        });
    }

    public function delete(int $id, int $revision): void
    {
        $this->mutate($revision, function (array $rows) use ($id): void {
            if ($this->isSystem($this->find($rows, $id))) $this->invalid();
            db()->query('DELETE FROM widget_instances WHERE id = ?', [$id]);
        });
    }

    /** Full layout must contain every stored ID exactly once. */
    public function reorder(array $layout, int $revision): void
    {
        $this->mutate($revision, function (array $rows) use ($layout): void {
            if (count($layout) !== count($rows)) $this->invalid();
            $seen = [];
            $positions = [];
            $validated = [];
            foreach ($layout as $entry) {
                if (!is_array($entry) || !is_int($entry['id'] ?? null) || !is_string($entry['area'] ?? null)) $this->invalid();
                $row = $this->find($rows, $entry['id']);
                $area = $this->areas->get($entry['area']);
                $slot = $this->areas->slot((string)($row['system_key'] ?? ''));
                $registered = $this->types->get($row['type']) !== null && $this->areas->get($row['area']) !== null;
                if (isset($seen[$row['id']]) || ($registered && ($area === null || !$this->types->allows($row['type'], $area)))
                    || (!$registered && $entry['area'] !== $row['area'])
                    || ($this->isSystem($row) && (($slot !== null && $slot['area'] !== $entry['area']) || ($slot === null && $entry['area'] !== $row['area'])))) $this->invalid();
                $seen[$row['id']] = true;
                $position = $positions[$entry['area']] ?? 0;
                $positions[$entry['area']] = $position + 1;
                // Carry known visibility, use true for newly introduced pages.
                $visibility = [];
                foreach ($area['pages'] ?? array_fill_keys(array_keys($row['visibility']), '') as $page => $label) $visibility[$page] = $row['visibility'][$page] ?? true;
                $validated[] = [$entry['area'], $position, $this->json($visibility), $row['id']];
            }
            foreach ($validated as $params) db()->query('UPDATE widget_instances SET area = ?, position = ?, visibility = ? WHERE id = ?', $params);
        });
    }

    public function isSystem(array $row): bool
    {
        return !empty($row['system_key']) || !empty($this->types->get($row['type'])['system']);
    }

    private function find(array $rows, int $id): array
    {
        foreach ($rows as $row) if ($id > 0 && $row['id'] === $id) return $row;
        $this->invalid();
    }

    private function mutate(int $revision, callable $callback): mixed
    {
        db()->beginTransaction();
        try {
            $current = (int)db()->query('SELECT revision FROM widget_state WHERE id = 1 FOR UPDATE')->getColumn();
            if ($revision < 1 || $current !== $revision) throw new WidgetConflictException(return_translation('widgets_conflict'));
            $this->snapshot = null;
            $rows = $this->snapshot()['instances'];
            $result = $callback($rows);
            db()->query('UPDATE widget_state SET revision = revision + 1 WHERE id = 1');
            db()->commit();
            $this->snapshot = null;
            return $result;
        } catch (\Throwable $exception) {
            if (db()->inTransaction()) db()->rollBack();
            $this->snapshot = null;
            throw $exception;
        }
    }

    private function json(array $value): string { return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); }
    private function invalid(): never { throw new \InvalidArgumentException(return_translation('widgets_invalid_request')); }
}
