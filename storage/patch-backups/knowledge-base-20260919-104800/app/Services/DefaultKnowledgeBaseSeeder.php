<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Support\DefaultKnowledgeBase;

/** Synchronizes public help articles while preserving site-owner content. */
final class DefaultKnowledgeBaseSeeder
{
    private const MARKER_KEY = 'support_kb_catalog_version';

    public function seed(): array
    {
        (new SiteSetting())->ensureTableExists();
        $currentSignature = (string)(db()->query(
            'SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1',
            [self::MARKER_KEY]
        )->getColumn() ?: '');
        $catalogSignature = DefaultKnowledgeBase::signature();

        if ($currentSignature === $catalogSignature) {
            return ['categories' => 0, 'articles' => 0, 'version' => DefaultKnowledgeBase::VERSION];
        }

        $database = db();
        $ownsTransaction = !$database->inTransaction();
        $insertedCategories = 0;
        $insertedArticles = 0;

        try {
            if ($ownsTransaction) {
                $database->beginTransaction();
            }

            $categoryIds = [];
            $now = date('Y-m-d H:i:s');
            $this->removeLegacyCatalog($database);

            foreach (DefaultKnowledgeBase::categories() as $category) {
                $slug = (string)$category['slug'];
                $existingId = (int)($database->query(
                    'SELECT id FROM support_kb_categories WHERE slug = ? LIMIT 1',
                    [$slug]
                )->getColumn() ?: 0);
                if ($existingId <= 0) {
                    $database->query(
                        'INSERT INTO support_kb_categories (name, slug, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                        [(string)$category['name'], $slug, (int)$category['sort_order'], $now, $now]
                    );
                    $existingId = (int)$database->getInsertId();
                    $insertedCategories++;
                }
                $categoryIds[$slug] = $existingId;
            }

            foreach (DefaultKnowledgeBase::articles() as $article) {
                $categoryId = (int)($categoryIds[(string)$article['category_slug']] ?? 0);
                if ($categoryId <= 0) {
                    continue;
                }
                $slug = (string)$article['slug'];
                $exists = (int)($database->query(
                    'SELECT COUNT(*) FROM support_kb_articles WHERE slug = ?',
                    [$slug]
                )->getColumn() ?: 0) > 0;
                if ($exists) {
                    continue;
                }

                $database->query(
                    'INSERT INTO support_kb_articles (title, slug, excerpt, content, category_id, is_published, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
                    [
                        (string)$article['title'],
                        $slug,
                        (string)$article['excerpt'],
                        (string)$article['content'],
                        $categoryId,
                        $now,
                        $now,
                    ]
                );
                $insertedArticles++;
            }

            $database->query(
                'INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
                [self::MARKER_KEY, $catalogSignature, $now]
            );

            if ($ownsTransaction) {
                $database->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }
            if (function_exists('log_error_details')) {
                log_error_details('Default knowledge base seed failed', [
                    'Catalog Version' => DefaultKnowledgeBase::VERSION,
                ], $exception);
            }

            return ['categories' => 0, 'articles' => 0, 'version' => DefaultKnowledgeBase::VERSION, 'failed' => true];
        }

        SiteSetting::clearPublicCache();

        return [
            'categories' => $insertedCategories,
            'articles' => $insertedArticles,
            'version' => DefaultKnowledgeBase::VERSION,
        ];
    }

    /**
     * Удаляет только прежние встроенные технические материалы. Пользовательские
     * статьи не затрагиваются, а старая категория удаляется лишь после опустошения.
     */
    private function removeLegacyCatalog(object $database): void
    {
        $currentArticleSlugs = array_column(DefaultKnowledgeBase::articles(), 'slug');
        $legacyArticleSlugs = array_values(array_diff(
            DefaultKnowledgeBase::legacyArticleSlugs(),
            $currentArticleSlugs
        ));

        if ($legacyArticleSlugs !== []) {
            $placeholders = implode(',', array_fill(0, count($legacyArticleSlugs), '?'));
            $database->query(
                "DELETE FROM support_kb_articles WHERE slug IN ({$placeholders})",
                $legacyArticleSlugs
            );
        }

        $currentCategorySlugs = array_column(DefaultKnowledgeBase::categories(), 'slug');
        foreach (array_diff(DefaultKnowledgeBase::legacyCategorySlugs(), $currentCategorySlugs) as $legacyCategorySlug) {
            $database->query(
                'DELETE FROM support_kb_categories
                 WHERE slug = ?
                   AND NOT EXISTS (
                       SELECT 1 FROM support_kb_articles
                       WHERE support_kb_articles.category_id = support_kb_categories.id
                   )',
                [$legacyCategorySlug]
            );
        }
    }
}
