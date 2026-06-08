<?php

namespace App\Controllers;

use FBL\Controller;

/**
 * Базовый контроллер пользовательской части, который выполняет общую инициализацию для наследников.
 */
class BaseController extends Controller
{

    public function __construct()
    {
//        if (!$menu = cache()->get('menu')) {
//            cache()->set('menu', $this->renderMenu(), 1);
//        }

    }

    /**
     * Рендерит общее меню сайта как частичное представление.
     */
    public function renderMenu(): string
    {
        return view()->renderPartial('incs/menu');
    }

}
