# Меню страниц

CMS поддерживает три типа меню страниц:

- `header` — основное меню;
- `footer` — ссылки в навигационном блоке футера;
- `legal_information` — отдельный блок правовой информации.

## Legal information

В меню `legal_information` входят только опубликованные страницы с включённым
полем `show_in_legal_information`.

Сортировка:

1. `menu_order` по возрастанию;
2. локализованное название по алфавиту;
3. идентификатор страницы.

Список доступен через Theme API:

```php
<?php $items = theme()->getLegalInformationMenu(); ?>
```

И через HTTP API:

```text
GET /api/v1/menu/legal_information
```

Пример ответа:

```json
{
  "status": "success",
  "type": "legal_information",
  "data": [
    {
      "id": 12,
      "href": "/privacy-policy",
      "label": "Политика конфиденциальности",
      "menu_order": 10
    }
  ]
}
```

Для локализации названия страницы используется ключ
`page_menu_<slug>` в языковом файле приложения. Если перевода нет, CMS
возвращает сохранённое название страницы на языке по умолчанию.
