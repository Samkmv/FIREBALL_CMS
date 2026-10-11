<?php
// Use the icon font already supplied and loaded by Cartzilla.
$size = isset($size) && $size === 44 ? 44 : 24;
[$class, $color] = match ($icon ?? '') {
    'sun' => ['ci-sun', 'text-warning'],
    'moon' => ['ci-moon', 'text-info'],
    'cloud' => ['ci-cloud', 'text-body-secondary'],
    'fog' => ['ci-wind', 'text-body-secondary'],
    'drizzle' => ['ci-cloud-drizzle', 'text-info'],
    'rain' => ['ci-cloud-rain', 'text-info'],
    'snow' => ['ci-cloud-snow', 'text-info'],
    'storm' => ['ci-cloud-lightning', 'text-warning'],
    default => ['ci-thermometer', 'text-body-secondary'],
};
?>
<i class="<?= $class ?> <?= $color ?> d-inline-flex align-items-center justify-content-center flex-shrink-0" style="font-size: <?= $size ?>px; width: <?= $size ?>px; height: <?= $size ?>px; line-height: 1" aria-hidden="true"></i>
