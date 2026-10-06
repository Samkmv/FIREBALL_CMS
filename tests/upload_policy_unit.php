<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
function config_value(string $key, mixed $default = null): mixed { return ['max_file_size' => 52428800]; }
function return_translation(string $key): string { static $data; $data ??= require dirname(__DIR__) . '/app/Languages/ru.php'; return $data[$key] ?? $key; }
use App\Services\UploadPolicy as P;
use App\Services\UploadException;
$checks = 0;
function check(bool $ok, string $label): void { $GLOBALS['checks']++; if (!$ok) throw new RuntimeException($label); }
foreach (['8M' => 8388608, '50M' => 52428800, '1G' => 1073741824, '8m' => 8388608, '1024K' => 1048576, '512' => 512, '0' => null, '-1' => null, 'bad' => 0] as $v => $expected) check(P::parseIniBytes((string)$v) === $expected, 'INI ' . $v);
check(P::limits(52428800, '8M', '55M')['effective'] === 8388608, 'PHP caps CMS');
check(P::limits(52428800, '50M', '8M')['effective'] === 8388608 - P::MULTIPART_OVERHEAD, 'Multipart overhead');
check(P::limits(52428800, '0', '0')['effective'] === 52428800, 'Unlimited PHP keeps CMS cap');
check(P::limits(1024, '50M', '55M')['effective'] === 1024, 'Component cap');
P::assertUpload(UPLOAD_ERR_OK, 1024);
check(true, 'OK');
foreach ([UPLOAD_ERR_INI_SIZE => 'size', UPLOAD_ERR_FORM_SIZE => 'size', UPLOAD_ERR_PARTIAL => 'partial', UPLOAD_ERR_NO_FILE => 'no_file', UPLOAD_ERR_NO_TMP_DIR => 'no_tmp', UPLOAD_ERR_CANT_WRITE => 'write', UPLOAD_ERR_EXTENSION => 'extension', 99 => 'invalid'] as $error => $suffix) {
    try { P::assertUpload($error, 0); throw new RuntimeException('Error not rejected'); }
    catch (UploadException $e) { check($e->translationKey === 'upload_error_' . $suffix, 'PHP error ' . $error); check(!str_contains($e->getMessage(), '/tmp'), 'No internal paths'); }
}
foreach ([52428801, 12 * 1048576] as $size) {
    try { P::assertUpload(UPLOAD_ERR_OK, $size); throw new RuntimeException('Limit not rejected'); }
    catch (UploadException $e) { check($e->translationKey === 'upload_error_size', 'CMS/runtime oversize'); }
}
// Valid MPEG layer III frames, libmagic validates actual audio instead of the browser MIME.
$tmp = tempnam(sys_get_temp_dir(), 'fbl-mp3-');
try {
    file_put_contents($tmp, str_repeat("\xff\xfb\x90\x64" . str_repeat("\0", 413), 4));
    $mime = (new App\Services\SafeUploadService())->validate($tmp, 'sound.mp3', filesize($tmp), 52428800);
    check($mime === 'audio/mpeg', 'Actual MP3 allowed');
    try { (new App\Services\SafeUploadService())->validate($tmp, 'photo.jpg', filesize($tmp), 52428800); throw new RuntimeException('MIME spoof accepted'); }
    catch (UploadException $e) { check($e->translationKey === 'upload_error_mime', 'MIME spoof rejected'); }
} finally { unlink($tmp); }
foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $lang = require dirname(__DIR__) . '/app/Languages/' . $locale . '.php';
    foreach (['size', 'partial', 'no_file', 'no_tmp', 'write', 'extension', 'invalid', 'type', 'mime', 'image'] as $suffix) check(isset($lang['upload_error_' . $suffix]), 'Translation ' . $locale . $suffix);
}
echo "$checks upload checks passed\n";
