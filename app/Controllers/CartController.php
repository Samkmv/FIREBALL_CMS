<?php

namespace App\Controllers;

use App\Helpers\Cart\Cart;

/**
 * Управляет добавлением и удалением товаров из корзины.
 */
class CartController extends BaseController
{

    /**
     * Добавляет товар в корзину и возвращает обновлённые данные мини-корзины.
     */
    public function addToCart()
    {
        $product_id = (int) request()->post('product_id');

        if (!$product_id) {
            response()->text('Product id is required', 400);
        }

        if (Cart::addToCart($product_id)){
            $mini_cart = view()->renderPartial('incs/mini-cart');

            response()->json(['data' => 'Product added to cart successfully.', 'mini_cart' => $mini_cart, 'cart_qty' => Cart::getCartQuantityTotal()]);
        }

        response()->text('Product not found in cart', 400);
    }

    /**
     * Удаляет товар из корзины и возвращает обновлённые данные мини-корзины.
     */
    public function removeFromCart()
    {
        $product_id = (int) request()->post('product_id');

        if (!$product_id) {
            response()->text('Product id is required', 400);
        }

        if (Cart::removeProductFromCart($product_id)){
            $mini_cart = view()->renderPartial('incs/mini-cart');

            response()->json(['data' => 'Product removed from cart successfully.', 'mini_cart' => $mini_cart, 'cart_qty' => Cart::getCartQuantityTotal()]);
        }

        response()->text('Product already exists in cart', 400);
    }

}
