<?php

namespace Fireball\Commerce;

use FireballPluginCommerce as Plugin;

final class Slides
{
    public function listed(bool $public = false): array
    {
        return db()->query('SELECT s.*, c.name AS category_name, CASE WHEN c.is_active = 1 THEN c.slug END AS category_slug
            FROM commerce_home_slides s LEFT JOIN commerce_categories c ON c.id = s.category_id'
            . ($public ? ' WHERE s.is_active = 1' : '') . ' ORDER BY s.sort_order, s.id')->get() ?: [];
    }

    public function find(int $id): ?array
    {
        return db()->query('SELECT * FROM commerce_home_slides WHERE id = ?', [$id])->getOne() ?: null;
    }

    public function save(int $id, array $input, ?string $image): void
    {
        $category = Input::number($input['category_id'] ?? '0', 4294967295);
        if ($category > 0 && !(new Catalog())->category($category)) {
            throw new ValidationError(Plugin::t('error_category'));
        }
        $values = [
            Input::text($input['title'] ?? null, 160, true),
            Input::text($input['subtitle'] ?? '', 160),
            Input::text($input['button_label'] ?? '', 60),
            $category ?: null,
            Input::number($input['sort_order'] ?? '0', 1000000),
            !empty($input['is_active']) ? 1 : 0,
        ];
        if ($id === 0) {
            if ($image === null) {
                throw new ValidationError(Plugin::t('slide_image_required'));
            }
            db()->query('INSERT INTO commerce_home_slides (title, subtitle, button_label, category_id, sort_order, is_active, image_path)
                VALUES (?,?,?,?,?,?,?)', [...$values, $image]);
            return;
        }
        $revision = Input::number($input['revision'] ?? null, 4294967295);
        db()->query('UPDATE commerce_home_slides SET title = ?, subtitle = ?, button_label = ?, category_id = ?, sort_order = ?,
            is_active = ?, image_path = COALESCE(?, image_path), revision = revision + 1 WHERE id = ? AND revision = ?',
            [...$values, $image, $id, $revision]);
        $this->requireChangedRow();
    }

    public function delete(int $id, int $revision): void
    {
        db()->query('DELETE FROM commerce_home_slides WHERE id = ? AND revision = ?', [$id, $revision]);
        $this->requireChangedRow();
    }

    private function requireChangedRow(): void
    {
        if (db()->rowCount() !== 1) {
            throw new ValidationError(Plugin::t('error_conflict'));
        }
    }
}
