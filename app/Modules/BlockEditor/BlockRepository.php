<?php

namespace App\Modules\BlockEditor;

final class BlockRepository
{
    private const ENTITY_TABLES = [
        'post' => 'posts',
        'page' => 'pages',
    ];

    public function findEntity(string $entityType, int $entityId): array|false
    {
        $table = $this->tableFor($entityType);
        if ($table === null || $entityId <= 0) {
            return false;
        }

        return db()->query("SELECT id, content FROM {$table} WHERE id = ? LIMIT 1", [$entityId])->getOne();
    }

    public function getBlocks(string $entityType, int $entityId): array
    {
        $entity = $this->findEntity($entityType, $entityId);
        if (!$entity) {
            return [];
        }

        return $this->decodeBlocks((string)($entity['content'] ?? ''));
    }

    public function saveBlocks(string $entityType, int $entityId, array $blocks): void
    {
        $table = $this->tableFor($entityType);
        if ($table === null || $entityId <= 0) {
            throw new \InvalidArgumentException('Unsupported block editor entity.');
        }

        $content = json_encode(['version' => 1, 'blocks' => array_values($blocks)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        db()->query("UPDATE {$table} SET content = ?, updated_at = ? WHERE id = ?", [
            $content ?: '{"version":1,"blocks":[]}',
            date('Y-m-d H:i:s'),
            $entityId,
        ]);
    }

    public function decodeBlocks(string $content): array
    {
        $content = trim($content);
        if ($content === '' || $content[0] !== '{') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['blocks']) || !is_array($decoded['blocks'])) {
            return [];
        }

        return array_values($decoded['blocks']);
    }

    private function tableFor(string $entityType): ?string
    {
        $entityType = (new BlockEditorService())->normalizeEntityType($entityType);
        return self::ENTITY_TABLES[$entityType] ?? null;
    }
}
