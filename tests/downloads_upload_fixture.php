<?php
// Isolated HTTP fixture. Only its temporary upload directory may be written.
namespace App\Controllers { class BaseController {} }
namespace FBL {
    function request(): object { return \App\Modules\BlockEditor\request(); }
    final class Language { public static function get($key): string { return (string)$key; } }
}
namespace App\Services {
    function config_value(string $key, mixed $default = null): mixed {
        return $key === 'UPLOAD_SETTINGS' ? ['max_file_size' => 1024] : $default;
    }
}
namespace App\Models { function base_url(string $path = ''): string { return $path; } }
namespace App\Modules\BlockEditor {
    function request(): object {
        return new class {
            public array $files;
            public function __construct() { $this->files = $_FILES; }
            public function post(string $key, mixed $default = null): mixed { return $_POST[$key] ?? $default; }
        };
    }
    function response(): object {
        return new class {
            public function json(array $data, int $status = 200): void {
                http_response_code($status);
                header('Content-Type: application/json');
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
                exit;
            }
        };
    }
    function log_error_details(string $title, array $context = [], ?\Throwable $error = null): void {}
}
namespace {
    $directory = (string)getenv('FIREBALL_UPLOAD_TEST_DIR');
    if ($directory === '' || !is_dir($directory) || !str_starts_with(realpath($directory), realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR)) {
        http_response_code(500); exit('A dedicated temporary directory is required.');
    }
    if ($_SERVER['REQUEST_URI'] === '/health') { echo 'ready'; exit; }
    define('UPLOADS', $directory);
    require __DIR__ . '/../helpers/helpers.php';
    foreach (['Services/UploadSettings', 'Services/SafeUploadService', 'Models/FileManager', 'Modules/BlockEditor/BlockEditorService', 'Modules/BlockEditor/BlockEditorController'] as $class) require __DIR__ . '/../app/' . $class . '.php';
    require __DIR__ . '/../core/File.php';
    $reflection = new \ReflectionClass(\App\Modules\BlockEditor\BlockEditorController::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('service')->setValue($controller, new \App\Modules\BlockEditor\BlockEditorService());
    $controller->uploadFile();
}
