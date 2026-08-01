# FIREBALL CMS Editor 2.0

Editor 2.0 — полноэкранный гибридный редактор записей и страниц. Пользователь пишет
как в обычном документе, но каждый смысловой элемент хранится отдельным блоком.

## Формат и совместимость

Основное поле `content` по-прежнему содержит готовый публичный HTML. В конце документа
редактор сохраняет скрытый снимок состояния:

```html
<template data-fb-editor-state="2">base64(JSON)</template>
```

Это сохраняет совместимость со старыми шаблонами и одновременно позволяет без потерь
восстановить блоки, их порядок и настройки. Поддерживаются три входных формата:

1. HTML со снимком Editor 2.0;
2. прежний JSON `{ "version": 1|2, "blocks": [...] }`;
3. обычный старый HTML, который клиентский importer разбивает на блоки.

Миграция базы данных не требуется. Новые поля `settings` и `meta` необязательны.

## Архитектура клиента

- `registry.js` — публичный Block/Plugin API, actions и filters;
- `sanitizer.js` — allowlist-очистка вставляемого HTML и URL;
- `importer.js` — HTML, Markdown, plain text, внутренний clipboard и snapshot;
- `history.js` — ограниченная история документа с объединением ввода;
- `editor.js` — состояние, UI, команды, drag-and-drop, autosave и preview.

Редактор не использует `document.execCommand`. Форматирование строится через
`Selection`, `Range`, безопасные DOM-узлы и собственную историю.

## Block API

Плагин может зарегистрировать тип блока после загрузки `registry.js`:

```js
window.FireballEditor2.registerBlockType('productCard', {
    title: 'Карточка товара',
    icon: 'ci-shopping-bag',
    defaults: {
        productId: '',
        title: '',
        price: ''
    },
    supports: {
        align: true,
        spacing: true,
        visibility: true
    },
    renderEditor(block, context) {
        return '<div class="my-product-card">' +
            '<input value="' + context.escape(block.data.title || '') + '"' +
            ' data-editor-field="data.title">' +
            '</div>';
    },
    renderPublic(block, context) {
        return '<article class="product-card"><h3>' +
            context.escape(block.data.title || '') +
            '</h3></article>';
    }
});
```

Доступные методы:

- `registerBlockType(name, definition)` и `unregisterBlockType(name)`;
- `getBlockType(name)` и `getBlockTypes()`;
- `registerExtension(name, extension)`;
- `addAction(name, callback, priority)` и `doAction(name, ...args)`;
- `addFilter(name, callback, priority)` и `applyFilters(name, value, ...args)`;
- `getEditors()` — активные экземпляры редактора.

Полезные client actions: `editor:ready`, `editor:change`, `selection:change`,
`mode:change`, `paste:blocks`, `preview:open`, `autosave:success`,
`autosave:error`.

Старые события формы `fireball:post-editor-sync` и
`fireball:post-editor-serialize` сохранены.

## Серверное расширение

Типы и конфигурация расширяются стандартными фильтрами CMS:

```php
add_filter('fireball_editor_block_types', static function (array $types): array {
    $types['productCard'] = [
        'machine_name' => 'productCard',
        'title' => 'Карточка товара',
        'icon' => 'ci-shopping-bag',
        'defaults' => ['productId' => '', 'title' => '', 'price' => ''],
        'supports' => ['align' => true, 'spacing' => true, 'visibility' => true],
    ];

    return $types;
});

add_filter('fireball_editor_render_block', static function (
    mixed $html,
    array $block
): mixed {
    if (($block['type'] ?? '') !== 'productCard') {
        return $html;
    }

    $title = htmlSC((string)($block['data']['title'] ?? ''));
    return '<article class="product-card"><h3>' . $title . '</h3></article>';
}, 10);
```

Доступны также:

- `fireball_editor_config`;
- `fireball_editor_style_assets`;
- `fireball_editor_script_assets`;
- `fireball_editor_embed_hosts`.

Неизвестные типы не удаляются из снимка. Для публичного вывода плагин обязан
реализовать `renderPublic` на клиенте и/или `fireball_editor_render_block` на сервере.

## Smart Paste и безопасность

При вставке редактор сначала проверяет внутренний MIME
`application/x-fireball-blocks+json`, затем HTML, Markdown и обычный текст.
Office-разметка и опасные атрибуты удаляются. Ссылки, изображения, таблицы,
заголовки, списки и цитаты превращаются в отдельные блоки.

Короткий форматированный фрагмент без блочных HTML-элементов вставляется в текущий
абзац и сохраняет разрешённое inline-форматирование. Если полноценная статья
вставляется в середину существующего абзаца, редактор делит абзац по выделению:
текст до курсора остаётся перед импортированными блоками, а текст после курсора
становится отдельным текстовым блоком после них. Поэтому Smart Paste не меняет
логический порядок уже набранного документа.

В списках `Enter` на пустом пункте завершает список и создаёт текстовый блок.
`Backspace` на пустом пункте удаляет только этот пункт, а пустой список превращает
в обычный абзац, не удаляя соседние блоки.

Клиентская очистка не заменяет серверную: перед записью и публичным выводом контент
проходит `sanitize_content_html`. Embed разрешён только для YouTube/Vimeo или хостов,
добавленных фильтром `fireball_editor_embed_hosts`.

## Сохранение и восстановление

- Изменения формы и блоков автосохраняются с debounce.
- Новая сущность после первого autosave получает ID, edit URL и preview URL без
  перезагрузки страницы.
- Уже опубликованный материал сохраняет свой статус; autosave не переводит его в
  черновик.
- При сетевой ошибке состояние кладётся в `localStorage`.
- Более свежая локальная версия предлагается для восстановления при следующем входе.
- Перед уходом с несохранёнными изменениями показывается стандартное предупреждение.

## Горячие клавиши

| Комбинация | Действие |
|---|---|
| `Cmd/Ctrl + Z` | Отменить |
| `Cmd/Ctrl + Shift + Z` | Повторить |
| `Cmd/Ctrl + K` | Добавить/изменить ссылку |
| `Cmd/Ctrl + B` | Жирный |
| `Cmd/Ctrl + I` | Курсив |
| `Cmd/Ctrl + U` | Подчёркивание |
| `Cmd/Ctrl + Shift + P` | Палитра команд |
| `/` в пустом абзаце | Slash-команды |
| `Shift + ↑/↓` | Переместить выбранные блоки |
| `Backspace` в начале | Объединить с предыдущим блоком |

## Проверка

```bash
php tests/editor2_unit.php
php -l app/Modules/BlockEditor/BlockRenderer.php
node --check public/assets/default/js/editor2/editor.js
```

Отдельный аудит исходной реализации и решения по совместимости находятся в
`docs/editor-2-audit.md`.
