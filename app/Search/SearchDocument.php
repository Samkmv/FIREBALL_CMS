<?php

namespace App\Search;

use App\Search\Contracts\SearchableEntityInterface;

/**
 * Storage-agnostic representation of one searchable CMS entity.
 */
final class SearchDocument
{
    public function __construct(
        public readonly string $type,
        public readonly int|string $entityId,
        public readonly string $title,
        public readonly string $subtitle = '',
        public readonly string $content = '',
        public readonly array $keywords = [],
        public readonly string $url = '',
        public readonly ?string $locale = null,
        public readonly string $module = 'core',
        public readonly string $icon = 'file-text',
        public readonly int $priority = 0,
        public readonly string $status = 'published',
        public readonly ?string $publishedAt = null,
        public readonly array $metadata = [],
    ) {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $this->type)) {
            throw new \InvalidArgumentException('Search document type is invalid.');
        }
        if (trim((string)$this->entityId) === '') {
            throw new \InvalidArgumentException('Search document entity ID is required.');
        }
        if (trim($this->title) === '') {
            throw new \InvalidArgumentException('Search document title is required.');
        }
    }

    public static function fromEntity(
        SearchableEntityInterface $entity,
        string $module = 'core',
        array $overrides = []
    ): self {
        return new self(
            type: (string)($overrides['type'] ?? $entity->getSearchType()),
            entityId: $overrides['entity_id'] ?? $entity->getSearchEntityId(),
            title: (string)($overrides['title'] ?? $entity->getSearchTitle()),
            subtitle: (string)($overrides['subtitle'] ?? ''),
            content: (string)($overrides['content'] ?? $entity->getSearchContent()),
            keywords: (array)($overrides['keywords'] ?? $entity->getSearchKeywords()),
            url: (string)($overrides['url'] ?? $entity->getSearchUrl()),
            locale: array_key_exists('locale', $overrides)
                ? ($overrides['locale'] !== null ? (string)$overrides['locale'] : null)
                : $entity->getSearchLocale(),
            module: (string)($overrides['module'] ?? $module),
            icon: (string)($overrides['icon'] ?? 'file-text'),
            priority: (int)($overrides['priority'] ?? 0),
            status: (string)($overrides['status'] ?? 'published'),
            publishedAt: isset($overrides['published_at']) ? (string)$overrides['published_at'] : null,
            metadata: (array)($overrides['metadata'] ?? []),
        );
    }

    public static function fromIndexRow(array $row): self
    {
        $keywords = json_decode((string)($row['keywords'] ?? '[]'), true);
        $metadata = json_decode((string)($row['metadata'] ?? '[]'), true);

        return new self(
            type: (string)$row['entity_type'],
            entityId: (string)$row['entity_id'],
            title: (string)$row['title'],
            subtitle: (string)($row['subtitle'] ?? ''),
            content: (string)($row['search_text'] ?? ''),
            keywords: is_array($keywords) ? $keywords : [],
            url: (string)$row['url'],
            locale: trim((string)($row['locale'] ?? '')) !== '' ? (string)$row['locale'] : null,
            module: (string)$row['module'],
            icon: (string)($row['icon'] ?? 'file-text'),
            priority: (int)($row['priority'] ?? 0),
            status: (string)($row['status'] ?? 'published'),
            publishedAt: !empty($row['published_at']) ? (string)$row['published_at'] : null,
            metadata: is_array($metadata) ? $metadata : [],
        );
    }
}
