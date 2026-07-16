<?php

namespace App\Search;

use Throwable;

/**
 * Persists normalized documents and a prefix-friendly token index.
 */
final class SearchIndexer
{
    private const PROVIDER_REFRESH_SECONDS = 300;

    private bool $schemaReady = false;

    public function __construct(private readonly SearchRegistry $registry)
    {
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS search_index (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                provider VARCHAR(120) NOT NULL,
                provider_owner VARCHAR(120) NULL,
                entity_type VARCHAR(100) NOT NULL,
                entity_id VARCHAR(190) NOT NULL,
                module VARCHAR(120) NOT NULL,
                locale VARCHAR(20) NOT NULL DEFAULT '',
                title VARCHAR(500) NOT NULL,
                subtitle TEXT NULL,
                search_text MEDIUMTEXT NULL,
                normalized_title VARCHAR(500) NOT NULL,
                normalized_text MEDIUMTEXT NULL,
                keywords TEXT NULL,
                normalized_keywords TEXT NULL,
                url VARCHAR(1000) NOT NULL,
                icon VARCHAR(100) NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'published',
                priority INT NOT NULL DEFAULT 0,
                published_at DATETIME NULL,
                metadata MEDIUMTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY provider_entity_locale (provider, entity_type, entity_id, locale),
                KEY entity_type (entity_type),
                KEY entity_id (entity_id),
                KEY module (module),
                KEY provider (provider),
                KEY provider_owner (provider_owner),
                KEY locale (locale),
                KEY status (status),
                KEY normalized_title (normalized_title(191)),
                KEY published_at (published_at),
                KEY public_lookup (status, locale, entity_type, published_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->query(
            "CREATE TABLE IF NOT EXISTS search_index_tokens (
                search_index_id BIGINT UNSIGNED NOT NULL,
                token VARCHAR(191) NOT NULL,
                field_name VARCHAR(20) NOT NULL,
                PRIMARY KEY (search_index_id, field_name, token),
                KEY token_lookup (token, search_index_id),
                KEY field_token_lookup (field_name, token, search_index_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->query(
            "CREATE TABLE IF NOT EXISTS search_index_state (
                provider VARCHAR(120) NOT NULL,
                indexed_at DATETIME NOT NULL,
                document_count INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (provider)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->schemaReady = true;
    }

    public function save(string $providerName, SearchDocument $document): int
    {
        $this->ensureSchema();
        if (!$this->registry->has($providerName)) {
            throw new \InvalidArgumentException('Unknown search provider: ' . $providerName);
        }

        $now = date('Y-m-d H:i:s');
        $locale = trim((string)$document->locale);
        $keywords = array_values(array_unique(array_filter(array_map(
            static fn(mixed $keyword): string => trim((string)$keyword),
            $document->keywords
        ))));
        $plainContent = SearchText::plainText($document->content);
        $normalizedTitle = SearchNormalizer::normalize($document->title);
        $normalizedKeywords = SearchNormalizer::normalize(implode(' ', $keywords));
        $normalizedText = SearchNormalizer::normalize(implode(' ', array_filter([
            $document->subtitle,
            $plainContent,
        ])));
        $encodedKeywords = json_encode($keywords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encodedMetadata = json_encode($document->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        db()->query(
            "INSERT INTO search_index
             (provider, provider_owner, entity_type, entity_id, module, locale, title, subtitle,
              search_text, normalized_title, normalized_text, keywords, normalized_keywords,
              url, icon, status, priority, published_at, metadata, created_at, updated_at)
             VALUES
             (:provider, :provider_owner, :entity_type, :entity_id, :module, :locale, :title, :subtitle,
              :search_text, :normalized_title, :normalized_text, :keywords, :normalized_keywords,
              :url, :icon, :status, :priority, :published_at, :metadata, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                provider_owner = VALUES(provider_owner), module = VALUES(module), title = VALUES(title),
                subtitle = VALUES(subtitle), search_text = VALUES(search_text),
                normalized_title = VALUES(normalized_title), normalized_text = VALUES(normalized_text),
                keywords = VALUES(keywords), normalized_keywords = VALUES(normalized_keywords),
                url = VALUES(url), icon = VALUES(icon), status = VALUES(status),
                priority = VALUES(priority), published_at = VALUES(published_at),
                metadata = VALUES(metadata), updated_at = VALUES(updated_at)",
            [
                'provider' => $providerName,
                'provider_owner' => $this->registry->owner($providerName),
                'entity_type' => $document->type,
                'entity_id' => (string)$document->entityId,
                'module' => $document->module,
                'locale' => $locale,
                'title' => trim($document->title),
                'subtitle' => trim($document->subtitle),
                'search_text' => $plainContent,
                'normalized_title' => $normalizedTitle,
                'normalized_text' => $normalizedText,
                'keywords' => $encodedKeywords !== false ? $encodedKeywords : '[]',
                'normalized_keywords' => $normalizedKeywords,
                'url' => $document->url,
                'icon' => $document->icon,
                'status' => $document->status,
                'priority' => $document->priority,
                'published_at' => $this->validDateTime($document->publishedAt),
                'metadata' => $encodedMetadata !== false ? $encodedMetadata : '[]',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $id = (int)db()->query(
            'SELECT id FROM search_index
             WHERE provider = ? AND entity_type = ? AND entity_id = ? AND locale = ? LIMIT 1',
            [$providerName, $document->type, (string)$document->entityId, $locale]
        )->getColumn();

        $this->replaceTokens($id, [
            'title' => $normalizedTitle,
            'keywords' => $normalizedKeywords,
            'content' => $normalizedText,
        ]);

        return $id;
    }

    public function sync(string $providerName, int|string $entityId): void
    {
        $this->ensureSchema();
        $provider = $this->registry->provider($providerName);
        $document = $provider->getDocument($entityId);
        $this->removeEntity($providerName, $entityId);
        if ($document !== null) {
            $this->save($providerName, $document);
        }
    }

    public function removeEntity(string $providerName, int|string $entityId): void
    {
        $this->ensureSchema();
        $ids = db()->query(
            'SELECT id FROM search_index WHERE provider = ? AND entity_id = ?',
            [$providerName, (string)$entityId]
        )->get() ?: [];
        $this->removeRows(array_map(static fn(array $row): int => (int)$row['id'], $ids));
    }

    public function removeProvider(string $providerName): void
    {
        $this->ensureSchema();
        $ids = db()->query('SELECT id FROM search_index WHERE provider = ?', [$providerName])->get() ?: [];
        $this->removeRows(array_map(static fn(array $row): int => (int)$row['id'], $ids));
        db()->query('DELETE FROM search_index_state WHERE provider = ?', [$providerName]);
    }

    public function removeOwner(string $owner): void
    {
        $this->ensureSchema();
        $ids = db()->query('SELECT id FROM search_index WHERE provider_owner = ?', [$owner])->get() ?: [];
        $this->removeRows(array_map(static fn(array $row): int => (int)$row['id'], $ids));
        foreach ($this->registry->namesByOwner($owner) as $providerName) {
            db()->query('DELETE FROM search_index_state WHERE provider = ?', [$providerName]);
        }
    }

    public function reindexProvider(string $providerName): int
    {
        $this->ensureSchema();
        $provider = $this->registry->provider($providerName);

        db()->beginTransaction();
        try {
            $this->removeProvider($providerName);
            $count = 0;
            foreach ($provider->getDocuments() as $document) {
                if (!$document instanceof SearchDocument) {
                    throw new \RuntimeException('Search providers must yield SearchDocument instances.');
                }
                $this->save($providerName, $document);
                $count++;
            }
            db()->query(
                'INSERT INTO search_index_state (provider, indexed_at, document_count)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE indexed_at = VALUES(indexed_at), document_count = VALUES(document_count)',
                [$providerName, date('Y-m-d H:i:s'), $count]
            );
            if (db()->inTransaction()) {
                db()->commit();
            }

            return $count;
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $exception;
        }
    }

    public function reindexAll(): array
    {
        $counts = [];
        foreach ($this->registry->names() as $providerName) {
            $counts[$providerName] = $this->reindexProvider($providerName);
        }

        return $counts;
    }

    public function ensureProvidersIndexed(): void
    {
        $this->ensureSchema();
        foreach ($this->registry->names() as $providerName) {
            $indexedAt = (string)db()->query(
                'SELECT indexed_at FROM search_index_state WHERE provider = ? LIMIT 1',
                [$providerName]
            )->getColumn();
            $indexedTimestamp = $indexedAt !== '' ? strtotime($indexedAt) : false;
            if (
                $indexedTimestamp === false
                || $indexedTimestamp < time() - self::PROVIDER_REFRESH_SECONDS
            ) {
                $this->reindexProvider($providerName);
            }
        }
    }

    private function replaceTokens(int $searchIndexId, array $fields): void
    {
        db()->query('DELETE FROM search_index_tokens WHERE search_index_id = ?', [$searchIndexId]);
        foreach ($fields as $fieldName => $value) {
            foreach (SearchNormalizer::tokens((string)$value, 1) as $token) {
                db()->query(
                    'INSERT IGNORE INTO search_index_tokens (search_index_id, token, field_name)
                     VALUES (?, ?, ?)',
                    [$searchIndexId, mb_substr($token, 0, 191, 'UTF-8'), $fieldName]
                );
            }
        }
    }

    private function removeRows(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        db()->query("DELETE FROM search_index_tokens WHERE search_index_id IN ({$placeholders})", $ids);
        db()->query("DELETE FROM search_index WHERE id IN ({$placeholders})", $ids);
    }

    private function validDateTime(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        return $value;
    }
}
