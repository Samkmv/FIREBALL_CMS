# SEO

Страницы и записи получают единые поля:

```php
$page['seo_title'];
$page['seo_description'];
$page['seo_keywords'];

$post['seo_title'];
$post['seo_description'];
$post['seo_keywords'];
```

Layout также получает `$seo_title`, `$seo_description`, `$seo_keywords`, `$seo_image`, `$seo_canonical` и `$seo_robots`.

Используйте переданные значения и настройки сайта как fallback. Не генерируйте второй canonical или второй набор meta-тегов в дочернем шаблоне.
