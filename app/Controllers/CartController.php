<?php

namespace App\Controllers;

use App\Helpers\Cart\Cart;

class CartController extends BaseController
{

    public function addToCart()
    {
        $product_id = (int) request()->get('product_id');

        if (!$product_id) {
            response()->text('Product id is required', 400);
        }

        if (Cart::addToCart($product_id)){
            $mini_cart = view()->renderPartial('incs/mini-cart');

            response()->json(['data' => 'Product added to cart successfully.', 'mini_cart' => $mini_cart, 'cart_qty' => Cart::getCartQuantityTotal()]);
        }

        response()->text('Product already exists in cart', 400);
    }

    public function removeFromCart()
    {
        $product_id = (int) request()->get('product_id');

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