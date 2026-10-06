<?php
declare(strict_types=1);
// Isolated SAPI fixture: no configuration, database, sessions or application bootstrap.
if (getenv('FIREBALL_UPLOAD_FIXTURE') !== '1') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/vendor/autoload.php';
function config_value(string $key, mixed $default = null): mixed { return ['max_file_size' => 52428800]; }
function return_translation(string $key): string { static $data; $data ??= require dirname(__DIR__, 2) . '/app/Languages/ru.php'; return $data[$key] ?? $key; }
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'GET') { echo '{}'; exit; }
try {
    if (App\Services\UploadPolicy::requestExceedsPostLimit($_SERVER)) {
        http_response_code(413);
        echo json_encode(['key' => 'upload_error_size', 'post_empty' => $_POST === [], 'files_empty' => $_FILES === []]); exit;
    }
    if (($_POST['needCSRFToken'] ?? '') !== 'fixture') { http_response_code(419); echo '{}'; exit; }
    $file = $_FILES['file'] ?? [];
    App\Services\UploadPolicy::assertUpload((int)($file['error'] ?? UPLOAD_ERR_NO_FILE), (int)($file['size'] ?? 0));
    $mime = (new App\Services\SafeUploadService())->validate((string)$file['tmp_name'], (string)$file['name'], (int)$file['size'], 52428800);
    echo json_encode(['mime' => $mime, 'native_upload' => is_uploaded_file($file['tmp_name'])]);
} catch (App\Services\UploadException $e) {
    http_response_code(422); echo json_encode(['key' => $e->translationKey, 'message' => $e->getMessage(), 'php_error' => $file['error'] ?? 4]);
}
