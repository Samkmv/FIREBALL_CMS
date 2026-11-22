<ul class="list-unstyled vstack gap-3 mb-0">
    <?php foreach ($plan['features'] as $feature): ?>
        <li class="d-flex align-items-start gap-2">
            <i class="ci-check-circle text-success mt-1 flex-shrink-0" aria-hidden="true"></i>
            <div class="min-w-0 text-break">
                <span><?= htmlSC((string)$feature['title']) ?></span>
                <?php if (trim((string)($feature['description'] ?? '')) !== ''): ?>
                    <div class="small text-body-secondary mt-1"><?= nl2br(htmlSC((string)$feature['description'])) ?></div>
                <?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
