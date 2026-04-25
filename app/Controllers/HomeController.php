<?php

namespace App\Controllers;

use App\Helpers\Cart\Cart;
use App\Models\ContactRequest;
use App\Models\Post;

/**
 * Обрабатывает главную страницу и страницу контактов сайта.
 */
class HomeController extends BaseController
{

    protected ContactRequest $contactRequests;
    protected Post $posts;

    /**
     * Инициализирует модели, используемые на публичных страницах контроллера.
     */
    public function __construct()
    {
        parent::__construct();
        $this->contactRequests = new ContactRequest();
        $this->posts = new Post();
    }

    /**
     * Формирует данные для главной страницы с товарами, категориями и постами.
     */
    public function index()
    {
//        session()->setFlash('success', 'Успешно!');
//        session()->setFlash('error', 'Ошибка!');

//        Cart::clearCart();
//        dump(Cart::getCart());

        $sales_products = db()->query("select * from products where is_sale = 1 order by id desc limit 10")->get();

        $root_categories = db()->query("select * from categories where parent_id = 0")->get();
        $featured_posts = $this->posts->getHomeFeaturedPosts(8);

        return view('home/index', [
            'title' => return_translation('home_index_title'),
            'sales_products' => $sales_products,
            'root_categories' => $root_categories,
            'featured_posts' => $featured_posts,
        ]);
    }

    /**
     * Показывает страницу контактов и обрабатывает отправку формы обратной связи.
     */
    public function contacts()
    {
        if (request()->isPost()) {
            $data = $this->normalizeContactData(request()->getData());
            $errors = $this->validateContactData($data);

            if (!empty($errors)) {
                session()->set('form_data', $data);
                session()->set('form_errors', $errors);
                session()->setFlash('error', return_translation('contacts_form_error'));
                response()->redirect(base_href('/contacts'));
            }

            $this->contactRequests->create($data);
            session()->remove('form_data');
            session()->remove('form_errors');
            session()->setFlash('success', return_translation('contacts_form_success'));
            response()->redirect(base_href('/contacts'));
        }

        return view('home/contacts', [
            'title' => return_translation('contacts_page_title'),
            'footer_scripts' => [
                base_url('/assets/default/js/contact.js?v=' . filemtime(WWW . '/assets/default/js/contact.js')),
            ],
        ]);
    }

    /**
     * Нормализует данные формы контактов перед валидацией и сохранением.
     */
    protected function normalizeContactData(array $data): array
    {
        return [
            'name' => trim((string)($data['name'] ?? '')),
            'email' => mb_strtolower(trim((string)($data['email'] ?? ''))),
            'subject' => trim((string)($data['subject'] ?? '')),
            'message' => trim((string)($data['message'] ?? '')),
        ];
    }

    /**
     * Проверяет обязательные поля и формат e-mail в форме контактов.
     */
    protected function validateContactData(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'][] = return_translation('contacts_validation_name');
        }

        if ($data['email'] === '') {
            $errors['email'][] = return_translation('contacts_validation_email');
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = return_translation('contacts_validation_email_invalid');
        }

        if ($data['subject'] === '') {
            $errors['subject'][] = return_translation('contacts_validation_subject');
        }

        if ($data['message'] === '') {
            $errors['message'][] = return_translation('contacts_validation_message');
        }

        return $errors;
    }

}
