<?php

namespace Fireball\Subscriptions\Search;

use App\Search\AbstractSearchProvider;
use App\Search\SearchDocument;
use App\Search\SearchText;
use Fireball\Subscriptions\Repositories\BusinessRepository;

final class BusinessSearchProvider extends AbstractSearchProvider
{
    public const NAME = 'subscriptions.businesses';

    public function getDocuments(): iterable
    {
        if (!\FireballPluginSubscriptions::businessPublicEnabled()) { return; }
        $lastId = 0;
        do {
            $rows = db()->query('SELECT id,slug,name,address,description,created_at,updated_at
                FROM subscription_business_pages WHERE is_published=1 AND id>? ORDER BY id LIMIT 200', [$lastId])->get() ?: [];
            foreach ($rows as $row) {
                $lastId = (int)$row['id'];
                yield $this->document($row);
            }
        } while (count($rows) === 200);
    }

    public function getDocument(int|string $entityId): ?SearchDocument
    {
        if (!\FireballPluginSubscriptions::businessPublicEnabled()) { return null; }
        $page = (new BusinessRepository())->find((int)$entityId, true);
        return $page ? $this->document($page) : null;
    }

    public function canAccess(SearchDocument $document, array $context = []): bool
    {
        // Recheck publication even if a previous index update failed.
        return parent::canAccess($document) && \FireballPluginSubscriptions::businessPublicEnabled()
            && (new BusinessRepository())->find((int)$document->entityId, true) !== null;
    }

    private function document(array $page): SearchDocument
    {
        return new SearchDocument(
            type: 'business', entityId: (int)$page['id'], title: (string)$page['name'],
            subtitle: (string)$page['address'], content: SearchText::plainText((string)$page['description']),
            keywords: [(string)$page['address'], (string)($page['slug'] ?? '')],
            url: base_href(BusinessRepository::publicPath($page)), module: 'subscriptions', icon: 'briefcase',
            publishedAt: $page['created_at'] ?? null,
            metadata: ['type_label'=>\FireballPluginSubscriptions::t('business_search_type'), 'updated_at'=>$page['updated_at'] ?? null],
        );
    }
}
