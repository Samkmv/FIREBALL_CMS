# Пагинация

Объект `$pagination` сохраняет старый HTML-интерфейс и поддерживает доступ как к массиву:

```php
<?php if ($pagination && $pagination['has_prev']): ?>
    <a href="<?= htmlSC($pagination['prev_url']) ?>">Назад</a>
<?php endif; ?>

<?= $pagination ?>
```

Поля: `current_page`, `total_pages`, `total_records`, `per_page`, `has_next`, `has_prev`, `next_url`, `prev_url`.

Не рассчитывайте номера страниц в теме повторно.
