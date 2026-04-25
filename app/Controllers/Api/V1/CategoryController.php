<?php

namespace App\Controllers\Api\V1;

/**
 * Предоставляет простые API-методы для работы с категориями.
 */
class CategoryController
{

    /**
     * Возвращает список всех категорий в формате JSON.
     */
    public function index()
    {
        $categories = db()->findAll('categories');
        response()->json(['status' => 'success', 'data' => $categories]);
    }

    /**
     * Возвращает тестовый ответ для просмотра категории через API.
     */
    public function view()
    {
        return 'Hello from category view API';
    }

}
