<?php
$icons = require dirname(__DIR__).'/includes/icon-map.php';
$iconName = $icons[$name ?? 'music'] ?? $icons['music'];
?>
<i class="ci-<?= htmlSC($iconName) ?> tr-icon" aria-hidden="true"></i>
