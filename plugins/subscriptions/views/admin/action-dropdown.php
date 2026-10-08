<?php
// Same CMS dropdown markup as VPN Manager, without a runtime dependency on that plugin.
return static function (array $actions): string {
    $attributes = static function (array $values): string {
        $html = '';
        foreach ($values as $name => $value) {
            if ($value === false || $value === null) continue;
            $html .= ' ' . htmlSC((string)$name);
            if ($value !== true) $html .= '="' . htmlSC((string)$value) . '"';
        }
        return $html;
    };
    ob_start();
    ?>
    <div class="dropdown admin-post-actions-dropdown d-inline-block" data-admin-post-actions-dropdown>
        <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false" aria-label="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_actions')) ?>">
            <i class="ci-more-vertical" aria-hidden="true"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
            <?php foreach ($actions as $action): ?>
                <?php if (($action['type'] ?? '') === 'divider'): ?>
                    <hr class="dropdown-divider">
                <?php elseif (($action['type'] ?? 'link') === 'form'): ?>
                    <form action="<?= htmlSC((string)$action['action']) ?>" method="post"<?= $attributes((array)($action['form_attributes'] ?? [])) ?>>
                        <?= get_csrf_field() ?>
                        <?php foreach ((array)($action['hidden'] ?? []) as $name => $value): ?><input type="hidden" name="<?= htmlSC((string)$name) ?>" value="<?= htmlSC((string)$value) ?>"><?php endforeach; ?>
                        <button class="dropdown-item d-flex align-items-center gap-2 <?= htmlSC((string)($action['class'] ?? '')) ?>" type="submit">
                            <i class="<?= htmlSC((string)$action['icon']) ?>" aria-hidden="true"></i><span><?= htmlSC((string)$action['label']) ?></span>
                        </button>
                    </form>
                <?php else: ?>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?= htmlSC((string)$action['href']) ?>">
                        <i class="<?= htmlSC((string)$action['icon']) ?>" aria-hidden="true"></i><span><?= htmlSC((string)$action['label']) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return trim((string)ob_get_clean());
};
