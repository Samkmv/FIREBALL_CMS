<?php

namespace App\Search;

/**
 * Executes provider-independent token lookup, access checks and relevance sorting.
 */
final class SearchEngine
{
    public function __construct(
        private readonly SearchRegistry $registry,
        private readonly SearchIndexer $indexer,
        private readonly SearchConfig $config,
        private readonly SearchScorer $scorer = new SearchScorer(),
    ) {
    }

    public function search(string $query, int $limit = 20, int $offset = 0): array
    {
        $query = mb_substr(trim($query), 0, $this->config->maximumQueryLength(), 'UTF-8');
        $normalizedQuery = SearchNormalizer::normalize($query);
        if (mb_strlen($normalizedQuery, 'UTF-8') < $this->config->minimumQueryLength()) {
            return $this->emptyResult($query, $normalizedQuery);
        }

        $tokens = array_slice(
            SearchNormalizer::tokens($normalizedQuery, $this->config->minimumTokenLength()),
            0,
            $this->config->maximumQueryTokens()
        );
        if ($tokens === []) {
            return $this->emptyResult($query, $normalizedQuery);
        }

        $this->indexer->ensureProvidersIndexed();
        $candidateIds = $this->candidateIds($tokens);
        if ($candidateIds === []) {
            return $this->emptyResult($query, $normalizedQuery, $tokens);
        }

        $rows = $this->loadCandidates($candidateIds);
        $ranked = [];
        $context = [
            'user' => function_exists('current_user') ? current_user() : null,
            'locale' => function_exists('current_locale') ? current_locale() : null,
        ];

        foreach ($rows as $row) {
            $providerName = (string)$row['provider'];
            $document = SearchDocument::fromIndexRow($row);
            if (!$this->registry->provider($providerName)->canAccess($document, $context)) {
                continue;
            }

            $score = $this->scorer->score(
                $row,
                $query,
                $normalizedQuery,
                $tokens,
                $this->config->partialMatching(),
                $this->config->minimumTokenLength(),
                $this->config->weights()
            );
            if ($score <= 0) {
                continue;
            }

            $ranked[] = ['row' => $row, 'document' => $document, 'score' => $score];
        }

        usort($ranked, static function (array $left, array $right): int {
            return [$right['score'], $right['document']->priority, (string)($right['document']->publishedAt ?? ''), (int)$right['row']['id']]
                <=> [$left['score'], $left['document']->priority, (string)($left['document']->publishedAt ?? ''), (int)$left['row']['id']];
        });

        $maximum = $this->config->maximumResults();
        $ranked = array_slice($ranked, 0, $maximum);
        $total = count($ranked);
        $counts = array_count_values(array_map(
            static fn(array $item): string => $item['document']->type,
            $ranked
        ));
        $limit = max(1, min($maximum, $limit));
        $offset = max(0, $offset);
        $items = array_map(
            fn(array $item): array => $this->formatResult($item['document'], $item['score'], $tokens),
            array_slice($ranked, $offset, $limit)
        );

        return [
            'query' => $query,
            'normalized_query' => $normalizedQuery,
            'tokens' => $tokens,
            'items' => $items,
            'counts' => $counts,
            'total' => $total,
        ];
    }

    private function candidateIds(array $tokens): array
    {
        $sets = [];
        $candidateLimit = max(500, $this->config->maximumResults() * 50);
        foreach ($tokens as $token) {
            $allowPrefix = $this->config->partialMatching()
                && mb_strlen($token, 'UTF-8') >= $this->config->minimumTokenLength();
            $prefixes = $allowPrefix
                ? SearchNormalizer::matchingPrefixes($token, $this->config->minimumTokenLength())
                : [$token];
            $conditions = [];
            $params = [];
            foreach ($prefixes as $prefix) {
                $conditions[] = $allowPrefix ? 'token LIKE ?' : 'token = ?';
                $params[] = $allowPrefix ? $prefix . '%' : $prefix;
            }
            $rows = db()->query(
                "SELECT DISTINCT search_index_id
                 FROM search_index_tokens
                 WHERE (" . implode(' OR ', $conditions) . ")
                 LIMIT {$candidateLimit}",
                $params
            )->get() ?: [];
            $ids = array_map(static fn(array $row): int => (int)$row['search_index_id'], $rows);
            if ($ids === []) {
                return [];
            }
            $sets[] = $ids;
        }

        $ids = array_shift($sets) ?: [];
        foreach ($sets as $set) {
            $ids = array_values(array_intersect($ids, $set));
            if ($ids === []) {
                break;
            }
        }

        return array_values(array_unique($ids));
    }

    private function loadCandidates(array $ids): array
    {
        $providers = $this->registry->names();
        if ($ids === [] || $providers === []) {
            return [];
        }

        $where = [
            'id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ')',
            "status = 'published'",
            '(published_at IS NULL OR published_at <= ?)',
            'provider IN (' . implode(', ', array_fill(0, count($providers), '?')) . ')',
        ];
        $params = array_merge($ids, [date('Y-m-d H:i:s')], $providers);

        if ($this->config->currentLocaleOnly() && function_exists('current_locale')) {
            $where[] = "(locale = '' OR locale = ?)";
            $params[] = current_locale();
        }

        $types = $this->config->enabledTypes();
        if ($types !== []) {
            $where[] = 'entity_type IN (' . implode(', ', array_fill(0, count($types), '?')) . ')';
            array_push($params, ...$types);
        }

        return db()->query(
            'SELECT * FROM search_index WHERE ' . implode(' AND ', $where),
            $params
        )->get() ?: [];
    }

    private function formatResult(SearchDocument $document, int $score, array $tokens): array
    {
        $excerpt = SearchText::excerpt($document->content !== '' ? $document->content : $document->subtitle);
        $entityId = (string)$document->entityId;

        return [
            'type' => $document->type,
            'id' => ctype_digit($entityId) ? (int)$entityId : $entityId,
            'title' => $document->title,
            'subtitle' => $document->subtitle,
            'excerpt' => $excerpt,
            'url' => $document->url,
            'module' => $document->module,
            'icon' => $document->icon,
            'score' => $score,
            'highlighted_title' => SearchHighlighter::highlight($document->title, $tokens),
            'highlighted_excerpt' => SearchHighlighter::highlight($excerpt, $tokens),
            'metadata' => $document->metadata,
        ];
    }

    private function emptyResult(string $query, string $normalizedQuery, array $tokens = []): array
    {
        return [
            'query' => $query,
            'normalized_query' => $normalizedQuery,
            'tokens' => $tokens,
            'items' => [],
            'counts' => [],
            'total' => 0,
        ];
    }
}
