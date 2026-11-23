<?php

namespace Fireball\Commerce\Controllers;

use Fireball\Commerce\Cart;
use Fireball\Commerce\Catalog;
use Fireball\Commerce\Input;
use Fireball\Commerce\Orders;
use Fireball\Commerce\Reviews;
use Fireball\Commerce\Slides;
use Fireball\Commerce\ValidationError;
use FireballPluginCommerce as Plugin;

final class ShopController
{
    public function home(array $layout = []): string
    {
        if (!Plugin::hasMigration('002_storefront_home.sql')) {
            return $this->catalog($layout);
        }
        $catalog = new Catalog();
        $settings = $catalog->settings();
        $popular = $catalog->popular();
        return $this->render('home', $settings['name'] ?: Plugin::t('catalog'), $layout + [
            'settings' => $settings,
            'slides' => (new Slides())->listed(public: true),
            'categories' => $catalog->categories(public: true),
            'categoryProducts' => $catalog->categoryProducts(),
            'products' => $popular,
            'variants' => $catalog->variants($popular),
            'favorites' => $this->favoriteIds(),
            'favoriteReturn' => uri_without_lang() === '' ? 'homepage' : 'home',
        ]);
    }

    public function catalog(array $layout = []): string
    {
        $catalog = new Catalog();
        $page = Input::page(request()->get('page', 1));
        $search = Input::query(request()->get('q'));
        $category = Input::query(request()->get('category'), 160);
        $sort = Input::query(request()->get('sort'), 16);
        $sort = in_array($sort, ['popular', 'new', 'price-asc', 'price-desc', 'name'], true) ? $sort : '';
        $colors = Plugin::hasMigration('002_storefront_home.sql') ? array_column($catalog->colors(), 'name', 'hex') : [];
        $color = strtoupper(Input::query(request()->get('color'), 7));
        $color = isset($colors[$color]) ? $color : '';
        $minimum = $this->catalogPrice('min');
        $maximum = $this->catalogPrice('max');
        $available = request()->get('available') === '1';
        $view = request()->get('view') === 'alternate' ? 'alternate' : '';
        $criteria = ['color' => $color, 'min' => $minimum, 'max' => $maximum, 'available' => $available];
        $products = $catalog->listed($search, $category, $sort, $page, limit: 24, criteria: $criteria);
        $total = $catalog->countMatches($search, $category, $criteria);
        $categories = $catalog->categories(public: true);
        $categoryName = array_column($categories, 'name', 'slug')[$category] ?? '';
        $settings = $catalog->settings();
        $heading = $search !== '' ? Plugin::t('search_results') : ($categoryName ?: Plugin::t('shop_catalog'));
        return $this->render('catalog', $heading, $layout + [
            'heading' => $heading, 'settings' => $settings,
            'products' => $products, 'total' => $total, 'page' => $page,
            'filters' => [
                'q' => $search, 'category' => $category, 'sort' => $sort, 'color' => $color,
                'min' => $minimum === null ? '' : Input::money($minimum, form: true),
                'max' => $maximum === null ? '' : Input::money($maximum, form: true),
                'available' => $available ? '1' : '', 'view' => $view,
            ],
            'categories' => $categories, 'categoryName' => $categoryName, 'colors' => $colors,
            'variants' => $catalog->variants($products), 'favorites' => $this->favoriteIds(),
            'seo_robots' => $search !== '' ? 'noindex,follow' : '',
        ]);
    }

    private function catalogPrice(string $parameter): ?int
    {
        $value = Input::query(request()->get($parameter), 11);
        if ($value === '') {
            return null;
        }
        try {
            return Input::price($value);
        } catch (ValidationError) {
            return null;
        }
    }

    public function suggest(): void
    {
        header('Cache-Control: private, no-store');
        $query = Input::query(request()->get('q'));
        $products = mb_strlen($query) >= 2 ? (new Catalog())->listed($query, '', '', 1, limit: 8) : [];
        response()->json([
            'items' => array_map(static fn(array $product): array => [
                'type_label' => $product['category_name'] ?: Plugin::t('product'),
                'title' => $product['name'],
                'meta' => Input::money((int)$product['price_minor']),
                'url' => base_href('/shop/product/' . $product['slug']),
            ], $products),
            'empty_text' => Plugin::t('empty_catalog'),
        ]);
    }

    public function favorites(): string
    {
        $catalog = new Catalog();
        $favorites = $this->favoriteIds();
        $products = $catalog->favorites($favorites);
        return $this->render('favorites', Plugin::t('favorites'), [
            'favorites' => $favorites, 'products' => $products, 'variants' => $catalog->variants($products),
            'favoriteReturn' => 'favorites',
        ]);
    }

    public function toggleFavorite(): never
    {
        $favorites = $this->favoriteIds();
        $id = 0;
        $errorMessage = '';
        $target = match (request()->post('return')) {
            'homepage' => '/#commerce-popular',
            'favorites' => '/shop/favorites',
            'catalog' => $this->catalogReturn(),
            default => '/shop/home#commerce-popular',
        };
        try {
            $id = Input::number(request()->post('id'), 4294967295);
            $product = (new Catalog())->favorites([$id])[0] ?? null;
            if (in_array($id, $favorites, true)) {
                $favorites = array_values(array_diff($favorites, [$id]));
            } elseif ($product !== null) {
                array_unshift($favorites, $id);
            }
            if ($product && request()->post('return') === 'product') {
                $section = request()->post('section');
                $target = '/shop/product/' . $product['slug'] . (in_array($section, ['details', 'reviews'], true) ? '/' . $section : '');
            }
            session()->set('commerce.favorites', array_slice($favorites, 0, 100));
        } catch (ValidationError $error) {
            $errorMessage = $error->getMessage();
        }
        if (request()->isAjax()) {
            header('Cache-Control: private, no-store');
            response()->json([
                'id' => $id,
                'favorite' => in_array($id, $favorites, true),
                'count' => count($favorites),
                'error' => $errorMessage,
            ], $errorMessage === '' ? 200 : 422);
        }
        if ($errorMessage !== '') {
            session()->setFlash('error', $errorMessage);
        }
        response()->redirect(base_href($target));
    }

    private function catalogReturn(): string
    {
        parse_str(Input::query(request()->post('catalog_query'), 1500), $params);
        $params = array_intersect_key($params, array_flip(['q', 'category', 'sort', 'color', 'min', 'max', 'available', 'view', 'page']));
        $query = http_build_query(array_filter($params, 'is_string'), '', '&', PHP_QUERY_RFC3986);
        return '/shop' . ($query === '' ? '' : '?' . $query);
    }

    private function favoriteIds(): array
    {
        return (array)session()->get('commerce.favorites', []);
    }

    public function product(): string
    {
        $catalog = new Catalog();
        $product = $catalog->publicProduct(get_route_param('slug'));
        if (!$product) {
            abort('', 404);
        }
        $id = (int)$product['id'];
        $viewedIds = array_values(array_diff((array)session()->get('commerce.viewed', []), [$id]));
        session()->set('commerce.viewed', array_slice([$id, ...$viewedIds], 0, 9));
        $visibleViewed = array_column($catalog->favorites($viewedIds), null, 'id');
        $viewed = [];
        foreach ($viewedIds as $viewedId) {
            if (isset($visibleViewed[$viewedId])) {
                $viewed[] = $visibleViewed[$viewedId];
            }
        }
        $section = get_route_param('section', 'overview');
        $page = Input::page(request()->get('page', 1));
        $reviews = Plugin::hasMigration(Reviews::MIGRATION) ? new Reviews() : null;
        $summary = $reviews?->summary($id) ?? ['total' => 0, 'average' => 0, 'counts' => []];
        $title = match ($section) {
            'details' => Plugin::t('product_details') . ' · ' . $product['name'],
            'reviews' => Plugin::t('reviews') . ' · ' . $product['name'],
            default => $product['name'],
        };
        return $this->render('product', $title, [
            'product' => $product, 'section' => $section, 'viewed' => $viewed,
            'variants' => $catalog->variants([$product, ...$viewed]), 'favorites' => $this->favoriteIds(),
            'rating' => $summary, 'reviewsReady' => $reviews !== null, 'page' => $page,
            'reviews' => $section === 'reviews' ? ($reviews?->published($id, $page) ?? []) : [],
            'canReview' => $section === 'reviews' && $reviews?->canWrite($id, (int)(get_user()['id'] ?? 0)),
        ]);
    }

    public function submitReview(): never
    {
        $product = (new Catalog())->publicProduct(get_route_param('slug'));
        if (!$product) {
            abort('', 404);
        }
        try {
            if (!Plugin::hasMigration(Reviews::MIGRATION)) {
                throw new ValidationError(Plugin::t('error_retry'));
            }
            (new Reviews())->submit((int)$product['id'], (int)(get_user()['id'] ?? 0), request()->getData());
            session()->setFlash('success', Plugin::t('review_submitted'));
        } catch (ValidationError $error) {
            session()->setFlash('error', $error->getMessage());
        }
        response()->redirect(base_href('/shop/product/' . $product['slug'] . '/reviews'));
    }

    public function cart(): string
    {
        return $this->render('cart', Plugin::t('cart'), ['review' => $this->review()]);
    }

    public function add(): never
    {
        $cart = new Cart();
        try {
            $cart->add(Input::number(request()->post('id'), 4294967295), Input::number(request()->post('quantity', '1'), Cart::MAX_QUANTITY));
        } catch (ValidationError $error) {
            $this->cartResponse($cart, $error->getMessage());
        }
        $this->cartResponse($cart, success: Plugin::t('added'));
    }

    public function updateLine(): never
    {
        $cart = new Cart();
        try {
            $cart->changeLine(Input::number(request()->post('id'), 4294967295), Input::query(request()->post('operation'), 12));
        } catch (ValidationError $error) {
            $this->cartResponse($cart, $error->getMessage());
        }
        $this->cartResponse($cart);
    }

    public function clearCart(): never
    {
        $cart = new Cart();
        $cart->replace([]);
        $this->cartResponse($cart);
    }

    private function cartResponse(Cart $cart, string $error = '', string $success = ''): never
    {
        if (request()->isAjax()) {
            header('Cache-Control: private, no-store');
            $review = $cart->currentReview();
            $data = [
                'count' => array_sum($cart->items()),
                'panel' => $cart->drawer($review),
                'error' => $error,
                'message' => $success,
            ];
            if (request()->post('context') === 'cart') {
                $data['page'] = plugin_view(Plugin::SLUG, 'public/cart-content', ['review' => $review], false);
            } elseif (request()->post('context') === 'checkout') {
                $data['checkout'] = [
                    'lines' => plugin_view(Plugin::SLUG, 'public/checkout-lines', ['review' => $review], false),
                    'subtotal' => $review['subtotal'],
                    'subtotalText' => Input::money($review['subtotal']),
                    'valid' => $review['valid'],
                    'key' => $review['valid'] ? $cart->placeKey() : '',
                    'quote' => $review['quote'],
                ];
            }
            response()->json($data, $error === '' ? 200 : 422);
        }
        if ($error !== '' || $success !== '') {
            session()->setFlash($error !== '' ? 'error' : 'success', $error !== '' ? $error : $success);
        }
        response()->redirect(base_href('/shop/cart'));
    }

    public function update(): never
    {
        $cart = new Cart();
        try {
            $items = $cart->items();
            $submitted = request()->post('quantities', []);
            $submitted = is_array($submitted) ? $submitted : [];
            $remove = Input::number(request()->post('remove', '0'), 4294967295);
            if ($remove) {
                unset($items[$remove]);
            } else {
                foreach ($items as $id => $quantity) {
                    $count = Input::number($submitted[$id] ?? $quantity, Cart::MAX_QUANTITY);
                    if ($count > 0) {
                        $items[$id] = $count;
                    } else {
                        unset($items[$id]);
                    }
                }
            }
            $cart->replace($items);
        } catch (ValidationError $error) {
            $this->cartResponse($cart, $error->getMessage());
        }
        $this->cartResponse($cart);
    }

    public function checkout(array $form = [], string $error = ''): string
    {
        $review = $this->review();
        if (!$review['valid']) {
            session()->setFlash('error', Plugin::t('error_stock'));
            response()->redirect(base_href('/shop/cart'));
        }
        $user = get_user();
        $displayName = Input::query($form['name'] ?? $user['name'] ?? '', 190);
        [$firstName, $lastName] = array_pad(preg_split('/\s+/u', $displayName, 2) ?: [], 2, '');
        return $this->render('checkout', Plugin::t('checkout'), [
            'review' => $review, 'settings' => $review['settings'], 'key' => (new Cart())->placeKey(), 'formError' => $error,
            'form' => $form + ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $user['email'] ?? ''],
        ]);
    }

    public function place(): string
    {
        $cart = new Cart();
        $input = request()->getData();
        $key = $input['key'] ?? null;
        if (!is_string($key) || !hash_equals($cart->placeKey(), $key)) {
            session()->setFlash('error', Plugin::t('error_quote'));
            response()->redirect(base_href('/shop/checkout'));
        }
        try {
            $userId = (int)(get_user()['id'] ?? 0);
            $id = (new Orders())->place($cart->items(), $input, $key, $userId ?: null);
            $cart->rememberOrder($id);
            response()->redirect(base_href('/shop/order/' . $id));
        } catch (ValidationError $error) {
            http_response_code(422);
            return $this->checkout($input, $error->getMessage());
        } catch (\Throwable $error) {
            log_error_details('Commerce checkout failed', [], $error);
            http_response_code(503);
            return $this->checkout($input, Plugin::t('error_retry'));
        }
    }

    public function order(): string
    {
        $orders = new Orders();
        $id = (int)get_route_param('id');
        $order = $orders->find($id);
        $userId = (int)(get_user()['id'] ?? 0);
        $isOwner = $order && ($order['user_id'] !== null
            ? (int)$order['user_id'] === $userId
            : in_array($id, (array)session()->get('commerce.orders', []), true));
        if (!$isOwner) {
            abort('', 404);
        }
        return $this->render('order', Plugin::t('order') . ' #' . $id, [
            'order' => $order, 'items' => $orders->items($id),
        ]);
    }

    public function history(): string
    {
        $userId = (int)(get_user()['id'] ?? 0);
        if ($userId <= 0) {
            abort('', 403);
        }
        $page = Input::page(request()->get('page', 1));
        $orders = (new Orders())->listed('', $page, $userId);
        return $this->render('history', Plugin::t('my_orders'), [
            'orders' => array_slice($orders, 0, 24), 'page' => $page, 'hasNext' => count($orders) > 24,
        ]);
    }

    private function review(): array
    {
        return (new Cart())->currentReview();
    }

    private function render(string $view, string $title, array $data): string
    {
        header('Cache-Control: private, no-store');
        if (!in_array($view, ['catalog', 'home', 'product'], true)) {
            header('X-Robots-Tag: noindex, nofollow');
        }
        $asset = dirname(__DIR__, 2) . '/assets/storefront.css';
        if (in_array($view, ['catalog', 'home', 'product', 'checkout'], true)) {
            $data['footer_scripts'][] = base_href('/plugins/commerce/storefront.js?v='
                . filemtime(dirname(__DIR__, 2) . '/assets/storefront.js'));
        }
        return plugin_view(Plugin::SLUG, 'public/' . $view, $data + [
            'title' => $title,
            'settings' => (new Catalog())->settings(),
            'cartItems' => (new Cart())->items(),
            'styles' => [base_href('/plugins/commerce/storefront.css?v=' . filemtime($asset))],
        ]);
    }
}
