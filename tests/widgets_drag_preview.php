<?php
// Standalone visual fixture: real editor assets and handlers, synthetic widgets, no database.
if (PHP_SAPI !== 'cli-server') exit(1);
$widgetPreviewPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($widgetPreviewPath, '/assets/default/')) return false;
if ($widgetPreviewPath === '/save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'revision' => 2, 'message' => 'Тестовый порядок обновлён']);
    exit;
}
if ($widgetPreviewPath !== '/') { http_response_code(404); exit; }
?>
<!doctype html>
<html lang="ru" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Проверка индикатора вставки виджетов</title>
<link rel="stylesheet" href="/assets/default/css/theme.min.css">
<link rel="stylesheet" href="/assets/default/css/admin-ui.css">
<link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css">
<link rel="stylesheet" href="/assets/default/css/admin-widgets.css">
<style>main { width:min(760px,100%); margin:3rem auto; padding:0 1.5rem } .preview-controls { display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem }</style>
</head><body class="fb-admin-body"><main>
<h1 class="h4 mb-2">Индикатор вставки виджетов</h1>
<p class="small text-body-secondary">Проверка реального редактора на тестовых карточках.</p>
<div class="preview-controls">
<button class="btn btn-outline-secondary btn-sm" data-preview="start">В начало</button>
<button class="btn btn-outline-secondary btn-sm" data-preview="middle">Между виджетами</button>
<button class="btn btn-outline-secondary btn-sm" data-preview="end">В конец</button>
<button class="btn btn-outline-secondary btn-sm" data-preview="cancel">Отменить</button>
</div>
<div id="widget-editor" data-revision="1" data-save-url="/save" data-error="Тестовая страница не сохраняет данные">
<input type="hidden" name="needCSRFToken" value="TEST-CSRF">
<div class="alert d-none" role="status" data-widget-status></div>
<div data-widget-content><div class="card widget-area-card"><div class="card-body p-4">
<div class="widget-area-heading"><i class="ci-sidebar" aria-hidden="true"></i><h2 class="h5 mb-0">Боковая панель публикаций</h2></div>
<div class="vstack gap-3 widget-dropzone" data-widget-area="posts.sidebar">
<?php foreach ([1 => ['Категории', 'ci-folder'], 2 => ['Свежие записи', 'ci-trending-up'], 3 => ['Погода', 'ci-cloud']] as $widgetPreviewId => [$widgetPreviewName, $widgetPreviewIcon]): ?>
<article class="widget-instance" data-widget-id="<?= $widgetPreviewId ?>" data-allowed-areas='["posts.sidebar"]'>
<div class="widget-instance-header">
<button class="btn btn-sm btn-outline-secondary widget-drag-handle widget-icon-button" type="button" draggable="true" data-widget-handle aria-label="Переместить виджет"><i class="ci-move" aria-hidden="true"></i></button>
<span class="widget-type-icon"><i class="<?= $widgetPreviewIcon ?>" aria-hidden="true"></i></span>
<div class="widget-instance-title"><span class="fw-semibold"><?= $widgetPreviewName ?></span></div>
</div>
<details class="widget-settings-panel"><summary><i class="ci-sliders" aria-hidden="true"></i><span>Настройки</span><i class="ci-chevron-down widget-settings-chevron" aria-hidden="true"></i></summary></details>
</article>
<?php endforeach; ?>
</div></div></div></div></div>
<p class="small text-body-secondary mt-3">Оранжевая линия отмечает место вставки. Подсветка исчезает при отмене.</p>
</main><script src="/assets/default/js/admin-widgets.js"></script>
<script>
    const previewCard = document.querySelector('[data-widget-id="3"]');
    const previewArea = document.querySelector('[data-widget-area]');
    let previewActive = false;
    document.querySelectorAll('[data-preview]').forEach(button => button.addEventListener('click', () => {
        if (previewActive) {
            previewCard.querySelector('[data-widget-handle]').dispatchEvent(new DragEvent('dragend', { bubbles: true }));
            previewActive = false;
        }
        if (button.dataset.preview === 'cancel') return;
        const handle = previewCard.querySelector('[data-widget-handle]');
        handle.dispatchEvent(new DragEvent('dragstart', { bubbles: true, dataTransfer: new DataTransfer() }));
        previewActive = true;
        const first = previewArea.children[0].getBoundingClientRect();
        const second = previewArea.children[1].getBoundingClientRect();
        const area = previewArea.getBoundingClientRect();
        const y = button.dataset.preview === 'start' ? first.top : button.dataset.preview === 'end' ? area.bottom + 1 : second.top + 1;
        previewArea.dispatchEvent(new DragEvent('dragover', { bubbles: true, cancelable: true, clientY: y }));
    }));
</script></body></html>
