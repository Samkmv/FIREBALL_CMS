<?php
declare(strict_types=1);

// Run without CMS bootstrap or database access: php -n tests/update_manifest.php
require dirname(__DIR__) . '/vendor/autoload.php';

final class UpdateManifestProbe extends App\Services\UpdateCenter
{
    public function __construct()
    {
        $this->engineRelease = ['version' => '1.8.34'];
        $this->componentPolicy = [];
    }

    public function validatePackage(array $manifest, string $root): void
    {
        $this->validateUpdateManifest($manifest, $root);
    }
}

$project = dirname(__DIR__);
$manifest = json_decode((string)file_get_contents($project . '/update.json'), true, 512, JSON_THROW_ON_ERROR);
$release = require $project . '/config/version.php';
if (($manifest['version'] ?? null) !== ($release['version'] ?? null)) {
    throw new RuntimeException('Release version mismatch: update.json=' . ($manifest['version'] ?? 'missing')
        . ', config/version.php=' . ($release['version'] ?? 'missing'));
}

$temporaryRoot = __DIR__ . '/.tmp';
if (!is_dir($temporaryRoot)) mkdir($temporaryRoot, 0700, true);
$scratch = $temporaryRoot . '/update-manifest-' . bin2hex(random_bytes(8));
mkdir($scratch . '/config', 0700, true);
try {
    $probe = new UpdateManifestProbe();
    copy($project . '/config/version.php', $scratch . '/config/version.php');
    $probe->validatePackage($manifest, $scratch);

    // The same guard that blocked the broken release must still reject a mismatch.
    $mismatched = $manifest;
    $mismatched['version'] = $manifest['version'] . '-mismatch';
    try {
        $probe->validatePackage($mismatched, $scratch);
        throw new LogicException('Updater accepted inconsistent package versions.');
    } catch (RuntimeException $error) {
        if ($error->getMessage() !== 'Update manifest version does not match config/version.php.') throw $error;
    }

    file_put_contents($scratch . '/config/broken.php', '<?php return [;');
    try {
        $probe->validatePackage($manifest, $scratch);
        throw new LogicException('Updater accepted invalid package PHP.');
    } catch (RuntimeException $error) {
        if (!($error->getPrevious() instanceof ParseError)) throw $error;
    }
    echo 'PASS: release ' . $manifest['version'] . '; package validation; mismatched versions and invalid PHP rejected.' . PHP_EOL;
} finally {
    foreach (['config/broken.php', 'config/version.php'] as $file) {
        if (is_file($scratch . '/' . $file)) unlink($scratch . '/' . $file);
    }
    rmdir($scratch . '/config');
    rmdir($scratch);
}
