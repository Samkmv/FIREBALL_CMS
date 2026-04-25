<?php

namespace App\Helpers\Cart;

class Cart
{

    public static function getCart():array
    {
        return session()->get('cart') ?: [];
    }

    public static function addToCart(int $product_id, int $quantity = 1)
    {
        $added = false;
        $quantity = $quantity > 0 ? $quantity : 1;

        if (self::hasProductInCart($product_id)){
            session()->set("cart.$product_id.quantity", session()->get("cart.$product_id.quantity") + $quantity);
            $added = true;
        } else {
            $product = db()->query("select * from products where id = ? and in_stock = 1", [$product_id])->getOne();

            if ($product){
                $new_product = [
                    'id' => $product['id'],
                    'title' => $product['title'],
                    'slug' => $product['slug'],
                    'image' => get_image($product['image']),
                    'price' => $product['price'],
                    'quantity' => $quantity,
                ];

                session()->set("cart.$product_id", $new_product);
                $added = true;
            }
        }

        return $added;
    }

    public static function removeProductFromCart(int $product_id): bool
    {
        if (self::hasProductInCart($product_id)){
            session()->remove("cart.$product_id");
            return true;
        }

        return false;
    }

    public static function clearCart(): void
    {
        session()->remove("cart");
    }

    public static function updateProductQuantity(int $product_id, int $quantity): bool
    {
        $quantity = $quantity > 0 ? $quantity : 1;

        if (self::hasProductInCart($product_id)){
            session()->set("cart.$product_id.quantity", $quantity);
            return true;
        }

        return false;
    }

    public static function getCartQuantityItem(): int
    {
        return count(self::getCart());
    }

    public static function getCartQuantityTotal(): int
    {
        $cart = self::getCart();

        return array_sum(array_column($cart, 'quantity'));
    }

    public static function getCartSum(): int
    {
        $sum = 0;
        $cart = self::getCart();

        foreach ($cart as $item){
            $sum += $item['price'] * $item['quantity'];
        }

        return $sum;
    }

    public static function hasProductInCart(int $product_id): bool
    {
        return session()->has("cart.$product_id");
    }

}