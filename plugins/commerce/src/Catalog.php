<?php

namespace Fireball\Commerce;

use FireballPluginCommerce as Plugin;

final class Catalog
{
    public const BADGES_MIGRATION = '004_product_badges.sql';
    private const VISIBLE = 'p.is_active = 1 AND (p.category_id IS NULL OR c.is_active = 1)';
    private const SALES = 'LEFT JOIN (
        SELECT i.product_id, SUM(i.quantity) AS sold FROM commerce_order_items i
        INNER JOIN commerce_orders o ON o.id = i.order_id
        WHERE o.status = \'completed\' AND o.payment_status = \'paid\' GROUP BY i.product_id
    ) sales ON sales.product_id = p.id';

    public function categories(bool $public = false): array
    {
        return db()->query('SELECT * FROM commerce_categories'
            . ($public ? ' WHERE is_active = 1' : '') . ' ORDER BY name, id')->get() ?: [];
    }

    public function category(int $id): ?array
    {
        return db()->query('SELECT * FROM commerce_categories WHERE id = ?', [$id])->getOne() ?: null;
    }

    public function categoryProducts(): array
    {
        $rows = db()->query(
            'SELECT category_id, name, slug, image_path FROM (
                SELECT p.category_id, p.name, p.slug, p.image_path,
                       ROW_NUMBER() OVER(PARTITION BY p.category_id ORDER BY p.is_featured DESC, p.id DESC) AS category_rank
                FROM commerce_products p
                INNER JOIN commerce_categories c ON c.id = p.category_id
                WHERE ' . self::VISIBLE . '
             ) ranked WHERE category_rank <= 3 ORDER BY category_id, category_rank'
        )->get() ?: [];
        $result = [];
        foreach ($rows as $row) {
            $result[$row['category_id']][] = $row;
        }
        return $result;
    }

    public function popular(): array
    {
        $rows = db()->query(
            'SELECT p.* FROM commerce_products p
             LEFT JOIN commerce_categories c ON c.id = p.category_id
             ' . self::SALES . '
             WHERE ' . self::VISIBLE . '
             ORDER BY p.is_featured DESC, COALESCE(sales.sold, 0) DESC, (p.stock > 0) DESC, p.id DESC LIMIT 48'
        )->get() ?: [];
        $groups = [];
        $unique = [];
        foreach ($rows as $product) {
            $key = $product['variant_group'] !== '' ? 'group:' . mb_strtolower($product['variant_group']) : 'product:' . $product['id'];
            if (!isset($groups[$key])) {
                $unique[] = $product;
                $groups[$key] = true;
                if (count($unique) === 12) {
                    break;
                }
            }
        }
        return $unique;
    }

    public function variants(array $products): array
    {
        $groups = array_values(array_unique(array_filter(array_column($products, 'variant_group'))));
        if ($groups === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($groups), '?'));
        $rows = db()->query(
            "SELECT p.id, p.variant_group, p.slug, p.color_hex, p.color_name
             FROM commerce_products p LEFT JOIN commerce_categories c ON c.id = p.category_id
             WHERE p.variant_group IN ({$placeholders}) AND p.color_hex <> '' AND " . self::VISIBLE . ' ORDER BY p.id',
            $groups
        )->get() ?: [];
        $result = [];
        foreach ($rows as $row) {
            $result[mb_strtolower($row['variant_group'])][] = $row;
        }
        return $result;
    }

    public function favorites(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return db()->query(
            "SELECT p.* FROM commerce_products p LEFT JOIN commerce_categories c ON c.id = p.category_id
                WHERE p.id IN ({$placeholders}) AND " . self::VISIBLE . ' ORDER BY p.id DESC',
            $ids
        )->get() ?: [];
    }

    public function listed(string $search, string $category, string $sort, int $page, bool $public = true, string $stock = '', int $limit = 25, array $criteria = []): array
    {
        [$filters, $params] = $this->listingFilters($search, $category, $public, $stock, $criteria);
        $order = match ($sort) {
            'popular' => 'COALESCE(sales.sold, 0) DESC, (p.stock > 0) DESC, p.id DESC',
            'price-asc' => 'p.price_minor ASC, p.id DESC',
            'price-desc' => 'p.price_minor DESC, p.id DESC',
            'name' => 'p.name ASC, p.id DESC',
            '' => $public && $search !== '' ? 'CASE WHEN p.sku = ? THEN 0 WHEN p.name = ? THEN 1 ELSE 2 END, p.id DESC' : 'p.id DESC',
            default => 'p.id DESC',
        };
        if ($sort === '' && $public && $search !== '') {
            array_push($params, $search, $search);
        }
        $salesJoin = $sort === 'popular' ? self::SALES : '';
        return db()->query(
            'SELECT p.*, c.name AS category_name FROM commerce_products p
             LEFT JOIN commerce_categories c ON c.id = p.category_id ' . $salesJoin
            . ' WHERE ' . implode(' AND ', $filters) . ' ORDER BY ' . $order
             . ' LIMIT ' . $limit . ' OFFSET ' . (($page - 1) * 24),
            $params
        )->get() ?: [];
    }

    public function countMatches(string $search, string $category, array $criteria): int
    {
        [$filters, $params] = $this->listingFilters($search, $category, true, '', $criteria);
        return (int)db()->query('SELECT COUNT(*) FROM commerce_products p
            LEFT JOIN commerce_categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $filters),
            $params)->getColumn();
    }

    public function colors(): array
    {
        return db()->query("SELECT p.color_hex AS hex, MIN(p.color_name) AS name FROM commerce_products p
            LEFT JOIN commerce_categories c ON c.id = p.category_id WHERE " . self::VISIBLE . "
            AND p.color_hex REGEXP '^#[0-9A-Fa-f]{6}$' AND p.color_name <> ''
            GROUP BY p.color_hex ORDER BY name")->get() ?: [];
    }

    private function listingFilters(string $search, string $category, bool $public, string $stock, array $criteria): array
    {
        $filters = $public ? [self::VISIBLE] : ['1 = 1'];
        $params = [];
        if ($search !== '') {
            $needle = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $filters[] = "(p.name LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!')";
            array_push($params, $needle, $needle);
        }
        if (!$public && $stock === 'low') {
            $filters[] = 'p.is_active = 1 AND p.stock <= 5';
        }
        if ($category !== '') {
            $filters[] = 'c.slug = ?';
            $params[] = $category;
        }
        if (!empty($criteria['available'])) {
            $filters[] = 'p.stock > 0';
        }
        if (!empty($criteria['color'])) {
            $filters[] = 'p.color_hex = ?';
            $params[] = $criteria['color'];
        }
        foreach (['min' => '>=', 'max' => '<='] as $key => $operator) {
            if (isset($criteria[$key])) {
                $filters[] = "p.price_minor {$operator} ?";
                $params[] = $criteria[$key];
            }
        }
        return [$filters, $params];
    }

    public function product(int $id): ?array
    {
        return db()->query('SELECT * FROM commerce_products WHERE id = ?', [$id])->getOne() ?: null;
    }

    public function publicProduct(string $slug): ?array
    {
        return db()->query(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM commerce_products p LEFT JOIN commerce_categories c ON c.id = p.category_id
             WHERE p.slug = ? AND ' . self::VISIBLE,
            [$slug]
        )->getOne() ?: null;
    }

    /** @param array<int, int> $cart */
    public function cartProducts(array $cart, bool $lock = false): array
    {
        if ($cart === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($cart), '?'));
        return db()->query(
            'SELECT p.id, p.name, p.slug, p.sku, p.price_minor, p.image_path, p.stock, (' . self::VISIBLE . ') AS available
             FROM commerce_products p LEFT JOIN commerce_categories c ON c.id = p.category_id
             WHERE p.id IN (' . $placeholders . ') ORDER BY p.id' . ($lock ? ' FOR UPDATE' : ''),
            array_keys($cart)
        )->get() ?: [];
    }

    public function saveCategory(int $id, array $input, ?string $newImage = null): void
    {
        $name = Input::text($input['name'] ?? null, 160, true);
        $values = [$name, Input::slug($input['slug'] ?? '', $name), !empty($input['is_active']) ? 1 : 0];
        try {
            if ($id > 0) {
                $imageSql = '';
                if ($newImage !== null || !empty($input['remove_image'])) {
                    $imageSql = ', image_path = ?';
                    $values[] = $newImage;
                }
                $revision = Input::number($input['revision'] ?? null, 4294967295);
                db()->query('UPDATE commerce_categories SET name = ?, slug = ?, is_active = ?, revision = revision + 1'
                    . $imageSql . ' WHERE id = ? AND revision = ?', [...$values, $id, $revision]);
                if (db()->rowCount() !== 1) {
                    throw new ValidationError(Plugin::t('error_conflict'));
                }
            } else {
                db()->query('INSERT INTO commerce_categories (name, slug, is_active, image_path) VALUES (?,?,?,?)', [...$values, $newImage]);
            }
        } catch (\PDOException $error) {
            $this->handleDuplicate($error);
        }
    }

    public function saveProduct(int $id, array $input, ?string $newImage, ?string $hoverImage = null, ?array $gallery = null): int
    {
        $name = Input::text($input['name'] ?? null, 190, true);
        $category = Input::number($input['category_id'] ?? '0', 4294967295);
        if ($category && !db()->query('SELECT id FROM commerce_categories WHERE id = ?', [$category])->getColumn()) {
            throw new ValidationError(Plugin::t('error_category'));
        }
        $color = strtoupper(Input::text($input['color_hex'] ?? '', 7));
        $colorName = Input::text($input['color_name'] ?? '', 60, $color !== '');
        if ($color !== '' && !preg_match('/\A#[0-9A-F]{6}\z/', $color)) {
            throw new ValidationError(Plugin::t('error_color'));
        }
        $price = Input::price($input['price'] ?? null);
        $values = [
            $category ?: null, Input::text($input['sku'] ?? null, 80, true), $name,
            Input::slug($input['slug'] ?? '', $name), Input::text($input['description'] ?? '', 15000),
            $price, Input::number($input['stock'] ?? null, 1000000),
            !empty($input['is_active']) ? 1 : 0, !empty($input['is_featured']) ? 1 : 0,
            $color, $colorName, Input::text($input['variant_group'] ?? '', 80),
        ];
        $extra = [];
        if (array_key_exists('specifications', $input) && Plugin::hasMigration(Reviews::MIGRATION)) {
            $extra['specifications'] = Input::text($input['specifications'], 8000);
        }
        if ((array_key_exists('is_new', $input) || array_key_exists('compare_at', $input)) && Plugin::hasMigration(self::BADGES_MIGRATION)) {
            if (array_key_exists('is_new', $input)) {
                $extra['is_new'] = !empty($input['is_new']) ? 1 : 0;
            }
            if (array_key_exists('compare_at', $input)) {
                $compareAt = Input::text($input['compare_at'], 11);
                $previous = $compareAt === '' ? null : Input::price($compareAt);
                if ($previous !== null && $previous <= $price) {
                    throw new ValidationError(Plugin::t('error_compare_at'));
                }
                $extra['compare_at_minor'] = $previous;
            }
        }
        if ($gallery !== null) {
            $extra['gallery_images'] = json_encode($gallery, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }
        $extraColumns = array_keys($extra);
        $values = [...$values, ...array_values($extra)];
        try {
            if ($id > 0) {
                $revision = Input::number($input['revision'] ?? null, 4294967295);
                $imageSql = '';
                if ($newImage !== null || !empty($input['remove_image'])) {
                    $imageSql = ', image_path = ?';
                    $values[] = $newImage;
                }
                if ($hoverImage !== null || !empty($input['remove_hover_image'])) {
                    $imageSql .= ', hover_image_path = ?';
                    $values[] = $hoverImage;
                }
                $extraSql = $extra === [] ? '' : ', ' . implode(' = ?, ', $extraColumns) . ' = ?';
                db()->query('UPDATE commerce_products SET category_id = ?, sku = ?, name = ?, slug = ?,
                    description = ?, price_minor = ?, stock = ?, is_active = ?, is_featured = ?,
                    color_hex = ?, color_name = ?, variant_group = ?' . $extraSql . ', revision = revision + 1'
                    . $imageSql . ' WHERE id = ? AND revision = ?', [...$values, $id, $revision]);
                if (db()->rowCount() !== 1) {
                    throw new ValidationError(Plugin::t('error_product_changed'));
                }
                return $id;
            }

            $extraColumnList = $extra === [] ? '' : ', ' . implode(', ', $extraColumns);
            $values = [...$values, $newImage, $hoverImage];
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            db()->query('INSERT INTO commerce_products (category_id, sku, name, slug, description, price_minor, stock,
                is_active, is_featured, color_hex, color_name, variant_group' . $extraColumnList . ', image_path, hover_image_path)
                VALUES (' . $placeholders . ')', $values);
            return (int)db()->getInsertId();
        } catch (\PDOException $error) {
            $this->handleDuplicate($error);
        }
    }

    public static function previousPrice(array $product): ?int
    {
        $previous = (int)($product['compare_at_minor'] ?? 0);
        return $previous > (int)$product['price_minor'] ? $previous : null;
    }

    public function settings(bool $lock = false): array
    {
        return db()->query('SELECT * FROM commerce_settings WHERE id = 1' . ($lock ? ' LOCK IN SHARE MODE' : ''))->getOne();
    }

    public function saveSettings(array $input): void
    {
        $pickup = !empty($input['pickup_enabled']);
        $delivery = !empty($input['delivery_enabled']);
        if (!$pickup && !$delivery) {
            throw new ValidationError(Plugin::t('error_fulfillment'));
        }
        $address = Input::text($input['pickup_address'] ?? '', 255, $pickup);
        db()->query('UPDATE commerce_settings SET name = ?, introduction = ?, pickup_enabled = ?,
            pickup_address = ?, delivery_enabled = ?, delivery_minor = ? WHERE id = 1', [
            Input::text($input['name'] ?? '', 190), Input::text($input['introduction'] ?? '', 500),
            (int)$pickup, $address, (int)$delivery, Input::price($input['delivery_price'] ?? '0'),
        ]);
    }

    private function handleDuplicate(\PDOException $error): never
    {
        if ((int)($error->errorInfo[1] ?? 0) === 1062) {
            throw new ValidationError(Plugin::t('error_duplicate'));
        }
        throw $error;
    }
}
