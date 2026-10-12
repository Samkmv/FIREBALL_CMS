<?php
// Private browser fixture: production widget view and CMS modal, synthetic cards, no database.
$previewDirectory = realpath((string)getenv('WIDGET_PREVIEW_DIR'));
if (PHP_SAPI !== 'cli-server' || !$previewDirectory || !str_starts_with($previewDirectory, __DIR__ . '/.tmp/widgets-delete-')) {
    http_response_code(404); exit;
}
$previewPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($previewPath, '/assets/default/')) return false;
$translations = require dirname(__DIR__) . '/app/Languages/ru.php';
function return_translation(string $key): string { global $translations; return $translations[$key] ?? $key; }
function htmlSC(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function print_translation(string $key): void { echo htmlSC(return_translation($key)); }
function base_href(string $path): string { return '/save'; }
function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="PREVIEW-CSRF">'; }
function preview_cards(?int $deleted = null): string
{
    ob_start(); ?>
    <div class="card widget-area-card"><div class="card-body p-4">
        <div class="widget-area-heading"><i class="ci-sidebar" aria-hidden="true"></i><h2 class="h5 mb-0">Боковая панель публикаций</h2></div>
        <div class="vstack gap-3 widget-dropzone" data-widget-area="posts.sidebar">
        <?php foreach ([1 => ['Погода', 'ci-cloud'], 2 => ['Недоступный виджет', 'ci-box']] as $id => [$label, $icon]): if ($id === $deleted) continue; ?>
            <article class="widget-instance" data-widget-id="<?= $id ?>" data-allowed-areas='["posts.sidebar"]'>
                <div class="widget-instance-header"><span class="widget-type-icon"><i class="<?= $icon ?>" aria-hidden="true"></i></span><div class="widget-instance-title"><span class="fw-semibold"><?= $label ?></span></div></div>
                <?php if ($id === 1): ?>
                <details class="widget-settings-panel" open><summary><i class="ci-sliders" aria-hidden="true"></i><span>Настройки</span></summary>
                    <form data-widget-settings><label class="form-check mb-3"><input type="checkbox" class="form-check-input" data-widget-enabled checked><span class="form-check-label">Включён</span></label>
                        <div class="widget-settings-actions"><button type="button" class="btn btn-outline-danger btn-sm" data-widget-delete><i class="ci-trash" aria-hidden="true"></i>Удалить</button></div>
                    </form>
                </details>
                <?php else: ?><p class="small text-body-secondary">Тип или область недоступны.</p><button type="button" class="btn btn-outline-danger btn-sm" data-widget-delete>Удалить</button><?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
    </div></div>
    <?php return ob_get_clean();
}
function view(): object
{
    return new class {
        public function renderPartial(string $name, array $data = []): string {
            return match ($name) {
                'admin/shell_open' => '<h1 class="h4 mb-4">Виджеты</h1>',
                'admin/widgets_editor' => preview_cards(),
                default => '',
            };
        }
    };
}
if ($previewPath === '/save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $valid = ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') === 'PREVIEW-CSRF' && ($payload['action'] ?? '') === 'delete' && in_array($payload['id'] ?? 0, [1, 2], true);
    file_put_contents($previewDirectory . '/requests.jsonl', json_encode(['payload' => $payload, 'valid' => $valid]) . "\n", FILE_APPEND);
    header('Content-Type: application/json');
    http_response_code($valid ? 200 : 422);
    echo json_encode(['status' => $valid ? 'ok' : 'error', 'revision' => 2, 'message' => 'Виджет удалён', 'html' => preview_cards($payload['id'] ?? null)]);
    exit;
}
if ($previewPath !== '/') { http_response_code(404); exit; }
$snapshot = ['revision' => 1]; $widget_context = null; $editor_error = '';
$layout = file_get_contents(dirname(__DIR__) . '/app/Views/layouts/default.php');
if (!preg_match('/    <div class="modal fade" id="adminDeleteModal".*?\n    <\/div>/s', $layout, $modalMatch)) throw new RuntimeException('CMS delete modal missing');
$modal = preg_replace_callback('/<\?= print_translation\(\'([^\']+)\'\) \?>/', static fn($match) => htmlSC(return_translation($match[1])), $modalMatch[0]);
?>
<!doctype html><html lang="ru" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Удаление виджета — окно CMS</title>
<link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/admin-ui.css"><link rel="stylesheet" href="/assets/default/css/admin-widgets.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css">
<style>main{width:min(760px,100%);margin:3rem auto;padding:0 1.5rem}</style></head><body class="fb-admin-body"><main>
<?php require dirname(__DIR__) . '/app/Views/themes/default/admin/widgets.php'; ?>
</main><?= $modal ?>
<script src="/assets/default/js/jquery-3.7.1.min.js"></script><script src="/assets/default/js/bootstrap-guard.js"></script><script src="/assets/default/js/theme.min.js"></script><script src="/assets/default/js/admin-delete-modal.js"></script><script src="/assets/default/js/admin-widgets.js"></script>
</body></html>
