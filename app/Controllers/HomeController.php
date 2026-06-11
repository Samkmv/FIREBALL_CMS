<?php

namespace App\Controllers;

use App\Helpers\Cart\Cart;
use App\Models\ContactRequest;
use App\Models\ContactSubject;
use App\Models\Page;
use App\Models\Post;
use App\Models\SiteSetting;
use FBL\Theme;

/**
 * Обрабатывает главную страницу и страницу контактов сайта.
 */
class HomeController extends BaseController
{

    protected ContactRequest $contactRequests;
    protected ContactSubject $contactSubjects;
    protected Page $pages;
    protected Post $posts;
    protected SiteSetting $siteSettings;

    /**
     * Инициализирует модели, используемые на публичных страницах контроллера.
     */
    public function __construct()
    {
        parent::__construct();
        $this->contactRequests = new ContactRequest();
        $this->contactSubjects = new ContactSubject();
        $this->pages = new Page();
        $this->posts = new Post();
        $this->siteSettings = new SiteSetting();
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

        $homepageType = $this->siteSettings->get('homepage_type', 'default');

        if ($homepageType === 'page') {
            $page = $this->pages->findPublishedById((int)$this->siteSettings->get('homepage_page_id', '0'));
            if ($page) {
                return $this->renderPageHomepage($page);
            }
        }

        if ($homepageType === 'posts') {
            return $this->renderPostsHomepage();
        }

        return $this->renderDefaultHomepage();
    }

    /**
     * Renders the original system homepage.
     */
    protected function renderDefaultHomepage(): string
    {
        $featured_posts = $this->posts->getHomeFeaturedPosts(10);

        return Theme::render('home', [
            'title' => return_translation('home_index_title'),
            'sales_products' => [],
            'root_categories' => [],
            'featured_posts' => $featured_posts,
        ]);
    }

    /**
     * Renders a CMS page as the site root while keeping canonical URL at "/".
     */
    protected function renderPageHomepage(array $page): string
    {
        return Theme::render('page', [
            'title' => $page['title'],
            'page' => $page,
            'seo_title' => $page['meta_title'] !== '' ? $page['meta_title'] : $page['title'],
            'seo_description' => $page['meta_description'],
            'seo_canonical' => base_href('/'),
        ]);
    }

    /**
     * Renders a latest-posts feed as the site root.
     */
    protected function renderPostsHomepage(): string
    {
        $limit = max(1, min(100, (int)$this->siteSettings->get('posts_per_page', '10')));
        $posts = $this->posts->getLatestPublishedPosts($limit);
        $sidebarData = $this->posts->getSidebarData();

        return Theme::render('posts', [
            'title' => return_translation('posts_index_title'),
            'posts' => $posts,
            'total_posts' => count($posts),
            'pagination' => null,
            'current_category' => null,
            'current_category_label' => null,
            'categories' => $sidebarData['categories'],
            'trending_posts' => $sidebarData['trending_posts'],
            'seo_title' => return_translation('posts_index_title'),
            'seo_canonical' => base_href('/'),
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
            'contact_subjects' => $this->getContactSubjectOptions(),
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

        if ($data['subject'] === '' || !in_array($data['subject'], $this->getContactSubjectOptions(), true)) {
            $errors['subject'][] = return_translation('contacts_validation_subject');
        }

        if ($data['message'] === '') {
            $errors['message'][] = return_translation('contacts_validation_message');
        }

        return $errors;
    }

    protected function getContactSubjectOptions(): array
    {
        return $this->contactSubjects->getActiveNames();
    }

}
