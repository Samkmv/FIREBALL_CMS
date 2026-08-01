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
        $entityType = (new BlockEditorService())->normalizeEntityType($entityType);
        $table = $this->tableFor($entityType);
        if ($table === null || $entityId <= 0) {
            throw new \InvalidArgumentException('Unsupported block editor entity.');
        }

        $content = json_encode(['version' => 2, 'blocks' => array_values($blocks)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($entityType === 'page') {
            db()->query("UPDATE {$table} SET content = ?, updated_at = ? WHERE id = ?", [
                $content ?: '{"version":2,"blocks":[]}',
                date('Y-m-d H:i:s'),
                $entityId,
            ]);
            return;
        }

        db()->query("UPDATE {$table} SET content = ? WHERE id = ?", [
            $content ?: '{"version":2,"blocks":[]}',
            $entityId,
        ]);
    }

    public function decodeBlocks(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        if ($content[0] !== '{') {
            return $this->decodeHtmlSnapshot($content);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['blocks']) || !is_array($decoded['blocks'])) {
            return [];
        }

        return array_values($decoded['blocks']);
    }

    private function decodeHtmlSnapshot(string $content): array
    {
        $encoded = '';
        if (class_exists(\DOMDocument::class)) {
            $document = new \DOMDocument('1.0', 'UTF-8');
            $previousErrors = libxml_use_internal_errors(true);
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><div id="fb-editor-snapshot-root">' . $content . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);

            if ($loaded) {
                $xpath = new \DOMXPath($document);
                $nodes = $xpath->query('//template[@data-fb-editor-state]');
                if ($nodes !== false && $nodes->length > 0) {
                    $encoded = trim((string)$nodes->item(0)?->textContent);
                }
            }
        }

        if ($encoded === '' && preg_match('~<template\b[^>]*data-fb-editor-state[^>]*>([^<]+)</template>~is', $content, $matches)) {
            $encoded = trim((string)$matches[1]);
        }

        if ($encoded === '') {
            return [];
        }

        $json = base64_decode($encoded, true);
        if ($json === false) {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) && is_array($decoded['blocks'] ?? null)
            ? array_values($decoded['blocks'])
            : [];
    }

    private function tableFor(string $entityType): ?string
    {
        $entityType = (new BlockEditorService())->normalizeEntityType($entityType);
        return self::ENTITY_TABLES[$entityType] ?? null;
    }
}
