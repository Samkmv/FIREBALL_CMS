<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Support\DefaultKnowledgeBase;

/**
 * Синхронизирует встроенную публичную базу знаний.
 *
 * Встроенные записи обновляются по известным slug, устаревшие встроенные записи
 * удаляются, а пользовательские категории и статьи с другими slug сохраняются.
 */
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
            return [
                'categories' => 0,
                'articles' => 0,
                'updated_categories' => 0,
                'updated_articles' => 0,
                'version' => DefaultKnowledgeBase::VERSION,
            ];
        }

        $database = db();
        $ownsTransaction = !$database->inTransaction();
        $insertedCategories = 0;
        $updatedCategories = 0;
        $insertedArticles = 0;
        $updatedArticles = 0;
        $removedArticles = 0;
        $removedCategories = 0;

        try {
            if ($ownsTransaction) {
                $database->beginTransaction();
            }

            $now = date('Y-m-d H:i:s');
            $removedArticles = $this->removeLegacyArticles($database);

            $categoryIds = [];
            foreach (DefaultKnowledgeBase::categories() as $category) {
                $slug = (string)$category['slug'];
                $existing = $database->query(
                    'SELECT id, name, sort_order FROM support_kb_categories WHERE slug = ? LIMIT 1',
                    [$slug]
                )->getOne();

                if (!$existing) {
                    $database->query(
                        'INSERT INTO support_kb_categories (name, slug, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                        [(string)$category['name'], $slug, (int)$category['sort_order'], $now, $now]
                    );
                    $categoryId = (int)$database->getInsertId();
                    $insertedCategories++;
                } else {
                    $categoryId = (int)$existing['id'];
                    if (
                        (string)$existing['name'] !== (string)$category['name']
                        || (int)$existing['sort_order'] !== (int)$category['sort_order']
                    ) {
                        $database->query(
                            'UPDATE support_kb_categories
                             SET name = ?, sort_order = ?, updated_at = ?
                             WHERE id = ?',
                            [
                                (string)$category['name'],
                                (int)$category['sort_order'],
                                $now,
                                $categoryId,
                            ]
                        );
                        $updatedCategories++;
                    }
                }

                $categoryIds[$slug] = $categoryId;
            }

            foreach (DefaultKnowledgeBase::articles() as $article) {
                $categoryId = (int)($categoryIds[(string)$article['category_slug']] ?? 0);
                if ($categoryId <= 0) {
                    continue;
                }

                $slug = (string)$article['slug'];
                $existing = $database->query(
                    'SELECT id, title, excerpt, content, category_id
                     FROM support_kb_articles
                     WHERE slug = ?
                     LIMIT 1',
                    [$slug]
                )->getOne();

                if (!$existing) {
                    $database->query(
                        'INSERT INTO support_kb_articles
                         (title, slug, excerpt, content, category_id, is_published, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
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
                    continue;
                }

                if (
                    (string)$existing['title'] !== (string)$article['title']
                    || (string)($existing['excerpt'] ?? '') !== (string)$article['excerpt']
                    || (string)$existing['content'] !== (string)$article['content']
                    || (int)($existing['category_id'] ?? 0) !== $categoryId
                ) {
                    $database->query(
                        'UPDATE support_kb_articles
                         SET title = ?, excerpt = ?, content = ?, category_id = ?, updated_at = ?
                         WHERE id = ?',
                        [
                            (string)$article['title'],
                            (string)$article['excerpt'],
                            (string)$article['content'],
                            $categoryId,
                            $now,
                            (int)$existing['id'],
                        ]
                    );
                    $updatedArticles++;
                }
            }

            // Категории удаляем после переноса статей, чтобы бывшие категории
            // «Доступ к материалам» и «Проблемы и помощь» успели опустеть.
            $removedCategories = $this->removeLegacyCategories($database);

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

            return [
                'categories' => 0,
                'articles' => 0,
                'updated_categories' => 0,
                'updated_articles' => 0,
                'removed_categories' => 0,
                'removed_articles' => 0,
                'version' => DefaultKnowledgeBase::VERSION,
                'failed' => true,
            ];
        }

        SiteSetting::clearPublicCache();

        return [
            'categories' => $insertedCategories,
            'articles' => $insertedArticles,
            'updated_categories' => $updatedCategories,
            'updated_articles' => $updatedArticles,
            'removed_categories' => $removedCategories,
            'removed_articles' => $removedArticles,
            'version' => DefaultKnowledgeBase::VERSION,
        ];
    }

    /**
     * Удаляет только известные встроенные статьи прошлых версий.
     * Любые статьи с пользовательскими slug остаются нетронутыми.
     */
    private function removeLegacyArticles(object $database): int
    {
        $currentArticleSlugs = array_column(DefaultKnowledgeBase::articles(), 'slug');
        $legacyArticleSlugs = array_values(array_diff(
            array_unique(DefaultKnowledgeBase::legacyArticleSlugs()),
            $currentArticleSlugs
        ));

        if ($legacyArticleSlugs === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($legacyArticleSlugs), '?'));
        $rows = $database->query(
            "SELECT id
             FROM support_kb_articles
             WHERE slug IN ({$placeholders})",
            $legacyArticleSlugs
        )->get() ?: [];

        $articleIds = array_values(array_filter(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $rows
        )));

        if ($articleIds === []) {
            return 0;
        }

        $idPlaceholders = implode(',', array_fill(0, count($articleIds), '?'));
        $database->query(
            "DELETE FROM support_kb_article_votes WHERE article_id IN ({$idPlaceholders})",
            $articleIds
        );
        $database->query(
            "DELETE FROM support_kb_article_stats WHERE article_id IN ({$idPlaceholders})",
            $articleIds
        );
        $database->query(
            "DELETE FROM support_kb_articles WHERE id IN ({$idPlaceholders})",
            $articleIds
        );

        return count($articleIds);
    }

    /**
     * Удаляет прежние встроенные категории только после того, как они пусты.
     * Пользовательские статьи тем самым защищены от каскадной очистки.
     */
    private function removeLegacyCategories(object $database): int
    {
        $currentCategorySlugs = array_column(DefaultKnowledgeBase::categories(), 'slug');
        $legacyCategorySlugs = array_values(array_diff(
            array_unique(DefaultKnowledgeBase::legacyCategorySlugs()),
            $currentCategorySlugs
        ));

        $removed = 0;
        foreach ($legacyCategorySlugs as $legacyCategorySlug) {
            $categoryId = (int)($database->query(
                'SELECT id
                 FROM support_kb_categories
                 WHERE slug = ?
                   AND NOT EXISTS (
                       SELECT 1
                       FROM support_kb_articles
                       WHERE support_kb_articles.category_id = support_kb_categories.id
                   )
                 LIMIT 1',
                [$legacyCategorySlug]
            )->getColumn() ?: 0);

            if ($categoryId <= 0) {
                continue;
            }

            $database->query(
                'DELETE FROM support_kb_categories WHERE id = ?',
                [$categoryId]
            );
            $removed++;
        }

        return $removed;
    }
}
