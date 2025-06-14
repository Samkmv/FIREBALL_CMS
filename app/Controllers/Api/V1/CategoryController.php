<?php

namespace App\Controllers\Api\V1;

class CategoryController
{

    public function index()
    {
        $categories = db()->findAll('categories');
        response()->json(['status' => 'success', 'data' => $categories]);
    }

    public function view()
    {
        return 'Hello from category view API';
    }

}