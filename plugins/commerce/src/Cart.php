<?php

namespace Fireball\Commerce;

use FireballPluginCommerce as Plugin;

final class Cart
{
    public const MAX_LINES = 50;
    public const MAX_QUANTITY = 99;

    public function items(): array
    {
        return (array)session()->get('commerce.cart', []);
    }

    public function replace(array $cart): void
    {
        ksort($cart, SORT_NUMERIC);
        session()->set('commerce.cart', $cart);
        session()->remove('commerce.checkout');
    }

    public function placeKey(): string
    {
        $key = session()->get('commerce.checkout');
        if (!is_string($key)) {
            $key = bin2hex(random_bytes(32));
            session()->set('commerce.checkout', $key);
        }
        return $key;
    }

    public function rememberOrder(int $id): void
    {
        session()->set('commerce.cart', []);
        $orders = (array)session()->get('commerce.orders', []);
        $orders[] = $id;
        session()->set('commerce.orders', array_slice(array_values(array_unique($orders)), -50));
    }

    public function add(int $id, int $quantity): void
    {
        $cart = $this->items();
        $cart[$id] = ($cart[$id] ?? 0) + $quantity;
        if (count($cart) > self::MAX_LINES || $quantity < 1
            || $cart[$id] > self::MAX_QUANTITY) {
            throw new ValidationError(Plugin::t('error_cart_limit'));
        }
        $product = (new Catalog())->cartProducts([$id => $quantity])[0] ?? null;
        if (!$product || !$product['available'] || (int)$product['stock'] < $cart[$id]) {
            throw new ValidationError(Plugin::t('error_stock'));
        }
        $this->replace($cart);
    }

    public function changeLine(int $id, string $operation): void
    {
        $items = $this->items();
        if (!isset($items[$id]) || !in_array($operation, ['increase', 'decrease', 'remove'], true)) {
            throw new ValidationError(Plugin::t('error_fields'));
        }
        if ($operation === 'increase') {
            $this->add($id, 1);
            return;
        }
        if ($operation === 'remove' || $items[$id] === 1) {
            unset($items[$id]);
        } else {
            $items[$id]--;
        }
        $this->replace($items);
    }

    public function currentReview(): array
    {
        $items = $this->items();
        $catalog = new Catalog();
        return $this->review($items, $catalog->cartProducts($items), $catalog->settings());
    }

    public function drawer(?array $review = null): string
    {
        $review ??= $this->items() === [] ? ['lines' => [], 'subtotal' => 0, 'valid' => false] : $this->currentReview();
        return plugin_view(Plugin::SLUG, 'public/mini-cart', ['review' => $review], false);
    }

    public function review(array $cart, array $products, array $settings): array
    {
        $byId = array_column($products, null, 'id');
        $lines = [];
        $subtotal = 0;
        $valid = $cart !== [];
        foreach ($cart as $id => $quantity) {
            $item = $byId[$id] ?? ['id' => $id, 'name' => Plugin::t('unavailable'), 'available' => 0, 'price_minor' => 0, 'stock' => 0];
            $item['quantity'] = (int)$quantity;
            $item['line_minor'] = (int)$item['price_minor'] * $item['quantity'];
            $item['valid'] = $item['available'] && $item['quantity'] > 0 && $item['quantity'] <= min(self::MAX_QUANTITY, (int)$item['stock']);
            $valid = $valid && $item['valid'];
            $subtotal += $item['line_minor'];
            $lines[] = $item;
        }

        $quote = hash('sha256', serialize([
            array_map(static fn(array $line): array => [(int)$line['id'], $line['quantity'], (int)$line['price_minor']], $lines),
            (int)$settings['delivery_enabled'], (int)$settings['delivery_minor'],
            (int)$settings['pickup_enabled'], $settings['pickup_address'],
        ]));
        return compact('lines', 'subtotal', 'valid', 'quote', 'settings');
    }
}
