<?php

// CLI-only fixture: never exposes a second public profile endpoint.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../src/Support/RussianRegionCatalog.php';
final class FireballPluginSubscriptions
{
    public static function t(string $key): string
    {
        $translations = require __DIR__ . '/../../lang/ru.php';
        return $translations[$key] ?? $key;
    }
}
function htmlSC(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
$country = $argv[1] ?? 'Russia';
$fieldValue = $argv[2] ?? '';
$old = static fn(string $key): string => $key === 'country' ? $country : '';
$field = ['label' => 'Область / край', 'placeholder' => '', 'is_required' => true];
?>
<!doctype html><html lang="ru" data-bs-theme="dark"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Region selector test</title>
<body><main class="container py-4" style="max-width: 600px"><form class="border rounded-5 p-4">
<label class="form-label" for="test-country">Страна</label><input id="test-country" class="form-control mb-3" name="country" value="<?= htmlSC($country) ?>">
<label class="form-label" id="subscriptions-region-label">Область / край</label>
<?php require __DIR__ . '/../../views/public/region-field.php'; ?>
<button class="btn btn-primary mt-4" type="button">Сохранить</button>
</form></main></body></html>
