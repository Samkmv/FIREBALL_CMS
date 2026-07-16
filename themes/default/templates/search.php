<?php
$query = trim((string)($query ?? ''));
$results = (array)($results ?? []);
$total = (int)($total ?? count($results));
?>

<main class="container py-4 py-md-5">
    <div class="mx-auto" style="max-width: 960px">
        <section class="border rounded-5 p-4 p-md-5 mb-4 mb-lg-5">
            <h1 class="h3 mb-3"><?= print_translation('search_index_heading') ?></h1>
            <form action="<?= base_href('/search') ?>" method="get" class="position-relative">
                <input
                    class="form-control form-control-lg rounded-pill pe-5"
                    type="search"
                    name="q"
                    value="<?= htmlSC($query) ?>"
                    placeholder="<?= htmlSC(return_translation('tpl_menu_search')) ?>"
                    aria-label="<?= htmlSC(return_translation('search_index_heading')) ?>"
                    autocomplete="off"
                >
                <button class="btn btn-icon btn-ghost fs-lg btn-secondary border-0 position-absolute top-50 end-0 translate-middle-y rounded-circle me-2" type="submit" aria-label="<?= htmlSC(return_translation('search_index_title')) ?>">
                    <i class="ci-search"></i>
                </button>
            </form>

            <?php if ($query !== ''): ?>
                <p class="text-body-secondary mt-3 mb-0">
                    <?= print_translation('search_index_found') ?>: <strong><?= $total ?></strong>
                </p>
            <?php else: ?>
                <p class="text-body-secondary mt-3 mb-0"><?= print_translation('search_index_hint') ?></p>
            <?php endif; ?>
        </section>

        <?php if ($query !== ''): ?>
            <?php if ($results): ?>
                <section class="d-flex flex-column gap-3" aria-label="<?= htmlSC(return_translation('search_index_found')) ?>">
                    <?php foreach ($results as $result): ?>
                        <article class="border rounded-5 p-4 p-md-5">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                <span class="badge rounded-pill text-body-emphasis bg-body-tertiary border">
                                    <?= htmlSC((string)($result['type_label'] ?? $result['type'] ?? '')) ?>
                                </span>
                                <?php if (trim((string)($result['subtitle'] ?? '')) !== ''): ?>
                                    <span class="small text-body-secondary"><?= htmlSC((string)$result['subtitle']) ?></span>
                                <?php endif; ?>
                            </div>

                            <h2 class="h5 mb-2">
                                <a class="text-decoration-none" href="<?= htmlSC((string)($result['url'] ?? '#')) ?>">
                                    <?= $result['highlighted_title'] ?? htmlSC((string)($result['title'] ?? '')) ?>
                                </a>
                            </h2>

                            <?php if (trim((string)($result['excerpt'] ?? '')) !== ''): ?>
                                <p class="text-body-secondary mb-3">
                                    <?= $result['highlighted_excerpt'] ?? htmlSC((string)$result['excerpt']) ?>
                                </p>
                            <?php endif; ?>

                            <a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?= htmlSC((string)($result['url'] ?? '#')) ?>">
                                <?= print_translation('search_index_open') ?>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php else: ?>
                <section class="border rounded-5 p-5 text-center">
                    <h2 class="h5 mb-2"><?= print_translation('search_index_empty') ?></h2>
                    <p class="text-body-secondary mb-0"><?= print_translation('search_index_empty_desc') ?></p>
                </section>
            <?php endif; ?>

            <?php if (!empty($pagination)): ?>
                <nav class="mt-5" aria-label="<?= htmlSC(return_translation('search_index_title')) ?>">
                    <?= $pagination ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
