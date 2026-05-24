<?php

namespace App\Controllers;

use App\Models\Analytics;
use FBL\Controller;

/**
 * Базовый контроллер пользовательской части, который выполняет общую инициализацию для наследников.
 */
class BaseController extends Controller
{

    /**
     * Запускает общие действия для публичных страниц, например сбор аналитики.
     */
    public function __construct()
    {
        (new Analytics())->trackPublicRequest();

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
