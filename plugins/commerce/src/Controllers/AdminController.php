<?php

namespace Fireball\Commerce\Controllers;

use Fireball\Commerce\Catalog;
use Fireball\Commerce\Input;
use Fireball\Commerce\Orders;
use Fireball\Commerce\ProductImage;
use Fireball\Commerce\Reviews;
use Fireball\Commerce\Slides;
use Fireball\Commerce\ValidationError;
use FireballPluginCommerce as Plugin;

final class AdminController
{
    private const BASE = '/admin/commerce';

    public function overview(): string
    {
        return $this->render('overview', 'admin_title', ['stats' => (new Orders())->stats()]);
    }

    public function products(): string
    {
        $query = Input::query(request()->get('q'));
        $page = Input::page(request()->get('page', 1));
        $stock = request()->get('stock') === 'low' ? 'low' : '';
        $products = (new Catalog())->listed($query, '', '', $page, public: false, stock: $stock);
        return $this->render('products', 'products', [
            'query' => $query, 'stock' => $stock, 'page' => $page, 'hasNext' => count($products) > 24, 'products' => array_slice($products, 0, 24),
        ]);
    }

    public function productForm(array $form = [], string $error = ''): string
    {
        $id = (int)get_route_param('id');
        $catalog = new Catalog();
        $product = $id > 0 ? $catalog->product($id) : null;
        if ($id > 0 && !$product) {
            abort('', 404);
        }
        return $this->render('product-form', $id > 0 ? 'edit_product' : 'add_product', [
            'product' => $product, 'id' => $id, 'form' => $form, 'formError' => $error, 'categories' => $catalog->categories(),
            'specificationsReady' => Plugin::hasMigration(Reviews::MIGRATION),
            'badgesReady' => Plugin::hasMigration(Catalog::BADGES_MIGRATION),
            'galleryReady' => Plugin::hasMigration(ProductImage::GALLERY_MIGRATION),
            'gallery' => ProductImage::gallery($product ?? []),
        ]);
    }

    public function saveProduct(): string
    {
        $id = (int)get_route_param('id');
        $catalog = new Catalog();
        $product = $id > 0 ? $catalog->product($id) : null;
        if ($id > 0 && !$product) {
            abort('', 404);
        }
        $form = request()->getData();
        $originalGallery = ProductImage::gallery($product ?? []);
        $upload = $hoverImage = $gallery = null;
        $addedPhotos = [];
        try {
            $upload = ProductImage::upload($_FILES['image'] ?? []);
            $hoverImage = ProductImage::upload($_FILES['hover_image'] ?? []);
            $newImage = $upload;
            if (Plugin::hasMigration(ProductImage::GALLERY_MIGRATION)) {
                $removed = array_filter((array)($form['remove_gallery'] ?? []), 'is_string');
                $gallery = array_values(array_diff($originalGallery, $removed));
                $addedPhotos = ProductImage::uploadGallery($_FILES['gallery'] ?? [], max(0, ProductImage::MAX_GALLERY - count($gallery)));
                $gallery = [...$gallery, ...$addedPhotos];
                if ($newImage === null && (empty($product['image_path']) || !empty($form['remove_image'])) && $gallery !== []) {
                    $newImage = array_shift($gallery);
                }
            }
            $catalog->saveProduct($id, $form, $newImage, $hoverImage, $gallery);
        } catch (\Throwable $error) {
            foreach ([$upload, $hoverImage, ...$addedPhotos] as $photo) {
                ProductImage::remove($photo);
            }
            if ($error instanceof ValidationError) {
                http_response_code(422);
                return $this->productForm($form, $error->getMessage());
            }
            log_error_details('Commerce product save failed', ['Product' => $id], $error);
            http_response_code(503);
            return $this->productForm($form, Plugin::t('error_retry'));
        }
        if ($newImage !== null || !empty($form['remove_image'])) {
            ProductImage::remove($product['image_path'] ?? null);
        }
        if ($hoverImage !== null || !empty($form['remove_hover_image'])) {
            ProductImage::remove($product['hover_image_path'] ?? null);
        }
        if ($gallery !== null) {
            foreach (array_diff($originalGallery, $gallery, [$newImage ?? '']) as $photo) {
                ProductImage::remove($photo);
            }
        }
        session()->setFlash('success', Plugin::t('saved'));
        response()->redirect(base_href(self::BASE . '/products'));
    }

    public function categories(): string
    {
        $categories = (new Catalog())->categories();
        $editing = (int)Input::query(request()->get('edit'), 10);
        $selected = array_column($categories, null, 'id')[$editing] ?? null;
        return $this->render('categories', 'categories', ['categories' => $categories, 'selected' => $selected]);
    }

    public function saveCategory(): never
    {
        $id = (int)get_route_param('id');
        $this->save(self::BASE . '/categories', static function () use ($id): void {
            $catalog = new Catalog();
            $existing = $id > 0 ? $catalog->category($id) : null;
            $form = request()->getData();
            $image = ProductImage::upload($_FILES['image'] ?? []);
            try {
                $catalog->saveCategory($id, $form, $image);
            } catch (\Throwable $error) {
                ProductImage::remove($image);
                throw $error;
            }
            if ($image !== null || !empty($form['remove_image'])) {
                ProductImage::remove($existing['image_path'] ?? null);
            }
        });
    }

    public function slides(): string
    {
        $repository = new Slides();
        $editing = (int)Input::query(request()->get('edit'), 10);
        return $this->render('slides', 'slides', [
            'slides' => $repository->listed(),
            'selected' => $editing > 0 ? $repository->find($editing) : null,
            'categories' => (new Catalog())->categories(),
        ]);
    }

    public function saveSlide(): never
    {
        $id = (int)get_route_param('id');
        $this->save(self::BASE . '/slides' . ($id > 0 ? '?edit=' . $id : ''), static function () use ($id): void {
            $repository = new Slides();
            $existing = $id > 0 ? $repository->find($id) : null;
            $image = ProductImage::upload($_FILES['image'] ?? []);
            try {
                $repository->save($id, request()->getData(), $image);
            } catch (\Throwable $error) {
                ProductImage::remove($image);
                throw $error;
            }
            if ($image !== null) {
                ProductImage::remove($existing['image_path'] ?? null);
            }
        });
    }

    public function deleteSlide(): never
    {
        $id = (int)get_route_param('id');
        $this->save(self::BASE . '/slides', static function () use ($id): void {
            $repository = new Slides();
            $existing = $repository->find($id);
            $repository->delete($id, Input::number(request()->post('revision'), 4294967295));
            ProductImage::remove($existing['image_path'] ?? null);
        });
    }

    public function reviews(): string
    {
        $page = Input::page(request()->get('page', 1));
        $status = request()->get('status');
        $status = in_array($status, Reviews::STATUSES, true) ? $status : 'pending';
        $rows = Plugin::hasMigration(Reviews::MIGRATION) ? (new Reviews())->queue($status, $page) : [];
        return $this->render('reviews', 'reviews', [
            'reviews' => array_slice($rows, 0, Reviews::PER_PAGE), 'status' => $status, 'page' => $page,
            'hasNext' => count($rows) > Reviews::PER_PAGE,
        ]);
    }

    public function moderateReview(): never
    {
        $id = (int)get_route_param('id');
        $this->save(self::BASE . '/reviews', static fn() => (new Reviews())->moderate($id, Input::query(request()->post('status'), 12)));
    }

    public function orders(): string
    {
        $status = Input::query(request()->get('status'), 24);
        $query = Input::query(request()->get('q'));
        $page = Input::page(request()->get('page', 1));
        $orders = (new Orders())->listed($status, $page, search: $query);
        return $this->render('orders', 'orders', [
            'status' => $status, 'query' => $query, 'page' => $page, 'hasNext' => count($orders) > 24,
            'orders' => array_slice($orders, 0, 24),
        ]);
    }

    public function order(): string
    {
        $orders = new Orders();
        $id = (int)get_route_param('id');
        $order = $orders->find($id);
        if (!$order) {
            abort('', 404);
        }
        return $this->render('order', 'order', [
            'title' => Plugin::t('order') . ' #' . $id, 'order' => $order, 'items' => $orders->items($id),
            'statuses' => $orders->nextStatuses($order), 'payment' => $orders->nextPayment($order),
            'events' => $orders->events($id),
        ]);
    }

    public function updateOrder(): never
    {
        $id = (int)get_route_param('id');
        $this->save(self::BASE . '/orders/' . $id, static fn() => (new Orders())->change(
            $id, Input::query(request()->post('type'), 24), Input::query(request()->post('value'), 24), (int)(get_user()['id'] ?? 0)
        ));
    }

    public function settings(): string
    {
        return $this->render('settings', 'settings', ['settings' => (new Catalog())->settings()]);
    }

    public function saveSettings(): never
    {
        $this->save(self::BASE . '/settings', static fn() => (new Catalog())->saveSettings(request()->getData()));
    }

    private function save(string $redirect, callable $save): never
    {
        try {
            $save();
            session()->setFlash('success', Plugin::t('saved'));
        } catch (ValidationError $error) {
            session()->setFlash('error', $error->getMessage());
        } catch (\Throwable $error) {
            log_error_details('Commerce admin save failed', [], $error);
            session()->setFlash('error', Plugin::t('error_retry'));
        }
        response()->redirect(base_href($redirect));
    }

    private function render(string $view, string $title, array $data = []): string
    {
        header('Cache-Control: private, no-store');
        return plugin_view(Plugin::SLUG, 'admin/' . $view, $data + ['title' => Plugin::t($title)]);
    }
}
