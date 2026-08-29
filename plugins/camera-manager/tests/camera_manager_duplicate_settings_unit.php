<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/Plugins/PluginInterface.php';
require_once dirname(__DIR__, 3) . '/app/Services/SqlFileRunner.php';

final class CameraManagerFakeDatabase
{
    public array $rows = [];
    private mixed $result = null;

    public function query(string $sql, array $params = []): self
    {
        if (str_starts_with($sql, 'SELECT COUNT(*) FROM plugin_settings')) {
            $this->result = count(array_filter(
                $this->rows,
                static fn(array $row): bool => $row['plugin_slug'] === $params[0]
                    && $row['setting_key'] === $params[1]
            ));
            return $this;
        }

        if (str_starts_with($sql, 'SELECT setting_key, setting_value, updated_at FROM plugin_settings')) {
            $rows = array_values(array_filter(
                $this->rows,
                static fn(array $row): bool => $row['plugin_slug'] === $params[0]
            ));
            usort($rows, static fn(array $left, array $right): int => $right['id'] <=> $left['id']);
            $this->result = $rows;
            return $this;
        }

        if (str_starts_with($sql, 'UPDATE plugin_settings SET setting_value')) {
            foreach ($this->rows as &$row) {
                if ($row['plugin_slug'] === $params[2] && $row['setting_key'] === $params[3]) {
                    $row['setting_value'] = $params[0];
                    $row['updated_at'] = $params[1];
                }
            }
            unset($row);
            $this->result = null;
            return $this;
        }

        if (str_starts_with($sql, 'INSERT INTO plugin_settings')) {
            $this->rows[] = [
                'id' => count($this->rows) + 1,
                'plugin_slug' => $params[0],
                'setting_key' => $params[1],
                'setting_value' => $params[2],
                'updated_at' => $params[3],
            ];
            $this->result = null;
            return $this;
        }

        throw new RuntimeException('Unexpected SQL in duplicate settings test: ' . $sql);
    }

    public function getColumn(): mixed
    {
        return $this->result;
    }

    public function get(): array
    {
        return is_array($this->result) ? $this->result : [];
    }
}

$GLOBALS['camera_manager_fake_db'] = new CameraManagerFakeDatabase();

function db(): CameraManagerFakeDatabase
{
    return $GLOBALS['camera_manager_fake_db'];
}

function plugin_setting(string $slug, string $key, mixed $default = null): mixed
{
    foreach ($GLOBALS['camera_manager_fake_db']->rows as $row) {
        if ($row['plugin_slug'] !== $slug || $row['setting_key'] !== $key) {
            continue;
        }
        $decoded = json_decode((string)$row['setting_value'], true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : (string)$row['setting_value'];
    }

    return $default;
}

function plugin_setting_set(string $slug, string $key, mixed $value): void
{
    throw new RuntimeException('Legacy setting writer must not be used when the database is available.');
}

require_once dirname(__DIR__) . '/Plugin.php';

$oldHash = hash('sha256', str_repeat('cd', 32));
$GLOBALS['camera_manager_fake_db']->rows = [
    ['id' => 1, 'plugin_slug' => 'camera-manager', 'setting_key' => 'pull_token_hash', 'setting_value' => '""', 'updated_at' => '2026-08-29 08:00:00'],
    ['id' => 2, 'plugin_slug' => 'camera-manager', 'setting_key' => 'pull_token_hash', 'setting_value' => json_encode($oldHash), 'updated_at' => '2026-08-29 09:00:00'],
];

$token = str_repeat('ab', 32);
FireballPluginCameraManager::savePullToken($token);
$expectedHash = hash('sha256', $token);
foreach ($GLOBALS['camera_manager_fake_db']->rows as $row) {
    if (json_decode((string)$row['setting_value'], true) !== $expectedHash) {
        throw new RuntimeException('Duplicate Camera Manager setting rows were not synchronized on write.');
    }
}

echo "Camera Manager duplicate settings check passed.\n";
