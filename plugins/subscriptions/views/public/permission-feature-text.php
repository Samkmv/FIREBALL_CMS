<?php
$permissionText = (array)($plan['permission_details'][$permission] ?? []);
$permissionTitle = trim((string)($permissionText['title'] ?? ''));
?>
<span class="d-block text-break"><?= htmlSC($permissionTitle !== '' ? $permissionTitle : FireballPluginSubscriptions::t($label)) ?><?= htmlSC((string)($featureSuffix ?? '')) ?></span>
<?php if (trim((string)($permissionText['description'] ?? '')) !== ''): ?>
    <small class="d-block text-body-secondary mt-1 text-break"><?= nl2br(htmlSC((string)$permissionText['description'])) ?></small>
<?php endif; ?>
