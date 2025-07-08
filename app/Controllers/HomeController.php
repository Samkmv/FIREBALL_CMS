<?php

namespace App\Controllers;

use App\Helpers\Cart\Cart;

class HomeController extends BaseController
{

    public function index()
    {
//        session()->setFlash('success', 'Успешно!');
//        session()->setFlash('error', 'Ошибка!');

//        Cart::clearCart();
//        dump(Cart::getCart());

        $sales_products = db()->query("select * from products where is_sale = 1 order by id desc limit 10")->get();

        $root_categories = db()->query("select * from categories where parent_id = 0")->get();

        return view('home/index', [
            'title' => return_translation('home_index_title'),
            'sales_products' => $sales_products,
            'root_categories' => $root_categories,
        ]);
    }

}