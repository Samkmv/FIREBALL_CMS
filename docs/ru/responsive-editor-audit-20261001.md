# Адаптивность чата и блочного редактора — 01.10.2026

## Область работ и причины

Аудит выполнен в локальном checkout FIREBALL CMS. Боевой сайт, GitHub и база данных не изменялись. Реальные шаблоны и загружаемые ресурсы проверены вместе, включая копии ресурсов темы. Предыдущие исправления общего viewport не заменялись параллельной системой.

1. **Viewport чата и редактора.** В `app-viewport.js` клавиатура определялась относительно первого измерения `visualViewport.height`. При возобновлении приложения или смене ориентации с уже открытой клавиатурой первое измерение могло быть уменьшенным: PWA переходила в ветку закрытой клавиатуры и получала полную высоту за клавиатурой. Теперь закрытая CSS-высота PWA измеряется до определения состояния клавиатуры. В обычном браузере начальный ориентир учитывает layout height. Открытая клавиатура по-прежнему использует только visual height и offsetTop, без дополнительного сдвига composer.
2. **Конкурирующая прокрутка.** `resizeMessageInput` / `resizeInput` сохраняли новый якорь уже после изменения высоты приложения, отменяя восстановление из обработчика viewport. Во время перехода теперь используется один сохранённый якорь. Последние сообщения остаются у нижнего края; при чтении истории сохраняется расстояние до конца истории. Это применяется в личных и групповых сообщениях, в обеих копиях JS.
3. **Исходящие сообщения.** `.chat-message-stack` имел `width:fit-content`, но не имел flex-выравнивания содержимого. Широкая строка действий увеличивала stack, а короткий bubble оставался слева внутри него. Stack теперь колонка с выравниванием собственных bubble/actions вправо и `margin-left:auto`; padding области сообщений не меняется. Входящие остаются слева.
4. **Отступ composer.** Убрана арифметика вычитания внутреннего padding из safe-area. В закрытом приложении нижний inset учитывается один раз в composer через `max(.5rem, env(safe-area-inset-bottom))`. При клавиатуре остаётся небольшой обычный отступ; shell не добавляет второй inset и не применяет keyboardHeight к composer. Composer остаётся обычным flex-элементом, а не fixed-кнопкой.
5. **Шапка редактора.** Мобильная identity ограничивалась `min(32vw,170px)`, а заголовок имел nowrap/ellipsis. Первая строка теперь содержит навигацию и полный переносимый заголовок, следующая — действия. Высота верхнего ряда form рассчитывается по содержимому. На узких экранах действия переносятся без уменьшения touch-area. Десктопная grid раньше сжимала кнопки с длинными подписями: колонка действий теперь определяется по содержимому, кнопки не сжимаются. На размерах до 1199px preview и draft доступны через существующее меню «Ещё». В том же меню доступны мобильные режимы «Документ / Структура».
6. **Форматирование.** Старые размеры select были 126/116/78px, но мобильный font-size увеличивался до 16px для защиты от autozoom, что делало подписи нечитаемыми. На телефоне select имеют ширины 190/140/100px, flex-shrink отключён; панель форматирования горизонтально прокручивается. Кнопки форматирования, действий, drag handle и пунктов контекстного меню имеют мобильную touch-area 44px.
7. **Действия блоков.** Toolbar был absolute с `top:-36px`, вне границы карточки. Для него существовали отдельные поправки structure/mobile и подъём блока через z-index. Теперь header находится внутри поверхности блока, перед content. Нет отрицательного top, hover-зависимости доступности действий или слоя поверх предыдущего блока. Selected accent сохранён. Мобильные wide/full блоки остаются внутри canvas, не обрезая header; публичное отображение этих настроек не изменяется.
8. **Меню и выбор.** Старое открытие «Ещё» меняло только activeId, но не selectedIds; удаление из меню использовало selectedIds. Теперь при открытии меню для другого блока согласуются выбранный блок и активный блок. Существующее множественное выделение сохраняется, если меню открывают у уже выделенного блока. После обновления структуры её anchor заново находится в DOM.
9. **Прокрутка редактора.** `scrollToBlock` вызывал `scrollIntoView` всей цепочки предков. В workspace теперь прокручивается только `.fb-editor-workspace__document`, с учётом фактической высоты sticky-toolbar. Для использования редактора вне workspace оставлен штатный fallback.

## Единая архитектура действий

В существующий `FireballEditor` добавлены два небольших метода:

- `renderBlockActions(block,index)` — один список действий и состояние disabled для первого/последнего блока.
- `renderBlockHeader(block,index,outline)` — иконка/локализованное имя типа плюс общие actions; в canvas добавляется существующий drag handle, в outline — кнопка выбора.

Document и Structure используют эти методы и один `handleBlockAction`. Обработчики reorder, duplicate, visibility, copy, confirmation/remove, история и сериализация не дублируются. На телефоне постоянны ↑, ↓, delete, more; copy/duplicate/hide остаются в существующем контекстном меню. В узкой структуре применяется тот же сокращённый ряд. В HTML нет вложенных button: карточка структуры — div, выбор и действия — отдельные кнопки.

## Изменённые рабочие файлы

- `public/assets/default/js/app-viewport.js` — начальное определение клавиатуры и восстановление после rotation/resume.
- `public/assets/default/js/chat.js`, `public/assets/default/js/chat-group.js` — общий якорь при изменении textarea и viewport.
- `themes/default/assets/js/chat.js`, `themes/default/assets/js/chat-group.js` — аналогичная поправка копий темы без их полного перезаписывания.
- `public/assets/default/css/style.css`, `themes/default/assets/css/style.css` — outgoing alignment и корректный нижний inset composer.
- `public/assets/default/js/editor2/editor.js` — shared header/actions, outline delegation, корректный выбор для контекстного меню, внутренняя прокрутка документа.
- `public/assets/default/css/block-editor.css` — внутренние headers, responsive topbar/toolbar, touch-areas, мобильные wide/full, удаление прежних floating-toolbar/rail/outline правил.
- `app/Views/themes/default/admin/post_form.php` — preview/draft и mobile mode в существующем меню действий. Тот же шаблон используется для записей и страниц.

Просмотрены, но не изменены: `chat-viewport.js`, чатовые index/group/viewport, базовый layout, BlockEditorService/editor.php, theme copy `block-editor.css`, PwaService. Footer чата остаётся normal flow. Bottom sheet редактора остаётся позиционированным относительно уже фиксированного workspace/form: независимый второй viewport для него не создан.

Ресурсы уже используют versioned URL/filemtime. Service worker сопоставляет полный URL, не игнорируя query, и не кеширует приватный HTML чата/админки. Новая параллельная система cache busting не добавлялась.

## Проверки

Регрессионные тесты:

- `tests/app_viewport_unit.cjs` — фокус/blur, stale pan, delayed keyboard dismissal, первое наблюдение с открытой клавиатурой, поворот при вводе, pinch zoom, touch boundaries, несколько владельцев, release на desktop.
- `tests/chat_anchor_unit.cjs` (новый) — исполняет реальные функции viewport/textarea в личном и групповом чате, в обеих копиях ресурсов; проверяет last-message/history anchors и конкурирующий resize.
- `tests/chat_viewport_unit.cjs` — 69 проверок общих PWA/browser адаптеров.
- `tests/chat_mobile_geometry.cjs` — реальные CSS и inline-правила шаблона, светлая/тёмная темы, 320/360/390/393/430px; right edge короткого/длинного сообщения, reply и attachment; геометрия composer/header при смоделированных keyboard/pan/close; desktop и opt-in diagnostics.
- `tests/editor2_browser.cjs` — реальная шапка post_form и компонент editor.php со всеми базовыми стилями CMS и локальными шрифтами; 1920/1440/1280/1024/768/430/393/390/360/320px. Ввод, форматирование, duplicate, visibility, delete confirmation, move, undo/redo, round-trip snapshot, actions в outline, drag/drop DOM events, header boundaries и отсутствие внешнего scroll. PWA-модель 390×844 и landscape 844×390: workspace/footer/sheet, клавиатура с pan, закрытие sheet и восстановление прокрутки. Видео использует тот же header.
- `tests/editor2_fixture.php` — fixture включает реальную шапку production view, структуру и UTF-8/mobile meta, а не упрощённые кнопки.
- `tests/editor2_reliability.cjs` — autosave race/serialization, draft recovery, final submit, title-only recovery, partial gallery upload, history branching, импорт повторяющихся IDs.
- `tests/chat_selection_guard.cjs` — SSE, deferred updates и сохранность выделения текста.

Браузерные проверки выполнены в локальном Chromium на тестовых страницах без записи в CMS. Проверены синтаксис JS/PHP и `git diff --check`. Снимки тестового редактора просмотрены визуально.

## Ограничения и риски

Это не проверка нативной клавиатуры реального iPhone, Android или установленной PWA. В browser suite visualViewport и standalone моделируются. WebKit в доступной среде не запускается; результат нельзя выдавать за доказанный native iOS Safari fix. Нужна контрольная проверка на устройстве: открытие/закрытие клавиатуры несколько раз, history scroll, поворот при вводе, возврат из фона, zoom, safe-area и открытие/закрытие sheet.

Основной оставшийся риск iOS — реальное поведение fixed layout / visualViewport при смене ориентации и восстановлении приложения. `100vh` используется только как измеритель закрытого native extent PWA; открытое приложение следует visualViewport. Нативные размеры и safe-area не подменяются фиксированными пикселями.

Для desktop сохранены колонки редактора, настройки, preview/draft и существующие обработчики. Намеренное изменение: headers/actions блоков теперь видны внутри карточки постоянно. На среднем размере часть действий шапки переносится в меню, а не сжимается. Сохранение, публикация и реальная сеть/загрузка файлов на боевом сайте не выполнялись; их локальная логика покрыта имеющимися regression tests.

Удалённые workaround: отрицательный top floating actions, отдельные mobile/structure offsets этой панели, padding-top:55px ради панели, подъём активного блока z-index:10, transform выбранной карточки, лимит mobile identity 32vw, старые rail/meta/outline стили и вычитание inner padding из composer safe-area. Временные console.log и закомментированные старые реализации не добавлялись.
