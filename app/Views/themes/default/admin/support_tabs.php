<?php
$active = (string)($active ?? 'requests');
$tabs = [
    'requests' => [base_href('/admin/support/requests'), return_translation('admin_support_nav_requests')],
    'faq' => [base_href('/admin/support/faq'), return_translation('admin_support_nav_faq')],
    'kb' => [base_href('/admin/support/knowledge-base'), return_translation('admin_support_nav_kb')],
    'settings' => [base_href('/admin/support/settings'), return_translation('admin_support_nav_settings')],
];
?>

<div class="d-flex flex-wrap gap-2 mb-4">
    <?php foreach ($tabs as $key => [$href, $label]): ?>
        <a
            class="btn btn-sm rounded-pill <?= $active === $key ? 'btn-dark' : 'btn-outline-secondary' ?>"
            href="<?= htmlSC($href) ?>"
        ><?= htmlSC($label) ?></a>
    <?php endforeach; ?>
</div>
