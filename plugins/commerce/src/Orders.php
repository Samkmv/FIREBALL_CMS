<?php

namespace Fireball\Commerce;

use FireballPluginCommerce as Plugin;

final class Orders
{
    public const STATUSES = ['new', 'confirmed', 'fulfillment', 'completed', 'cancelled'];
    public const PAYMENT = ['pending', 'paid', 'refunded'];

    public function place(array $cart, array $input, string $key, ?int $userId): int
    {
        if ($existing = $this->byKey($key)) {
            return $existing;
        }
        if (array_key_exists('first_name', $input) || array_key_exists('last_name', $input)) {
            $firstName = Input::text($input['first_name'] ?? null, 95, true);
            $lastName = Input::text($input['last_name'] ?? null, 94, true);
            $name = $firstName . ' ' . $lastName;
        } else {
            $name = Input::text($input['name'] ?? null, 190, true);
        }
        $email = Input::text($input['email'] ?? null, 190, true);
        $phone = Input::text($input['phone'] ?? null, 45, true);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)
            || !preg_match('/\A[+()0-9 .-]+\z/', $phone)
            || strlen(preg_replace('/\D/', '', $phone)) < 7) {
            throw new ValidationError(Plugin::t('error_contact'));
        }

        $method = $input['fulfillment'] ?? null;
        $note = Input::text($input['note'] ?? '', 1500);
        $address = Input::text($input['address'] ?? '', 500, $method === 'delivery');
        $quote = is_string($input['quote'] ?? null) ? $input['quote'] : '';
        $catalog = new Catalog();
        $database = db();
        $database->beginTransaction();
        try {
            $settings = $catalog->settings(lock: true);
            $review = (new Cart())->review($cart, $catalog->cartProducts($cart, lock: true), $settings);
            if (!$review['valid']) {
                throw new ValidationError(Plugin::t('error_stock'));
            }
            if (!hash_equals($review['quote'], $quote)) {
                throw new ValidationError(Plugin::t('error_quote'));
            }
            $fee = match ($method) {
                'pickup' => $settings['pickup_enabled'] ? 0 : null,
                'delivery' => $settings['delivery_enabled'] ? (int)$settings['delivery_minor'] : null,
                default => null,
            };
            if ($fee === null) {
                throw new ValidationError(Plugin::t('error_fulfillment'));
            }
            $database->query(
                'INSERT INTO commerce_orders (user_id, checkout_key, customer_name, customer_email, customer_phone,
                    customer_note, fulfillment, fulfillment_address, subtotal_minor, delivery_minor, total_minor)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$userId, $key, $name, $email, $phone, $note, $method,
                    $method === 'pickup' ? $settings['pickup_address'] : $address, $review['subtotal'],
                    $fee, $review['subtotal'] + $fee]
            );
            $id = (int)$database->getInsertId();
            foreach ($review['lines'] as $line) {
                $database->query('INSERT INTO commerce_order_items (order_id, product_id, sku, name, quantity, unit_minor)
                    VALUES (?,?,?,?,?,?)', [$id, $line['id'], $line['sku'], $line['name'], $line['quantity'], $line['price_minor']]);
                $database->query('UPDATE commerce_products SET stock = stock - ?, revision = revision + 1 WHERE id = ?',
                    [$line['quantity'], $line['id']]);
            }
            $database->commit();
            return $id;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ($error instanceof \PDOException && (int)($error->errorInfo[1] ?? 0) === 1062 && ($existing = $this->byKey($key))) {
                return $existing;
            }
            throw $error;
        }
    }

    private function byKey(string $key): int
    {
        return (int)db()->query('SELECT id FROM commerce_orders WHERE checkout_key = ?', [$key])->getColumn();
    }

    public function find(int $id): ?array
    {
        return db()->query('SELECT * FROM commerce_orders WHERE id = ?', [$id])->getOne() ?: null;
    }

    public function items(int $id): array
    {
        return db()->query('SELECT * FROM commerce_order_items WHERE order_id = ? ORDER BY id', [$id])->get() ?: [];
    }

    public function listed(string $status, int $page, ?int $userId = null, string $search = ''): array
    {
        $filters = ['1 = 1'];
        $values = [];
        if ($userId !== null) {
            $filters[] = 'user_id = ?';
            $values[] = $userId;
        }
        if (in_array($status, self::STATUSES, true)) {
            $filters[] = 'status = ?';
            $values[] = $status;
        }
        if ($search !== '' && $userId === null) {
            $query = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $filters[] = "(customer_name LIKE ? ESCAPE '!' OR customer_email LIKE ? ESCAPE '!' OR id = ?)";
            array_push($values, $query, $query, ctype_digit($search) ? $search : 0);
        }
        return db()->query(
            'SELECT id, customer_name, status, payment_status, fulfillment, total_minor, created_at
             FROM commerce_orders WHERE ' . implode(' AND ', $filters)
             . ' ORDER BY id DESC LIMIT 25 OFFSET ' . (($page - 1) * 24), $values
        )->get() ?: [];
    }

    public function stats(): array
    {
        return db()->query("SELECT
            (SELECT COUNT(*) FROM commerce_products WHERE is_active = 1) AS products,
            (SELECT COUNT(*) FROM commerce_products WHERE is_active = 1 AND stock <= 5) AS low_stock,
            (SELECT COUNT(*) FROM commerce_orders WHERE status = 'new') AS new_orders,
            (SELECT COALESCE(SUM(total_minor),0) FROM commerce_orders WHERE payment_status = 'paid') AS paid_total"
        )->getOne();
    }

    public function events(int $id): array
    {
        return db()->query('SELECT e.*, u.name AS admin_name FROM commerce_order_events e
            LEFT JOIN users u ON u.id = e.admin_id WHERE e.order_id = ? ORDER BY e.id DESC', [$id])->get() ?: [];
    }

    public function nextStatuses(array $order): array
    {
        $allowed = match ($order['status']) {
            'new' => ['confirmed', 'cancelled'],
            'confirmed' => ['fulfillment', 'cancelled'],
            'fulfillment' => $order['payment_status'] === 'paid' ? ['completed'] : ['cancelled'],
            default => [],
        };
        if ($order['payment_status'] === 'paid') {
            return array_values(array_diff($allowed, ['cancelled']));
        }
        return $allowed;
    }

    public function nextPayment(array $order): array
    {
        return match ($order['payment_status']) {
            'pending' => in_array($order['status'], ['confirmed', 'fulfillment'], true) ? ['paid'] : [],
            'paid' => ['refunded'],
            default => [],
        };
    }

    public function change(int $id, string $type, string $value, int $admin): void
    {
        $database = db();
        $database->beginTransaction();
        try {
            $order = $database->query('SELECT * FROM commerce_orders WHERE id = ? FOR UPDATE', [$id])->getOne();
            if (!$order || !in_array($type, ['status', 'payment_status'], true)) {
                throw new ValidationError(Plugin::t('error_status'));
            }
            if ($order[$type] === $value) {
                $database->commit();
                return;
            }
            $allowed = $type === 'status' ? $this->nextStatuses($order) : $this->nextPayment($order);
            if (!in_array($value, $allowed, true)) {
                throw new ValidationError(Plugin::t('error_status'));
            }
            if ($type === 'status' && $value === 'cancelled') {
                $items = $database->query('SELECT product_id, quantity FROM commerce_order_items WHERE order_id = ? ORDER BY product_id', [$id])->get() ?: [];
                foreach ($items as $item) {
                    $database->query('UPDATE commerce_products SET stock = stock + ?, revision = revision + 1 WHERE id = ?',
                        [$item['quantity'], $item['product_id']]);
                }
            }
            $database->query('UPDATE commerce_orders SET ' . $type . ' = ? WHERE id = ?', [$value, $id]);
            $database->query('INSERT INTO commerce_order_events (order_id, admin_id, event_type, new_value) VALUES (?,?,?,?)',
                [$id, $admin > 0 ? $admin : null, $type === 'status' ? 'status' : 'payment', $value]);
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }
}
